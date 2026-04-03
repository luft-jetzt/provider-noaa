<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\NoaaFetchCommand;
use App\SourceFetcher\SourceFetcherInterface;
use Caldera\LuftApiBundle\Api\ValueApiInterface;
use Caldera\LuftModel\Model\Value;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class NoaaFetchCommandTest extends TestCase
{
    public function testSuccessfulFetch(): void
    {
        $value = new Value();
        $value->setValue(421.37)
            ->setStationCode('USHIMALO')
            ->setPollutant('co2')
            ->setDateTime(new \DateTime('2024-03-15'));

        $sourceFetcher = $this->createStub(SourceFetcherInterface::class);
        $sourceFetcher->method('fetch')->willReturn($value);

        $valueApi = $this->createMock(ValueApiInterface::class);
        $valueApi->expects(self::once())->method('putValue')->with($value);

        $tester = $this->createCommandTester($sourceFetcher, $valueApi);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('421.37', $tester->getDisplay());
    }

    public function testFetchReturnsNullShowsError(): void
    {
        $sourceFetcher = $this->createStub(SourceFetcherInterface::class);
        $sourceFetcher->method('fetch')->willReturn(null);

        $valueApi = $this->createMock(ValueApiInterface::class);
        $valueApi->expects(self::never())->method('putValue');

        $tester = $this->createCommandTester($sourceFetcher, $valueApi);
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Could not fetch CO2 data', $tester->getDisplay());
    }

    public function testCommandName(): void
    {
        $sourceFetcher = $this->createStub(SourceFetcherInterface::class);
        $valueApi = $this->createStub(ValueApiInterface::class);

        $command = new NoaaFetchCommand($sourceFetcher, $valueApi);

        self::assertSame('luft:fetch', $command->getName());
    }

    private function createCommandTester(SourceFetcherInterface $sourceFetcher, ValueApiInterface $valueApi): CommandTester
    {
        $command = new NoaaFetchCommand($sourceFetcher, $valueApi);

        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('luft:fetch'));
    }
}
