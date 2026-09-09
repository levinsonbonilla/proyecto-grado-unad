// Pasarela de pago simulada para desarrollo/demo del proyecto.
//
// NO es una integración real: no procesa tarjetas ni mueve dinero. Emula el
// contrato mínimo de una pasarela real (Stripe-like) para poder probar el
// flujo de checkout de punta a punta sin depender de credenciales externas:
//
//   1. El proyecto crea un "payment intent"      -> POST /v1/payment-intents
//   2. El navegador del cliente se redirige -> GET  /pay/:id
//   3. El cliente decide pagar/rechazar     -> POST /pay/:id/resolve
//   4. Este servicio notifica al proyecto      -> POST {webhookUrl} (firmado HMAC)
//
// Estado en memoria (Map) — se pierde si el contenedor se reinicia. Es
// aceptable para un mock de desarrollo; no usar como base para producción.

const express = require('express');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const app = express();
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

const PORT = process.env.PORT || 8070;
const SECRET = process.env.PAYMENT_GATEWAY_SECRET || 'dev-secret-change-me';
const SELF_BASE_URL = process.env.SELF_BASE_URL || `http://localhost:${PORT}`;

/** @type {Map<string, {orderId: string, amount: number, currency: string, webhookUrl: string, returnUrl: string|null, status: string}>} */
const intents = new Map();

const payPageTemplate = fs.readFileSync(path.join(__dirname, 'views', 'pay.html'), 'utf8');

function formatAmount(amountCents, currency) {
  const value = (amountCents / 100).toLocaleString('es-CO', { maximumFractionDigits: 0 });
  return `$${value} ${currency}`;
}

function sign(body) {
  return crypto.createHmac('sha256', SECRET).update(body).digest('hex');
}

// --- API ---------------------------------------------------------------

app.post('/v1/payment-intents', (req, res) => {
  const { orderId, amount, currency, webhookUrl, returnUrl } = req.body || {};

  if (!orderId || !amount || !webhookUrl) {
    return res.status(400).json({ error: 'orderId, amount y webhookUrl son requeridos' });
  }

  const id = 'pi_' + crypto.randomUUID();
  intents.set(id, {
    orderId,
    amount: Number(amount),
    currency: currency || 'COP',
    webhookUrl,
    returnUrl: returnUrl || null,
    status: 'requires_action',
  });

  res.json({
    id,
    status: 'requires_action',
    redirectUrl: `${SELF_BASE_URL}/pay/${id}`,
  });
});

app.get('/v1/payment-intents/:id', (req, res) => {
  const intent = intents.get(req.params.id);
  if (!intent) {
    return res.status(404).json({ error: 'not_found' });
  }
  res.json({ id: req.params.id, orderId: intent.orderId, status: intent.status });
});

// --- Página de pago simulada --------------------------------------------

app.get('/pay/:id', (req, res) => {
  const intent = intents.get(req.params.id);
  if (!intent) {
    return res.status(404).send('<h1>Intento de pago no encontrado o expirado</h1>');
  }

  const html = payPageTemplate
    .replaceAll('{{ORDER_ID}}', escapeHtml(intent.orderId))
    .replaceAll('{{AMOUNT_FORMATTED}}', escapeHtml(formatAmount(intent.amount, intent.currency)))
    .replaceAll('{{INTENT_ID}}', escapeHtml(req.params.id))
    .replaceAll('{{INTENT_ID_JSON}}', JSON.stringify(req.params.id));

  res.type('html').send(html);
});

app.post('/pay/:id/resolve', async (req, res) => {
  const intent = intents.get(req.params.id);
  if (!intent) {
    return res.status(404).json({ error: 'not_found' });
  }

  const outcome = req.body?.outcome === 'succeeded' ? 'succeeded' : 'failed';

  // Idempotencia local: si ya se resolvió, no se vuelve a notificar el webhook.
  if (intent.status === 'succeeded' || intent.status === 'failed') {
    return res.json({ status: intent.status, returnUrl: buildReturnUrl(intent) });
  }

  intent.status = outcome;

  const payload = JSON.stringify({
    intentId: req.params.id,
    orderId: intent.orderId,
    status: outcome,
  });
  const signature = sign(payload);

  try {
    await fetch(intent.webhookUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Gateway-Signature': signature },
      body: payload,
    });
  } catch (err) {
    // En un mock, solo se registra — no hay reintentos ni cola de reenvío.
    console.error(`[payment-gateway] fallo al notificar webhook para ${req.params.id}:`, err.message);
  }

  res.json({ status: outcome, returnUrl: buildReturnUrl(intent) });
});

function buildReturnUrl(intent) {
  return intent.returnUrl || null;
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));
}

app.get('/health', (req, res) => res.json({ ok: true }));

app.listen(PORT, () => {
  console.log(`[payment-gateway] mock listening on :${PORT} (self base url: ${SELF_BASE_URL})`);
});
