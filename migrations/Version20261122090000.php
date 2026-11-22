<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Tenants\Domains\Domains;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122090000 extends AbstractMigration
{
    private const TEMPLATE_VALUES = ['Replace with your domain', 'Reemplace por su dominio'];

    public function getDescription(): string
    {
        return 'Convierte los dominios guardados con el texto de plantilla "Replace with your domain"/"Reemplace por su dominio" en dominios pendientes de configurar.';
    }

    public function up(Schema $schema): void
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT id FROM domains WHERE domain IN (?, ?)',
            self::TEMPLATE_VALUES
        );

        foreach ($ids as $id) {
            $this->addSql('UPDATE domains SET domain = ? WHERE id = ?', [Domains::pendingConfigurationPlaceholder(), $id]);
        }

        $this->write(sprintf('Dominios con texto de plantilla corregidos: %d', count($ids)));
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE domains SET domain = ? WHERE domain LIKE ?', ['Replace with your domain', '%' . Domains::PENDING_CONFIGURATION_SUFFIX]);
    }
}
