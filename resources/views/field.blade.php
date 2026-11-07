@php
    $id = $idPrefix.'-'.str_replace('.', '-', $field->name);
    $value = $field->isSensitive() ? null : data_get($values, explode('.', $field->name), $field->getDefault());
    if ($field->type === \MrNewport\LaravelFields\Type::Json && $value !== null && ! is_string($value)) {
        $value = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
    $textValue = is_array($value) ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR)
        : ((is_scalar($value) || $value instanceof \Stringable) ? (string) $value : '');
    $selected = [];
    if (in_array($field->type, [\MrNewport\LaravelFields\Type::Select, \MrNewport\LaravelFields\Type::MultiSelect], true)) {
        $selection = $field->type === \MrNewport\LaravelFields\Type::MultiSelect
            ? (is_array($value) && array_is_list($value) ? $value : []) : [$value];
        foreach ($selection as $item) {
            if (is_string($item) || is_int($item)) {
                $selected[(string) $item] = true;
            }
        }
    }
    $selectedRadio = is_string($value) || is_int($value) ? (string) $value : null;
    $messages = $fieldErrors->get($field->name);
    if ($field->type === \MrNewport\LaravelFields\Type::MultiSelect) {
        $messages = array_merge($messages, ...array_values($fieldErrors->get($field->name.'.*')));
    }
    $describedBy = trim(($field->getHelp() !== '' ? $id.'-help' : '').' '.($messages !== [] ? $id.'-error' : ''));
    $attributes = new \Illuminate\View\ComponentAttributeBag([
        'id' => $id, 'name' => $field->inputName(),
        'required' => $field->isRequired() && $field->isEditable(),
        'disabled' => ! $field->isEditable(),
        'aria-invalid' => $messages !== [] ? 'true' : null,
        'aria-describedby' => $describedBy !== '' ? $describedBy : null,
        'placeholder' => $field->getPlaceholder(),
        'class' => 'block w-full rounded-xl border border-slate-500 bg-white px-3.5 py-2.5 text-base text-slate-900 shadow-xs outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500 placeholder:text-slate-500 dark:border-slate-400 dark:bg-slate-950 dark:placeholder:text-slate-400 dark:text-slate-100 dark:disabled:bg-slate-800',
    ]);
@endphp

@if ($field->type === \MrNewport\LaravelFields\Type::Hidden)
    <input type="hidden" {{ $attributes->except(['class', 'placeholder']) }} value="{{ is_scalar($value) ? $value : '' }}">
@else
    <div class="min-w-0 space-y-2">
        @if (in_array($field->type, [\MrNewport\LaravelFields\Type::Radio, \MrNewport\LaravelFields\Type::Display], true) || $field->isReadOnly())
            <span id="{{ $id }}-label" class="block text-sm font-medium text-slate-800 dark:text-slate-200">
                {{ $field->getLabel() }}
                @if ($field->isRequired() && $field->isEditable())<span aria-hidden="true" class="text-indigo-600">*</span>@endif
            </span>
        @elseif ($field->type !== \MrNewport\LaravelFields\Type::Checkbox)
            <label for="{{ $id }}" class="block text-sm font-medium text-slate-800 dark:text-slate-200">
                {{ $field->getLabel() }}
                @if ($field->isRequired() && $field->isEditable())<span aria-hidden="true" class="text-indigo-600">*</span>@endif
            </label>
        @endif

        @if ($field->type === \MrNewport\LaravelFields\Type::Display || $field->isReadOnly())
            <p id="{{ $id }}" aria-labelledby="{{ $id }}-label" class="whitespace-pre-wrap break-words text-sm text-slate-700 dark:text-slate-300">{{ $textValue }}</p>
        @elseif (in_array($field->type, [\MrNewport\LaravelFields\Type::Textarea, \MrNewport\LaravelFields\Type::Json], true))
            <textarea rows="4" {{ $attributes }}>{{ $textValue }}</textarea>
        @elseif (in_array($field->type, [\MrNewport\LaravelFields\Type::Select, \MrNewport\LaravelFields\Type::MultiSelect], true))
            @if ($field->type === \MrNewport\LaravelFields\Type::MultiSelect && $field->isEditable())<input type="hidden" name="{{ substr($field->inputName(), 0, -2) }}" value="">@endif
            <select {{ $attributes->merge(['multiple' => $field->type === \MrNewport\LaravelFields\Type::MultiSelect]) }}>
                @if ($field->type === \MrNewport\LaravelFields\Type::Select)<option value="">{{ $field->getPlaceholder() ?: __('laravel-fields::messages.choose_option') }}</option>@endif
                @foreach ($field->getOptions() as $key => $label)
                    <option value="{{ $key }}" @selected(isset($selected[(string) $key]))>{{ $label }}</option>
                @endforeach
            </select>
        @elseif ($field->type === \MrNewport\LaravelFields\Type::Radio)
            <div id="{{ $id }}" role="radiogroup" aria-labelledby="{{ $id }}-label" @if($describedBy) aria-describedby="{{ $describedBy }}" @endif @if($field->isRequired() && $field->isEditable()) aria-required="true" @endif class="space-y-2">
                @foreach ($field->getOptions() as $key => $label)
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-3.5 py-3 text-sm text-slate-800 dark:border-slate-700 dark:text-slate-200">
                        <input type="radio" {{ $attributes->except(['id', 'class', 'placeholder']) }} value="{{ $key }}" @checked($selectedRadio !== null && $selectedRadio === (string) $key) class="size-4 accent-indigo-600">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        @elseif ($field->type === \MrNewport\LaravelFields\Type::Checkbox)
            @if ($field->isEditable())<input type="hidden" name="{{ $field->inputName() }}" value="0">@endif
            <label for="{{ $id }}" class="flex items-center gap-3 text-sm font-medium text-slate-800 dark:text-slate-200">
                <input type="checkbox" {{ $attributes->except(['class', 'placeholder', 'required']) }} value="1" @checked(in_array($value, [true, 1, '1'], true)) class="size-4 rounded accent-indigo-600">
                {{ $field->getLabel() }}
            </label>
        @elseif ($field->type === \MrNewport\LaravelFields\Type::File)
            <input type="file" {{ $attributes->except(['placeholder']) }}>
        @else
            <input type="{{ $field->inputType() }}" {{ $attributes }} value="{{ is_scalar($value) ? $value : '' }}" @if($field->type === \MrNewport\LaravelFields\Type::Decimal) inputmode="decimal" @endif @if($field->type === \MrNewport\LaravelFields\Type::Password) autocomplete="new-password" @endif>
        @endif

        @if ($field->getHelp() !== '')<p id="{{ $id }}-help" class="text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $field->getHelp() }}</p>@endif
        @if ($messages !== [])<p id="{{ $id }}-error" role="alert" class="text-sm text-red-700 dark:text-red-400">{{ implode(' ', $messages) }}</p>@endif
    </div>
@endif
