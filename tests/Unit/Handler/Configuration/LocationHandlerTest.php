<?php

namespace App\Tests\Unit\Handler\Configuration;

use App\Handler\Configuration\LocationHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class LocationHandlerTest extends TestCase
{
    private const DEBUG_IP_UK = '81.2.69.142';

    private CountriesRepository&MockObject $countriesRepository;
    private RegionsRepository&MockObject $regionsRepository;
    private CitiesRepository&MockObject $citiesRepository;
    private CustomeEntityManagerInterface&MockObject $entityManager;

    protected function setUp(): void
    {
        $this->countriesRepository = $this->createMock(CountriesRepository::class);
        $this->countriesRepository->method('findOneBy')->willReturn(null);
        $this->regionsRepository = $this->createMock(RegionsRepository::class);
        $this->regionsRepository->method('findOneBy')->willReturn(null);
        $this->citiesRepository = $this->createMock(CitiesRepository::class);
        $this->citiesRepository->method('findOneBy')->willReturn(null);
        $this->entityManager = $this->createMock(CustomeEntityManagerInterface::class);
    }

    private function buildHandler(string $environment, ?string $debugIpHeader, string $devSimulatedIp = ''): LocationHandler
    {
        $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);
        if ($debugIpHeader !== null) {
            $request->headers->set('X-Debug-Ip', $debugIpHeader);
        }

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $parameters = $this->createMock(ParameterBagInterface::class);
        $parameters->method('get')->willReturnMap([
            ['kernel.project_dir', '/var/www/html'],
            ['ip2_archive_database', '/geolocalization/'],
            ['ip2_file_geolite_city', 'GeoLite2-City.mmdb'],
            ['environment', $environment],
            ['dev_simulated_ip', $devSimulatedIp],
        ]);

        return new LocationHandler(
            $requestStack,
            $parameters,
            $this->countriesRepository,
            $this->regionsRepository,
            $this->citiesRepository,
            $this->entityManager,
        );
    }

    public function testInProdTheDebugHeaderIsIgnoredAndRealClientIpIsUsed(): void
    {

        $handler = $this->buildHandler('prod', self::DEBUG_IP_UK);

        $this->assertNull($handler->getCountry());
    }

    public function testInDevTheDebugHeaderIsHonored(): void
    {
        $handler = $this->buildHandler('dev', self::DEBUG_IP_UK);

        $country = $handler->getCountry();
        $this->assertNotNull($country);
        $this->assertSame('GB', $country->getIsoCode());
    }

    public function testInDevWithoutHeaderOrEnvVarBehavesLikeBefore(): void
    {

        $handler = $this->buildHandler('dev', null);

        $this->assertNull($handler->getCountry());
    }

    public function testInDevTheEnvVarIsUsedAsFallbackWhenNoHeaderIsSent(): void
    {
        $handler = $this->buildHandler('dev', null, self::DEBUG_IP_UK);

        $country = $handler->getCountry();
        $this->assertNotNull($country);
        $this->assertSame('GB', $country->getIsoCode());
    }

    public function testHeaderTakesPrecedenceOverEnvVarInDev(): void
    {

        $handler = $this->buildHandler('dev', self::DEBUG_IP_UK, '190.0.0.1');

        $country = $handler->getCountry();
        $this->assertNotNull($country);
        $this->assertSame('GB', $country->getIsoCode());
    }
}
