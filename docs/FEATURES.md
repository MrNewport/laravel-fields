# Feature and API index

All methods below are current public contracts. Links lead to explanations and examples, with executable schemas in [examples/schemas.php](../examples/schemas.php) and regression coverage in [DocumentationExamplesTest](../tests/DocumentationExamplesTest.php). Core usage requires no optional adapters. `self` means a new immutable definition/schema where applicable.

## Field construction and types

| Signature → return | Behavior / errors | Guide and example |
| --- | --- | --- |
| `Field::make(string $name, Type|string $type = Type::Text): self` | Safe dot path ≤190 bytes; invalid name → InvalidArgumentException; unknown string type → ValueError. | [Names](HANDBOOK.md#immutable-definitions-and-names), [all types](HANDBOOK.md#every-field-type) |
| `text`, `textarea`, `email`, `url`, `phone`, `password`, `integer`, `date`, `dateTime`, `month`, `time`, `select`, `multiSelect`, `radio`, `checkbox`, `json`, `file`, `hidden`, `display` `(string $name): self` | Factories for every Type enum case; see the full accepted-value/normalization/control matrix. | [Type matrix](HANDBOOK.md#every-field-type) |
| `decimal(string $name, int $scale = 2): self` | Exact decimal string, 0–30 scale; invalid scale → InvalidArgumentException. | [Decimals/JSON](HANDBOOK.md#exact-decimals-and-json) |
| `Type` backed enum | Text, Textarea, Email, Url, Phone, Password, Integer, Decimal, Date, DateTime, Month, Time, Select, MultiSelect, Radio, Checkbox, Json, File, Hidden, Display. Use enum `from`/`tryFrom` for backing values. | [Type matrix](HANDBOOK.md#every-field-type) |

## Immutable field configuration and inspection

| Signature → return | Contract / errors | Guide |
| --- | --- | --- |
| `label(string)`, `help(string)`, `placeholder(string)`, `group(?string): self` | Escaped presentation/validation labels and grouping. | [Options](HANDBOOK.md#all-fluent-options) |
| `required(bool = true)`, `readOnly(bool = true)`, `disabled(bool = true): self` | Presence and editability; readonly/disabled omitted from input. | [Options](HANDBOOK.md#all-fluent-options), [validation](HANDBOOK.md#validation-and-output-shape) |
| `sensitive(bool = true)`, `exportable(bool = true): self` | Blank sensitive hydration; password always sensitive; files always nonexportable. | [Options](HANDBOOK.md#all-fluent-options), [account recipe](RECIPES.md#account-update-with-exact-amounts-and-password-changes) |
| `default(mixed): self` | Detached finite scalar/array display fallback; sensitive/file/object/resource/nonfinite/deep values refused; JSON encoding can throw JsonException. | [Options](HANDBOOK.md#all-fluent-options) |
| `rules(array): self`, `validationRules(): array`, `itemRules(): array` | Custom list replaces previous custom list. validationRules includes bail/presence/type rules. itemRules exposes string/distinct/membership item rules; core uses bounded Choices instead of wildcard expansion. | [Validation](HANDBOOK.md#validation-and-output-shape) |
| `options(array): self`, `getOptions(): array` | Exact nonempty string/integer IDs and string labels; malformed options → InvalidArgumentException. | [Choices](HANDBOOK.md#exact-decimals-and-json) |
| `scale(int): self`, `getScale(): int` | 0–30 precision, default 2; invalid → InvalidArgumentException. | [Decimals](HANDBOOK.md#exact-decimals-and-json) |
| `getLabel(): string`, `getHelp(): string`, `getPlaceholder(): string`, `getGroup(): ?string`, `getDefault(): mixed` | Read configuration; sensitive default reads null. | [Options](HANDBOOK.md#all-fluent-options) |
| `isRequired()`, `isReadOnly()`, `isDisabled()`, `isSensitive()`, `isEditable()`, `isExportable(): bool` | Derived flags; editability excludes display/readonly/disabled; exportability excludes secrets/files. | [Options](HANDBOOK.md#all-fluent-options) |
| `normalize(mixed): mixed` | Post-validation conversion only; int/bool/exact decimal/decoded JSON, other values unchanged. Null stays null. Invalid JSON can throw JsonException; call Schema::validate for untrusted data. | [Types](HANDBOOK.md#every-field-type) |
| `inputName(): string`, `inputType(): string` | Dot path → HTML bracket name, multiselect suffix; integer number input, decimal text input. | [Names](HANDBOOK.md#immutable-definitions-and-names) |
| `describe(): array` | Display metadata, excludes defaults/values/custom rules. `name` and `type` are public readonly properties. | [Metadata](HANDBOOK.md#schema-methods-and-metadata) |

## Schema

| Signature → return | Contract / errors | Guide |
| --- | --- | --- |
| `Schema::make(iterable $fields): self` | Ordered Fields only; wrong item, duplicate or overlapping name → InvalidArgumentException. | [Names](HANDBOOK.md#immutable-definitions-and-names) |
| `fields(): array`, `count(): int`, `getIterator(): Traversable` | Definitions in original order; `count($schema)` and foreach supported. | [Schema methods](HANDBOOK.md#schema-methods-and-metadata) |
| `only(array $names): self`, `group(string): self`, `map(callable): self` | New subsets/transforms; unknown only names ignored; nonstring only names/bad transformed schema fail. | [Schema methods](HANDBOOK.md#schema-methods-and-metadata) |
| `rules(): array` | Editable path → Laravel rules. Does not itself normalize values. | [Validation](HANDBOOK.md#validation-and-output-shape) |
| `validate(array $data, array $messages = [], ?Illuminate\Contracts\Validation\Factory $factory = null): array` | Exact registered nested editable normalized values. ValidationException on bad input; defaults not written. | [Validation](HANDBOOK.md#validation-and-output-shape), [recipes](RECIPES.md) |
| `render(array $values = [], array|MessageBag $errors = [], string $idPrefix = 'fields'): View` | Escaped package view. Invalid prefix → InvalidArgumentException. | [Web quickstart](HANDBOOK.md#complete-web-form), [redisplay](HANDBOOK.md#redisplay-and-multiple-forms) |
| `toFilament(): array` | Filament5 components; missing optional package → LogicException. | [Filament](HANDBOOK.md#filament-5) |
| `jsonSerialize(): array` | Metadata version1, no executable schema hydration. | [Metadata](HANDBOOK.md#schema-methods-and-metadata) |
| `exportRow(array $values): array` | Redacted flat path row; missing → null; no validation. | [Metadata](HANDBOOK.md#schema-methods-and-metadata), [reports](RECIPES.md#report-from-the-same-definitions) |

## Adapters, integration and operations

| Surface → return | Contract / errors | Guide |
| --- | --- | --- |
| `Csv::export(Schema, iterable $rows): string` | Headered formula-protected CSV, JSON/boolean encoding; invalid objects/resources → InvalidArgumentException; JSON failures propagate. | [CSV](HANDBOOK.md#csv), [review recipe](RECIPES.md#review-a-csv-before-writing-records) |
| `Csv::import(Schema, string $csv, int $maxRows = 1000, int $maxBytes = 2_000_000): array` | Validates every row; shape/limits → InvalidArgumentException; invalid values → ValidationException. | [CSV](HANDBOOK.md#csv) |
| `new Pdf(PdfRendererInterface)`, `export(Schema, array, ?string $title = null): string` | Escaped report → DocSign renderer → bytes. Renderer errors propagate. | [PDF](HANDBOOK.md#pdf-and-docsign) |
| `FilamentAdapter::convert(Schema): array` | Same adapter as toFilament; nested state/dependent rules/false/uploads documented. | [Filament](HANDBOOK.md#filament-5) |
| `Choices::MAX_ITEMS = 1000`, `new Choices(array)`, `validate(string, mixed, Closure): void` | Public Laravel ValidationRule; checks list shape, exact membership, uniqueness and budget. | [Choices](HANDBOOK.md#exact-decimals-and-json) |
| `new FilamentField(Field)`, `setData(array): static`, `validate(string, mixed, Closure): void` | Adapter's implicit data-aware bridge; unwraps full form state. | [Filament](HANDBOOK.md#filament-5) |
| `BoundedSelect::getInValidationRule(): In|Enum|null` | Returns null so shared whole-list Choices owns membership checking. Use through converter. | [Filament](HANDBOOK.md#filament-5) |
| `FieldsServiceProvider`, `laravel-fields-views`, `laravel-fields-translations` | Autodiscovery, view/translation publishing; no routes/tables. | [Install](HANDBOOK.md#fresh-laravel-application), [localization](HANDBOOK.md#localization-and-customization) |
| Tailwind4 source scanning / `dist/fields.css` / build scripts | Standalone Blade styling, no JS requirement. | [CSS](HANDBOOK.md#css-and-browser-setup) |
| Optional Workflow `FieldsActionValidator` | Trusted definition/version/step/action schema registration; finite JSON payload boundary. | [Composition recipe](RECIPES.md#optional-scoped-workflow-action) |
| Data adoption, tests, browser checks | Saved values, ownership, uploads, concurrency remain host contracts. | [Adoption](MIGRATION.md), [verification](HANDBOOK.md#verification-and-adoption) |
