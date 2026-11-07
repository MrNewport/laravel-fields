<?php

declare(strict_types=1);

namespace MrNewport\LaravelFields\Adapters;

use Filament\Forms\Components\Select;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;

/** Choice membership is checked by the shared bounded rule, never an unbounded wildcard. */
final class BoundedSelect extends Select
{
    public function getInValidationRule(): In|Enum|null
    {
        return null;
    }
}
