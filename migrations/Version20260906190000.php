<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Elimina el esquema del sistema de módulos por dominio: modules, module_features, domain_modules, domain_module_features, modules_tenants, module_conditions, cities_modules, regions_modules, countries_modules.';
    }

    public function up(Schema $schema): void
    {

        $this->addSql('DROP TABLE domain_module_features');
        $this->addSql('DROP TABLE domain_modules');

        $this->addSql('DROP TABLE cities_modules');
        $this->addSql('DROP TABLE countries_modules');
        $this->addSql('DROP TABLE regions_modules');
        $this->addSql('DROP TABLE module_conditions');

        $this->addSql('DROP TABLE modules_tenants');

        $this->addSql('DROP TABLE module_features');
        $this->addSql('DROP TABLE modules');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE modules (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(150) NOT NULL, slug VARCHAR(100) NOT NULL, description VARCHAR(5000) NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_2EB743D7989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE module_features (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', module_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(150) NOT NULL, slug VARCHAR(100) NOT NULL, description VARCHAR(5000) NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_DB000FED989D9B62 (slug), INDEX IDX_DB000FEDAFC2B591 (module_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE module_features ADD CONSTRAINT FK_DB000FEDAFC2B591 FOREIGN KEY (module_id) REFERENCES modules (id)');

        $this->addSql('CREATE TABLE modules_tenants (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', tenant_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', module_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CCB1D58F9033212A (tenant_id), INDEX IDX_CCB1D58FAFC2B591 (module_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE modules_tenants ADD CONSTRAINT FK_CCB1D58F9033212A FOREIGN KEY (tenant_id) REFERENCES tenants (id)');
        $this->addSql('ALTER TABLE modules_tenants ADD CONSTRAINT FK_CCB1D58FAFC2B591 FOREIGN KEY (module_id) REFERENCES modules (id)');

        $this->addSql('CREATE TABLE module_conditions (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', module_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', country_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', region_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', city_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_61832234AFC2B591 (module_id), INDEX IDX_61832234F92F3E70 (country_id), INDEX IDX_6183223498260155 (region_id), INDEX IDX_618322348BAC62AF (city_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE module_conditions ADD CONSTRAINT FK_61832234AFC2B591 FOREIGN KEY (module_id) REFERENCES modules (id)');
        $this->addSql('ALTER TABLE module_conditions ADD CONSTRAINT FK_61832234F92F3E70 FOREIGN KEY (country_id) REFERENCES countries (id)');
        $this->addSql('ALTER TABLE module_conditions ADD CONSTRAINT FK_6183223498260155 FOREIGN KEY (region_id) REFERENCES regions (id)');
        $this->addSql('ALTER TABLE module_conditions ADD CONSTRAINT FK_618322348BAC62AF FOREIGN KEY (city_id) REFERENCES cities (id)');

        $this->addSql('CREATE TABLE regions_modules (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', region_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', modules_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_4DFDD7EF98260155 (region_id), INDEX IDX_4DFDD7EF60D6DC42 (modules_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE regions_modules ADD CONSTRAINT FK_4DFDD7EF98260155 FOREIGN KEY (region_id) REFERENCES regions (id)');
        $this->addSql('ALTER TABLE regions_modules ADD CONSTRAINT FK_4DFDD7EF60D6DC42 FOREIGN KEY (modules_id) REFERENCES modules (id)');

        $this->addSql('CREATE TABLE countries_modules (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', country_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', modules_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_743E4527F92F3E70 (country_id), INDEX IDX_743E452760D6DC42 (modules_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE countries_modules ADD CONSTRAINT FK_743E4527F92F3E70 FOREIGN KEY (country_id) REFERENCES countries (id)');
        $this->addSql('ALTER TABLE countries_modules ADD CONSTRAINT FK_743E452760D6DC42 FOREIGN KEY (modules_id) REFERENCES modules (id)');

        $this->addSql('CREATE TABLE cities_modules (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', city_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', modules_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_65895C528BAC62AF (city_id), INDEX IDX_65895C5260D6DC42 (modules_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cities_modules ADD CONSTRAINT FK_65895C528BAC62AF FOREIGN KEY (city_id) REFERENCES cities (id)');
        $this->addSql('ALTER TABLE cities_modules ADD CONSTRAINT FK_65895C5260D6DC42 FOREIGN KEY (modules_id) REFERENCES modules (id)');

        $this->addSql('CREATE TABLE domain_modules (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', domain_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', module_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CC605ACF115F0EE5 (domain_id), INDEX IDX_CC605ACFAFC2B591 (module_id), UNIQUE INDEX uq_domain_module (domain_id, module_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE domain_modules ADD CONSTRAINT FK_CC605ACF115F0EE5 FOREIGN KEY (domain_id) REFERENCES domains (id)');
        $this->addSql('ALTER TABLE domain_modules ADD CONSTRAINT FK_CC605ACFAFC2B591 FOREIGN KEY (module_id) REFERENCES modules (id)');

        $this->addSql('CREATE TABLE domain_module_features (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', domain_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', module_feature_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_DB374446115F0EE5 (domain_id), INDEX IDX_DB374446F50193E4 (module_feature_id), UNIQUE INDEX uq_domain_module_feature (domain_id, module_feature_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE domain_module_features ADD CONSTRAINT FK_DB374446115F0EE5 FOREIGN KEY (domain_id) REFERENCES domains (id)');
        $this->addSql('ALTER TABLE domain_module_features ADD CONSTRAINT FK_DB374446F50193E4 FOREIGN KEY (module_feature_id) REFERENCES module_features (id)');
    }
}
