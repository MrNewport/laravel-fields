# Data adoption guide

Laravel Fields separates definitions, submitted values, and application persistence. Define the shape of each form in server code, then use the same schema for rendering and validation.

## Map application data

Declare the editable values explicitly:

```php
$schema = Schema::make([
    Field::text('contact.name')->required()->rules(['max:120']),
    Field::email('contact.email')->required(),
    Field::decimal('budget', 2),
    Field::checkbox('receive_updates'),
]);

$values = [
    'contact' => ['name' => $profile->name, 'email' => $profile->email],
    'budget' => $profile->budget,
    'receive_updates' => $profile->receive_updates,
];
```

Dot paths become nested validated arrays. Supply their values in that nested shape; literal dotted input keys are ignored. A field path must be unique and cannot be an ancestor or descendant of another field path in the same schema. Keep authorization and tenant scoping in the application's queries and actions.

## Choose storage formats

| Value | Form-boundary contract |
| --- | --- |
| Decimal quantities | Supply canonical decimal strings, such as `"1200.50"`. Set the intended scale with `Field::decimal('budget', 2)`. Values retain their exact precision; inputs with excess fraction places fail validation. |
| Local dates and times | Supply the documented date/time string format. Associate local DateTime values with the application's explicit timezone and DST policy. |
| Choice IDs | Supply string keys matching the field's authorized option map. Use string `"0"` when zero is an option ID. |
| Booleans | Preserve `false` and zero as values. Native checkbox controls submit `0` when unchecked. |
| JSON | Validation accepts JSON text and returns decoded arrays/scalars. Render arrays, numbers, and booleans directly, or supply JSON text. JSON-encode a saved scalar string before rendering it. |
| Passwords | Validate the submitted plaintext, then hash it once in the application's persistence action. Leave the field absent when a partial update should retain the saved hash. |
| Files | Submit an `UploadedFile` for a new upload. Resolve existing file records and download links separately in the application. |

Formatting for currency, percentages, phone regions, and postal addresses follows the application's domain rules. For example, a percentage can use `Field::decimal('percentage', 2)->rules(['min:0', 'max:100'])`. Decimal fields use Laravel's exact numeric comparisons for bounds.

## Preserve missing and explicit values

A missing optional field is absent from the validation result. An explicit empty string becomes `null`. Defaults are used for display and are not automatically written.

Use the presence of a validated key when a persistence action must distinguish “retain the current value” from “clear the value.” For an application-defined partial update, build the relevant schema with `only([...])`. Required fields in that partial schema still require values.

Readonly, disabled, and display fields are excluded from validation output. Supply their display values separately. Sensitive fields display an empty input and are omitted from exports.

## Persist validated changes

```php
$data = $schema->validate($request->all());

DB::transaction(function () use ($data, $profileId) {
    $profile = Profile::query()->whereKey($profileId)->lockForUpdate()->firstOrFail();
    Gate::authorize('update', $profile);

    $profile->name = $data['contact']['name'];
    $profile->email = $data['contact']['email'];
    if (array_key_exists('budget', $data)) {
        $profile->budget = $data['budget'];
    }
    $profile->save();
});
```

Application actions own relationship changes, revision checks, actor attribution, audit records, and transaction recovery. Keep file storage and any external effects consistent with that transaction's success or failure.

## Adopt uploads

Add MIME and size constraints to file definitions:

```php
Field::file('attachment')->rules(['mimes:pdf', 'max:10240'])
```

The validated value is an `UploadedFile`. The application stores it on its chosen disk, attaches it to an authorized record, and supplies download access. A file input represents a new upload; a saved path is application data rather than an upload value. Filament file components use `storeFiles(false)` to support the same storage action.

## Review imported rows

`Csv::export()` accepts normalized values, including decoded JSON values. `Csv::import()` validates the complete input before returning rows. Choose limits appropriate to the form, show a preview when needed, and commit approved rows through the application's ordinary persistence action. Keep ownership, duplicate detection, and request replay rules consistent with other ways of updating the same records.

CSV export escapes spreadsheet formula-like cells with an apostrophe, which import preserves. Store original source data separately when a workflow requires exact round trips or provenance.

## Share a schema across interfaces

Use `Schema::validate()` as the final input boundary for Blade forms, API requests, CSV imports, and Filament form state. Associate workflow actions with their schemas in trusted application configuration. Use native Filament components for repeated records, relationship search, rich text, and multi-step layouts.

Before adopting a schema, exercise representative stored values: zero, false, null, missing fields, large decimals, JSON scalars, regional dates, file references, and the application's concurrent-update behavior.

[Package usage](../README.md)
