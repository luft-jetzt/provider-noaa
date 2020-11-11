<?php declare(strict_types=1);

namespace App\ApiPusher;

use App\Model\Value;

interface ApiPusherInterface
{
    public function pushValue(Value $value): void;
}
