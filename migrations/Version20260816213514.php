<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260816213514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea statistics_events -- tracking de clicks/eventos del storefront';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE statistics_events (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', domain_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', user_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', event_name VARCHAR(100) NOT NULL, event_target VARCHAR(500) DEFAULT NULL, page VARCHAR(500) DEFAULT NULL, metadata JSON DEFAULT NULL, session_id VARCHAR(255) DEFAULT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_34C2F05D115F0EE5 (domain_id), INDEX IDX_34C2F05DA76ED395 (user_id), INDEX idx_stats_events_domain_date (domain_id, created_at), INDEX idx_stats_events_domain_name (domain_id, event_name), INDEX idx_stats_events_session (session_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE statistics_events ADD CONSTRAINT FK_34C2F05D115F0EE5 FOREIGN KEY (domain_id) REFERENCES domains (id)');
        $this->addSql('ALTER TABLE statistics_events ADD CONSTRAINT FK_34C2F05DA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE statistics_events DROP FOREIGN KEY FK_34C2F05D115F0EE5');
        $this->addSql('ALTER TABLE statistics_events DROP FOREIGN KEY FK_34C2F05DA76ED395');
        $this->addSql('DROP TABLE statistics_events');
    }
}
