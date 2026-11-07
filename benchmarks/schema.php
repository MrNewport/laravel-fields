<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use MrNewport\LaravelFields\Field;
use MrNewport\LaravelFields\Schema;

foreach ([100, 1000, 5000] as $size) {
    $fields = [];
    for ($i = 0; $i < $size; $i++) {
        $fields[] = Field::text('section_'.$i.'.name');
    }
    $samples = [];
    for ($run = 0; $run < 5; $run++) {
        $start = hrtime(true);
        $schema = Schema::make($fields);
        $samples[] = (hrtime(true) - $start) / 1_000_000;
        if (count($schema) !== $size) {
            throw new RuntimeException('Benchmark schema size changed.');
        }
    }
    sort($samples);
    echo json_encode(['fields' => $size, 'median_ms' => round($samples[2], 3)], JSON_THROW_ON_ERROR).PHP_EOL;
}
