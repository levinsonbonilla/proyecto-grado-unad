<?php

namespace App\Command;

use App\Service\Geo\GeoDuplicateMergerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:geo:merge-duplicates',
    description: 'Fusiona regiones y ciudades duplicadas (mismo nombre normalizado) conservando las referencias de pedidos, usuarios y asignaciones',
)]
class MergeGeoDuplicatesCommand extends Command
{
    public function __construct(private readonly GeoDuplicateMergerService $merger)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Muestra qué se fusionaría sin modificar la base de datos');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $report = $this->merger->merge($dryRun);

        $io->section('Regiones');
        $report['regions'] === [] ? $io->text('Sin duplicados.') : $io->listing($report['regions']);
        $io->section('Nombres de región corregidos');
        $report['renamed'] === [] ? $io->text('Sin cambios.') : $io->listing($report['renamed']);
        $io->section('Ciudades');
        $report['cities'] === [] ? $io->text('Sin duplicados.') : $io->listing($report['cities']);

        $io->success($dryRun ? 'Simulación terminada; no se modificó nada.' : 'Duplicados fusionados.');

        return Command::SUCCESS;
    }
}
