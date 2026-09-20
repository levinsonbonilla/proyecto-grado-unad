<?php

namespace App\Handler\Configuration;

use App\ArgumentHandler\CitiesArgument;
use App\ArgumentHandler\CountriesArgument;
use App\ArgumentHandler\RegionsArgument;
use App\Entity\Configurations\Globals\Cities;
use App\Entity\Configurations\Globals\Countries;
use App\Entity\Configurations\Globals\Regions;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\LocationInterface;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use GeoIp2\Database\Reader;
use GeoIp2\Model\City;
use GeoIp2\Record\City as RecordCity;
use GeoIp2\Record\Country;
use GeoIp2\Record\Subdivision;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Yaml\Yaml;

final class LocationHandler implements LocationInterface
{
    private readonly Reader $reader;
    private ?Countries $country;
    private ?Regions $region;
    private ?Cities $city;
    private string $routeDirGeoIp;
    private string $isoCode;

    public function __construct(
        private readonly RequestStack $request,
        private readonly ParameterBagInterface $parameters,
        private readonly CountriesRepository $countriesRepository,
        private readonly RegionsRepository $regionsRepository,
        private readonly CitiesRepository $citiesRepository,
        private readonly CustomeEntityManagerInterface $customeEntityManager
    ) {
        $this->routeDirGeoIp = rtrim(
            $parameters->get('kernel.project_dir'),
            DIRECTORY_SEPARATOR
        );

        $this->routeDirGeoIp .= DIRECTORY_SEPARATOR .
            trim(
                $parameters->get('ip2_archive_database'),
                DIRECTORY_SEPARATOR
            );

        $databasePath = $this->routeDirGeoIp .
        DIRECTORY_SEPARATOR .
        trim(
            $parameters->get('ip2_file_geolite_city'),
            DIRECTORY_SEPARATOR
        );

        $this->reader = new Reader($databasePath);
        $this->process();
    }

    private function getCityGeoIp2(): ?City
    {
        $request = $this->request->getCurrentRequest();
        $ipAddress = $this->resolveIpForLookup($request);

        try {
            $city = $this->reader->city($ipAddress);
            $this->isoCode = $city->country->isoCode ?? "CO";
            return $city;
        } catch (\Exception $e) {
            $this->isoCode = "CO";
            return null;
        }
    }

    private function resolveIpForLookup(Request $request): string
    {
        $realIp = $request->getClientIp();

        if ($this->parameters->get('environment') === 'prod') {
            return $realIp;
        }

        $debugIp = $request->headers->get('X-Debug-Ip');
        if (!empty($debugIp)) {
            return $debugIp;
        }

        $envIp = $this->parameters->get('dev_simulated_ip');
        if (!empty($envIp)) {
            return $envIp;
        }

        return $realIp;
    }

    private function process(): void
    {
        $location = $this->getCityGeoIp2();
        $this->country = $this->country($location->country ?? null);
        $this->region = $this->region($location->subdivisions[0] ?? null);
        $this->city = $this->city($location->city ?? null);
    }

    private function country(?Country $country = null): ?Countries
    {
        if (empty($country) || empty($country->name ?? null)) {
            return null;
        }

        $return = $this->countriesRepository->findOneBy([
            "isoCode" => $country->isoCode,
            "active" => true
        ]);

        if (empty($return)) {
            $data = [
                "name" =>  json_encode($country->names),
                "description" => json_encode($country),
                "isoCode" => $country->isoCode
            ];

            $argument = new CountriesArgument($data);
            $countries = new Countries();
            $countries->add($argument);
            $this->customeEntityManager->add($countries, true);
            $return = $countries;
        }

        return $return;
    }

    private function region(?Subdivision $subdivisions = null): ?Regions
    {
        if (empty($subdivisions) || empty($subdivisions->isoCode ?? null)) {
            return null;
        }

        $return = $this->regionsRepository->findOneBy([
            "isoCode" => $subdivisions->isoCode,
            "active" => true
        ]);

        if (empty($return)) {
            $data = [
                "name" =>  json_encode($subdivisions->names),
                "description" => json_encode($subdivisions),
                "isoCode" => $subdivisions->isoCode
            ];

            $argument = new RegionsArgument($data, $this->country);
            $regions = new Regions();
            $regions->add($argument);
            $this->customeEntityManager->add($regions, true);
            $return = $regions;
        }

        return $return;
    }

    private function city(?RecordCity $city): ?Cities
    {
        if (empty($city) || empty($city->name ?? null)) {
            return null;
        }

        $return = $this->citiesRepository->findOneBy([
            "name" => $city->geonameId,
            "active" => true
        ]);

        if (empty($return)) {
            $data = [
                "names" =>  json_encode($city->names),
                "description" => json_encode($city),
                "name" => $city->geonameId
            ];

            $argument = new CitiesArgument($data, $this->region);
            $cities = new Cities();
            $cities->add($argument);
            $this->customeEntityManager->add($cities, true);
            $return = $cities;
        }

        return $return;
    }

    public function getCountry(): ?Countries
    {
        return $this->country;
    }

    public function getRegion(): ?Regions
    {
        return $this->region;
    }

    public function getCity(): ?Cities
    {
        return $this->city;
    }

    public function getLanguage(): string {
        $originalLang = Yaml::parseFile(($this->parameters->get('environment') == "prod" ? "/" : "") . $this->routeDirGeoIp . "/mapping.yml")[$this->isoCode] ?? "en";
        if (in_array($originalLang, explode("|", $this->parameters->get('supported_locales')) )) {
            $lang = $originalLang;
        } else {
            $lang = "en";
        }

        return $lang;
    }

}
