<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260824232433 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catálogo de tallas (sizes) + products_colors.size_id + índice único por combinación exacta + alta de la feature products_sizes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sizes (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', domain_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(100) NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_SIZES_DOMAIN (domain_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE sizes ADD CONSTRAINT FK_SIZES_DOMAIN FOREIGN KEY (domain_id) REFERENCES domains (id)');

        $this->addSql('ALTER TABLE products_colors ADD size_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE products_colors ADD CONSTRAINT FK_PRODUCTS_COLORS_SIZE FOREIGN KEY (size_id) REFERENCES sizes (id)');
        $this->addSql('CREATE INDEX IDX_PRODUCTS_COLORS_SIZE ON products_colors (size_id)');

        $this->addSql('DROP INDEX uniq_product_color ON products_colors');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_color_size ON products_colors (product_id, color_id, size_id)');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->addSql(
            "INSERT INTO module_features (id, module_id, name, slug, description, active, created_at, updated_at)
            SELECT UUID_TO_BIN(UUID()), m.id, 'Tallas', 'products_sizes',
                'Catálogo de tallas reutilizable para definir variantes de talla por producto', 1, '$now', '$now'
            FROM modules m WHERE m.slug = 'products'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM module_features WHERE slug = 'products_sizes'");

        $this->addSql('DROP INDEX uniq_product_color_size ON products_colors');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_color ON products_colors (product_id, color_id)');

        $this->addSql('ALTER TABLE products_colors DROP FOREIGN KEY FK_PRODUCTS_COLORS_SIZE');
        $this->addSql('DROP INDEX IDX_PRODUCTS_COLORS_SIZE ON products_colors');
        $this->addSql('ALTER TABLE products_colors DROP size_id');

        $this->addSql('ALTER TABLE sizes DROP FOREIGN KEY FK_SIZES_DOMAIN');
        $this->addSql('DROP TABLE sizes');
    }
}
