<?php

namespace App\Tests\Unit\Service\Products;

use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Colors\Colors;
use App\Entity\Products\Medidas\Medidas;
use App\Entity\Products\Products;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\HelpMessages;
use App\Entity\Users\Users;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Users\UsersRepository;
use App\Service\Products\LowStockAlertService;
use App\Service\Products\VariantLabelFormatterInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class LowStockAlertServiceTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private GetDomainDataInterface&MockObject $getDomainData;
    private VariantLabelFormatterInterface&MockObject $variantLabelFormatter;
    private LogInterface&MockObject $log;
    private UsersRepository&MockObject $usersRepository;
    private CustomeEntityManagerInterface&MockObject $customeEntityManager;
    private LowStockAlertService $service;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->getDomainData = $this->createMock(GetDomainDataInterface::class);
        $this->variantLabelFormatter = $this->createMock(VariantLabelFormatterInterface::class);
        $this->log = $this->createMock(LogInterface::class);
        $this->usersRepository = $this->createMock(UsersRepository::class);
        $this->customeEntityManager = $this->createMock(CustomeEntityManagerInterface::class);

        $this->service = new LowStockAlertService(
            $this->mailer,
            $this->getDomainData,
            $this->variantLabelFormatter,
            $this->log,
            $this->usersRepository,
            $this->customeEntityManager,
            'noreply@example.com',
            'proyecto-grado-unad',
        );
    }

    public function testMarkProductIfLowReturnsFalseWhenStockIsNull(): void
    {
        $product = $this->createMock(Products::class);
        $product->method('getStock')->willReturn(null);
        $product->expects($this->never())->method('markLowStockAlertSent');

        $this->assertFalse($this->service->markProductIfLow($product));
    }

    public function testMarkProductIfLowReturnsFalseWhenStockAboveThreshold(): void
    {
        $product = $this->createMock(Products::class);
        $product->method('getStock')->willReturn(6);
        $product->expects($this->never())->method('markLowStockAlertSent');

        $this->assertFalse($this->service->markProductIfLow($product));
    }

    public function testMarkProductIfLowReturnsTrueAndMarksAtThreshold(): void
    {
        $product = $this->createMock(Products::class);
        $product->method('getStock')->willReturn(5);
        $product->method('hasLowStockAlertSent')->willReturn(false);
        $product->expects($this->once())->method('markLowStockAlertSent');

        $this->assertTrue($this->service->markProductIfLow($product));
    }

    public function testMarkProductIfLowReturnsFalseWhenAlreadySent(): void
    {
        $product = $this->createMock(Products::class);
        $product->method('getStock')->willReturn(1);
        $product->method('hasLowStockAlertSent')->willReturn(true);
        $product->expects($this->never())->method('markLowStockAlertSent');

        $this->assertFalse($this->service->markProductIfLow($product));
    }

    public function testMarkBlockIfLowReturnsFalseWhenStockAboveThreshold(): void
    {
        $block = $this->createMock(ProductsColors::class);
        $block->method('getStock')->willReturn(2);
        $block->expects($this->never())->method('markLowStockAlertSent');

        $this->assertFalse($this->service->markBlockIfLow($block));
    }

    public function testMarkBlockIfLowReturnsTrueAndMarksAtThreshold(): void
    {
        $block = $this->createMock(ProductsColors::class);
        $block->method('getStock')->willReturn(1);
        $block->method('hasLowStockAlertSent')->willReturn(false);
        $block->expects($this->once())->method('markLowStockAlertSent');

        $this->assertTrue($this->service->markBlockIfLow($block));
    }

    public function testMarkBlockIfLowReturnsFalseWhenAlreadySent(): void
    {
        $block = $this->createMock(ProductsColors::class);
        $block->method('getStock')->willReturn(0);
        $block->method('hasLowStockAlertSent')->willReturn(true);
        $block->expects($this->never())->method('markLowStockAlertSent');

        $this->assertFalse($this->service->markBlockIfLow($block));
    }

    public function testNotifyProductSendsEmailToDomainNotificationAddress(): void
    {
        $product = $this->createMock(Products::class);
        $product->method('getName')->willReturn('Camiseta X');
        $product->method('getStock')->willReturn(3);

        $domain = $this->createMock(Domains::class);
        $domain->method('getDomain')->willReturn('tienda.test');
        $domain->method('getNotificationEmail')->willReturn('alerts@tienda.test');
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return $email->getTo()[0]->getAddress() === 'alerts@tienda.test'
                    && str_contains($email->getSubject(), 'Camiseta X');
            }));

        $this->service->notifyProduct($product);
    }

    public function testNotifyBlockIncludesVariantLabelInSubject(): void
    {
        $color = $this->createMock(Colors::class);
        $color->method('getName')->willReturn('Rojo');
        $medida = $this->createMock(Medidas::class);
        $medida->method('getName')->willReturn('M');

        $product = $this->createMock(Products::class);
        $product->method('getName')->willReturn('Camiseta X');

        $block = $this->createMock(ProductsColors::class);
        $block->method('getStock')->willReturn(1);
        $block->method('getColor')->willReturn($color);
        $block->method('getMedida')->willReturn($medida);
        $block->method('getProduct')->willReturn($product);

        $this->variantLabelFormatter->method('format')->with('Rojo', 'M')->willReturn(' (Rojo, M)');

        $domain = $this->createMock(Domains::class);
        $domain->method('getDomain')->willReturn('tienda.test');
        $domain->method('getNotificationEmail')->willReturn('alerts@tienda.test');
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return str_contains($email->getSubject(), 'Camiseta X')
                    && str_contains($email->getSubject(), 'Rojo, M');
            }));

        $this->service->notifyBlock($block);
    }

    public function testNotifyProductUsesFixedSenderNotDomainDerivedAddress(): void
    {
        $product = $this->createMock(Products::class);
        $product->method('getName')->willReturn('Camiseta X');
        $product->method('getStock')->willReturn(3);

        $domain = $this->createMock(Domains::class);
        $domain->method('getDomain')->willReturn('http://localhost:8060');
        $domain->method('getNotificationEmail')->willReturn('alerts@tienda.test');
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $from = $email->getFrom()[0]->getAddress();
                return $from === 'noreply@example.com';
            }));

        $this->service->notifyProduct($product);
    }

    public function testNotifyProductCreatesInternalMessageForEachDomainSuperAdmin(): void
    {
        $product = $this->createMock(Products::class);
        $product->method('getName')->willReturn('Camiseta X');
        $product->method('getStock')->willReturn(3);

        $domain = $this->createMock(Domains::class);
        $domain->method('getNotificationEmail')->willReturn('alerts@tienda.test');
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $admin1 = $this->createMock(Users::class);
        $admin2 = $this->createMock(Users::class);
        $this->usersRepository->method('findSuperAdminsByDomain')->with($domain)->willReturn([$admin1, $admin2]);

        $this->customeEntityManager->expects($this->exactly(2))
            ->method('add')
            ->with($this->callback(function ($entity) {
                return $entity instanceof HelpMessages
                    && str_contains($entity->getSubject(), 'Camiseta X')
                    && str_contains($entity->getMessage(), 'Camiseta X');
            }), false);
        $this->customeEntityManager->expects($this->once())->method('flush');

        $this->service->notifyProduct($product);
    }

    public function testNotifyProductSkipsInternalMessageWhenNoSuperAdminsFound(): void
    {
        $product = $this->createMock(Products::class);
        $product->method('getName')->willReturn('Camiseta X');
        $product->method('getStock')->willReturn(3);

        $domain = $this->createMock(Domains::class);
        $domain->method('getNotificationEmail')->willReturn('alerts@tienda.test');
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $this->usersRepository->method('findSuperAdminsByDomain')->willReturn([]);
        $this->customeEntityManager->expects($this->never())->method('add');
        $this->customeEntityManager->expects($this->never())->method('flush');

        $this->service->notifyProduct($product);
    }

    public function testNotifyBlockCreatesInternalMessageWithVariantLabel(): void
    {
        $color = $this->createMock(Colors::class);
        $color->method('getName')->willReturn('Rojo');
        $medida = $this->createMock(Medidas::class);
        $medida->method('getName')->willReturn('M');

        $product = $this->createMock(Products::class);
        $product->method('getName')->willReturn('Camiseta X');

        $block = $this->createMock(ProductsColors::class);
        $block->method('getStock')->willReturn(1);
        $block->method('getColor')->willReturn($color);
        $block->method('getMedida')->willReturn($medida);
        $block->method('getProduct')->willReturn($product);

        $this->variantLabelFormatter->method('format')->willReturn(' (Rojo, M)');

        $domain = $this->createMock(Domains::class);
        $domain->method('getNotificationEmail')->willReturn('alerts@tienda.test');
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $admin = $this->createMock(Users::class);
        $this->usersRepository->method('findSuperAdminsByDomain')->willReturn([$admin]);

        $this->customeEntityManager->expects($this->once())
            ->method('add')
            ->with($this->callback(function ($entity) {
                return $entity instanceof HelpMessages && str_contains($entity->getMessage(), 'Rojo, M');
            }), false);

        $this->service->notifyBlock($block);
    }

    public function testNotifyProductInternalMessageFailureDoesNotThrow(): void
    {
        $product = $this->createMock(Products::class);
        $product->method('getName')->willReturn('Camiseta X');
        $product->method('getStock')->willReturn(3);

        $domain = $this->createMock(Domains::class);
        $domain->method('getNotificationEmail')->willReturn('alerts@tienda.test');
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $this->usersRepository->method('findSuperAdminsByDomain')->willThrowException(new \RuntimeException('DB down'));
        $this->log->expects($this->once())->method('handler');

        $this->service->notifyProduct($product);
        $this->addToAssertionCount(1);
    }

    public function testNotifyProductDoesNotThrowWhenMailerFails(): void
    {
        $product = $this->createMock(Products::class);
        $product->method('getName')->willReturn('Camiseta X');
        $product->method('getStock')->willReturn(3);

        $domain = $this->createMock(Domains::class);
        $domain->method('getDomain')->willReturn('tienda.test');
        $domain->method('getNotificationEmail')->willReturn('alerts@tienda.test');
        $this->getDomainData->method('getDomainCache')->willReturn($domain);

        $this->mailer->method('send')->willThrowException(new \RuntimeException('SMTP down'));
        $this->log->expects($this->once())->method('handler');

        $this->service->notifyProduct($product);
        $this->addToAssertionCount(1);
    }
}
