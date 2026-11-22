<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\Geo\GeoReferenceRepointer;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120091000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fusiona regiones y ciudades duplicadas (mismo país y código, mismo geonameid) conservando sus referencias y agrega claves únicas para impedir nuevos duplicados.';
    }

    public function up(Schema $schema): void
    {
        $merged = (new GeoReferenceRepointer($this->connection))->mergeExactDuplicates();
        $this->write(sprintf('Duplicados fusionados: %d regiones, %d ciudades.', $merged['regions'], $merged['cities']));

        $this->addSql('CREATE UNIQUE INDEX uniq_regions_country_iso_code ON regions (country_id, iso_code)');
        $this->addSql('CREATE UNIQUE INDEX uniq_cities_geoname_id ON cities (name)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_regions_country_iso_code ON regions');
        $this->addSql('DROP INDEX uniq_cities_geoname_id ON cities');
    }
}
