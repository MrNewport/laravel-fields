<?php

declare(strict_types=1);

namespace MrNewport\LaravelFields;

use Illuminate\Support\ServiceProvider;

final class FieldsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-fields');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'laravel-fields');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/laravel-fields'),
            ], 'laravel-fields-views');
            $this->publishes([
                __DIR__.'/../lang' => lang_path('vendor/laravel-fields'),
            ], 'laravel-fields-translations');
        }
    }
}
