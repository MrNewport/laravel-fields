<?php

declare(strict_types=1);

namespace MrNewport\LaravelFields\Tests;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\MessageBag;
use MrNewport\LaravelFields\Field;
use MrNewport\LaravelFields\Schema;
use MrNewport\LaravelFields\Type;

final class RenderingTest extends TestCase
{
    public function test_renders_escaped_accessible_fields_with_nested_values_and_validation_errors(): void
    {
        $schema = Schema::make([
            Field::text('profile.name')->label('<script>alert(1)</script>')->required()->help('<b>Help</b>')->group('Your details'),
            Field::select('choice')->options(['0' => '<em>Zero</em>', 'a' => 'Option A']),
            Field::multiSelect('tags')->options(['a' => 'Option A']),
        ]);
        $html = $schema->render(['profile' => ['name' => '"><img src=x onerror=alert(1)>'], 'choice' => '0', 'tags' => ['a']], new MessageBag([
            'profile.name' => ['<b>Invalid</b>'], 'tags.0' => ['Invalid selection'],
        ]), 'review')->render();
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('&lt;img', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('<b>Help</b>', $html);
        self::assertStringContainsString('name="profile[name]"', $html);
        self::assertStringContainsString('aria-invalid="true"', $html);
        self::assertStringContainsString('review-profile-name-help review-profile-name-error', $html);
        self::assertStringContainsString('role="alert"', $html);
        self::assertStringContainsString('Invalid selection', $html);
        self::assertStringContainsString('name="tags[]"', $html);
        $document = new DOMDocument;
        @$document->loadHTML($html);
        $xpath = new DOMXPath($document);
        self::assertSame(1, $xpath->query('//select[@name="choice"]/option[@value="0"][@selected]')->length);
        self::assertSame(1, $xpath->query('//input[@name="profile[name]"][@required]')->length);
    }

    public function test_sensitive_readonly_disabled_and_file_fields_do_not_leak_values(): void
    {
        $schema = Schema::make([
            Field::password('password'), Field::text('secret')->sensitive()->readOnly(),
            Field::text('owner')->readOnly(), Field::text('locked')->disabled(), Field::file('attachment'),
            Field::checkbox('consent'), Field::json('metadata'),
        ]);
        $html = $schema->render([
            'password' => 'PASSWORD_HASH', 'secret' => 'PRIVATE_TOKEN', 'owner' => 'Avery',
            'locked' => 'Display', 'attachment' => 'private/path.pdf', 'consent' => false, 'metadata' => ['a' => 0],
        ])->render();
        self::assertStringNotContainsString('PASSWORD_HASH', $html);
        self::assertStringNotContainsString('PRIVATE_TOKEN', $html);
        self::assertStringNotContainsString('private/path.pdf', $html);
        self::assertStringContainsString('Avery', $html);
        self::assertStringContainsString('disabled', $html);
        self::assertStringContainsString('type="hidden" name="consent" value="0"', $html);
        self::assertStringNotContainsString('cdn.', $html);
        self::assertStringNotContainsString('onclick=', $html);
    }

    public function test_all_native_field_types_render_and_id_prefixes_isolate_forms(): void
    {
        $fields = array_map(fn ($type) => Field::make('field_'.$type->name, $type)->options(['a' => 'Option A']), Type::cases());
        $schema = Schema::make($fields);
        $html = $schema->render([], [], 'one')->render().$schema->render([], [], 'two')->render();
        self::assertStringContainsString('one-field_Text', $html);
        self::assertStringContainsString('two-field_Text', $html);
        self::assertStringContainsString('role="radiogroup"', $html);
        self::assertStringContainsString('inputmode="decimal"', $html);
        self::assertStringContainsString('type="month"', $html);
        self::assertStringContainsString('type="datetime-local"', $html);
    }

    public function test_provider_registers_no_package_routes(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())->map(fn ($route) => $route->uri());
        self::assertFalse($routes->contains(fn ($uri) => str_contains($uri, 'fields')));
    }

    public function test_json_editing_preserves_false_and_raw_invalid_input_without_double_encoding(): void
    {
        $schema = Schema::make([Field::json('metadata')]);
        foreach ([[false, 'false'], [true, 'true']] as [$value, $expected]) {
            $html = $schema->render(['metadata' => $value])->render();
            self::assertStringContainsString('>'.$expected.'</textarea>', $html);
        }
        self::assertStringContainsString('>{bad}</textarea>', $schema->render(['metadata' => '{bad}'])->render());
        self::assertStringContainsString('&quot;hello&quot;</textarea>', $schema->render(['metadata' => '"hello"'])->render());
    }

    public function test_native_json_editing_preserves_float_types_on_unchanged_submission(): void
    {
        $schema = Schema::make([Field::json('metadata')]);
        foreach ([1.0, ['amount' => 1.0, 'nested' => [0.0, 2.0]]] as $value) {
            $html = $schema->render(['metadata' => $value])->render();
            $document = new DOMDocument;
            @$document->loadHTML($html);
            $text = $document->getElementsByTagName('textarea')->item(0)->textContent;
            self::assertSame(['metadata' => $value], $schema->validate(['metadata' => $text]));
        }
        $report = view('laravel-fields::report', [
            'title' => 'Report', 'row' => ['metadata' => ['amount' => 1.0]], 'labels' => ['metadata' => 'Metadata'],
        ])->render();
        self::assertStringContainsString('1.0', $report);
    }
}
