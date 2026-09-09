<?php

namespace App\Tests\Integration\Service\Geo;

use App\Entity\Configurations\Globals\Cities;
use App\Entity\Configurations\Globals\Countries;
use App\Entity\Configurations\Globals\Regions;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use App\Service\Geo\GeoNamesCountryImporterService;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GeoNamesCountryImporterServiceTest extends IntegrationTestCase
{
    private const COUNTRY_INFO = "ZZ\tZZZ\t999\tZZ\tZedland\tZ City\t100\t1000\tXX\t.zz\n";
    private const ADMIN1 = "ZZ.01\tRegion One\tRegion One\t1001\n";

    private function buildZipBytes(): string
    {
        $rows = [
            ['5001', 'Capital City', 'Capital City', '', '1.0', '1.0', 'P', 'PPLC', 'ZZ', '', '01', '', '', '', '500000', '', '', 'UTC', ''],
            ['5002', 'Big Town', 'Big Town', '', '1.0', '1.0', 'P', 'PPL', 'ZZ', '', '01', '', '', '', '8000', '', '', 'UTC', ''],
        ];
        $content = implode("\n", array_map(fn (array $r) => implode("\t", $r), $rows));

        $tmpFile = tempnam(sys_get_temp_dir(), 'geonames_it_');
        $zip = new \ZipArchive();
        $zip->open($tmpFile, \ZipArchive::OVERWRITE);
        $zip->addFromString('ZZ.txt', $content);
        $zip->close();
        $bytes = file_get_contents($tmpFile);
        unlink($tmpFile);

        return $bytes;
    }

    private function makeService(): GeoNamesCountryImporterService
    {
        $zipBytes = $this->buildZipBytes();
        $httpClient = new MockHttpClient(fn (string $method, string $url) => match (true) {
            str_ends_with($url, 'countryInfo.txt')      => new MockResponse(self::COUNTRY_INFO),
            str_ends_with($url, 'admin1CodesASCII.txt') => new MockResponse(self::ADMIN1),
            str_ends_with($url, 'ZZ.zip')                => new MockResponse($zipBytes),
            default => new MockResponse(''),
        });

        return new GeoNamesCountryImporterService(
            $httpClient,
            static::getContainer()->get(CountriesRepository::class),
            static::getContainer()->get(RegionsRepository::class),
            static::getContainer()->get(CitiesRepository::class),
            static::getContainer()->get(CustomeEntityManagerInterface::class),
        );
    }

    public function testImportCountryPersistsCountryRegionAndCitiesToDatabase(): void
    {
        $result = $this->makeService()->importCountry('ZZ', 5000);

        $this->assertTrue($result->countryCreated);
        $this->assertSame(1, $result->regionsCreated);
        $this->assertSame(2, $result->citiesCreated);

        $country = $this->em->getRepository(Countries::class)->findOneBy(['isoCode' => 'ZZ']);
        $this->assertNotNull($country);
        $this->assertSame('Zedland', $country->getName('es'));

        $region = $this->em->getRepository(Regions::class)->findOneBy(['isoCode' => '01']);
        $this->assertNotNull($region);
        $this->assertSame('Region One', $region->getName('es'));
        $this->assertSame((string) $country->getId(), (string) $region->getCountry()->getId());

        $capital = $this->em->getRepository(Cities::class)->findOneBy(['name' => '5001']);
        $this->assertNotNull($capital);
        $this->assertSame('Capital City', $capital->getName('es'));
        $this->assertSame((string) $region->getId(), (string) $capital->getRegion()->getId());

        $bigTown = $this->em->getRepository(Cities::class)->findOneBy(['name' => '5002']);
        $this->assertNotNull($bigTown);
    }

    public function testRunningImportTwiceDoesNotDuplicateAnything(): void
    {
        $this->makeService()->importCountry('ZZ', 5000);
        $second = $this->makeService()->importCountry('ZZ', 5000);

        $this->assertFalse($second->countryCreated);
        $this->assertSame(0, $second->regionsCreated);
        $this->assertSame(1, $second->regionsSkipped);
        $this->assertSame(0, $second->citiesCreated);
        $this->assertSame(2, $second->citiesSkipped);

        $countries = $this->em->getRepository(Countries::class)->findBy(['isoCode' => 'ZZ']);
        $this->assertCount(1, $countries);

        $regions = $this->em->getRepository(Regions::class)->findBy(['isoCode' => '01']);
        $this->assertCount(1, $regions);

        $cities = $this->em->getRepository(Cities::class)->findBy(['name' => '5001']);
        $this->assertCount(1, $cities);
    }
}
