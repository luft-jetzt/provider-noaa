<?php declare(strict_types=1);

namespace App\Command;

use App\ApiPusher\ApiPusherInterface;
use App\SourceFetcher\SourceFetcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class NoaaFetchCommand extends Command
{
    protected static $defaultName = 'luft:fetch';

    protected SourceFetcherInterface $sourceFetcher;
    protected ApiPusherInterface $apiPusher;

    public function __construct(string $name = null, SourceFetcherInterface $sourceFetcher, ApiPusherInterface $apiPusher)
    {
        $this->sourceFetcher = $sourceFetcher;
        $this->apiPusher = $apiPusher;

        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Add a short description for your command')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $value = $this->sourceFetcher->fetch();

        $this->apiPusher->pushValue($value);

        return Command::SUCCESS;
    }
}
