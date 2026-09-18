<?php

declare(strict_types=1);

namespace Fixtures\StructArmed\Service;

use Fixtures\StructArmed\Core\Base;

final class Service
{
    public function base(): Base
    {
        return new Base();
    }
}
