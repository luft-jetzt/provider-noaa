<?php declare(strict_types=1);

namespace App\SourceFetcher;

use Caldera\LuftModel\Model\Value;

interface SourceFetcherInterface
{
    public function fetch(): ?Value;
}
