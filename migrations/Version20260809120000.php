<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260809120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ventas: PaymentMethods.provider/instructions, Orders.trackingNumber/trackingCarrier, PaymentTransactions.gatewayReference/gatewayResponse';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE payment_methods ADD provider VARCHAR(50) NOT NULL DEFAULT 'manual', ADD instructions LONGTEXT DEFAULT NULL");
        $this->addSql('ALTER TABLE orders ADD tracking_number VARCHAR(100) DEFAULT NULL, ADD tracking_carrier VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE payment_transactions ADD gateway_reference VARCHAR(255) DEFAULT NULL, ADD gateway_response LONGTEXT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PAYMENT_TRANSACTIONS_GATEWAY_REFERENCE ON payment_transactions (gateway_reference)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_PAYMENT_TRANSACTIONS_GATEWAY_REFERENCE ON payment_transactions');
        $this->addSql('ALTER TABLE payment_transactions DROP gateway_reference, DROP gateway_response');
        $this->addSql('ALTER TABLE orders DROP tracking_number, DROP tracking_carrier');
        $this->addSql('ALTER TABLE payment_methods DROP provider, DROP instructions');
    }
}
