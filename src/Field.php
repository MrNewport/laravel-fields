<?php

declare(strict_types=1);

namespace MrNewport\LaravelFields;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use JsonException;
use MrNewport\LaravelFields\Rules\Choices;

/** A server-owned definition. Fluent changes return a new instance. */
final class Field
{
    private string $label;

    private bool $required = false;

    private bool $readOnly = false;

    private bool $disabled = false;

    private bool $sensitive = false;

    private bool $exportable = true;

    private string $help = '';

    private string $placeholder = '';

    private ?string $group = null;

    private int $scale = 2;

    private mixed $default = null;

    private array $rules = [];

    private array $options = [];

    private function __construct(public readonly string $name, public readonly Type $type)
    {
        if (! preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z0-9_]+)*\z/D', $name)
            || strlen($name) > 190) {
            throw new InvalidArgumentException('Field names must be safe dot-separated paths without wildcards.');
        }

        $this->label = (string) Str::of($name)->afterLast('.')->replace('_', ' ')->headline();
        $this->sensitive = $type === Type::Password;
        $this->readOnly = $type === Type::Display;
    }

    public static function make(string $name, Type|string $type = Type::Text): self
    {
        return new self($name, is_string($type) ? Type::from($type) : $type);
    }

    public static function text(string $name): self
    {
        return self::make($name);
    }

    public static function textarea(string $name): self
    {
        return self::make($name, Type::Textarea);
    }

    public static function email(string $name): self
    {
        return self::make($name, Type::Email);
    }

    public static function url(string $name): self
    {
        return self::make($name, Type::Url);
    }

    public static function phone(string $name): self
    {
        return self::make($name, Type::Phone);
    }

    public static function password(string $name): self
    {
        return self::make($name, Type::Password);
    }

    public static function integer(string $name): self
    {
        return self::make($name, Type::Integer);
    }

    public static function decimal(string $name, int $scale = 2): self
    {
        return self::make($name, Type::Decimal)->scale($scale);
    }

    public static function date(string $name): self
    {
        return self::make($name, Type::Date);
    }

    public static function dateTime(string $name): self
    {
        return self::make($name, Type::DateTime);
    }

    public static function month(string $name): self
    {
        return self::make($name, Type::Month);
    }

    public static function time(string $name): self
    {
        return self::make($name, Type::Time);
    }

    public static function select(string $name): self
    {
        return self::make($name, Type::Select);
    }

    public static function multiSelect(string $name): self
    {
        return self::make($name, Type::MultiSelect);
    }

    public static function radio(string $name): self
    {
        return self::make($name, Type::Radio);
    }

    public static function checkbox(string $name): self
    {
        return self::make($name, Type::Checkbox);
    }

    public static function json(string $name): self
    {
        return self::make($name, Type::Json);
    }

    public static function file(string $name): self
    {
        return self::make($name, Type::File);
    }

    public static function hidden(string $name): self
    {
        return self::make($name, Type::Hidden);
    }

    public static function display(string $name): self
    {
        return self::make($name, Type::Display);
    }

    private function with(string $property, mixed $value): self
    {
        $copy = clone $this;
        $copy->{$property} = $value;

        return $copy;
    }

    public function label(string $label): self
    {
        return $this->with('label', $label);
    }

    public function required(bool $required = true): self
    {
        return $this->with('required', $required);
    }

    public function readOnly(bool $readOnly = true): self
    {
        return $this->with('readOnly', $readOnly);
    }

    public function disabled(bool $disabled = true): self
    {
        return $this->with('disabled', $disabled);
    }

    public function sensitive(bool $sensitive = true): self
    {
        return $this->with('sensitive', $sensitive || $this->type === Type::Password);
    }

    public function exportable(bool $exportable = true): self
    {
        return $this->with('exportable', $exportable);
    }

    public function help(string $help): self
    {
        return $this->with('help', $help);
    }

    public function placeholder(string $placeholder): self
    {
        return $this->with('placeholder', $placeholder);
    }

    public function group(?string $group): self
    {
        return $this->with('group', $group);
    }

    public function rules(array $rules): self
    {
        return $this->with('rules', $rules);
    }

    public function default(mixed $value): self
    {
        if ($this->sensitive || $this->type === Type::File) {
            throw new InvalidArgumentException('Sensitive fields and files cannot have default values.');
        }
        $copy = self::copyDefault($value);
        json_encode($copy, JSON_THROW_ON_ERROR);

        return $this->with('default', $copy);
    }

    private static function copyDefault(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 512 || is_object($value) || is_resource($value) || (is_float($value) && ! is_finite($value))) {
            throw new InvalidArgumentException('Defaults must contain finite JSON-compatible data within 512 nesting levels.');
        }
        if (! is_array($value)) {
            return $value;
        }
        $copy = [];
        foreach ($value as $key => $item) {
            $copy[$key] = self::copyDefault($item, $depth + 1);
        }

        return $copy;
    }

    public function scale(int $scale): self
    {
        if ($scale < 0 || $scale > 30) {
            throw new InvalidArgumentException('Decimal scale must be between 0 and 30.');
        }

        return $this->with('scale', $scale);
    }

    /** @param array<string|int, mixed> $options Keys are exact, case-sensitive IDs; labels are validated here. */
    public function options(array $options): self
    {
        $copy = [];
        foreach ($options as $key => $label) {
            if (! is_string($label) || (string) $key === '') {
                throw new InvalidArgumentException('Options need nonempty string/integer keys and string labels.');
            }
            $copy[$key] = $label;
        }

        return $this->with('options', $copy);
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    public function isSensitive(): bool
    {
        return $this->sensitive;
    }

    public function isEditable(): bool
    {
        return ! $this->disabled && ! $this->readOnly && $this->type !== Type::Display;
    }

    public function isExportable(): bool
    {
        return $this->exportable && ! $this->sensitive && $this->type !== Type::File;
    }

    public function getHelp(): string
    {
        return $this->help;
    }

    public function getPlaceholder(): string
    {
        return $this->placeholder;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function getDefault(): mixed
    {
        return $this->sensitive ? null : $this->default;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getScale(): int
    {
        return $this->scale;
    }

    public function validationRules(): array
    {
        $typeRules = match ($this->type) {
            Type::Email => ['string', 'email:rfc'],
            Type::Url => ['string', 'url:http,https'],
            Type::Integer => ['integer', 'between:'.PHP_INT_MIN.','.PHP_INT_MAX],
            Type::Decimal => ['string', 'numeric', 'regex:/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]{1,'.$this->scale.'})?\z/D'],
            Type::Checkbox => ['boolean'],
            Type::Date => ['date_format:Y-m-d', 'regex:/\A(?!0000)[0-9]{4}-/D'],
            Type::DateTime => ['date_format:Y-m-d\TH:i', 'regex:/\A(?!0000)[0-9]{4}-/D'],
            Type::Month => ['date_format:Y-m', 'regex:/\A(?!0000)[0-9]{4}-/D'],
            Type::Time => ['date_format:H:i'],
            Type::Json => ['string', 'json', static function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    // JSON syntax permits numbers outside PHP's finite float range.
                    // Ensure every accepted value can also be represented in output JSON.
                    json_encode(json_decode($value, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING), JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    $fail('laravel-fields::messages.finite_json')->translate();
                }
            }],
            Type::File => ['file'],
            Type::MultiSelect => ['array', 'list', 'max:'.Choices::MAX_ITEMS, new Choices($this->options)],
            Type::Select, Type::Radio => ['string', Rule::in(array_map('strval', array_keys($this->options)))],
            default => ['string'],
        };

        if ($this->type === Type::Decimal && $this->scale === 0) {
            $typeRules = ['string', 'numeric', 'regex:/\A-?(?:0|[1-9][0-9]*)\z/D'];
        }

        return ['bail', $this->required ? 'required' : 'nullable', ...$typeRules, ...$this->rules];
    }

    public function itemRules(): array
    {
        return ['bail', 'string', 'distinct:strict', Rule::in(array_map('strval', array_keys($this->options)))];
    }

    /** Called only after successful validation. Decimal values never pass through a float. */
    public function normalize(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->type) {
            Type::Integer => (int) $value,
            Type::Checkbox => (bool) $value,
            Type::Json => json_decode($value, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING),
            Type::Decimal => $this->normalizeDecimal($value),
            default => $value,
        };
    }

    private function normalizeDecimal(string $value): string
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $result = $whole.($this->scale > 0 ? '.'.str_pad($fraction, $this->scale, '0') : '');

        return preg_match('/\A-0(?:\.0+)?\z/', $result) ? substr($result, 1) : $result;
    }

    public function inputName(): string
    {
        $segments = explode('.', $this->name);
        $name = array_shift($segments);

        return $name.implode('', array_map(fn (string $segment): string => '['.$segment.']', $segments))
            .($this->type === Type::MultiSelect ? '[]' : '');
    }

    public function inputType(): string
    {
        return match ($this->type) {
            Type::Integer => 'number',
            Type::Decimal => 'text',
            default => $this->type->value,
        };
    }

    /** Display metadata only: no values, executable rules or class names. */
    public function describe(): array
    {
        return [
            'name' => $this->name, 'type' => $this->type->value, 'label' => $this->label,
            'required' => $this->required, 'read_only' => $this->readOnly, 'disabled' => $this->disabled,
            'sensitive' => $this->sensitive, 'help' => $this->help, 'placeholder' => $this->placeholder,
            'group' => $this->group, 'options' => $this->options, 'scale' => $this->scale,
        ];
    }
}
