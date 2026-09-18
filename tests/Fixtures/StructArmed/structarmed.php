<?php

declare(strict_types=1);

use Boundwize\StructArmed\Architecture;

return Architecture::define()
    ->layer('Core', 'tests/Fixtures/StructArmed/src/Core/')
    ->layer('Service', 'tests/Fixtures/StructArmed/src/Service/')
    ->ruleset([
        'Core' => [],
        'Service' => ['Core'],
    ]);
