<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260816213130 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega statistics.user_id (nullable) -- vincula visitas con el usuario logueado';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE statistics ADD user_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE statistics ADD CONSTRAINT FK_E2D38B22A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('CREATE INDEX IDX_E2D38B22A76ED395 ON statistics (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE statistics DROP FOREIGN KEY FK_E2D38B22A76ED395');
        $this->addSql('DROP INDEX IDX_E2D38B22A76ED395 ON statistics');
        $this->addSql('ALTER TABLE statistics DROP user_id');
    }
}
