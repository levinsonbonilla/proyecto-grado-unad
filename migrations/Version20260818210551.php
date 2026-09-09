<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818210551 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'PLACEHOLDER-005: links de redes sociales opcionales por dominio (footer público)';
    }

    public function up(Schema $schema): void
    {

        $this->addSql('ALTER TABLE domains ADD facebook_url VARCHAR(500) DEFAULT NULL, ADD instagram_url VARCHAR(500) DEFAULT NULL, ADD pinterest_url VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE domains DROP facebook_url, DROP instagram_url, DROP pinterest_url');
    }
}
