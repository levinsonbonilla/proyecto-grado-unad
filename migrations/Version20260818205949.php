<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818205949 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FORM-004: asunto opcional en mensajes internos (help_messages.subject)';
    }

    public function up(Schema $schema): void
    {

        $this->addSql('ALTER TABLE help_messages ADD subject VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE help_messages DROP subject');
    }
}
