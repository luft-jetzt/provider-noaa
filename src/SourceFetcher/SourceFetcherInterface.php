<?php declare(strict_types=1);

namespace App\SourceFetcher;

use App\Model\Value;

interface SourceFetcherInterface
{
    public function fetch(): ?Value;
}
