<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Filament\Forms\Components\TextInput;
use MrNewport\LaravelFields\Field;
use MrNewport\LaravelFields\Schema;

$schema = Schema::make([Field::text('name')->required(), Field::decimal('amount', 4)]);
if (count($schema) !== 2 || $schema->fields()[1]->normalize('12.3') !== '12.3000') {
    throw new RuntimeException('Core schema contract failed.');
}
if (class_exists(TextInput::class)) {
    throw new RuntimeException('Optional Filament dependency unexpectedly present.');
}
try {
    $schema->toFilament();
    throw new RuntimeException('Missing optional integration was not detected.');
} catch (LogicException $exception) {
    if (! str_contains($exception->getMessage(), 'Install filament/forms')) {
        throw $exception;
    }
}
echo "Core-only dependency check passed.\n";
