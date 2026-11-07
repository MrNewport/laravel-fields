<div class="space-y-8" data-fields-schema>
    @foreach (collect($schema->fields())->groupBy(fn ($field) => $field->getGroup() ?? '') as $group => $fields)
        <fieldset class="min-w-0 space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            @if ($group !== '')
                <legend class="px-2 text-sm font-semibold tracking-wide text-slate-900 dark:text-slate-100">{{ $group }}</legend>
            @endif
            @foreach ($fields as $field)
                @include('laravel-fields::field', ['field' => $field])
            @endforeach
        </fieldset>
    @endforeach
</div>
