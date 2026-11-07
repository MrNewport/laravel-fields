# Laravel Fields handbook

## Fresh Laravel application

Choose the Laravel major your application uses:

```sh
composer create-project laravel/laravel review-app '^12.0'
cd review-app
composer require mrnewport/laravel-fields:^1.0
```

Use `'^13.0'` for Laravel 13 and a PHP version supported by that release. Fields itself requires PHP 8.3+. If Composer cannot find the release in your registry, add `composer config repositories.laravel-fields vcs https://github.com/MrNewport/laravel-fields` and repeat the requirement. No package migration is needed. Laravel discovers `MrNewport\LaravelFields\FieldsServiceProvider`; if discovery is disabled, register it in `bootstrap/providers.php`. It registers views and translations, and creates no public routes or application records.

### Complete web form

Create `app/Forms/ContactForm.php`:

```php
<?php
namespace App\Forms;

use MrNewport\LaravelFields\{Field, Schema};

final class ContactForm
{
    public static function schema(): Schema
    {
        return Schema::make([
            Field::text('contact.name')->label('Full name')->required()
                ->rules(['max:120'])->group('Contact'),
            Field::email('contact.email')->label('Email')->required()
                ->rules(['max:254'])->group('Contact'),
            Field::decimal('budget', 2)->rules(['min:0', 'max:1000000'])
                ->help('Use a decimal point, for example 1250.50.'),
            Field::checkbox('receive_updates')->label('Receive updates')->required(),
        ]);
    }
}
```

Add these routes to `routes/web.php`. This example works without a database or optional packages:

```php
use App\Forms\ContactForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/contact', function () {
    return view('contact', [
        'schema' => ContactForm::schema(),
        'values' => session('contact', ['receive_updates' => false]),
    ]);
})->name('contact.edit');

Route::post('/contact', function (Request $request) {
    $values = ContactForm::schema()->validate($request->all());
    $request->session()->put('contact', $values);
    return redirect()->route('contact.edit')->with('status', 'Saved.');
})->name('contact.save');
```

Create `resources/views/contact.blade.php`:

```blade
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact preferences</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-slate-900 dark:bg-slate-950 dark:text-slate-100">
<main class="mx-auto max-w-2xl p-6">
    <h1 class="mb-6 text-2xl font-semibold">Contact preferences</h1>
    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    <form method="POST" action="{{ route('contact.save') }}">
        @csrf
        {!! $schema->render(
            session()->hasOldInput() ? old() : $values,
            $errors->getBag('default'),
            'contact'
        )->render() !!}
        <button class="mt-6 rounded bg-indigo-700 px-4 py-2 text-white" type="submit">Save</button>
    </form>
</main>
</body>
</html>
```

Choose redisplay values with `hasOldInput()` so an empty failed submission does not restore saved values. Laravel's web exception handler redirects with input and errors when validation fails; JSON requests receive Laravel's validation response, normally HTTP 422. The package returns trusted view HTML whose labels, help, option labels, values and errors are escaped.

### CSS and browser setup

Add this source path to `resources/css/app.css`, relative to the stylesheet:

```css
@import "tailwindcss";
@source "../../vendor/mrnewport/laravel-fields/resources/views";
```

Run the application's `npm install` and `npm run build`. The views use Tailwind 4. Alternatively copy `vendor/mrnewport/laravel-fields/dist/fields.css` into public assets and include it once; that bundle contains generated Tailwind styles, so select one CSS approach deliberately. Package contributors rebuild it with `npm ci` and `npm run build:css`. Native Blade controls need no package JavaScript. Focus rings, explicit boundaries, placeholder contrast and light/dark colors are included. Month/time controls follow browser support and native fallback behavior.

## Immutable definitions and names

Every builder returns a clone:

```php
$optional = Field::text('title');
$required = $optional->required()->label('Title');
// $optional remains optional.
```

Keep the returned instance. Defaults and option arrays are copied to detach external array references. Define schemas in trusted PHP, rather than deriving permissions or executable definitions from request metadata.

Names are safe dot paths of at most 190 bytes. The first segment starts with an ASCII letter or underscore; segments contain ASCII letters, digits and underscores. `contact.name` becomes HTML `contact[name]`; a multiselect adds `[]`. No wildcards, literal brackets or hyphens are accepted. Supply nested input (`['contact' => ['name' => 'Avery']]`); literal `'contact.name'` keys are ignored. A schema accepts only Fields and rejects duplicates or overlaps such as both `contact` and `contact.name`. Dynamic repeated records require a bounded application schema or a native Filament Repeater.

### All fluent options

| Builder | Default and behavior |
| --- | --- |
| `label(string)` | Last segment in headline form, e.g. `first_name` → `First Name`; also the validation attribute label. |
| `help(string)` / `placeholder(string)` | Empty text. Escaped output; placeholders do not replace labels. |
| `group(?string)` | Null. Presentation fieldset; exact-match selection through `Schema::group()`. |
| `required(bool = true)` | False. Uses Laravel `required`; optional fields use `nullable`. |
| `readOnly(bool = true)` | False except display. Blade shows escaped text; excluded from validation. |
| `disabled(bool = true)` | False. Disabled control; excluded from validation. |
| `sensitive(bool = true)` | False except password. Blank hydration; excluded from exports. Password sensitivity cannot be disabled. |
| `exportable(bool = true)` | True. False excludes another field from export/report rows. Sensitivity/file type still take precedence. |
| `default(mixed)` | Null. Display fallback; never inserts missing submitted data. Sensitive/file defaults are rejected. |
| `rules(array)` | Empty. Replaces previous custom rules and appends them to mandatory type rules. |
| `options(array)` | Empty. Exact nonempty string/integer IDs mapped to string labels. |
| `scale(int)` | 2. Decimal scale 0–30; invalid values throw `InvalidArgumentException`. |

Defaults accept finite JSON-compatible scalars/arrays within 512 nesting levels. Objects, resources and nonfinite floats are refused. Invalid UTF-8 can throw `JsonException`. Defaults are presentation, not type validation. Turning an already configured field sensitive suppresses an earlier default. Hidden inputs are editable and untrusted: resolve ownership and prices on the server.

## Every field type

Factories take a name; `decimal` also takes its scale. Generic construction is `Field::make('value', Type::Text)` or `Field::make('value', 'text')`. An unknown string enum value throws `ValueError`.

| Factory / enum value | Accepted input and output | Control and practical rule |
| --- | --- | --- |
| `text` / `text` | String unchanged. | Text; `rules(['max:120'])`. Fields does not trim whitespace; request middleware may. |
| `textarea` / `textarea` | String unchanged. | Four-row text area; `rules(['max:10000'])`. |
| `email` / `email` | RFC-valid email string. | Email input; add length/domain/ownership policy. No implicit DNS verification. |
| `url` / `url` | HTTP(S) URL string. | URL input; `javascript:` and `file:` fail. Host chooses allowed destinations. |
| `phone` / `tel` | String preserving entered number. | Telephone keyboard; supply regional rules. |
| `password` / `password` | Plaintext string for host hashing. | Empty hydration; `autocomplete="new-password"`; always sensitive. |
| `integer` / `integer` | Laravel integer input in PHP integer range, output PHP `int`. | Number input; fractions/overflow fail; add `min:0` for nonnegative counts. |
| `decimal` / `decimal` | Canonical decimal string → fixed-scale exact string. | Text plus decimal input mode; `decimal('rate', 4)->rules(['between:0,100'])`. |
| `date` / `date` | Exact `Y-m-d`, years 0001–9999. | Native date; invalid leap dates fail. |
| `dateTime` / `datetime-local` | Exact `Y-m-dTH:i`, years 0001–9999. | Local wall time without seconds/offset; host owns timezone/DST. |
| `month` / `month` | Exact `Y-m`, years 0001–9999. | Native month where supported. |
| `time` / `time` | Exact `H:i`, 00:00–23:59. | Native time; no seconds/timezone. |
| `select` / `select` | One exact string option ID. | Select with empty prompt; `options(['0' => 'Zero', 'a' => 'Alpha'])`. |
| `multiSelect` / `multiselect` | List of at most 1,000 distinct exact string IDs. | Multiple select; clearing submits empty marker, yielding optional null. |
| `radio` / `radio` | One exact string option ID. | Labeled radio group and shared help/errors. |
| `checkbox` / `checkbox` | true/false, 1/0, `'1'`/`'0'` → bool. | Hidden 0 plus checkbox 1. `required()` permits false; `rules(['accepted'])` requires consent. |
| `json` / `json` | JSON text → finite decoded arrays/scalars/null. | Textarea; large integers remain strings; invalid/deep/nonfinite JSON fails. |
| `file` / `file` | Valid `UploadedFile`, unchanged. | Never prefilled; add MIME/size rules and multipart form encoding. |
| `hidden` / `hidden` | String unchanged. | Hidden input; add server rules such as `in:known`. |
| `display` / `display` | No submitted value validated. | Escaped text; optionally exportable when nonsensitive. |

### Exact decimals and JSON

For scale 2, `'12'` becomes `'12.00'`, `'-12.3'` becomes `'-12.30'`, and `'-0.00'` becomes `'0.00'`. Decimals never pass through a float. Scientific notation, currency symbols, thousands separators, leading zeroes (`'01.00'`), excessive fraction places and PHP float inputs fail. Scale 0 accepts integer strings. Laravel numeric `min`, `max`, `between` compare numeric values; preserve strings in domain calculations and storage.

JSON text `'false'` returns false, `'0'` returns integer 0, and `'"hello"'` returns string `hello`. Render decoded arrays/numbers/booleans directly. Encode a saved scalar string with `json_encode($savedString, JSON_THROW_ON_ERROR)` before rendering, because ordinary strings are interpreted as JSON text. Filament follows the same convention. Validation accepts the raw JSON-text boundary; do not submit an already decoded JSON array to `validate()`.

Choice IDs remain case-sensitive strings even when PHP stores numeric option keys as integers. Submit `'0'`, not integer 0, in a multiselect list. Associative arrays, objects, duplicates, unknown IDs and >1,000 items fail. `Choices` validates the entire list without expanding unbounded wildcard rules or comparing every pair. Add `max:20` for a smaller form limit. For huge relationship datasets use a searchable native Filament component.

## Validation and output shape

`Schema::validate(array $data, array $messages = [], ?Factory $factory = null): array` uses Laravel's container factory by default. It validates editable registered fields, normalizes accepted types and reconstructs only their exact nested paths. Extra siblings are omitted. Readonly/disabled/display fields are omitted even when submitted.

| Situation | Result |
| --- | --- |
| Missing optional path | Absent; defaults are not inserted. |
| Explicit empty string | Converted to null; optional output contains null. |
| False or zero | Preserved according to the type. |
| Required missing/null/empty | `ValidationException`. |
| Extra path | Omitted. |
| Matching Laravel exclusion rule | Path absent. |

The method does not authorize, merge saved data, persist or trim strings. Laravel middleware can transform input first. For a trusted partial update use `$schema->only(['contact.name'])`; required fields in the subset remain required. Do not let a client choose which required fields exist.

Custom string rules, closures, `ValidationRule` objects, data-aware rules, conditional `Rule::requiredIf`/`excludeIf`, password rules and upload constraints run in Laravel validation. Type rules remain active. Multiple `rules()` calls replace custom lists:

```php
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

$schema = Schema::make([
    Field::text('profile.name')->required(),
    Field::text('confirmation')->rules(['same:profile.name']),
    Field::text('reason')->rules([Rule::requiredIf($mustExplain)]),
    Field::password('password')->rules([Password::min(12)->mixedCase()->numbers()]),
]);
$data = $schema->validate($input, ['confirmation.same' => 'Repeat the profile name.']);
```

Dependent rules see complete input. JSON is one validated structured value; validate its decoded domain structure separately. Calling only Laravel with `Schema::rules()` omits Fields' normalization and reconstruction; use `validate()` before persistence.

### Redisplay and multiple forms

`render($values, $errors, $idPrefix)` returns a Laravel View; `->render()` returns HTML. Errors accept a MessageBag or arrays such as `['profile.name' => ['Required.']]`. Prefixes start with an ASCII letter and contain letters/digits/underscore/hyphen. Give each form its own prefix to avoid duplicate label/help/error IDs. Invalid prefixes throw `InvalidArgumentException`.

Read-only and display values use a named group around their value paragraph, giving assistive technology a valid label association. Labels/help/errors are linked; invalid controls use `aria-invalid`; errors use `role="alert"`; groups use fieldsets. Malformed submitted array shapes and invalid UTF-8 redisplay without crashing. Native validation helps users, while server validation remains authoritative. Required checkboxes omit HTML required because it would demand true; required presence permits false. Clearing an optional multiselect yields null, so the persistence action should interpret that deliberately.

Use `$errors->getBag('billing')` for a named error bag. A manual catch can redirect with `->withErrors($exception->errors(), 'billing')->withInput()` and redisplay the same bag. Sensitive inputs remain blank. Use Laravel's normal sensitive-input exclusions when flashing requests, and avoid logging plaintext credentials.

## Schema methods and metadata

Schemas preserve definition order and implement Countable, IteratorAggregate and JsonSerializable:

```php
$contact = $schema->group('Contact');
$partial = $schema->only(['contact.name', 'contact.email']);
$readOnly = $schema->map(fn (Field $field): Field => $field->readOnly());
foreach ($schema as $field) { /* Field */ }
$definitions = $schema->fields();
$count = count($schema);
$metadata = json_encode($schema, JSON_THROW_ON_ERROR);
$row = $schema->exportRow($values);
```

`only()` ignores unknown names, rejects nonstrings and preserves schema order. `map()` must return valid Fields with nonoverlapping paths. Metadata is `['version' => 1, 'fields' => [...]]`, including name/type/label/required/read_only/disabled/sensitive/help/placeholder/group/options/scale. It excludes values, defaults, executable custom rules/class names and exportable settings. There is no schema-from-metadata constructor: keep executable definitions in PHP.

`exportRow()` returns flat dot-keyed values, null when missing. It does not validate or normalize. Sensitive/file fields are always excluded; readonly/display fields can appear. Authorize records and field selection before exporting, and pass normalized or explicitly mapped saved values.

## Filament 5

Install `filament/forms:^5.0` and configure Filament/Livewire in the host. Calling `toFilament()` without it throws an installation `LogicException`. Direct conversion is `(new FilamentAdapter)->convert($schema)`.

```php
namespace App\Livewire;

use App\Forms\ContactForm;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema as FilamentSchema;
use Livewire\Component;

final class ContactEditor extends Component implements HasSchemas
{
    use InteractsWithSchemas;
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill(session('contact', ['receive_updates' => false]));
    }

    public function form(FilamentSchema $form): FilamentSchema
    {
        return $form->components(ContactForm::schema()->toFilament())->statePath('data');
    }

    public function save(): void
    {
        $values = ContactForm::schema()->validate($this->form->getState());
        session()->put('contact', $values);
        session()->flash('status', 'Saved.');
    }

    public function render()
    {
        return view('livewire.contact-editor');
    }
}
```

The host view contains `<form wire:submit="save">{{ $this->form }}<button type="submit">Save</button></form>` in one root element, with normal Filament assets. `getState()` returns unwrapped data, not `['data' => ...]`. Dependent rules such as `same:profile.name` see the complete unwrapped state even under nested state paths. Closures and data-aware rules remain server-side.

Textarea/JSON map to Textarea; selects to Select/BoundedSelect; radios to Radio; checkboxes to Checkbox; uploads to FileUpload; hidden to Hidden; remaining types to TextInput (display uses disabled text). Labels, options, helpers, placeholders, defaults and rules carry over. Readonly/disabled/display fields are not dehydrated. Sensitive hydration is blank. Raw state is retained until final validation to preserve decimal precision and prevent double JSON decoding.

Required false checkboxes pass. Multiselects use bounded whole-list validation. FileUpload uses `storeFiles(false)`; the host handles its uploaded file exactly as a Blade upload. Existing paths are not new files. Use native Filament components for relationship search, rich text, repeaters, tabs and wizards, with an explicit final application data boundary.

## CSV

`Csv::export(Schema $schema, iterable $rows): string` emits CRLF CSV with field-path headers. Booleans become 1/0, arrays become JSON, JSON fields always become JSON text preserving scalars/null/numeric types. Pass normalized rows. The exporter has no file/network/database effects; the final string occupies memory even when rows are an iterator. Objects/resources and invalid JSON fail.

`Csv::import(Schema $schema, string $csv, int $maxRows = 1000, int $maxBytes = 2_000_000): array` accepts contents, not a filename/UploadedFile. A UTF-8 BOM is accepted; blank lines skipped. Headers must be unique, known, editable, nonsensitive and nonfile. Column counts must match. Every row validates before any rows are returned. Multiselect cells contain JSON lists; JSON cells contain JSON text; blank optional cells become null. A quoted empty record remains a record. Required schema fields still fail if missing from headers.

Positive limits are required. Excess bytes/rows, bad headers or columns throw `InvalidArgumentException`; invalid row data throws `ValidationException`. `exportable(false)` affects export, not admission to CSV import. Define a separate import schema for different permissions. Readonly export columns cannot be imported.

Formula-like cells beginning with `=`, `+`, `@`, `-`, or ignored leading whitespace/control characters receive an apostrophe prefix. This includes negative numeric strings. Import preserves that prefix. CSV is reviewed display exchange, not a lossless backup. Keep original source data separately and see the [reviewed-import recipe](RECIPES.md#review-a-csv-before-writing-records).

## PDF and DocSign

Install `mrnewport/laravel-docsign:^2.0` only for PDF rendering:

```php
use MrNewport\LaravelFields\Adapters\Pdf;
use MrNewport\LaravelDocSign\Services\Pdf\DomPdfRenderer;

$bytes = (new Pdf(new DomPdfRenderer))->export($schema, $values, 'Contact review');
return response($bytes, 200, [
    'Content-Type' => 'application/pdf',
    'Content-Disposition' => 'attachment; filename="contact-review.pdf"',
]);
```

`Pdf::export(Schema, array, ?string $title = null): string` renders the escaped report with `exportRow()`, then delegates to DocSign `PdfRendererInterface::renderPdf($html)`. Default title and boolean values follow locale. It returns bytes without creating Document records, files or signature requests; renderer failures propagate. Supply another renderer implementation when needed and install any required executable in the host. Core Fields works without DocSign. Follow DocSign's contracts separately for document storage/signing.

## Localization and customization

Use Laravel locale middleware or `app()->setLocale($locale)`. Translate application labels/help/options explicitly: `Field::text('title')->label(__('forms.title'))`. Package prompts, collection/JSON validation messages, report title and boolean labels use `laravel-fields::messages`.

```sh
php artisan vendor:publish --tag=laravel-fields-translations
php artisan vendor:publish --tag=laravel-fields-views
```

Overrides live under `lang/vendor/laravel-fields/<locale>/messages.php` and `resources/views/vendor/laravel-fields`. Preserve escaped content, input names, IDs, accessibility attributes and hidden checkbox/multiselect markers. Rebuild CSS after changes. Published copies are application-owned; compare them with upstream views on upgrades.

## Verification and adoption

Read [MIGRATION.md](MIGRATION.md) for mapping saved decimals, dates, scalar JSON, false, absent values and file references. Render and validate representative saved records before adoption; avoid mass-revalidating and overwriting history without a reviewed migration. Fields itself has no storage migrations.

`composer check` runs formatting, static analysis and PHP tests, including [executable schemas](../examples/schemas.php). Browser checks exercise Chromium/Firefox/WebKit controls and accessibility. The isolated CI production install runs `tests/core-only.php` without optional packages. The [feature index](FEATURES.md) maps public methods to this guide and examples.
