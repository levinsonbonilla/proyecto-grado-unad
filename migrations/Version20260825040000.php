<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'products_colors.variant_group_id -- correlaciona filas creadas de un mismo envío de bloque (múltiples medidas por color).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products_colors ADD variant_group_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('CREATE INDEX IDX_PRODUCTS_COLORS_VARIANT_GROUP ON products_colors (variant_group_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_PRODUCTS_COLORS_VARIANT_GROUP ON products_colors');
        $this->addSql('ALTER TABLE products_colors DROP variant_group_id');
    }
}
