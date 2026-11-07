<?php

declare(strict_types=1);

namespace MrNewport\LaravelFields\Tests;

use Filament\Actions\ActionsServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Illuminate\Support\ViewErrorBag;
use Livewire\LivewireServiceProvider;
use MrNewport\LaravelFields\FieldsServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Standalone rendering has the same error bag supplied by Laravel's web middleware.
        view()->share('errors', new ViewErrorBag);
    }

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            SchemasServiceProvider::class,
            FormsServiceProvider::class,
            FieldsServiceProvider::class,
        ];
    }
}
