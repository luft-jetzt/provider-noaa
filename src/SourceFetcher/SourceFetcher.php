<?php declare(strict_types=1);

namespace App\SourceFetcher;

use Caldera\LuftModel\Model\Value;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SourceFetcher implements SourceFetcherInterface
{
    private const string DATA_URI = 'https://www.esrl.noaa.gov/gmd/webdata/ccgg/trends/rss.xml';
    private const string STATION_CODE = 'USHIMALO';
    private const string POLLUTANT = 'co2';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {}

    public function fetch(): ?Value
    {
        $response = $this->httpClient->request('GET', self::DATA_URI);
        $xmlFile = $response->getContent();

        $simpleXml = new \SimpleXMLElement($xmlFile);

        $resultList = $this->parseXmlFile($simpleXml);

        if ($resultList === []) {
            return null;
        }

        $lastValueDateTimeString = array_key_last($resultList);
        $lastCo2Value = $resultList[$lastValueDateTimeString];

        return $this->createValue($lastCo2Value, new \DateTimeImmutable($lastValueDateTimeString));
    }

    private function createValue(float $co2Value, \DateTimeImmutable $dateTime): Value
    {
        $value = new Value();
        $value->setValue($co2Value)
            ->setStationCode(self::STATION_CODE)
            ->setPollutant(self::POLLUTANT)
            ->setDateTime($dateTime);

        return $value;
    }

    private function parseXmlFile(\SimpleXMLElement $xmlRoot): array
    {
        $resultList = [];

        foreach ($xmlRoot->channel->item as $item) {
            $guid = (string) $item->guid;

            if (!$guid || !$this->isYearMonthDayGuidString($guid)) {
                continue;
            }

            $co2Value = $this->fetchCo2ValueFromString((string) $item->description);

            if ($co2Value !== null) {
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
        if (preg_match('/\d{3,}\.\d{1,2}/', $description, $matches)) {
            return (float) $matches[0];
        }

        return null;
    }
}
