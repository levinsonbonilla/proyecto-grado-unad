<?php

namespace App\Tests\Unit\Service\Geo;

use App\Entity\Configurations\Globals\Cities;
use App\Entity\Configurations\Globals\Countries;
use App\Entity\Configurations\Globals\Regions;
use App\Exception\GenericException;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use App\Service\Geo\GeoNamesCountryImporterService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GeoNamesCountryImporterServiceTest extends TestCase
{
    private const COUNTRY_INFO = "#comment line, debe ignorarse\nZZ\tZZZ\t999\tZZ\tZedland\tZ City\t100\t1000\tXX\t.zz\n";
    private const ADMIN1 = "ZZ.01\tRegion One\tRegion One\t1001\nZZ.02\tRegion Two\tRegion Two\t1002\n";

    private CountriesRepository&MockObject $countriesRepository;
    private RegionsRepository&MockObject $regionsRepository;
    private CitiesRepository&MockObject $citiesRepository;
    private CustomeEntityManagerInterface&MockObject $em;

    protected function setUp(): void
    {
        $this->countriesRepository = $this->createMock(CountriesRepository::class);
        $this->regionsRepository   = $this->createMock(RegionsRepository::class);
        $this->citiesRepository    = $this->createMock(CitiesRepository::class);
        $this->em                  = $this->createMock(CustomeEntityManagerInterface::class);
    }

    private function buildZipBytes(): string
    {
        $rows = [
            ['5001', 'Capital City', 'Capital City', '', '1.0', '1.0', 'P', 'PPLC', 'ZZ', '', '01', '', '', '', '500000', '', '', 'UTC', ''],
            ['5002', 'Big Town', 'Big Town', '', '1.0', '1.0', 'P', 'PPL', 'ZZ', '', '01', '', '', '', '8000', '', '', 'UTC', ''],
            ['5003', 'Tiny Village', 'Tiny Village', '', '1.0', '1.0', 'P', 'PPL', 'ZZ', '', '02', '', '', '', '200', '', '', 'UTC', ''],
            ['5004', 'Some Mountain', 'Some Mountain', '', '1.0', '1.0', 'T', 'MT', 'ZZ', '', '01', '', '', '', '0', '', '', 'UTC', ''],
        ];
        $content = implode("\n", array_map(fn (array $r) => implode("\t", $r), $rows));

        $tmpFile = tempnam(sys_get_temp_dir(), 'geonames_test_');
        $zip = new \ZipArchive();
        $zip->open($tmpFile, \ZipArchive::OVERWRITE);
        $zip->addFromString('ZZ.txt', $content);
        $zip->close();

        $bytes = file_get_contents($tmpFile);
        unlink($tmpFile);

        return $bytes;
    }

    private function makeHttpClient(): MockHttpClient
    {
        $zipBytes = $this->buildZipBytes();

        return new MockHttpClient(function (string $method, string $url) use ($zipBytes) {
            return match (true) {
                str_ends_with($url, 'countryInfo.txt')       => new MockResponse(self::COUNTRY_INFO),
                str_ends_with($url, 'admin1CodesASCII.txt')  => new MockResponse(self::ADMIN1),
                str_ends_with($url, 'ZZ.zip')                => new MockResponse($zipBytes),
                default => throw new \RuntimeException("URL inesperada en el test: $url"),
            };
        });
    }

    public function testImportCountryCreatesCountryRegionsAndFilteredCities(): void
    {
        $this->countriesRepository->method('findOneBy')->willReturn(null);
        $this->regionsRepository->method('findOneBy')->willReturn(null);
        $this->citiesRepository->method('findOneBy')->willReturn(null);

        $addedEntities = [];
        $this->em->method('add')->willReturnCallback(function (object $entity) use (&$addedEntities) {
            $addedEntities[] = $entity;
        });

        $this->countriesRepository->expects($this->once())->method('invalidateAllActiveCache');

        $service = new GeoNamesCountryImporterService(
            $this->makeHttpClient(),
            $this->countriesRepository,
            $this->regionsRepository,
            $this->citiesRepository,
            $this->em,
        );

        $result = $service->importCountry('zz', 5000);

        $this->assertTrue($result->countryCreated);
        $this->assertSame('Zedland', $result->country->getName('es'));
        $this->assertSame(2, $result->regionsCreated);
        $this->assertSame(0, $result->regionsSkipped);

        $this->assertSame(2, $result->citiesCreated);
        $this->assertSame(0, $result->citiesSkipped);

        $cityNames = array_map(
            fn (Cities $c) => $c->getName('es'),
            array_filter($addedEntities, fn ($e) => $e instanceof Cities)
        );
        $this->assertContains('Capital City', $cityNames);
        $this->assertContains('Big Town', $cityNames);
        $this->assertNotContains('Tiny Village', $cityNames);
        $this->assertNotContains('Some Mountain', $cityNames);

        $regionCount = count(array_filter($addedEntities, fn ($e) => $e instanceof Regions));
        $this->assertSame(2, $regionCount);
    }

    public function testImportCountryWithZeroThresholdIncludesLowPopulationCity(): void
    {
        $this->countriesRepository->method('findOneBy')->willReturn(null);
        $this->regionsRepository->method('findOneBy')->willReturn(null);
        $this->citiesRepository->method('findOneBy')->willReturn(null);
        $this->em->method('add');

        $service = new GeoNamesCountryImporterService(
            $this->makeHttpClient(),
            $this->countriesRepository,
            $this->regionsRepository,
            $this->citiesRepository,
            $this->em,
        );

        $result = $service->importCountry('ZZ', 0);

        $this->assertSame(3, $result->citiesCreated);
    }

    public function testImportCountryIsIdempotentWhenEverythingAlreadyExists(): void
    {
        $existingCountry = $this->createMock(Countries::class);
        $existingRegion  = $this->createMock(Regions::class);
        $existingCity    = $this->createMock(Cities::class);

        $this->countriesRepository->method('findOneBy')->willReturn($existingCountry);
        $this->regionsRepository->method('findOneBy')->willReturn($existingRegion);
        $this->citiesRepository->method('findOneBy')->willReturn($existingCity);

        $this->em->expects($this->never())->method('add');

        $this->countriesRepository->expects($this->never())->method('invalidateAllActiveCache');

        $service = new GeoNamesCountryImporterService(
            $this->makeHttpClient(),
            $this->countriesRepository,
            $this->regionsRepository,
            $this->citiesRepository,
            $this->em,
        );

        $result = $service->importCountry('ZZ', 5000);

        $this->assertFalse($result->countryCreated);
        $this->assertSame(0, $result->regionsCreated);
        $this->assertSame(2, $result->regionsSkipped);
        $this->assertSame(0, $result->citiesCreated);
        $this->assertSame(2, $result->citiesSkipped);
    }

    public function testImportCountryRejectsInvalidIsoCode(): void
    {
        $service = new GeoNamesCountryImporterService(
            new MockHttpClient(),
            $this->countriesRepository,
            $this->regionsRepository,
            $this->citiesRepository,
            $this->em,
        );

        $this->expectException(GenericException::class);
        $service->importCountry('ColombiaEntera');
    }

    public function testImportCountryThrowsWhenIsoCodeUnknownToGeoNames(): void
    {
        $this->countriesRepository->method('findOneBy')->willReturn(null);
        $httpClient = new MockHttpClient(fn (string $method, string $url) => match (true) {
            str_ends_with($url, 'countryInfo.txt') => new MockResponse(self::COUNTRY_INFO),
            default => new MockResponse(''),
        });

        $service = new GeoNamesCountryImporterService(
            $httpClient,
            $this->countriesRepository,
            $this->regionsRepository,
            $this->citiesRepository,
            $this->em,
        );

        $this->expectException(GenericException::class);
        $service->importCountry('XX');
    }
}
