<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:ip:database',
    description: 'renew ip database',
)]
class IpDatabaseCommand extends Command
{
    protected ParameterBagInterface $params;

    public function __construct( ParameterBagInterface $params, string $name = null,)
    {
        parent::__construct($name);
        $this->params = $params;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Start renew ip database');
        $params = $this->params;
        $pathGeoLocalization = $params->get('kernel.project_dir').$params->get('ip2_archive_database');
        
        $url = $params->get('ip2_url_download_file');

        
        if(!file_put_contents($params->get('kernel.project_dir').$params->get('ip2_location_file_download'),file_get_contents($url))) {
            $io->error($params->get('ip2_download_fail'));

            return Command::FAILURE;
        }

        if (file_exists($pathGeoLocalization.$params->get('ip2_file_geolite_city'))) {
            unlink($pathGeoLocalization.$params->get('ip2_file_geolite_city'));
        }

        $pharData = new \PharData($params->get('kernel.project_dir').$params->get('ip2_location_file_download'));
        $pharData->extractTo($pathGeoLocalization);
        foreach ($pharData as $file) {
            
            $from=$pathGeoLocalization.$file->getFileName()."/".$params->get('ip2_file_geolite_city');
            $to=$pathGeoLocalization.$params->get('ip2_file_geolite_city');
            rename ($from,$to);
            
            $this->deleteFolder($pathGeoLocalization,$file->getFileName());
        }
        
        if (file_exists($params->get('kernel.project_dir').$params->get('ip2_location_file_download'))) {
            unlink($params->get('kernel.project_dir').$params->get('ip2_location_file_download'));
        }
        $io->success($params->get('ip2_process_sucess'));

        return Command::SUCCESS;
    }


    public function deleteFolder($pathGeoLocalization,$fileName):void{
        $dir = opendir($pathGeoLocalization.$fileName);
        
        while ($element = readdir($dir)){
            
            if( $element != "." && $element != ".."){
                
                if( !is_dir($pathGeoLocalization.$element) ){
                    unlink($pathGeoLocalization.$fileName."/".$element);
                }
            }
        }
        rmdir($pathGeoLocalization.$fileName);
    }

}
