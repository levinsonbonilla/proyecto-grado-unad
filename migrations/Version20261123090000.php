<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\Geo\GeoReferenceRepointer;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fusiona países duplicados (mismo código ISO) junto con sus regiones repetidas, conservando sus referencias, y agrega una clave única por código ISO.';
    }

    public function up(Schema $schema): void
    {
        $merged = (new GeoReferenceRepointer($this->connection))->mergeExactDuplicates();
        $this->write(sprintf('Duplicados fusionados: %d países, %d regiones, %d ciudades.', $merged['countries'], $merged['regions'], $merged['cities']));

        $this->addSql('CREATE UNIQUE INDEX uniq_countries_iso_code ON countries (iso_code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_countries_iso_code ON countries');
    }
}
