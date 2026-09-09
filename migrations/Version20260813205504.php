<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813205504 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tenants.isPrincipal. '
            . 'No incluye el drift preexistente de payment_methods/payment_transactions detectado por '
            . 'doctrine:migrations:diff (no relacionado con este cambio, no tocado a propósito).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenants ADD is_principal TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenants DROP is_principal');
    }
}
