# Pasarela de pago simulada (mock)

Microservicio Node/Express independiente que emula el contrato mínimo de una
pasarela de pago tipo Stripe/Wompi/Mercado Pago, para poder probar el checkout
del proyecto de punta a punta en desarrollo/demo sin depender de credenciales ni
integraciones reales.

**No procesa dinero real. No pide ni almacena datos de tarjeta.** El estado
vive en memoria (`Map`) y se pierde si el contenedor se reinicia — aceptable
para un mock, no usar este servicio como base para producción.

## Contrato

| Método | Ruta | Descripción |
|---|---|---|
| `POST` | `/v1/payment-intents` | Body: `{ orderId, amount, currency, webhookUrl, returnUrl }`. Responde `{ id, status, redirectUrl }`. |
| `GET` | `/pay/:id` | Página HTML donde el "cliente" simula pagar (Pagar / Rechazar / Timeout). |
| `POST` | `/pay/:id/resolve` | Body: `{ outcome: 'succeeded' \| 'failed' }`. Notifica `webhookUrl` (firmado HMAC-SHA256 en el header `X-Gateway-Signature`, secreto = `PAYMENT_GATEWAY_SECRET`) y responde `{ status, returnUrl }`. |
| `GET` | `/v1/payment-intents/:id` | Consulta de estado (respaldo/debug). |
| `GET` | `/health` | Healthcheck simple. |

`amount` va en la unidad mínima de la moneda (centavos), misma convención que
usa el proyecto en toda la app (`totalAmount`, `publicPrice`, etc. en centavos).

## Variables de entorno

- `PAYMENT_GATEWAY_SECRET` — secreto HMAC compartido con el proyecto (`.env` →
  `PAYMENT_GATEWAY_SECRET`, debe ser el mismo valor en ambos lados).
- `SELF_BASE_URL` — URL pública desde la que el navegador del cliente llega a
  este servicio (ej. `http://localhost:8070`), usada para construir
  `redirectUrl`. **No** es la URL interna de la red de Docker.
- `PORT` — puerto de escucha (default `8070`).

## Correrlo solo (fuera de docker-compose)

```bash
cd docker/payment-gateway
npm install
PAYMENT_GATEWAY_SECRET=dev-secret-change-me node server.js
```

## Probar manualmente

```bash
curl -X POST http://localhost:8070/v1/payment-intents \
  -H 'Content-Type: application/json' \
  -d '{"orderId":"test-order","amount":5000000,"currency":"COP","webhookUrl":"http://localhost:8060/es/checkout/webhook/payment","returnUrl":"http://localhost:8060/es/checkout/return/test-order"}'
```

Abrir el `redirectUrl` devuelto en el navegador y usar los botones de la
página para simular éxito o fallo.
