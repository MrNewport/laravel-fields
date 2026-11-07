<?php

declare(strict_types=1);

namespace MrNewport\LaravelFields;

use Countable;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\MessageBag;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/** @implements IteratorAggregate<int, Field> */
final class Schema implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var list<Field> */
    private array $fields;

    /** @var list<Field> */
    private array $editableFields = [];

    private array $labels = [];

    private ?array $validationRules = null;

    private function __construct(iterable $fields)
    {
        $this->fields = [];
        $paths = [];
        foreach ($fields as $field) {
            if (! $field instanceof Field) {
                throw new InvalidArgumentException('Schemas may contain only Field definitions.');
            }
            if (isset($paths[$field->name])) {
                throw new InvalidArgumentException('Field paths cannot be duplicated or overlap: '.$field->name);
            }
            $paths[$field->name] = true;
            $this->fields[] = $field;
            $this->labels[$field->name] = $field->getLabel();
            if ($field->isEditable()) {
                $this->editableFields[] = $field;
            }
        }
        $sortedPaths = array_keys($paths);
        sort($sortedPaths, SORT_STRING);
        $previous = null;
        foreach ($sortedPaths as $path) {
            if ($previous !== null && str_starts_with($path, $previous.'.')) {
                throw new InvalidArgumentException('Field paths cannot be duplicated or overlap: '.$path);
            }
            $previous = $path;
        }
    }

    public static function make(iterable $fields): self
    {
        return new self($fields);
    }

    public function fields(): array
    {
        return $this->fields;
    }

    public function count(): int
    {
        return count($this->fields);
    }

    public function getIterator(): Traversable
    {
        yield from $this->fields;
    }

    public function only(array $names): self
    {
        $selected = [];
        foreach ($names as $name) {
            if (! is_string($name)) {
                throw new InvalidArgumentException('Selected field paths must be strings.');
            }
            $selected[$name] = true;
        }

        return self::make(array_filter($this->fields, fn (Field $field): bool => isset($selected[$field->name])));
    }

    public function group(string $group): self
    {
        return self::make(array_filter($this->fields, fn (Field $field): bool => $field->getGroup() === $group));
    }

    public function map(callable $transform): self
    {
        return self::make(array_map($transform, $this->fields));
    }

    public function rules(): array
    {
        if ($this->validationRules !== null) {
            return $this->validationRules;
        }
        $rules = [];
        foreach ($this->editableFields as $field) {
            $rules[$field->name] = $field->validationRules();
        }

        return $this->validationRules = $rules;
    }

    /** Full-form validation. Missing optional keys stay absent; defaults are presentation only. */
    public function validate(array $data, array $messages = [], ?Factory $factory = null): array
    {
        $factory ??= app(Factory::class);
        foreach ($this->editableFields as $field) {
            if (data_get($data, explode('.', $field->name)) === '') {
                Arr::set($data, $field->name, null);
            }
        }
        $validated = $factory->make($data, $this->rules(), $messages, $this->labels)->validate();
        $result = [];

        // Reconstruct exactly the registered paths, including when an 'array' rule is used.
        foreach ($this->editableFields as $field) {
            if (Arr::has($validated, $field->name)) {
                Arr::set($result, $field->name, $field->normalize(Arr::get($validated, $field->name)));
            }
        }

        return $result;
    }

    public function render(array $values = [], array|MessageBag $errors = [], string $idPrefix = 'fields'): View
    {
        if (! preg_match('/\A[a-zA-Z][a-zA-Z0-9_-]*\z/', $idPrefix)) {
            throw new InvalidArgumentException('A form ID prefix must be a safe HTML identifier.');
        }

        return view('laravel-fields::schema', [
            'schema' => $this, 'values' => $values,
            'fieldErrors' => $errors instanceof MessageBag ? $errors : new MessageBag($errors),
            'idPrefix' => $idPrefix,
        ]);
    }

    public function toFilament(): array
    {
        return (new Adapters\FilamentAdapter)->convert($this);
    }

    /** API display metadata cannot be hydrated back into executable server definitions. */
    public function jsonSerialize(): array
    {
        return ['version' => 1, 'fields' => array_map(fn (Field $field): array => $field->describe(), $this->fields)];
    }

    /** Prepare a deliberately redacted display/export row, independently of input validation. */
    public function exportRow(array $values): array
    {
        $row = [];
        foreach ($this->fields as $field) {
            if ($field->isExportable()) {
                $row[$field->name] = data_get($values, explode('.', $field->name));
            }
        }

        return $row;
    }
}
