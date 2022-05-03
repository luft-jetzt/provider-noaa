<?php declare(strict_types=1);

namespace App\SourceFetcher;

use Caldera\LuftApiBundle\Model\Value;

interface SourceFetcherInterface
{
    public function fetch(): ?Value;
}
