<?php

declare(strict_types=1);

namespace MrNewport\LaravelFields\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use MrNewport\LaravelFields\Field;
use MrNewport\LaravelFields\Schema;
use MrNewport\LaravelFields\Type;
use PHPUnit\Framework\Attributes\DataProvider;

final class SchemaTest extends TestCase
{
    public function test_definitions_are_immutable_and_schemas_reject_duplicates_overlaps_and_unsafe_paths(): void
    {
        $original = Field::text('title');
        $changed = $original->label('Subject')->required()->help('Please explain')->group('Details');
        self::assertSame('Title', $original->getLabel());
        self::assertFalse($original->isRequired());
        self::assertSame('Subject', $changed->getLabel());
        self::assertCount(1, Schema::make([$changed])->group('Details'));
        self::assertCount(0, Schema::make([$changed])->only(['other']));
        foreach ([['a', 'a'], ['a', 'a.b'], ['a.b', 'a']] as $names) {
            try {
                Schema::make(array_map(Field::text(...), $names));
                self::fail('Overlapping names accepted.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
        foreach (['*', 'user..name', 'name[]', '<script>', 'x.*.name', '.x', ''] as $name) {
            try {
                Field::text($name);
                self::fail('Unsafe name accepted.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_server_validation_whitelists_nested_paths_and_excludes_noneditable_values(): void
    {
        $schema = Schema::make([
            Field::text('profile.name')->required(),
            Field::integer('age'),
            Field::text('owner_id')->readOnly(),
            Field::text('role')->disabled(),
            Field::hidden('token')->rules(['in:known']),
        ]);
        self::assertSame(['profile' => ['name' => 'Avery'], 'age' => 0, 'token' => 'known'], $schema->validate([
            'profile' => ['name' => 'Avery', 'is_admin' => true], 'age' => '0', 'role' => 'admin',
            'owner_id' => 999, 'token' => 'known', 'unexpected' => 'ignored',
        ]));
        self::assertSame('profile[name]', $schema->fields()[0]->inputName());
    }

    public function test_required_fields_cannot_be_bypassed_with_client_display_flags(): void
    {
        $this->expectException(ValidationException::class);
        Schema::make([Field::email('email')->required()])->validate(['disabled_tabs' => ['email'], 'showIf' => false]);
    }

    public function test_conditional_rules_execute_on_the_server(): void
    {
        $schema = Schema::make([Field::text('reason')->rules([Rule::requiredIf(true)])]);
        $this->expectException(ValidationException::class);
        $schema->validate([]);
    }

    public function test_optional_absence_does_not_overwrite_saved_values_with_defaults(): void
    {
        $schema = Schema::make([Field::text('name')->default('Display only'), Field::json('data'), Field::decimal('amount')]);
        self::assertSame([], $schema->validate([]));
        self::assertSame(['name' => null, 'data' => null, 'amount' => null], $schema->validate(['name' => '', 'data' => '', 'amount' => '']));
    }

    #[DataProvider('validValues')]
    public function test_type_validation_and_normalization(Type $type, mixed $value, mixed $expected): void
    {
        $field = Field::make('value', $type)->options(['a' => 'Option A', '0' => 'Zero']);
        self::assertSame(['value' => $expected], Schema::make([$field])->validate(['value' => $value]));
    }

    public static function validValues(): iterable
    {
        yield 'text preserves whitespace' => [Type::Text, ' hello ', ' hello '];
        yield 'textarea' => [Type::Textarea, "line\nline", "line\nline"];
        yield 'email' => [Type::Email, 'avery@example.test', 'avery@example.test'];
        yield 'url' => [Type::Url, 'https://example.test/path', 'https://example.test/path'];
        yield 'international phone without implicit country' => [Type::Phone, '+44 20 7946 0958', '+44 20 7946 0958'];
        yield 'password remains raw for host hasher' => [Type::Password, 'secret', 'secret'];
        yield 'integer zero' => [Type::Integer, '0', 0];
        yield 'integer maximum' => [Type::Integer, (string) PHP_INT_MAX, PHP_INT_MAX];
        yield 'integer negative' => [Type::Integer, '-123', -123];
        yield 'decimal no float conversion' => [Type::Decimal, '999999999999999999999999.12', '999999999999999999999999.12'];
        yield 'decimal padding' => [Type::Decimal, '-13.1', '-13.10'];
        yield 'decimal zero' => [Type::Decimal, '-0.00', '0.00'];
        yield 'leap date' => [Type::Date, '2024-02-29', '2024-02-29'];
        yield 'local datetime explicit' => [Type::DateTime, '2026-11-06T14:30', '2026-11-06T14:30'];
        yield 'month' => [Type::Month, '2026-11', '2026-11'];
        yield 'time' => [Type::Time, '00:00', '00:00'];
        yield 'select zero' => [Type::Select, '0', '0'];
        yield 'radio' => [Type::Radio, 'a', 'a'];
        yield 'multiselect' => [Type::MultiSelect, ['0', 'a'], ['0', 'a']];
        yield 'checkbox false' => [Type::Checkbox, '0', false];
        yield 'checkbox true' => [Type::Checkbox, '1', true];
        yield 'json false' => [Type::Json, 'false', false];
        yield 'json scalar zero' => [Type::Json, '0', 0];
        yield 'json nested object' => [Type::Json, '{"a":0,"b":false}', ['a' => 0, 'b' => false]];
        yield 'json large integer' => [Type::Json, '{"id":999999999999999999999999}', ['id' => '999999999999999999999999']];
    }

    #[DataProvider('invalidValues')]
    public function test_rejects_malformed_values_instead_of_silently_truncating_or_clamping(Type $type, mixed $value): void
    {
        $this->expectException(ValidationException::class);
        Schema::make([Field::make('value', $type)->options(['a' => 'Option A'])])->validate(['value' => $value]);
    }

    public static function invalidValues(): iterable
    {
        yield [Type::Text, ['admin' => true]];
        yield [Type::Email, 'not-an-email'];
        yield [Type::Url, 'javascript:alert(1)'];
        yield [Type::Url, 'file:///private/example'];
        yield [Type::Integer, '3.5'];
        yield [Type::Integer, '9223372036854775808'];
        yield [Type::Decimal, 12.34];
        yield [Type::Decimal, '$1,000.00'];
        yield [Type::Decimal, '1.234'];
        yield [Type::Decimal, '1e3'];
        yield [Type::Decimal, '01.00'];
        yield [Type::Date, '2025-02-29'];
        yield [Type::DateTime, '2026-11-06T14:30:00Z'];
        yield [Type::Month, '2026-13'];
        yield [Type::Time, '24:00'];
        yield [Type::Select, 'A'];
        yield [Type::Select, 'missing'];
        yield [Type::MultiSelect, ['a', 'missing']];
        yield [Type::MultiSelect, ['a', 'a']];
        yield [Type::MultiSelect, ['key' => 'a']];
        yield [Type::Checkbox, 'yes'];
        yield [Type::Json, '{bad}'];
        yield [Type::File, '../private.txt'];
    }

    public function test_arbitrary_decimal_scale_and_zero_bounds(): void
    {
        $field = Field::decimal('precise', 20);
        self::assertSame(['precise' => '0.00000000000000000001'], Schema::make([$field])->validate(['precise' => '0.00000000000000000001']));
        self::assertSame(['precise' => '1'], Schema::make([Field::decimal('precise', 0)])->validate(['precise' => '1']));
        $this->expectException(ValidationException::class);
        Schema::make([Field::integer('count')->rules(['min:0', 'max:5'])])->validate(['count' => '-1']);
    }

    public function test_file_validation_has_no_storage_side_effects(): void
    {
        $file = UploadedFile::fake()->create('evidence.pdf', 10, 'application/pdf');
        self::assertSame($file, Schema::make([Field::file('attachment')->rules(['mimes:pdf', 'max:20'])])->validate(['attachment' => $file])['attachment']);
        $this->expectException(ValidationException::class);
        Schema::make([Field::file('attachment')->rules(['max:1'])])->validate(['attachment' => $file]);
    }

    public function test_conflicting_array_rule_cannot_smuggle_unregistered_nested_data(): void
    {
        $this->expectException(ValidationException::class);
        Schema::make([Field::text('profile')->rules(['array'])])->validate(['profile' => ['is_admin' => true]]);
    }

    public function test_metadata_and_exports_never_contain_values_secrets_or_executable_rules(): void
    {
        $schema = Schema::make([
            Field::text('name')->default('private default'),
            Field::password('password')->sensitive(false),
            Field::text('secret')->default('never leak')->sensitive(),
            Field::file('attachment'),
            Field::text('internal')->exportable(false),
        ]);
        self::assertSame(['name' => 'Avery'], $schema->exportRow(['name' => 'Avery', 'password' => 'hash', 'secret' => 'token', 'attachment' => 'private/path', 'internal' => 'x']));
        $json = json_encode($schema, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('private default', $json);
        self::assertStringNotContainsString('never leak', $json);
        self::assertStringNotContainsString('closure', strtolower($json));
        self::assertTrue($schema->fields()[1]->isSensitive());
        $this->expectException(InvalidArgumentException::class);
        Field::password('password')->default('secret');
    }
}
