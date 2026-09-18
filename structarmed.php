<?php
declare(strict_types=1);

use Boundwize\StructArmed\Architecture;

return Architecture::define()
    ->layerPattern('Foundation', [
        '/^Crustum\\\\Essentia\\\\(Contracts|Exceptions|UserFilters)(\\\\.*)?$/',
        '/^Crustum\\\\Essentia\\\\(Autoload|OutputCleaner)$/',
    ])
    ->layerPattern('Console', '/^Crustum\\\\Essentia\\\\Console(\\\\.*)?$/')
    ->layerPattern('Drivers', '/^Crustum\\\\Essentia\\\\Drivers(\\\\.*)?$/')
    // Execution picks Starters; Starters use Execution singleton — cycle.
    ->layerPattern('Execution', '/^Crustum\\\\Essentia\\\\Execution$/')
    ->layerPattern('Plugin', '/^Crustum\\\\Essentia\\\\EssentiaPlugin$/')
    ->ruleset([
        'Foundation' => [],
        'Console' => ['Foundation'],
        'Drivers' => ['Console', 'Foundation', 'Execution'],
        'Execution' => ['Drivers', 'Foundation'],
        'Plugin' => ['+Drivers', 'Execution'],
    ]);
