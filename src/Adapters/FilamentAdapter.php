<?php

declare(strict_types=1);

namespace MrNewport\LaravelFields\Adapters;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use LogicException;
use MrNewport\LaravelFields\Field;
use MrNewport\LaravelFields\Rules\FilamentField;
use MrNewport\LaravelFields\Schema;
use MrNewport\LaravelFields\Type;

/** Optional integration; Filament is not required by the core package. */
final class FilamentAdapter
{
    public function convert(Schema $schema): array
    {
        if (! class_exists(TextInput::class)) {
            throw new LogicException('Install filament/forms ^5.0 to use the Filament adapter.');
        }

        return array_map($this->field(...), $schema->fields());
    }

    private function field(Field $field): \Filament\Forms\Components\Field
    {
        $component = match ($field->type) {
            Type::Textarea, Type::Json => Textarea::make($field->name),
            Type::Select => Select::make($field->name)->options($field->getOptions()),
            Type::MultiSelect => BoundedSelect::make($field->name)->options($field->getOptions())->multiple(),
            Type::Radio => Radio::make($field->name)->options($field->getOptions()),
            Type::Checkbox => Checkbox::make($field->name),
            Type::File => FileUpload::make($field->name)->storeFiles(false),
            Type::Hidden => Hidden::make($field->name),
            default => TextInput::make($field->name)->type($field->inputType() === 'display' ? 'text' : $field->inputType()),
        };

        $component->label($field->getLabel())
            ->helperText($field->getHelp())
            ->required($field->isEditable() && $field->isRequired() && $field->type !== Type::Checkbox)
            ->disabled(! $field->isEditable())
            ->dehydrated($field->isEditable())
            ->rules($field->isEditable() ? ['bail', $field->isRequired() ? 'required' : 'nullable', new FilamentField($field)] : []);

        if (method_exists($component, 'placeholder')) {
            $component->placeholder($field->getPlaceholder());
        }
        if ($field->isSensitive()) {
            // Never hydrate stored credentials or other secrets back into the browser.
            $component->formatStateUsing(fn (): mixed => null);
        } elseif ($field->type === Type::Json) {
            $component->formatStateUsing(fn (mixed $state): ?string => $state === null || is_string($state)
                ? $state : json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR));
        }
        if (! $field->isSensitive() && $field->getDefault() !== null) {
            $component->default($field->getDefault());
        }

        // Always validate the final form data through Schema::validate() before persistence.
        // Keeping dehydration raw avoids lossy numeric state casting or JSON double decoding.
        return $component;
    }
}
