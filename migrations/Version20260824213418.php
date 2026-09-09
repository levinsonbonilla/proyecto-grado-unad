<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260824213418 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'products_colors.color_id nullable (bloques sin color) + images_products.product_color_id (foto -> bloque de variante).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products_colors CHANGE color_id color_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');

        $this->addSql('ALTER TABLE images_products ADD product_color_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE images_products ADD CONSTRAINT FK_IMAGES_PRODUCTS_PRODUCT_COLOR FOREIGN KEY (product_color_id) REFERENCES products_colors (id)');
        $this->addSql('CREATE INDEX IDX_IMAGES_PRODUCTS_PRODUCT_COLOR ON images_products (product_color_id)');

        $this->addSql(
            'UPDATE images_products ip
             INNER JOIN products_colors pc ON pc.image = ip.image AND pc.product_id = ip.product_id
             SET ip.product_color_id = pc.id
             WHERE ip.product_color_id IS NULL'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE images_products DROP FOREIGN KEY FK_IMAGES_PRODUCTS_PRODUCT_COLOR');
        $this->addSql('DROP INDEX IDX_IMAGES_PRODUCTS_PRODUCT_COLOR ON images_products');
        $this->addSql('ALTER TABLE images_products DROP product_color_id');

        $this->addSql('ALTER TABLE products_colors CHANGE color_id color_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\'');
    }
}
