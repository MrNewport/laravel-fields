<?php

declare(strict_types=1);

namespace MrNewport\LaravelFields\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use MrNewport\LaravelFields\Field;

/** Resolve the complete form's state before Laravel interprets dependent rule parameters. */
final class FilamentField implements DataAwareRule, ValidationRule
{
    /** Dependent presence rules must run even when the component's value is missing. */
    public bool $implicit = true;

    private array $data = [];

    public function __construct(private readonly Field $field) {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $prefix = substr($attribute, 0, -strlen($this->field->name));
        $data = $prefix === '' ? $this->data : Arr::get($this->data, rtrim($prefix, '.'), []);
        $validator = app(Factory::class)->make(
            is_array($data) ? $data : [],
            [$this->field->name => $this->field->validationRules()],
            [],
            [$this->field->name => $this->field->getLabel()],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $fail($message);
            }
        }
    }
}
