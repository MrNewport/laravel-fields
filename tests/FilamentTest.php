<?php

declare(strict_types=1);

namespace MrNewport\LaravelFields\Tests;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use MrNewport\LaravelFields\Field;
use MrNewport\LaravelFields\Schema;

final class FilamentTest extends TestCase
{
    public function test_filament_json_hydration_preserves_scalar_and_nested_float_types(): void
    {
        $owner = new class extends Component implements HasSchemas
        {
            use InteractsWithSchemas;

            public array $data = [];

            public function render(): string
            {
                return '<div></div>';
            }
        };
        $schema = Schema::make([Field::json('metadata')]);
        foreach ([1.0, ['amount' => 1.0, 'nested' => [0.0, 2.0]]] as $value) {
            $form = \Filament\Schemas\Schema::make($owner)->components($schema->toFilament())->statePath('data');
            $form->fill(['metadata' => $value]);
            self::assertSame(['metadata' => $value], $schema->validate($form->getState()));
        }
    }

    public function test_application_closure_rules_run_as_validators_inside_filament(): void
    {
        $owner = new class extends Component implements HasSchemas
        {
            use InteractsWithSchemas;

            public array $data = [];

            public function render(): string
            {
                return '<div></div>';
            }
        };
        $schema = Schema::make([Field::text('code')->rules([
            static function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== 'accepted') {
                    $fail('Please enter the accepted code.');
                }
            },
        ])]);
        $form = \Filament\Schemas\Schema::make($owner)->components($schema->toFilament())->statePath('data');
        $form->fill(['code' => 'accepted']);
        self::assertSame(['code' => 'accepted'], $form->getState());
        $owner->data['code'] = 'other';
        $this->expectException(ValidationException::class);
        $form->getState();
    }

    public function test_real_filament_schema_hydrates_and_validates_with_the_same_final_contract(): void
    {
        $owner = new class extends Component implements HasSchemas
        {
            use InteractsWithSchemas;

            public array $data = [];

            public function render(): string
            {
                return '<div></div>';
            }
        };
        $schema = Schema::make([
            Field::text('name')->required(),
            Field::decimal('amount', 4),
            Field::multiSelect('tags')->options(['a' => 'Option A']),
            Field::password('password'),
            Field::text('owner')->readOnly(),
            Field::json('metadata'),
        ]);
        $form = \Filament\Schemas\Schema::make($owner)->components($schema->toFilament())->statePath('data');
        $form->fill(['name' => 'Avery', 'amount' => '999999999999999.1234', 'tags' => ['a'], 'password' => 'STORED_HASH', 'owner' => 'Trusted', 'metadata' => ['active' => false]]);
        self::assertNull($owner->data['password']);
        $owner->data['owner'] = 'Tampered';
        $state = $form->getState();
        self::assertArrayNotHasKey('owner', $state);
        self::assertSame('999999999999999.1234', $state['amount']);
        self::assertSame(['name' => 'Avery', 'amount' => '999999999999999.1234', 'tags' => ['a'], 'password' => null, 'metadata' => ['active' => false]], $schema->validate($state));
    }

    public function test_creates_real_filament_five_components_with_validation_and_safe_dehydration(): void
    {
        $components = Schema::make([
            Field::text('name')->label('Your name')->required()->help('As shown on your account'),
            Field::decimal('amount', 8), Field::select('choice')->options(['a' => 'A']),
            Field::multiSelect('tags')->options(['a' => 'A']), Field::checkbox('active'),
            Field::textarea('notes'), Field::file('attachment'), Field::hidden('token'),
            Field::radio('outcome')->options(['yes' => 'Yes']), Field::password('password'),
            Field::text('owner')->readOnly(), Field::text('disabled')->disabled(),
        ])->toFilament();
        self::assertInstanceOf(TextInput::class, $components[0]);
        self::assertSame('Your name', $components[0]->getLabel());
        self::assertTrue($components[0]->isRequired());
        self::assertSame('text', $components[1]->getType());
        self::assertInstanceOf(Select::class, $components[2]);
        self::assertSame(['a' => 'A'], $components[2]->getOptions());
        self::assertTrue($components[3]->isMultiple());
        self::assertInstanceOf(Checkbox::class, $components[4]);
        self::assertInstanceOf(Textarea::class, $components[5]);
        self::assertInstanceOf(FileUpload::class, $components[6]);
        self::assertFalse($components[6]->shouldStoreFiles());
        self::assertInstanceOf(Hidden::class, $components[7]);
        self::assertInstanceOf(Radio::class, $components[8]);
        self::assertSame('password', $components[9]->getType());
        self::assertFalse($components[10]->isDehydrated());
        self::assertFalse($components[11]->isDehydrated());
        self::assertTrue($components[10]->isDisabled());
    }
}
