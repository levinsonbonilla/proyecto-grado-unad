<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260824233000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename sizes -> medidas (tabla, columna FK, índice único, feature) -- sin cambio de comportamiento.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products_colors DROP FOREIGN KEY FK_PRODUCTS_COLORS_SIZE');
        $this->addSql('DROP INDEX uniq_product_color_size ON products_colors');
        $this->addSql('DROP INDEX IDX_PRODUCTS_COLORS_SIZE ON products_colors');

        $this->addSql('RENAME TABLE sizes TO medidas');
        $this->addSql('ALTER TABLE medidas RENAME INDEX IDX_SIZES_DOMAIN TO IDX_MEDIDAS_DOMAIN');
        $this->addSql('ALTER TABLE medidas DROP FOREIGN KEY FK_SIZES_DOMAIN');
        $this->addSql('ALTER TABLE medidas ADD CONSTRAINT FK_MEDIDAS_DOMAIN FOREIGN KEY (domain_id) REFERENCES domains (id)');

        $this->addSql('ALTER TABLE products_colors CHANGE size_id medida_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE products_colors ADD CONSTRAINT FK_PRODUCTS_COLORS_MEDIDA FOREIGN KEY (medida_id) REFERENCES medidas (id)');
        $this->addSql('CREATE INDEX IDX_PRODUCTS_COLORS_MEDIDA ON products_colors (medida_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_color_medida ON products_colors (product_id, color_id, medida_id)');

        $this->addSql("UPDATE module_features SET slug = 'products_medidas', name = 'Medidas', description = 'Catálogo de medidas reutilizable para definir variantes de medida por producto' WHERE slug = 'products_sizes'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE module_features SET slug = 'products_sizes', name = 'Tallas', description = 'Catálogo de tallas reutilizable para definir variantes de talla por producto' WHERE slug = 'products_medidas'");

        $this->addSql('ALTER TABLE products_colors DROP FOREIGN KEY FK_PRODUCTS_COLORS_MEDIDA');
        $this->addSql('DROP INDEX uniq_product_color_medida ON products_colors');
        $this->addSql('DROP INDEX IDX_PRODUCTS_COLORS_MEDIDA ON products_colors');
        $this->addSql('ALTER TABLE products_colors CHANGE medida_id size_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');

        $this->addSql('ALTER TABLE medidas DROP FOREIGN KEY FK_MEDIDAS_DOMAIN');
        $this->addSql('ALTER TABLE medidas RENAME INDEX IDX_MEDIDAS_DOMAIN TO IDX_SIZES_DOMAIN');
        $this->addSql('RENAME TABLE medidas TO sizes');
        $this->addSql('ALTER TABLE sizes ADD CONSTRAINT FK_SIZES_DOMAIN FOREIGN KEY (domain_id) REFERENCES domains (id)');

        $this->addSql('ALTER TABLE products_colors ADD CONSTRAINT FK_PRODUCTS_COLORS_SIZE FOREIGN KEY (size_id) REFERENCES sizes (id)');
        $this->addSql('CREATE INDEX IDX_PRODUCTS_COLORS_SIZE ON products_colors (size_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_color_size ON products_colors (product_id, color_id, size_id)');
    }
}
