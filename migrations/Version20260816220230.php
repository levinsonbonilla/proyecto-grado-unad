<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260816220230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Nombre corto opcional en domains, para el selector de dominio activo del dashboard.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE domains ADD name VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE domains DROP name');
    }
}
