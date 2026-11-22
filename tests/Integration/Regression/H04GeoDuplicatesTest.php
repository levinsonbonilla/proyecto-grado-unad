<?php

namespace App\Tests\Integration\Regression;

use App\ArgumentHandler\CitiesArgument;
use App\ArgumentHandler\RegionsArgument;
use App\Entity\Configurations\Globals\Cities;
use App\Entity\Configurations\Globals\Countries;
use App\Entity\Configurations\Globals\Regions;
use App\Entity\Configurations\Regions\RegionsDomains;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use App\Service\Geo\GeoCascadeResolverInterface;
use App\Service\Geo\GeoNamesCountryImporterService;
use App\Tests\Integration\IntegrationTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class H04GeoDuplicatesTest extends IntegrationTestCase
{
    private const ADMIN1 = "CO.24\tRisaralda Department\tRisaralda Department\t3670698\nCO.33\tCundinamarca\tCundinamarca\t3685413\nCO.34\tBogota D.C.\tBogota D.C.\t3688685\n";

    private function colombia(): Countries
    {
        $country = $this->em->getRepository(Countries::class)->findOneBy(['isoCode' => 'CO']);
        $this->assertNotNull($country);
        return $country;
    }

    private function regionNamed(string $spanishName): Regions
    {
        foreach ($this->em->getRepository(Regions::class)->findBy(['country' => $this->colombia()]) as $region) {
            if (mb_strtolower($region->getName('es')) === mb_strtolower($spanishName)) {
                return $region;
            }
        }
        $this->fail("No existe la región {$spanishName}");
    }

    private function buildDump(): string
    {
        $rows = [
            ['3667905', 'Soacha', 'Soacha', '', '4.5', '-74.2', 'P', 'PPLA2', 'CO', '', '33', '', '', '', '655025', '', '', 'America/Bogota', ''],
            ['3688689', 'Bogotá', 'Bogota', '', '4.6', '-74.1', 'P', 'PPLC', 'CO', '', '34', '', '', '', '7674366', '', '', 'America/Bogota', ''],
            ['3672486', 'Pereira', 'Pereira', '', '4.8', '-75.7', 'P', 'PPLA', 'CO', '', '24', '', '', '', '440000', '', '', 'America/Bogota', ''],
        ];
        $tmp = tempnam(sys_get_temp_dir(), 'geo_h04_');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $zip->addFromString('CO.txt', implode("\n", array_map(fn (array $r) => implode("\t", $r), $rows)));
        $zip->close();
        $bytes = file_get_contents($tmp);
        unlink($tmp);
        return $bytes;
    }

    private function importColombia(): void
    {
        $zip = $this->buildDump();
        $http = new MockHttpClient(fn (string $method, string $url) => match (true) {
            str_ends_with($url, 'admin1CodesASCII.txt') => new MockResponse(self::ADMIN1),
            str_ends_with($url, 'CO.zip') => new MockResponse($zip),
            default => throw new \RuntimeException("URL inesperada: {$url}"),
        });

        $container = static::getContainer();
        $importer = new GeoNamesCountryImporterService(
            $http,
            $container->get(CountriesRepository::class),
            $container->get(RegionsRepository::class),
            $container->get(CitiesRepository::class),
            $container->get(CustomeEntityManagerInterface::class),
            $container->get(\App\Service\Geo\GeoCatalogInterface::class),
        );
        $importer->importCountry('CO', 5000);
        $this->em->clear();
        $importer->importCountry('CO', 5000);
        $this->em->clear();
    }

    private function regionLabels(): array
    {
        $rows = static::getContainer()->get(GeoCascadeResolverInterface::class)
            ->regionsForCountries([(string) $this->colombia()->getId()], 'es');
        return array_map(fn (array $r) => mb_strtolower($r['name']), $rows);
    }

    private function cityLabels(Regions $region): array
    {
        $rows = static::getContainer()->get(GeoCascadeResolverInterface::class)
            ->citiesForRegions([(string) $region->getId()], 'es');
        return array_map(fn (array $r) => mb_strtolower($r['name']), $rows);
    }

    private function simulateGeoIpVisit(string $isoCode, array $regionNames, string $geoNameId, array $cityNames): void
    {
        $catalog = static::getContainer()->get(\App\Service\Geo\GeoCatalogInterface::class);
        $region = $catalog->findOrCreateRegion($this->colombia(), $isoCode, json_encode($regionNames, JSON_UNESCAPED_UNICODE));
        $catalog->findOrCreateCity($region, $geoNameId, json_encode(['names' => $cityNames, 'geoname_id' => (int) $geoNameId], JSON_UNESCAPED_UNICODE));
        $this->em->flush();
        $this->em->clear();
    }

    public function testSeedSoachaUsesItsOwnGeoNamesId(): void
    {
        $cities = $this->em->getRepository(Cities::class)->findBy(['region' => $this->regionNamed('Cundinamarca')]);
        $this->assertCount(1, $cities);
        $this->assertSame('3667905', $cities[0]->getGeoNameId(), 'Soacha es 3667905 en GeoNames; 3688689 es Bogotá');
    }

    public function testImportingColombiaTwiceReusesRegionsAndCitiesCreatedFromGeoIp(): void
    {
        $this->importColombia();

        $regions = $this->regionLabels();
        $this->assertSame(count($regions), count(array_unique($regions)), 'Regiones duplicadas: ' . implode(', ', $regions));
        $this->assertSame(['bogotá d.c.', 'cundinamarca', 'risaralda'], $regions);

        $this->assertSame(['soacha'], $this->cityLabels($this->regionNamed('Cundinamarca')), 'Soacha debe aparecer una sola vez en Cundinamarca');
        $this->assertSame(['bogotá'], $this->cityLabels($this->regionNamed('Bogotá D.C.')));
        $this->assertSame(['pereira'], $this->cityLabels($this->regionNamed('Risaralda')));
    }

    public function testGeoIpVisitAfterImportDoesNotCreateASecondRisaralda(): void
    {
        $this->importColombia();

        $this->simulateGeoIpVisit('RIS', ['en' => 'Risaralda', 'es' => 'Risaralda'], '3672486', ['en' => 'Pereira', 'es' => 'Pereira']);

        $this->assertSame(1, count(array_keys($this->regionLabels(), 'risaralda')), 'Risaralda debe quedar una sola vez');
        $this->assertSame(['pereira'], $this->cityLabels($this->regionNamed('Risaralda')));
    }

    public function testGeoIpVisitBeforeImportIsReusedByTheImport(): void
    {
        $this->simulateGeoIpVisit('RIS', ['en' => 'Risaralda', 'es' => 'Risaralda'], '3672486', ['en' => 'Pereira', 'es' => 'Pereira']);

        $this->importColombia();

        $this->assertSame(1, count(array_keys($this->regionLabels(), 'risaralda')));
        $this->assertSame(['pereira'], $this->cityLabels($this->regionNamed('Risaralda')));
    }

    public function testDatabaseRejectsDuplicateRegionCodeInTheSameCountry(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);

        $duplicate = (new Regions())->add(new RegionsArgument([
            'name' => json_encode(['es' => 'Cundinamarca']),
            'description' => 'Región',
            'isoCode' => 'CUN',
        ], $this->colombia()));
        $this->em->persist($duplicate);
        $this->em->flush();
    }

    public function testDatabaseRejectsDuplicateCityGeoNamesId(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);

        $duplicate = (new Cities())->add(new CitiesArgument([
            'name' => '3667905',
            'description' => 'Ciudad',
            'names' => json_encode(['es' => 'Soacha']),
        ], $this->regionNamed('Cundinamarca')));
        $this->em->persist($duplicate);
        $this->em->flush();
    }

    public function testMergeCommandCollapsesExistingDuplicatesAndKeepsReferences(): void
    {
        $country = $this->colombia();
        $risaralda = (new Regions())->add(new RegionsArgument(['name' => json_encode(['es' => 'Risaralda']), 'description' => 'Región', 'isoCode' => 'RIS'], $country));
        $risaraldaCopy = (new Regions())->add(new RegionsArgument(['name' => json_encode(['es' => 'Risaralda Department']), 'description' => 'Región', 'isoCode' => '24'], $country));
        $this->em->persist($risaralda);
        $this->em->persist($risaraldaCopy);
        $this->em->flush();
        $soachaCopy = (new Cities())->add(new CitiesArgument(['name' => 'manual-soacha', 'description' => 'Ciudad', 'names' => json_encode(['es' => 'Soacha'])], $this->regionNamed('Cundinamarca')));
        $this->em->persist($soachaCopy);
        $pereiraCopy = (new Cities())->add(new CitiesArgument(['name' => '3672486', 'description' => 'Ciudad', 'names' => json_encode(['es' => 'Pereira'])], $risaraldaCopy));
        $this->em->persist($pereiraCopy);
        $domain = $this->em->getRepository(Domains::class)->findOneBy(['domain' => 'http://localhost:8060']);
        $this->em->persist((new RegionsDomains())->add($risaralda, $domain));
        $this->em->persist((new RegionsDomains())->add($risaraldaCopy, $domain));
        $this->em->flush();

        $customer = $this->em->getRepository(Users::class)->findOneBy(['email' => 'customer.test@proyecto-grado.test']);
        $this->em->getConnection()->executeStatement(
            'UPDATE orders SET shipping_region_id = ?, shipping_city_id = ? WHERE user_id = ?',
            [$risaraldaCopy->getId()->toBinary(), $soachaCopy->getId()->toBinary(), $customer->getId()->toBinary()]
        );
        $this->em->clear();
        $this->assertSame(2, count(array_keys(array_map(fn ($n) => str_replace(' department', '', $n), $this->regionLabels()), 'risaralda')));

        $application = new Application(static::$kernel);
        $tester = new CommandTester($application->find('app:geo:merge-duplicates'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        $this->em->clear();

        $this->assertSame(['bogotá d.c.', 'cundinamarca', 'risaralda'], $this->regionLabels());
        $this->assertSame(['soacha'], $this->cityLabels($this->regionNamed('Cundinamarca')));
        $this->assertSame(['pereira'], $this->cityLabels($this->regionNamed('Risaralda')));

        $orphans = (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM orders o LEFT JOIN regions r ON r.id = o.shipping_region_id LEFT JOIN cities c ON c.id = o.shipping_city_id WHERE r.id IS NULL OR c.id IS NULL'
        );
        $this->assertSame(0, $orphans, 'Los pedidos deben seguir apuntando a una región y ciudad existentes');

        $links = (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM regions_domains WHERE region_id = ?', [$this->regionNamed('Risaralda')->getId()->toBinary()]);
        $this->assertSame(1, $links, 'La asignación región-dominio no debe quedar duplicada tras la fusión');

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
    }

    public function testGeoIpCreationSurvivesAConcurrentInsertOfTheSameRegionAndCity(): void
    {
        $catalog = static::getContainer()->get(\App\Service\Geo\GeoCatalogInterface::class);
        $cundinamarca = $this->regionNamed('Cundinamarca');

        $region = $catalog->insertRegionOrFetch($this->colombia(), 'CUN', json_encode(['es' => 'Cundinamarca']));
        $city = $catalog->insertCityOrFetch($region, '3667905', json_encode(['es' => 'Soacha']));

        $this->assertTrue($this->em->isOpen(), 'Perder la carrera no puede cerrar el EntityManager (provocaría error 500)');
        $this->assertTrue($region->getId()->equals($cundinamarca->getId()));
        $this->assertSame('3667905', $city->getGeoNameId());
        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM regions WHERE iso_code = 'CUN'"));
        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM cities WHERE name = '3667905'"));
    }

    public function testGeoIpVisitCreatesAMissingRegionOnlyOnce(): void
    {
        $catalog = static::getContainer()->get(\App\Service\Geo\GeoCatalogInterface::class);

        $first = $catalog->ensureRegion($this->colombia(), 'RIS', json_encode(['en' => 'Risaralda', 'es' => 'Risaralda']));
        $second = $catalog->insertRegionOrFetch($this->colombia(), 'RIS', json_encode(['en' => 'Risaralda', 'es' => 'Risaralda']));
        $third = $catalog->ensureRegion($this->colombia(), 'RIS', json_encode(['en' => 'Risaralda', 'es' => 'Risaralda']));

        $this->assertTrue($first->getId()->equals($second->getId()));
        $this->assertTrue($first->getId()->equals($third->getId()));
        $this->assertSame(1, count(array_keys($this->regionLabels(), 'risaralda')));
    }

    public function testMigrationMergesTheRisaraldaAndSoachaDuplicatesSeenByTheTutor(): void
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement('DROP INDEX uniq_regions_country_iso_code');
        $connection->executeStatement('DROP INDEX uniq_cities_geoname_id');

        $country = $this->colombia();
        $risaraldas = [];
        foreach ([1, 2, 3] as $copy) {
            $region = (new Regions())->add(new RegionsArgument(['name' => json_encode(['en' => 'Risaralda', 'es' => 'Risaralda']), 'description' => 'Región', 'isoCode' => 'RIS'], $country));
            $this->em->persist($region);
            $this->em->flush();
            $risaraldas[] = $region;
        }
        $soachaCopy = (new Cities())->add(new CitiesArgument(['name' => '3667905', 'description' => 'Ciudad', 'names' => json_encode(['es' => 'Soacha'])], $this->regionNamed('Cundinamarca')));
        $this->em->persist($soachaCopy);
        $domain = $this->em->getRepository(Domains::class)->findOneBy(['domain' => 'http://localhost:8060']);
        foreach ($risaraldas as $region) {
            $this->em->persist((new RegionsDomains())->add($region, $domain));
        }
        $this->em->flush();

        $customer = $this->em->getRepository(Users::class)->findOneBy(['email' => 'customer.test@proyecto-grado.test']);
        $connection->executeStatement(
            'UPDATE orders SET shipping_region_id = ?, shipping_city_id = ? WHERE user_id = ?',
            [$risaraldas[2]->getId()->toBinary(), $soachaCopy->getId()->toBinary(), $customer->getId()->toBinary()]
        );
        $this->em->clear();
        $this->assertSame(3, count(array_keys($this->regionLabels(), 'risaralda')));
        $this->assertSame(['soacha', 'soacha'], $this->cityLabels($this->regionNamed('Cundinamarca')));

        $merged = (new \App\Service\Geo\GeoReferenceRepointer($connection))->mergeExactDuplicates();
        $this->em->clear();

        $this->assertSame(['regions' => 2, 'cities' => 1], $merged);
        $this->assertSame(1, count(array_keys($this->regionLabels(), 'risaralda')));
        $this->assertSame(['soacha'], $this->cityLabels($this->regionNamed('Cundinamarca')));
        $this->assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM regions_domains WHERE region_id = ?', [$this->regionNamed('Risaralda')->getId()->toBinary()]));
        $orphans = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM orders o LEFT JOIN regions r ON r.id = o.shipping_region_id LEFT JOIN cities c ON c.id = o.shipping_city_id WHERE r.id IS NULL OR c.id IS NULL'
        );
        $this->assertSame(0, $orphans);

        $connection->executeStatement('CREATE UNIQUE INDEX uniq_regions_country_iso_code ON regions (country_id, iso_code)');
        $connection->executeStatement('CREATE UNIQUE INDEX uniq_cities_geoname_id ON cities (name)');
    }
}
