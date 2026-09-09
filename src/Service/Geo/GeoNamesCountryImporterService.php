<?php

namespace App\Service\Geo;

use App\ArgumentHandler\CitiesArgument;
use App\ArgumentHandler\CountriesArgument;
use App\ArgumentHandler\RegionsArgument;
use App\Entity\Configurations\Globals\Cities;
use App\Entity\Configurations\Globals\Countries;
use App\Entity\Configurations\Globals\Regions;
use App\Exception\GenericException;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GeoNamesCountryImporterService implements GeoNamesCountryImporterInterface
{
    private const BASE_URL = 'https://download.geonames.org/export/dump/';

    private const ALWAYS_INCLUDE_FEATURE_CODES = ['PPLC', 'PPLA', 'PPLA2'];

    public function __construct(
        private HttpClientInterface $httpClient,
        private CountriesRepository $countriesRepository,
        private RegionsRepository $regionsRepository,
        private CitiesRepository $citiesRepository,
        private CustomeEntityManagerInterface $em,
    ) {
    }

    public function importCountry(string $isoCode, int $minPopulation = 5000): GeoNamesImportResult
    {
        $isoCode = strtoupper(trim($isoCode));
        if ($isoCode === '' || !preg_match('/^[A-Z]{2}$/', $isoCode)) {
            throw new GenericException(sprintf('Código de país inválido: "%s" (se espera ISO 3166-1 alfa-2, ej. "CO").', $isoCode), 400);
        }

        $country        = $this->countriesRepository->findOneBy(['isoCode' => $isoCode]);
        $countryCreated = false;
        if ($country === null) {
            $country        = $this->createCountry($isoCode);
            $countryCreated = true;
        }

        [$regionsById, $regionsCreated, $regionsSkipped] = $this->importRegions($isoCode, $country);

        [$citiesCreated, $citiesSkipped] = $this->importCities($isoCode, $minPopulation, $regionsById);

        $this->em->flush();

        if ($countryCreated) {
            $this->countriesRepository->invalidateAllActiveCache();
        }

        return new GeoNamesImportResult($country, $countryCreated, $regionsCreated, $regionsSkipped, $citiesCreated, $citiesSkipped);
    }

    private function createCountry(string $isoCode): Countries
    {
        $name = null;
        foreach ($this->fetchLines('countryInfo.txt') as $line) {
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $cols = explode("\t", $line);
            if (($cols[0] ?? '') === $isoCode) {
                $name = $cols[4] ?? null;
                break;
            }
        }

        if ($name === null) {
            throw new GenericException(sprintf('País "%s" no encontrado en el catálogo de GeoNames.', $isoCode), 404);
        }

        $country = (new Countries())->add(new CountriesArgument([
            'name'        => json_encode(['es' => $name], JSON_UNESCAPED_UNICODE),
            'description' => 'País importado desde GeoNames',
            'isoCode'     => $isoCode,
        ]));
        $this->em->add($country, false);

        return $country;
    }

    private function importRegions(string $isoCode, Countries $country): array
    {
        $regionsById = [];
        $created     = 0;
        $skipped     = 0;

        foreach ($this->fetchLines('admin1CodesASCII.txt') as $line) {
            if ($line === '') {
                continue;
            }
            $cols = explode("\t", $line);
            $code = $cols[0] ?? '';
            $name = $cols[1] ?? null;

            if (!str_starts_with($code, $isoCode . '.') || $name === null) {
                continue;
            }
            $admin1Code = substr($code, strlen($isoCode) + 1);

            $existing = $this->regionsRepository->findOneBy(['isoCode' => $admin1Code, 'country' => $country]);
            if ($existing !== null) {
                $regionsById[$admin1Code] = $existing;
                $skipped++;
                continue;
            }

            $region = (new Regions())->add(new RegionsArgument([
                'name'        => json_encode(['es' => $name], JSON_UNESCAPED_UNICODE),
                'description' => 'Región',
                'isoCode'     => $admin1Code,
            ], $country));
            $this->em->add($region, false);

            $regionsById[$admin1Code] = $region;
            $created++;
        }

        return [$regionsById, $created, $skipped];
    }

    private function importCities(string $isoCode, int $minPopulation, array $regionsById): array
    {
        $created = 0;
        $skipped = 0;

        foreach ($this->fetchCountryDumpRows($isoCode) as [$admin1Code, $name, $geonameId, $featureCode, $population]) {
            $isAlwaysIncluded = in_array($featureCode, self::ALWAYS_INCLUDE_FEATURE_CODES, true);
            if (!$isAlwaysIncluded && $population < $minPopulation) {
                continue;
            }

            $region = $regionsById[$admin1Code] ?? null;
            if ($region === null) {

                continue;
            }

            $existing = $this->citiesRepository->findOneBy(['name' => $geonameId]);
            if ($existing !== null) {
                $skipped++;
                continue;
            }

            $city = (new Cities())->add(new CitiesArgument([
                'name'        => $geonameId,
                'description' => 'Ciudad',
                'names'       => json_encode(['es' => $name], JSON_UNESCAPED_UNICODE),
            ], $region));
            $this->em->add($city, false);
            $created++;
        }

        return [$created, $skipped];
    }

    private function fetchCountryDumpRows(string $isoCode): iterable
    {
        $response = $this->httpClient->request('GET', self::BASE_URL . $isoCode . '.zip');
        $zipBytes = $response->getContent();

        $tmpFile = tempnam(sys_get_temp_dir(), 'geonames_');
        try {
            file_put_contents($tmpFile, $zipBytes);

            $zip = new \ZipArchive();
            if ($zip->open($tmpFile) !== true) {
                throw new GenericException(sprintf('No se pudo leer el archivo de GeoNames para "%s".', $isoCode), 502);
            }

            $content = $zip->getFromName($isoCode . '.txt');
            $zip->close();

            if ($content === false) {
                throw new GenericException(sprintf('El dump de GeoNames para "%s" no trae el archivo esperado.', $isoCode), 502);
            }
        } finally {
            unlink($tmpFile);
        }

        foreach (explode("\n", $content) as $line) {
            if ($line === '') {
                continue;
            }
            $cols = explode("\t", $line);

            if (($cols[6] ?? '') !== 'P') {
                continue;
            }

            yield [
                $cols[10] ?? '',
                $cols[1] ?? '',
                $cols[0] ?? '',
                $cols[7] ?? '',
                (int) ($cols[14] ?? 0),
            ];
        }
    }

    private function fetchLines(string $fileName): array
    {
        $response = $this->httpClient->request('GET', self::BASE_URL . $fileName);
        return explode("\n", $response->getContent());
    }
}
