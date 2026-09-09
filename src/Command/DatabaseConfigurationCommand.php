<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'db:cf',
    description: 'Configuration of database',
)]
class DatabaseConfigurationCommand extends Command
{
    protected $projectDir;

    public function __construct(KernelInterface $kernel)
    {
        parent::__construct();
        $this->projectDir = $kernel->getProjectDir();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument('execute', InputArgument::OPTIONAL, 'Use the word start only in first execution why this option clean the database')
            
        ;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $execute = trim(strtolower((string) $input->getArgument('execute',null)));
        try {
            switch ($execute) {
                case 'start':
                    
                    shell_exec("php ".str_replace('\\', '/',$this->projectDir)."/bin/console doctrine:database:create --no-interaction");
                    shell_exec("php ".str_replace('\\', '/',$this->projectDir)."/bin/console doctrine:database:create --env=test --no-interaction");
                    $io->success('Database created successfully');

                    $migrationDir = "$this->projectDir/migrations";
                    $migrations = scandir($migrationDir);
                    if ($migrations !== false && count($migrations) > 2) {
                        $io->success('Migration exist');
                    } else {
                        shell_exec("php ".str_replace('\\', '/',$this->projectDir)."/bin/console make:migration --no-interaction");
                        $io->success('Migration created successfully');
                    }
                    break;
                case 'migration':
                    
                    shell_exec("php ".str_replace('\\', '/',$this->projectDir)."/bin/console make:migration --no-interaction");
                    $io->success('Migration created successfully');
                    break;            
                default:
                    break;
            }

            
            shell_exec("php ".str_replace('\\', '/',$this->projectDir)."/bin/console doctrine:migrations:migrate --no-interaction");
            shell_exec("php ".str_replace('\\', '/',$this->projectDir)."/bin/console doctrine:migrations:migrate --env=test --no-interaction");
            $io->success('Complete migration');

            if ($execute == 'start' || $execute == "fix") {
                
                shell_exec("php ".str_replace('\\', '/',$this->projectDir)."/bin/console doctrine:fixtures:load --no-interaction");
                shell_exec("php ".str_replace('\\', '/',$this->projectDir)."/bin/console doctrine:fixtures:load --env=test --no-interaction");
                $io->success('Data inserted successfully');
            }

            $io->success('Successful operation');
            return Command::SUCCESS;
        } catch (\Throwable $th) {
            $io->error($th->getMessage());
            return Command::FAILURE;
        }
    }
}
