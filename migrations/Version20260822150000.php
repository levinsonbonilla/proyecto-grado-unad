<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260822150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catálogo de colores (colors/products_colors), columna product_color_id en shopping_cart/orders_products y alta de la feature products_colors.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE colors (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', domain_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(100) NOT NULL, hex_code VARCHAR(7) NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_COLORS_DOMAIN (domain_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE colors ADD CONSTRAINT FK_COLORS_DOMAIN FOREIGN KEY (domain_id) REFERENCES domains (id)');

        $this->addSql('CREATE TABLE products_colors (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', product_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', color_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', stock INT DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_PRODUCTS_COLORS_PRODUCT (product_id), INDEX IDX_PRODUCTS_COLORS_COLOR (color_id), UNIQUE INDEX uniq_product_color (product_id, color_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE products_colors ADD CONSTRAINT FK_PRODUCTS_COLORS_PRODUCT FOREIGN KEY (product_id) REFERENCES products (id)');
        $this->addSql('ALTER TABLE products_colors ADD CONSTRAINT FK_PRODUCTS_COLORS_COLOR FOREIGN KEY (color_id) REFERENCES colors (id)');

        $this->addSql('ALTER TABLE shopping_cart ADD product_color_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE shopping_cart ADD CONSTRAINT FK_SHOPPING_CART_PRODUCT_COLOR FOREIGN KEY (product_color_id) REFERENCES products_colors (id)');
        $this->addSql('CREATE INDEX IDX_SHOPPING_CART_PRODUCT_COLOR ON shopping_cart (product_color_id)');

        $this->addSql('ALTER TABLE orders_products ADD product_color_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE orders_products ADD CONSTRAINT FK_ORDERS_PRODUCTS_PRODUCT_COLOR FOREIGN KEY (product_color_id) REFERENCES products_colors (id)');
        $this->addSql('CREATE INDEX IDX_ORDERS_PRODUCTS_PRODUCT_COLOR ON orders_products (product_color_id)');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->addSql(
            "INSERT INTO module_features (id, module_id, name, slug, description, active, created_at, updated_at)
            SELECT UUID_TO_BIN(UUID()), m.id, 'Colores', 'products_colors',
                'Catálogo de colores reutilizable para definir variantes de color por producto', 1, '$now', '$now'
            FROM modules m WHERE m.slug = 'products'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM module_features WHERE slug = 'products_colors'");

        $this->addSql('ALTER TABLE orders_products DROP FOREIGN KEY FK_ORDERS_PRODUCTS_PRODUCT_COLOR');
        $this->addSql('DROP INDEX IDX_ORDERS_PRODUCTS_PRODUCT_COLOR ON orders_products');
        $this->addSql('ALTER TABLE orders_products DROP product_color_id');

        $this->addSql('ALTER TABLE shopping_cart DROP FOREIGN KEY FK_SHOPPING_CART_PRODUCT_COLOR');
        $this->addSql('DROP INDEX IDX_SHOPPING_CART_PRODUCT_COLOR ON shopping_cart');
        $this->addSql('ALTER TABLE shopping_cart DROP product_color_id');

        $this->addSql('ALTER TABLE products_colors DROP FOREIGN KEY FK_PRODUCTS_COLORS_PRODUCT');
        $this->addSql('ALTER TABLE products_colors DROP FOREIGN KEY FK_PRODUCTS_COLORS_COLOR');
        $this->addSql('DROP TABLE products_colors');

        $this->addSql('ALTER TABLE colors DROP FOREIGN KEY FK_COLORS_DOMAIN');
        $this->addSql('DROP TABLE colors');
    }
}
