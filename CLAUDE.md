# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A Symfony console application that fetches CO2 concentration data from NOAA's Mauna Loa Observatory RSS feed. Parses the RSS XML to extract the latest CO2 measurement and pushes it to the Luft.jetzt API.

- **PHP**: ^8.5 (requires `ext-simplexml`)
- **Framework**: Symfony 8.0
- **Data Source**: NOAA RSS feed (`gml.noaa.gov`)
- **Pollutant**: CO2
- **Station**: Mauna Loa, Hawaii (station code: `USHIMALO`)

## Common Commands

```bash
composer install                         # Install dependencies
php bin/console luft:fetch               # Fetch CO2 data and push to API

vendor/bin/phpunit                       # Run tests
vendor/bin/phpstan analyse --no-progress # Static analysis
vendor/bin/php-cs-fixer fix             # Code style fixing
```

## Architecture

Very simple single-purpose provider:

- **`SourceFetcher/SourceFetcher`** — Fetches RSS from `https://gml.noaa.gov/webdata/ccgg/trends/rss.xml`
  - Parses XML with SimpleXML
  - Extracts CO2 value from RSS item descriptions via regex (`\d{3,}\.\d{1,2}`)
  - Creates a single `Value` object per fetch (station: USHIMALO, pollutant: co2)
  - Pushes to Luft.jetzt API via `luft-api-bundle`

- **`Command/Luft/FetchCommand.php`** — Console command `luft:fetch`

## Dependencies

- `ext-simplexml` — XML parsing
- `symfony/http-client` ^8.0, `symfony/console` ^8.0
- `luft-jetzt/luft-api-bundle` ^1.1 — Pushes data to Luft.jetzt API
