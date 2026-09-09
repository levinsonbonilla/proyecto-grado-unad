<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Elimina el esquema de catalogs, promotions, coupons, discount_types, point_conditions, product_reviews, currencies/domain_currencies y colors_scheme (funcionalidades retiradas del alcance).';
    }

    public function up(Schema $schema): void
    {

        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEE66C5951B');
        $this->addSql('ALTER TABLE orders DROP INDEX IDX_E52FFDEE66C5951B');
        $this->addSql('ALTER TABLE orders DROP COLUMN coupon_id');

        $this->addSql('DROP TABLE products_promotions');
        $this->addSql('DROP TABLE cities_promotions');
        $this->addSql('DROP TABLE countries_promotions');
        $this->addSql('DROP TABLE regions_promotions');
        $this->addSql('DROP TABLE promotions');

        $this->addSql('DROP TABLE cities_points_conditions');
        $this->addSql('DROP TABLE countries_points_conditions');
        $this->addSql('DROP TABLE regions_points_conditions');
        $this->addSql('DROP TABLE point_conditions');

        $this->addSql('DROP TABLE cities_coupons');
        $this->addSql('DROP TABLE countries_coupons');
        $this->addSql('DROP TABLE regions_coupons');
        $this->addSql('DROP TABLE coupons');

        $this->addSql('DROP TABLE catalogs_products');
        $this->addSql('DROP TABLE cities_catalogs');
        $this->addSql('DROP TABLE countries_catalogs');
        $this->addSql('DROP TABLE regions_catalogs');
        $this->addSql('DROP TABLE catalogs');

        $this->addSql('DROP TABLE discount_types');

        $this->addSql('DROP TABLE product_reviews');

        $this->addSql('DROP TABLE colors_scheme');

        $this->addSql('DROP TABLE domain_currencies');
        $this->addSql('DROP TABLE currencies');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE currencies (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', code VARCHAR(10) NOT NULL, name VARCHAR(100) NOT NULL, symbol VARCHAR(10) NOT NULL, decimal_places INT NOT NULL, thousands_separator VARCHAR(5) NOT NULL, decimal_separator VARCHAR(5) NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_CURRENCIES_CODE (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE domain_currencies (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', domain_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', currency_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_DOMAIN_CURRENCIES_DOMAIN (domain_id), INDEX IDX_DOMAIN_CURRENCIES_CURRENCY (currency_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE domain_currencies ADD CONSTRAINT FK_DOMAIN_CURRENCIES_DOMAIN FOREIGN KEY (domain_id) REFERENCES domains (id)');
        $this->addSql('ALTER TABLE domain_currencies ADD CONSTRAINT FK_DOMAIN_CURRENCIES_CURRENCY FOREIGN KEY (currency_id) REFERENCES currencies (id)');

        $this->addSql('CREATE TABLE colors_scheme (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', domain_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(100) NOT NULL, color VARCHAR(150) NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_BB9F98FF115F0EE5 (domain_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE colors_scheme ADD CONSTRAINT FK_BB9F98FF115F0EE5 FOREIGN KEY (domain_id) REFERENCES domains (id)');

        $this->addSql('CREATE TABLE product_reviews (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', product_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', user_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', rating BIGINT NOT NULL, comment LONGTEXT DEFAULT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_B8A9F0BF4584665A (product_id), INDEX IDX_B8A9F0BFA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE product_reviews ADD CONSTRAINT FK_B8A9F0BF4584665A FOREIGN KEY (product_id) REFERENCES products (id)');
        $this->addSql('ALTER TABLE product_reviews ADD CONSTRAINT FK_B8A9F0BFA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');

        $this->addSql('CREATE TABLE discount_types (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', domain_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', operation VARCHAR(50) NOT NULL, name VARCHAR(100) NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_721A51A7115F0EE5 (domain_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE discount_types ADD CONSTRAINT FK_721A51A7115F0EE5 FOREIGN KEY (domain_id) REFERENCES domains (id)');

        $this->addSql('CREATE TABLE catalogs (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', domain_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_F3AD370A115F0EE5 (domain_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE catalogs ADD CONSTRAINT FK_F3AD370A115F0EE5 FOREIGN KEY (domain_id) REFERENCES domains (id)');
        $this->addSql('CREATE TABLE catalogs_products (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', product_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', catalog_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_322616D94584665A (product_id), INDEX IDX_322616D9CC3C66FC (catalog_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE catalogs_products ADD CONSTRAINT FK_322616D94584665A FOREIGN KEY (product_id) REFERENCES products (id)');
        $this->addSql('ALTER TABLE catalogs_products ADD CONSTRAINT FK_322616D9CC3C66FC FOREIGN KEY (catalog_id) REFERENCES catalogs (id)');
        $this->addSql('CREATE TABLE cities_catalogs (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', city_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', catalogs_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_6E347EBA8BAC62AF (city_id), INDEX IDX_6E347EBA53B63F74 (catalogs_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cities_catalogs ADD CONSTRAINT FK_6E347EBA8BAC62AF FOREIGN KEY (city_id) REFERENCES cities (id)');
        $this->addSql('ALTER TABLE cities_catalogs ADD CONSTRAINT FK_6E347EBA53B63F74 FOREIGN KEY (catalogs_id) REFERENCES catalogs (id)');
        $this->addSql('CREATE TABLE countries_catalogs (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', country_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', catalogs_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_4E4A4C10F92F3E70 (country_id), INDEX IDX_4E4A4C1053B63F74 (catalogs_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE countries_catalogs ADD CONSTRAINT FK_4E4A4C10F92F3E70 FOREIGN KEY (country_id) REFERENCES countries (id)');
        $this->addSql('ALTER TABLE countries_catalogs ADD CONSTRAINT FK_4E4A4C1053B63F74 FOREIGN KEY (catalogs_id) REFERENCES catalogs (id)');
        $this->addSql('CREATE TABLE regions_catalogs (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', region_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', catalogs_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_DBCCC50098260155 (region_id), INDEX IDX_DBCCC50053B63F74 (catalogs_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE regions_catalogs ADD CONSTRAINT FK_DBCCC50098260155 FOREIGN KEY (region_id) REFERENCES regions (id)');
        $this->addSql('ALTER TABLE regions_catalogs ADD CONSTRAINT FK_DBCCC50053B63F74 FOREIGN KEY (catalogs_id) REFERENCES catalogs (id)');

        $this->addSql('CREATE TABLE coupons (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', discount_type_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', domain_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(150) NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_F56411187344E182 (discount_type_id), INDEX IDX_F5641118115F0EE5 (domain_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE coupons ADD CONSTRAINT FK_F56411187344E182 FOREIGN KEY (discount_type_id) REFERENCES discount_types (id)');
        $this->addSql('ALTER TABLE coupons ADD CONSTRAINT FK_F5641118115F0EE5 FOREIGN KEY (domain_id) REFERENCES domains (id)');
        $this->addSql('CREATE TABLE cities_coupons (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', city_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', coupons_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_BE5A0E9D8BAC62AF (city_id), INDEX IDX_BE5A0E9D6D72B15C (coupons_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cities_coupons ADD CONSTRAINT FK_BE5A0E9D8BAC62AF FOREIGN KEY (city_id) REFERENCES cities (id)');
        $this->addSql('ALTER TABLE cities_coupons ADD CONSTRAINT FK_BE5A0E9D6D72B15C FOREIGN KEY (coupons_id) REFERENCES coupons (id)');
        $this->addSql('CREATE TABLE countries_coupons (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', country_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', coupons_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_AFED17E8F92F3E70 (country_id), INDEX IDX_AFED17E86D72B15C (coupons_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE countries_coupons ADD CONSTRAINT FK_AFED17E8F92F3E70 FOREIGN KEY (country_id) REFERENCES countries (id)');
        $this->addSql('ALTER TABLE countries_coupons ADD CONSTRAINT FK_AFED17E86D72B15C FOREIGN KEY (coupons_id) REFERENCES coupons (id)');
        $this->addSql('CREATE TABLE regions_coupons (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', region_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', coupons_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_962E852098260155 (region_id), INDEX IDX_962E85206D72B15C (coupons_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE regions_coupons ADD CONSTRAINT FK_962E852098260155 FOREIGN KEY (region_id) REFERENCES regions (id)');
        $this->addSql('ALTER TABLE regions_coupons ADD CONSTRAINT FK_962E85206D72B15C FOREIGN KEY (coupons_id) REFERENCES coupons (id)');

        $this->addSql('CREATE TABLE point_conditions (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', domain_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', money_per_point BIGINT NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_28132A03115F0EE5 (domain_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE point_conditions ADD CONSTRAINT FK_28132A03115F0EE5 FOREIGN KEY (domain_id) REFERENCES domains (id)');
        $this->addSql('CREATE TABLE cities_points_conditions (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', city_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', point_conditions_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_19D77F548BAC62AF (city_id), INDEX IDX_19D77F548FD2F6B7 (point_conditions_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cities_points_conditions ADD CONSTRAINT FK_19D77F548BAC62AF FOREIGN KEY (city_id) REFERENCES cities (id)');
        $this->addSql('ALTER TABLE cities_points_conditions ADD CONSTRAINT FK_19D77F548FD2F6B7 FOREIGN KEY (point_conditions_id) REFERENCES point_conditions (id)');
        $this->addSql('CREATE TABLE countries_points_conditions (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', country_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', point_conditions_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_144E8D66F92F3E70 (country_id), INDEX IDX_144E8D668FD2F6B7 (point_conditions_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE countries_points_conditions ADD CONSTRAINT FK_144E8D66F92F3E70 FOREIGN KEY (country_id) REFERENCES countries (id)');
        $this->addSql('ALTER TABLE countries_points_conditions ADD CONSTRAINT FK_144E8D668FD2F6B7 FOREIGN KEY (point_conditions_id) REFERENCES point_conditions (id)');
        $this->addSql('CREATE TABLE regions_points_conditions (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', region_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', point_condition_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_529709DE98260155 (region_id), INDEX IDX_529709DE7EF86AD2 (point_condition_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE regions_points_conditions ADD CONSTRAINT FK_529709DE98260155 FOREIGN KEY (region_id) REFERENCES regions (id)');
        $this->addSql('ALTER TABLE regions_points_conditions ADD CONSTRAINT FK_529709DE7EF86AD2 FOREIGN KEY (point_condition_id) REFERENCES point_conditions (id)');

        $this->addSql('CREATE TABLE promotions (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', domain_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', discount_type_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, start_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', end_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', discount_rate BIGINT NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_EA1B3034115F0EE5 (domain_id), INDEX IDX_EA1B30347344E182 (discount_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE promotions ADD CONSTRAINT FK_EA1B3034115F0EE5 FOREIGN KEY (domain_id) REFERENCES domains (id)');
        $this->addSql('ALTER TABLE promotions ADD CONSTRAINT FK_EA1B30347344E182 FOREIGN KEY (discount_type_id) REFERENCES discount_types (id)');
        $this->addSql('CREATE TABLE products_promotions (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', promotion_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', product_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_3844C82A139DF194 (promotion_id), INDEX IDX_3844C82A4584665A (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE products_promotions ADD CONSTRAINT FK_3844C82A139DF194 FOREIGN KEY (promotion_id) REFERENCES promotions (id)');
        $this->addSql('ALTER TABLE products_promotions ADD CONSTRAINT FK_3844C82A4584665A FOREIGN KEY (product_id) REFERENCES products (id)');
        $this->addSql('CREATE TABLE cities_promotions (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', city_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', promotion_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_1DEFA218BAC62AF (city_id), INDEX IDX_1DEFA21139DF194 (promotion_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cities_promotions ADD CONSTRAINT FK_1DEFA218BAC62AF FOREIGN KEY (city_id) REFERENCES cities (id)');
        $this->addSql('ALTER TABLE cities_promotions ADD CONSTRAINT FK_1DEFA21139DF194 FOREIGN KEY (promotion_id) REFERENCES promotions (id)');
        $this->addSql('CREATE TABLE countries_promotions (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', country_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', promotions_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_9DE1DFBCF92F3E70 (country_id), INDEX IDX_9DE1DFBC10007789 (promotions_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE countries_promotions ADD CONSTRAINT FK_9DE1DFBCF92F3E70 FOREIGN KEY (country_id) REFERENCES countries (id)');
        $this->addSql('ALTER TABLE countries_promotions ADD CONSTRAINT FK_9DE1DFBC10007789 FOREIGN KEY (promotions_id) REFERENCES promotions (id)');
        $this->addSql('CREATE TABLE regions_promotions (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', region_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', promotion_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_434763EF98260155 (region_id), INDEX IDX_434763EF139DF194 (promotion_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE regions_promotions ADD CONSTRAINT FK_434763EF98260155 FOREIGN KEY (region_id) REFERENCES regions (id)');
        $this->addSql('ALTER TABLE regions_promotions ADD CONSTRAINT FK_434763EF139DF194 FOREIGN KEY (promotion_id) REFERENCES promotions (id)');

        $this->addSql('ALTER TABLE orders ADD coupon_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE orders ADD INDEX IDX_E52FFDEE66C5951B (coupon_id)');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE66C5951B FOREIGN KEY (coupon_id) REFERENCES coupons (id)');
    }
}
