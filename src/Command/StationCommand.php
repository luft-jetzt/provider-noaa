<?php declare(strict_types=1);

namespace App\Command;

use Caldera\LuftApiBundle\Api\StationApiInterface;
use Caldera\LuftModel\Model\Station;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'station:load', description: 'Push noaa station to luft')]
class StationCommand extends Command
{
    public function __construct(protected StationApiInterface $stationApi)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $station = new Station();
        $station
            ->setProvider('noaa')
            ->setStationCode('USHIMALO')
            ->setAltitude(3397)
            ->setLatitude(19.536270)
            ->setLongitude(-155.576198)
            ->setTitle('Mauna Loa Observatory')
            ->setFromDate(new \DateTime('1950-01-01 00:00:00'))
        ;

        $this->stationApi->putStations([$station]);

        return Command::SUCCESS;
    }
}
