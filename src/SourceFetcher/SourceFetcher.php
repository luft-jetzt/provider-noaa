<?php declare(strict_types=1);

namespace App\SourceFetcher;

use Caldera\LuftModel\Model\Value;
use GuzzleHttp\Client;

class SourceFetcher implements SourceFetcherInterface
{
    const DATA_URI = 'https://www.esrl.noaa.gov/gmd/webdata/ccgg/trends/rss.xml';

    public function fetch(): ?Value
    {
        $xmlFile = $this->loadXmlFileContent();

        $simpleXml = new \SimpleXMLElement($xmlFile);

        $resultList = $this->parseXmlFile($simpleXml);

        $lastValueDateTimeString = array_key_last($resultList);
        $lastCo2Value = (float) $resultList[$lastValueDateTimeString];

        $value = $this->createValue($lastCo2Value, new \DateTime($lastValueDateTimeString));

        return $value;
    }

    protected function createValue(float $lastCo2Value, \DateTime $dateTime): Value
    {
        $value = new Value();
        $value->setValue($lastCo2Value)
            ->setStationCode('USHIMALO')
            ->setPollutant('co2')
            ->setDateTime($dateTime);

        return $value;
    }

    protected function parseXmlFile(\SimpleXMLElement $xmlRoot): array
    {
        $resultList = [];

        foreach ($xmlRoot->channel->item as $item) {
            $guid = (string) $item->guid;

            if (!$guid || !$this->isYearMonthDayGuidString($guid)) {
                continue;
            }

            $co2Value = $this->fetchCo2ValueFromString((string) $item->description);

            $resultList[$guid] = $co2Value;
        }

        uksort($resultList, 'strnatcmp');

        return $resultList;
    }

    protected function isYearMonthDayGuidString(string $guid): bool
    {
        return 1 === preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $guid);
    }

    protected function fetchCo2ValueFromString(string $description): float
    {
        preg_match('/\d{3,3}\.\d{1,2}/', $description, $matches);

        return (float) array_pop($matches);
    }

    protected function loadXmlFileContent(): string
    {
        $client = new Client();
        $response = $client->get(self::DATA_URI);

        return $response->getBody()->getContents();
    }
}
