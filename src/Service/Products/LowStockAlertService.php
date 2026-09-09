<?php

namespace App\Service\Products;

use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Products;
use App\Entity\Users\HelpMessages;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Users\UsersRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class LowStockAlertService implements LowStockAlertInterface
{
    private const PRODUCT_THRESHOLD = 5;
    private const BLOCK_THRESHOLD = 1;

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly VariantLabelFormatterInterface $variantLabelFormatter,
        private readonly LogInterface $log,
        private readonly UsersRepository $usersRepository,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly string $senderEmail,
        private readonly string $senderName,
    ) {
    }

    public function markProductIfLow(Products $product): bool
    {
        if ($product->getStock() === null || $product->getStock() > self::PRODUCT_THRESHOLD) {
            return false;
        }
        if ($product->hasLowStockAlertSent()) {
            return false;
        }

        $product->markLowStockAlertSent();
        return true;
    }

    public function markBlockIfLow(ProductsColors $block): bool
    {
        if ($block->getStock() === null || $block->getStock() > self::BLOCK_THRESHOLD) {
            return false;
        }
        if ($block->hasLowStockAlertSent()) {
            return false;
        }

        $block->markLowStockAlertSent();
        return true;
    }

    public function notifyProduct(Products $product): void
    {
        $stock = $product->getStock() ?? 0;
        $subject = 'Stock bajo: ' . $product->getName();
        $plain = "El stock general de \"{$product->getName()}\" bajó a {$stock} "
            . ($stock === 1 ? 'unidad' : 'unidades') . ". "
            . "Repone stock desde el panel de administración cuando puedas.";

        $this->send($subject, "<p>El stock general de <strong>{$product->getName()}</strong> "
            . "bajó a <strong>{$stock}</strong> " . ($stock === 1 ? 'unidad' : 'unidades') . ".</p>"
            . "<p>Repone stock desde el panel de administración cuando puedas.</p>");
        $this->sendInternalMessage($subject, $plain);
    }

    public function notifyBlock(ProductsColors $block): void
    {
        $stock = $block->getStock() ?? 0;
        $label = trim($this->variantLabelFormatter->format(
            $block->getColor()?->getName(),
            $block->getMedida()?->getName(),
        ), ' ()');
        $productName = $block->getProduct()->getName();
        $variantText = $label !== '' ? " ({$label})" : '';
        $subject = 'Stock bajo: ' . $productName . $variantText;
        $plain = "El stock de \"{$productName}{$variantText}\" bajó a {$stock} "
            . ($stock === 1 ? 'unidad' : 'unidades') . ". "
            . "Repone stock desde el panel de administración cuando puedas.";

        $this->send($subject, "<p>El stock de <strong>{$productName}{$variantText}</strong> "
            . "bajó a <strong>{$stock}</strong> " . ($stock === 1 ? 'unidad' : 'unidades') . ".</p>"
            . "<p>Repone stock desde el panel de administración cuando puedas.</p>");
        $this->sendInternalMessage($subject, $plain);
    }

    private function send(string $subject, string $body): void
    {
        try {
            $domain = $this->getDomainData->getDomainCache();
            $email  = (new Email())

                ->from(sprintf('%s <%s>', $this->senderName, $this->senderEmail))
                ->to($domain->getNotificationEmail())
                ->subject($subject)
                ->html($body);
            $this->mailer->send($email);
        } catch (\Throwable $th) {

            $this->log->handler($th);
        }
    }

    private function sendInternalMessage(string $subject, string $plainMessage): void
    {
        try {
            $domain = $this->getDomainData->getDomainCache();
            $admins = $this->usersRepository->findSuperAdminsByDomain($domain);
            if (empty($admins)) {
                return;
            }

            foreach ($admins as $admin) {
                $helpMessage = (new HelpMessages())->add($admin, $admin, $plainMessage, $subject);
                $this->customeEntityManager->add($helpMessage, false);
            }
            $this->customeEntityManager->flush();
        } catch (\Throwable $th) {

            $this->log->handler($th);
        }
    }
}
