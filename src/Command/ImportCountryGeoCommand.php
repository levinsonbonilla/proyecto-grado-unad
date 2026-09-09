<?php

namespace App\Command;

use App\Service\Geo\GeoNamesCountryImporterInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:geo:import-country',
    description: 'Importa un país con sus departamentos/regiones y ciudades principales desde GeoNames',
)]
class ImportCountryGeoCommand extends Command
{
    public function __construct(private readonly GeoNamesCountryImporterInterface $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('isoCode', InputArgument::REQUIRED, 'Código ISO 3166-1 alfa-2 del país (ej. CO, MX, AR)')
            ->addOption('min-population', null, InputOption::VALUE_REQUIRED, 'Población mínima para incluir una ciudad (además de las capitales, que siempre se incluyen). 0 = todas las localidades pobladas.', 5000);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $isoCode = (string) $input->getArgument('isoCode');
        $minPop  = (int) $input->getOption('min-population');

        $io->title(sprintf('Importando geografía de "%s" desde GeoNames', strtoupper($isoCode)));

        try {
            $result = $this->importer->importCountry($isoCode, $minPop);
        } catch (\Throwable $th) {
            $io->error($th->getMessage());
            return Command::FAILURE;
        }

        $io->writeln(sprintf(
            'País: %s (%s)',
            $result->country->getName('es'),
            $result->countryCreated ? 'creado' : 'ya existía, reusado'
        ));
        $io->writeln(sprintf('Regiones/departamentos: %d creadas, %d ya existían', $result->regionsCreated, $result->regionsSkipped));
        $io->writeln(sprintf('Ciudades: %d creadas, %d ya existían', $result->citiesCreated, $result->citiesSkipped));

        $io->success('Import completo.');

        return Command::SUCCESS;
    }
}
