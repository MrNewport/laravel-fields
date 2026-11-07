<?php

declare(strict_types=1);

namespace MrNewport\LaravelFields\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Validates one bounded list without expanding wildcard rules or comparing every pair. */
final class Choices implements ValidationRule
{
    public const MAX_ITEMS = 1000;

    /** @param array<string|int, string> $options */
    public function __construct(private readonly array $options) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) > self::MAX_ITEMS) {
            $fail('laravel-fields::messages.choices')->translate(['attribute' => $attribute, 'max' => self::MAX_ITEMS]);

            return;
        }

        $seen = [];
        foreach ($value as $choice) {
            if (! is_string($choice) || ! array_key_exists($choice, $this->options) || isset($seen[$choice])) {
                $fail('laravel-fields::messages.choices')->translate(['attribute' => $attribute, 'max' => self::MAX_ITEMS]);

                return;
            }
            $seen[$choice] = true;
        }
    }
}
