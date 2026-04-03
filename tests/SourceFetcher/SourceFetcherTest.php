<?php

declare(strict_types=1);

namespace App\Tests\SourceFetcher;

use App\SourceFetcher\SourceFetcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class SourceFetcherTest extends TestCase
{
    public function testFetchReturnsValueFromValidXml(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <guid>2024-3-15</guid>
      <description>Latest CO2 level: 421.37 ppm</description>
    </item>
    <item>
      <guid>2024-3-16</guid>
      <description>Latest CO2 level: 422.50 ppm</description>
    </item>
  </channel>
</rss>
XML;

        $fetcher = $this->createFetcher($xml);
        $value = $fetcher->fetch();

        self::assertNotNull($value);
        self::assertSame(422.50, $value->getValue());
        self::assertSame('USHIMALO', $value->getStationCode());
        self::assertSame('co2', $value->getPollutant());
        self::assertSame('2024-03-16', $value->getDateTime()->format('Y-m-d'));
    }

    public function testFetchReturnsLatestValueSortedByDate(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <guid>2024-12-1</guid>
      <description>CO2: 425.10 ppm</description>
    </item>
    <item>
      <guid>2024-1-15</guid>
      <description>CO2: 420.00 ppm</description>
    </item>
    <item>
      <guid>2024-6-20</guid>
      <description>CO2: 423.50 ppm</description>
    </item>
  </channel>
</rss>
XML;

        $fetcher = $this->createFetcher($xml);
        $value = $fetcher->fetch();

        self::assertNotNull($value);
        self::assertSame(425.10, $value->getValue());
    }

    public function testFetchReturnsNullForEmptyFeed(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
  </channel>
</rss>
XML;

        $fetcher = $this->createFetcher($xml);
        self::assertNull($fetcher->fetch());
    }

    public function testFetchFiltersInvalidGuids(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <guid>some-text-guid</guid>
      <description>CO2: 421.37 ppm</description>
    </item>
    <item>
      <guid>2024-01</guid>
      <description>CO2: 422.00 ppm</description>
    </item>
    <item>
      <guid>2024-3-15</guid>
      <description>CO2: 423.00 ppm</description>
    </item>
  </channel>
</rss>
XML;

        $fetcher = $this->createFetcher($xml);
        $value = $fetcher->fetch();

        self::assertNotNull($value);
        self::assertSame(423.00, $value->getValue());
    }

    public function testFetchSkipsItemsWithNoMatchingCo2Value(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <guid>2024-3-15</guid>
      <description>No numeric value here</description>
    </item>
    <item>
      <guid>2024-3-16</guid>
      <description>CO2: 421.37 ppm</description>
    </item>
  </channel>
</rss>
XML;

        $fetcher = $this->createFetcher($xml);
        $value = $fetcher->fetch();

        self::assertNotNull($value);
        self::assertSame(421.37, $value->getValue());
    }

    public function testFetchHandlesCo2ValuesAbove999(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <guid>2050-1-1</guid>
      <description>CO2: 1050.25 ppm</description>
    </item>
  </channel>
</rss>
XML;

        $fetcher = $this->createFetcher($xml);
        $value = $fetcher->fetch();

        self::assertNotNull($value);
        self::assertSame(1050.25, $value->getValue());
    }

    public function testFetchReturnsNullWhenAllItemsHaveUnparseableCo2Values(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <guid>2024-3-15</guid>
      <description>No value here</description>
    </item>
    <item>
      <guid>2024-3-16</guid>
      <description>Also no value</description>
    </item>
  </channel>
</rss>
XML;

        $fetcher = $this->createFetcher($xml);
        self::assertNull($fetcher->fetch());
    }

    public function testFetchWithSingleItem(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <guid>2024-7-4</guid>
      <description>CO2: 419.80 ppm</description>
    </item>
  </channel>
</rss>
XML;

        $fetcher = $this->createFetcher($xml);
        $value = $fetcher->fetch();

        self::assertNotNull($value);
        self::assertSame(419.80, $value->getValue());
        self::assertSame('2024-07-04', $value->getDateTime()->format('Y-m-d'));
    }

    public function testFetchExtractsCo2WithSingleDecimalDigit(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <guid>2024-3-15</guid>
      <description>CO2: 421.3 ppm</description>
    </item>
  </channel>
</rss>
XML;

        $fetcher = $this->createFetcher($xml);
        $value = $fetcher->fetch();

        self::assertNotNull($value);
        self::assertSame(421.3, $value->getValue());
    }

    public function testFetchExtractsFirstCo2ValueFromMultipleNumbers(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <guid>2024-3-15</guid>
      <description>Previous: 419.50 ppm, Current: 421.37 ppm</description>
    </item>
  </channel>
</rss>
XML;

        $fetcher = $this->createFetcher($xml);
        $value = $fetcher->fetch();

        self::assertNotNull($value);
        self::assertSame(419.50, $value->getValue());
    }

    public function testFetchRequestsCorrectUrl(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <guid>2024-3-15</guid>
      <description>CO2: 421.37 ppm</description>
    </item>
  </channel>
</rss>
XML;

        $response = new MockResponse($xml);
        $httpClient = new MockHttpClient($response);

        $fetcher = new SourceFetcher($httpClient);
        $fetcher->fetch();

        self::assertSame('GET', $response->getRequestMethod());
        self::assertSame('https://gml.noaa.gov/webdata/ccgg/trends/rss.xml', $response->getRequestUrl());
    }

    public function testFetchThrowsOnHttpError(): void
    {
        $response = new MockResponse('', ['http_code' => 500]);
        $httpClient = new MockHttpClient($response);

        $fetcher = new SourceFetcher($httpClient);

        $this->expectException(ServerException::class);
        $fetcher->fetch();
    }

    #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
    public function testFetchThrowsOnInvalidXml(): void
    {
        $fetcher = $this->createFetcher('this is not xml');

        $this->expectException(\Exception::class);
        $fetcher->fetch();
    }

    public function testFetchIgnoresItemsWithEmptyGuid(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <guid></guid>
      <description>CO2: 421.37 ppm</description>
    </item>
  </channel>
</rss>
XML;

        $fetcher = $this->createFetcher($xml);
        self::assertNull($fetcher->fetch());
    }

    #[DataProvider('validGuidProvider')]
    public function testValidGuidsAreAccepted(string $guid): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <guid>{$guid}</guid>
      <description>CO2: 421.37 ppm</description>
    </item>
  </channel>
</rss>
XML;

        $fetcher = $this->createFetcher($xml);
        self::assertNotNull($fetcher->fetch());
    }

    /** @return iterable<string, array{string}> */
    public static function validGuidProvider(): iterable
    {
        yield 'standard date' => ['2024-01-15'];
        yield 'single digit month' => ['2024-1-15'];
        yield 'single digit day' => ['2024-12-1'];
        yield 'both single digit' => ['2024-3-5'];
    }

    #[DataProvider('invalidGuidProvider')]
    public function testInvalidGuidsAreRejected(string $guid): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <guid>{$guid}</guid>
      <description>CO2: 421.37 ppm</description>
    </item>
  </channel>
</rss>
XML;

        $fetcher = $this->createFetcher($xml);
        self::assertNull($fetcher->fetch());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidGuidProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'year only' => ['2024'];
        yield 'year-month only' => ['2024-01'];
        yield 'text' => ['foobar'];
        yield 'iso datetime' => ['2024-01-15T12:00:00'];
    }

    private function createFetcher(string $responseBody): SourceFetcher
    {
        $response = new MockResponse($responseBody);
        $httpClient = new MockHttpClient($response);

        return new SourceFetcher($httpClient);
    }
}
