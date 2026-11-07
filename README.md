# Laravel Fields

Define a form once in trusted PHP and use it for Laravel validation, accessible Blade controls, Filament 5 components, reviewed CSV exchange and PDF reports. Definitions are immutable; values and persistence stay in your application.

```php
use MrNewport\LaravelFields\{Field, Schema};

$schema = Schema::make([
    Field::text('contact.name')->required()->rules(['max:120']),
    Field::email('contact.email')->required(),
    Field::decimal('budget', 2)->rules(['min:0']),
    Field::checkbox('receive_updates')->required(),
]);
$data = $schema->validate($request->all());
// '12.3' becomes '12.30'; unchecked '0' becomes false.
```

PHP 8.3+ and Laravel 12 or 13 are supported. Laravel's own PHP requirement also applies. Core Fields requires no Filament, DocSign, JavaScript or database tables.

```sh
composer require mrnewport/laravel-fields:^1.0
```

If your Composer registry has not indexed the tag, configure its GitHub VCS repository and repeat the requirement:

```sh
composer config repositories.laravel-fields vcs https://github.com/MrNewport/laravel-fields
composer require mrnewport/laravel-fields:^1.0
```

The [handbook](docs/HANDBOOK.md) covers a fresh application, every field type and option, validation, missing/null/false, uploads, CSS, localization, CSV, PDF and Filament. The [feature index](docs/FEATURES.md) links public signatures, return contracts, errors and examples. The [recipes](docs/RECIPES.md) cover account updates, private uploads, reviewed imports and Workflow composition. Read [stored-data adoption](docs/MIGRATION.md) before mapping existing records.

Install optional integrations only when needed:

```sh
composer require filament/forms:^5.0
composer require mrnewport/laravel-docsign:^2.0
```

[Laravel Flow](https://github.com/MrNewport/laravel-flow) works independently. [Laravel Workflow](https://github.com/MrNewport/laravel-workflow) can register a Fields schema for a scoped action. Neither is required for an ordinary form.

Package development:

```sh
composer install
composer check
npm ci
npm run build:css
php tests/Browser/build-fixture.php
npx playwright install chromium firefox webkit
npm run test:browser
```

The PHP suite includes executable documentation recipes. Browser checks cover native controls, keyboard navigation, both color themes and axe accessibility. CI also checks production installation without optional adapters. Source distributions retain the CSS lockfile.

[Changelog](CHANGELOG.md) · [MIT license](LICENSE)
