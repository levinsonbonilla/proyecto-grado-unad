<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260812231754 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega stock opcional (nullable) a products.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products ADD stock INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products DROP stock');
    }
}
