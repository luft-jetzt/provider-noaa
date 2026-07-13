<?php

declare(strict_types=1);

namespace App\SourceFetcher;

use Caldera\LuftModel\Model\Value;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SourceFetcher implements SourceFetcherInterface
{
    private const string DATA_URI = 'https://gml.noaa.gov/webdata/ccgg/trends/rss.xml';
    private const string STATION_CODE = 'USHIMALO';
    private const string POLLUTANT = 'co2';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function fetch(): ?Value
    {
        $response = $this->httpClient->request('GET', self::DATA_URI);
        $xmlFile = $response->getContent();

        $simpleXml = new \SimpleXMLElement($xmlFile);

        $resultList = $this->parseXmlFile($simpleXml);

        if ([] === $resultList) {
            return null;
        }

        $lastValueDateTimeString = array_key_last($resultList);
        $lastCo2Value = $resultList[$lastValueDateTimeString];

        // The feed reports plain dates without a timezone; anchor them to UTC
        // explicitly so the pushed timestamp does not depend on the server's
        // default timezone.
        $dateTime = new \DateTime($lastValueDateTimeString, new \DateTimeZone('UTC'));

        return $this->createValue($lastCo2Value, $dateTime);
    }

    private function createValue(float $co2Value, \DateTime $dateTime): Value
    {
        $value = new Value();
        $value->setValue($co2Value)
            ->setStationCode(self::STATION_CODE)
            ->setPollutant(self::POLLUTANT)
            ->setDateTime($dateTime);

        return $value;
    }

    /**
     * Builds a map of `Y-m-d` GUID => CO2 value from the RSS items.
     *
     * Only weekly items with a full year-month-day GUID (e.g. `2026-6-21`) are
     * considered. Monthly aggregate items, whose GUID has only two parts
     * (e.g. `2026-5`), are intentionally skipped so that the pushed value is
     * always the most recent weekly reading.
     *
     * @return array<string, float>
     */
    private function parseXmlFile(\SimpleXMLElement $xmlRoot): array
    {
        $resultList = [];

        foreach ($xmlRoot->channel->item as $item) {
            $guid = (string) $item->guid;

            if (!$guid || !$this->isYearMonthDayGuidString($guid)) {
                continue;
            }

            $co2Value = $this->fetchCo2ValueFromString((string) $item->description);

            if (null !== $co2Value) {
                $resultList[$guid] = $co2Value;
            }
        }

        uksort($resultList, 'strnatcmp');

        return $resultList;
    }

    private function isYearMonthDayGuidString(string $guid): bool
    {
        return 1 === preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $guid);
    }

    private function fetchCo2ValueFromString(string $description): ?float
    {
        // Anchor on the "ppm" unit and a word boundary so that unrelated numbers
        // in the description are ignored and a value is never sliced out of a
        // longer number (e.g. `430.85` out of `1430.85`). The first match is the
        // current weekly reading in the live feed.
        if (preg_match('/\b(\d{3,4}\.\d{1,2})\s*ppm/i', $description, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }
}
