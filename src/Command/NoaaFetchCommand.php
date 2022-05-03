<?php declare(strict_types=1);

namespace App\Command;

use App\SourceFetcher\SourceFetcherInterface;
use Caldera\LuftApiBundle\Api\ValueApiInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class NoaaFetchCommand extends Command
{
    protected static $defaultName = 'luft:fetch';

    protected SourceFetcherInterface $sourceFetcher;
    protected ValueApiInterface $valueApi;

    public function __construct(SourceFetcherInterface $sourceFetcher, ValueApiInterface $valueApi)
    {
        $this->sourceFetcher = $sourceFetcher;
        $this->valueApi = $valueApi;

        parent::__construct();
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

        $this->valueApi->putValue($value);

        $io->success(sprintf('Pushed value %f to luft api', $value->getValue()));

        return Command::SUCCESS;
    }
}
