<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega orders.stock_released_at para que la devolución de stock al cancelar un pedido ocurra una sola vez.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD stock_released_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders DROP stock_released_at');
    }
}
