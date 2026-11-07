<?php

declare(strict_types=1);

namespace MrNewport\LaravelFields\Tests;

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use MrNewport\LaravelFields\Support\Csv;

final class DocumentationExamplesTest extends TestCase
{
    public function test_contact_recipe_preserves_false_precision_redaction_and_error_redisplay(): void
    {
        $schemas = require __DIR__.'/../examples/schemas.php';
        $schema = $schemas['contact'];
        $input = [
            'contact' => ['name' => 'Avery', 'email' => 'avery@example.test', 'owner_id' => 99],
            'budget' => '125.5', 'receive_updates' => '0', 'password' => 'new secret',
            'account_number' => 'forged',
        ];
        $values = $schema->validate($input);
        self::assertSame('125.50', $values['budget']);
        self::assertFalse($values['receive_updates']);
        self::assertArrayNotHasKey('owner_id', $values['contact']);
        self::assertArrayNotHasKey('account_number', $values);
        self::assertArrayNotHasKey('password', $schema->exportRow($values));
        try {
            $schema->validate(['receive_updates' => '0']);
            self::fail('A missing required contact must fail.');
        } catch (ValidationException $exception) {
            $html = $schema->render(['receive_updates' => false], $exception->errors(), 'contact')->render();
            self::assertStringContainsString('contact-contact-name-error', $html);
            self::assertStringNotContainsString('new secret', $html);
        }
    }

    public function test_reviewed_csv_recipe_produces_normalized_rows_and_rejects_a_bad_later_row(): void
    {
        $schemas = require __DIR__.'/../examples/schemas.php';
        $schema = $schemas['import'];
        $csv = "reference,contact.email,limit,channels\r\nC-101,avery@example.test,125.5,\"[\"\"email\"\"]\"\r\n";
        self::assertSame([
            ['reference' => 'C-101', 'contact' => ['email' => 'avery@example.test'], 'limit' => '125.50', 'channels' => ['email']],
        ], Csv::import($schema, $csv, maxRows: 200, maxBytes: 1_000_000));
        $this->expectException(ValidationException::class);
        Csv::import($schema, $csv."C-102,invalid,1,\r\n");
    }

    public function test_filament_recipe_unwraps_nested_state_and_keeps_false(): void
    {
        $schemas = require __DIR__.'/../examples/schemas.php';
        $schema = $schemas['contact']->only(['contact.name', 'contact.email', 'budget', 'receive_updates']);
        $owner = new class extends Component implements HasSchemas
        {
            use InteractsWithSchemas;

            public array $data = [];

            public function render(): string
            {
                return '<div></div>';
            }
        };
        $form = Schema::make($owner)->components($schema->toFilament())->statePath('data');
        $form->fill(['contact' => ['name' => 'Avery', 'email' => 'avery@example.test'], 'budget' => '12.3', 'receive_updates' => false]);
        $values = $schema->validate($form->getState());
        self::assertSame('12.30', $values['budget']);
        self::assertFalse($values['receive_updates']);
        self::assertArrayNotHasKey('data', $values);
    }
}
