<?php declare(strict_types=1);

namespace App\Command;

use App\SourceFetcher\SourceFetcherInterface;
use Caldera\LuftApiBundle\Api\ValueApiInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'luft:fetch', description: 'Push noaa data to luft')]
class NoaaFetchCommand extends Command
{
    public function __construct(protected SourceFetcherInterface $sourceFetcher, protected ValueApiInterface $valueApi)
    {
        parent::__construct();
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
