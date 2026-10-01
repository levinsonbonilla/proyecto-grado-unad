<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega la tabla about_sections para las secciones de la página Nosotros y los datos de contacto por dominio.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE about_sections (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', domain_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', title VARCHAR(255) NOT NULL, text LONGTEXT NOT NULL, image VARCHAR(255) DEFAULT NULL, position INT NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_678842F1115F0EE5 (domain_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE about_sections ADD CONSTRAINT FK_678842F1115F0EE5 FOREIGN KEY (domain_id) REFERENCES domains (id)');
        $this->addSql('ALTER TABLE domains ADD contact_address VARCHAR(500) DEFAULT NULL, ADD contact_phone VARCHAR(50) DEFAULT NULL, ADD contact_email VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE about_sections DROP FOREIGN KEY FK_678842F1115F0EE5');
        $this->addSql('DROP TABLE about_sections');
        $this->addSql('ALTER TABLE domains DROP contact_address, DROP contact_phone, DROP contact_email');
    }
}
