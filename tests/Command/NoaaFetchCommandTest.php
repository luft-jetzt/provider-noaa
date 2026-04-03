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

    public function testCommandDescription(): void
    {
        $sourceFetcher = $this->createStub(SourceFetcherInterface::class);
        $valueApi = $this->createStub(ValueApiInterface::class);

        $command = new NoaaFetchCommand($sourceFetcher, $valueApi);

        self::assertSame('Push noaa data to luft', $command->getDescription());
    }

    public function testSourceFetcherExceptionPropagates(): void
    {
        $sourceFetcher = $this->createStub(SourceFetcherInterface::class);
        $sourceFetcher->method('fetch')->willThrowException(new \RuntimeException('Connection failed'));

        $valueApi = $this->createStub(ValueApiInterface::class);

        $tester = $this->createCommandTester($sourceFetcher, $valueApi);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Connection failed');
        $tester->execute([]);
    }

    public function testSuccessOutputContainsPushedMessage(): void
    {
        $value = new Value();
        $value->setValue(425.00)
            ->setStationCode('USHIMALO')
            ->setPollutant('co2')
            ->setDateTime(new \DateTime('2024-06-01'));

        $sourceFetcher = $this->createStub(SourceFetcherInterface::class);
        $sourceFetcher->method('fetch')->willReturn($value);

        $valueApi = $this->createStub(ValueApiInterface::class);

        $tester = $this->createCommandTester($sourceFetcher, $valueApi);
        $tester->execute([]);

        self::assertStringContainsString('Pushed value', $tester->getDisplay());
        self::assertStringContainsString('luft api', $tester->getDisplay());
    }

    private function createCommandTester(SourceFetcherInterface $sourceFetcher, ValueApiInterface $valueApi): CommandTester
    {
        $command = new NoaaFetchCommand($sourceFetcher, $valueApi);

        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('luft:fetch'));
    }
}
