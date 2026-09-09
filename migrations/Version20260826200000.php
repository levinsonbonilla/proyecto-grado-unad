<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260826200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'products.low_stock_alert_sent + products_colors.low_stock_alert_sent -- evita repetir el email de alerta de stock bajo.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products ADD low_stock_alert_sent TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE products_colors ADD low_stock_alert_sent TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products DROP low_stock_alert_sent');
        $this->addSql('ALTER TABLE products_colors DROP low_stock_alert_sent');
    }
}
