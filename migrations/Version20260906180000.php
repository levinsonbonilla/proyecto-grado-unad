<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Elimina el esquema de templates/domain_templates/template_images, metadata y products_countries_prices/products_regions_prices/products_cities_prices (funcionalidades retiradas del alcance).';
    }

    public function up(Schema $schema): void
    {

        $this->addSql('DROP TABLE domain_templates');
        $this->addSql('DROP TABLE template_images');
        $this->addSql('DROP TABLE templates');

        $this->addSql('DROP TABLE metadata');

        $this->addSql('DROP TABLE products_cities_prices');
        $this->addSql('DROP TABLE products_countries_prices');
        $this->addSql('DROP TABLE products_regions_prices');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE templates (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, slug VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, demo_url VARCHAR(500) DEFAULT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_TEMPLATES_SLUG (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE domain_templates (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', domain_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', template_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_8958AFC05DA0FB8 (template_id), UNIQUE INDEX UNIQ_DOMAIN_TEMPLATES_DOMAIN (domain_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE domain_templates ADD CONSTRAINT FK_8958AFC0115F0EE5 FOREIGN KEY (domain_id) REFERENCES domains (id)');
        $this->addSql('ALTER TABLE domain_templates ADD CONSTRAINT FK_8958AFC05DA0FB8 FOREIGN KEY (template_id) REFERENCES templates (id)');
        $this->addSql('CREATE TABLE template_images (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', template_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', image_path VARCHAR(500) NOT NULL, image_url VARCHAR(500) NOT NULL, alt_text VARCHAR(255) DEFAULT NULL, sort_order INT NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_EBF7B7985DA0FB8 (template_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE template_images ADD CONSTRAINT FK_EBF7B7985DA0FB8 FOREIGN KEY (template_id) REFERENCES templates (id)');

        $this->addSql('CREATE TABLE metadata (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', domain_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', page_type VARCHAR(50) DEFAULT NULL, page_url VARCHAR(255) DEFAULT NULL, meta_title VARCHAR(70) DEFAULT NULL, meta_description VARCHAR(160) DEFAULT NULL, meta_keywords LONGTEXT DEFAULT NULL, canonical_url VARCHAR(255) DEFAULT NULL, robots_directive VARCHAR(100) DEFAULT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_4F143414115F0EE5 (domain_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE metadata ADD CONSTRAINT FK_4F143414115F0EE5 FOREIGN KEY (domain_id) REFERENCES domains (id)');

        $this->addSql('CREATE TABLE products_countries_prices (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', product_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', country_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', price BIGINT NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_34E9007A4584665A (product_id), INDEX IDX_34E9007AF92F3E70 (country_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE products_countries_prices ADD CONSTRAINT FK_34E9007A4584665A FOREIGN KEY (product_id) REFERENCES products (id)');
        $this->addSql('ALTER TABLE products_countries_prices ADD CONSTRAINT FK_34E9007AF92F3E70 FOREIGN KEY (country_id) REFERENCES countries (id)');
        $this->addSql('CREATE TABLE products_regions_prices (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', product_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', region_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', price BIGINT NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_F6DC7CE34584665A (product_id), INDEX IDX_F6DC7CE398260155 (region_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE products_regions_prices ADD CONSTRAINT FK_F6DC7CE34584665A FOREIGN KEY (product_id) REFERENCES products (id)');
        $this->addSql('ALTER TABLE products_regions_prices ADD CONSTRAINT FK_F6DC7CE398260155 FOREIGN KEY (region_id) REFERENCES regions (id)');
        $this->addSql('CREATE TABLE products_cities_prices (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', product_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', city_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', price BIGINT NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_EF0906044584665A (product_id), INDEX IDX_EF0906048BAC62AF (city_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE products_cities_prices ADD CONSTRAINT FK_EF0906044584665A FOREIGN KEY (product_id) REFERENCES products (id)');
        $this->addSql('ALTER TABLE products_cities_prices ADD CONSTRAINT FK_EF0906048BAC62AF FOREIGN KEY (city_id) REFERENCES cities (id)');
    }
}
