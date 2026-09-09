<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260826180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'products_colors.order_column -- orden manual del bloque de variante en la ficha pública (swatch + galería).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products_colors ADD order_column INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products_colors DROP order_column');
    }
}
