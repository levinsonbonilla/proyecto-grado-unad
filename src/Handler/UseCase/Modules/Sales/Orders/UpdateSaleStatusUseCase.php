<?php

namespace App\Handler\UseCase\Modules\Sales\Orders;

use App\Entity\Products\Orders\Orders;
use App\Exception\GenericException;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Modules\Sales\Orders\UpdateSaleStatusInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Configurations\Globals\StatusRepository;
use App\Repository\Products\Orders\PaymentTransactionsRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class UpdateSaleStatusUseCase implements UpdateSaleStatusInterface
{

    private const TRANSITIONS = [
        'Pendiente'           => ['Procesando', 'Cancelado'],
        'Procesando'          => ['Enviado', 'Cancelado'],
        'Enviado'             => ['Entregado'],
        'Retracto Solicitado' => ['Retracto Aprobado'],
    ];

    private const STATUS_MESSAGES = [
        'Procesando'         => 'Tu pago fue confirmado y estamos preparando tu pedido.',
        'Enviado'            => 'Tu pedido fue enviado.',
        'Entregado'          => 'Tu pedido fue entregado. ¡Gracias por tu compra!',
        'Cancelado'          => 'Tu pedido fue cancelado.',
        'Retracto Aprobado'  => 'Tu solicitud de retracto fue aprobada, el reembolso está en proceso.',
    ];

    public function __construct(
        private readonly StatusRepository $statusRepository,
        private readonly PaymentTransactionsRepository $paymentTransactionsRepository,
        private readonly CustomeEntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly LogInterface $log,
    ) {
    }

    public function handler(
        Orders $order,
        string $newStatusName,
        ?string $trackingNumber = null,
        ?string $trackingCarrier = null,
    ): array {
        try {
            $currentStatusName = $order->getStatus()->getName();
            $allowedNextStatuses = self::TRANSITIONS[$currentStatusName] ?? [];

            if (!in_array($newStatusName, $allowedNextStatuses, true)) {
                return [
                    'success' => false,
                    'message' => sprintf('No se puede pasar de "%s" a "%s".', $currentStatusName, $newStatusName),
                ];
            }

            $newStatus = $this->statusRepository->findOneBy(['name' => $newStatusName, 'active' => true]);
            if ($newStatus === null) {
                throw new GenericException('Estado no disponible.', 500);
            }

            if ($newStatusName === 'Enviado') {
                $order->setTracking($trackingNumber, $trackingCarrier);
            }

            $order->edit($newStatus);
            $this->em->add($order, true);

            if ($newStatusName === 'Procesando') {
                $transaction = $this->paymentTransactionsRepository->findLatestByOrder($order);
                if ($transaction !== null) {
                    $transaction->edit($newStatus);
                    $this->em->add($transaction, true);
                }
            }

            $this->sendStatusEmail($order, $newStatusName);

            return ['success' => true];
        } catch (GenericException $e) {
            $this->log->handler($e);
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['success' => false, 'message' => 'Error al actualizar el estado del pedido.'];
        }
    }

    private function sendStatusEmail(Orders $order, string $statusName): void
    {
        try {
            $domain = $this->getDomainData->getDomainCache();
            $user   = $order->getUser();
            $email  = (new Email())
                ->from('noreply@' . $domain->getDomain())
                ->to($user->getEmail())
                ->subject('Actualización de tu pedido #' . substr((string) $order->getId(), 0, 8))
                ->html($this->buildEmailHtml($order, $statusName));
            $this->mailer->send($email);
        } catch (\Throwable) {

        }
    }

    private function buildEmailHtml(Orders $order, string $statusName): string
    {
        $orderId = substr((string) $order->getId(), 0, 8);
        $message = self::STATUS_MESSAGES[$statusName] ?? "El estado de tu pedido cambió a: {$statusName}.";

        if ($statusName === 'Enviado' && $order->getTrackingNumber()) {
            $carrier = $order->getTrackingCarrier() ? " ({$order->getTrackingCarrier()})" : '';
            $message .= " Número de guía: {$order->getTrackingNumber()}{$carrier}.";
        }

        return "
<html><body style='font-family:sans-serif;color:#333'>
<h2>Actualización de tu pedido #{$orderId}</h2>
<p>{$message}</p>
</body></html>";
    }
}
