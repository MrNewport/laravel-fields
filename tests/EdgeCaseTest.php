<?php

declare(strict_types=1);

namespace MrNewport\LaravelFields\Tests;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use MrNewport\LaravelFields\Field;
use MrNewport\LaravelFields\Schema;
use MrNewport\LaravelFields\Support\Csv;

final class EdgeCaseTest extends TestCase
{
    public function test_decimal_bounds_compare_exact_numeric_values_including_large_fractions(): void
    {
        $schema = Schema::make([Field::decimal('value', 20)->rules(['min:99999999999999999999.00000000000000000001', 'max:99999999999999999999.00000000000000000002'])]);
        self::assertSame('99999999999999999999.00000000000000000001', $schema->validate(['value' => '99999999999999999999.00000000000000000001'])['value']);
        foreach (['99999999999999999999.00000000000000000000', '99999999999999999999.00000000000000000003'] as $value) {
            try {
                $schema->validate(['value' => $value]);
                self::fail('Out-of-range exact value accepted.');
            } catch (ValidationException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_json_rejects_nontext_and_nonfinite_numbers_with_validation_errors(): void
    {
        $schema = Schema::make([Field::json('value')]);
        foreach ([123, true, 1.5, '1e400', '{"nested":[-1e400]}'] as $value) {
            try {
                $schema->validate(['value' => $value]);
                self::fail('Invalid JSON input accepted.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('value', $exception->errors());
            }
        }
    }

    public function test_csv_round_trips_json_scalars_as_json_text(): void
    {
        $schema = Schema::make([Field::json('value')]);
        $values = [false, true, 'hello', ['nested' => false], 0, 1.5, null];
        foreach ($values as $value) {
            self::assertSame([['value' => $value]], Csv::import($schema, Csv::export($schema, [['value' => $value]])));
        }
    }

    public function test_optional_blank_csv_multiselect_is_nullable_and_invalid_json_is_a_validation_error(): void
    {
        $schema = Schema::make([Field::text('name'), Field::multiSelect('tags')->options(['a' => 'A'])]);
        self::assertSame([['name' => 'Avery', 'tags' => null]], Csv::import($schema, "name,tags\r\nAvery,\r\n"));
        $this->expectException(ValidationException::class);
        Csv::import($schema, "name,tags\r\nAvery,not-json\r\n");
    }

    public function test_default_values_are_detached_from_external_array_references(): void
    {
        $text = 'Original';
        $input = ['value' => &$text];
        $field = Field::json('value')->default($input);
        $text = 'Changed';
        self::assertSame(['value' => 'Original'], $field->getDefault());
    }

    public function test_nested_object_defaults_cannot_introduce_mutable_state(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Field::json('value')->default(['value' => new \stdClass]);
    }

    public function test_option_labels_are_detached_from_external_references(): void
    {
        $label = 'Original';
        $field = Field::select('value')->options(['id' => &$label]);
        $label = 'Changed';
        self::assertSame(['id' => 'Original'], $field->getOptions());
    }

    public function test_rendering_handles_invalid_submitted_choice_shapes_without_php_errors(): void
    {
        $schema = Schema::make([
            Field::select('single')->options(['a' => 'A']),
            Field::multiSelect('multiple')->options(['a' => 'A']),
            Field::radio('radio')->options(['a' => 'A']),
        ]);
        foreach ([['a'], ['nested' => ['a']]] as $single) {
            $html = $schema->render(['single' => $single, 'multiple' => [['a']], 'radio' => ['a']])->render();
            self::assertStringNotContainsString('selected', $html);
            self::assertStringNotContainsString('checked', $html);
        }
    }

    public function test_display_and_report_values_are_escaped_even_for_html_string_objects(): void
    {
        $schema = Schema::make([Field::display('value')]);
        $html = $schema->render(['value' => new HtmlString('<img src=x onerror=alert(1)>')])->render();
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img', $html);
        $report = view('laravel-fields::report', ['title' => 'Report', 'row' => ['value' => new HtmlString('<img src=x>')], 'labels' => ['value' => 'Value']])->render();
        self::assertStringNotContainsString('<img', $report);
        self::assertStringContainsString('&lt;img', $report);
    }

    public function test_native_multiple_select_submits_an_explicit_empty_value_when_cleared(): void
    {
        $schema = Schema::make([Field::multiSelect('profile.tags')->options(['a' => 'A'])]);
        $html = $schema->render(['profile' => ['tags' => ['a']]])->render();
        self::assertStringContainsString('type="hidden" name="profile[tags]" value=""', $html);
        self::assertSame(['profile' => ['tags' => null]], $schema->validate(['profile' => ['tags' => '']]));
    }

    public function test_explicit_empty_csv_records_are_preserved_alongside_skipped_blank_lines(): void
    {
        $schema = Schema::make([Field::text('value')]);
        $csv = Csv::export($schema, [['value' => null]]);
        self::assertSame([['value' => null]], Csv::import($schema, $csv));
        self::assertSame([['value' => null]], Csv::import($schema, $csv."\r\n"));
    }

    public function test_schema_reuse_evaluates_dynamic_rules_for_each_validation(): void
    {
        $required = false;
        $rule = Rule::requiredIf(function () use (&$required): bool {
            return $required;
        });
        $schema = Schema::make([Field::text('value')->rules([$rule])]);
        self::assertSame([], $schema->validate([]));
        $required = true;
        try {
            $schema->validate([]);
            self::fail('Cached schema rules ignored the current condition.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }
        $required = false;
        self::assertSame([], $schema->validate([]));
    }

    public function test_data_aware_rules_receive_fresh_input_for_each_validation(): void
    {
        $rule = new class implements DataAwareRule, ValidationRule
        {
            private array $data = [];

            public function setData(array $data): static
            {
                $this->data = $data;

                return $this;
            }

            public function validate(string $attribute, mixed $value, \Closure $fail): void
            {
                if ($value !== ($this->data['expected'] ?? null)) {
                    $fail('The value must match the current expected input.');
                }
            }
        };
        $schema = Schema::make([Field::text('value')->rules([$rule])]);
        self::assertSame(['value' => 'first'], $schema->validate(['value' => 'first', 'expected' => 'first']));
        self::assertSame(['value' => 'second'], $schema->validate(['value' => 'second', 'expected' => 'second']));
        $this->expectException(ValidationException::class);
        $schema->validate(['value' => 'second', 'expected' => 'first']);
    }

    public function test_large_path_validation_preserves_order_and_detects_ancestors_in_either_order(): void
    {
        $fields = [];
        for ($i = 999; $i >= 0; $i--) {
            $fields[] = Field::text('section_'.$i.'.name');
        }
        $schema = Schema::make($fields);
        self::assertSame('section_999.name', $schema->fields()[0]->name);
        self::assertSame(['section_999.name', 'section_0.name'], array_map(fn (Field $field): string => $field->name, $schema->only(['section_0.name', 'section_999.name'])->fields()));
        foreach (['section_0', 'section_999', 'section_500.name.more'] as $overlap) {
            foreach ([[...$fields, Field::text($overlap)], [Field::text($overlap), ...$fields]] as $conflicting) {
                try {
                    Schema::make($conflicting);
                    self::fail('Conflicting paths accepted.');
                } catch (InvalidArgumentException) {
                    self::assertTrue(true);
                }
            }
        }
    }

    public function test_recursive_default_arrays_are_rejected(): void
    {
        $value = [];
        $value['recursive'] = &$value;
        $this->expectException(InvalidArgumentException::class);
        Field::json('value')->default($value);
    }

    public function test_radio_groups_and_display_values_have_valid_label_relationships(): void
    {
        $html = Schema::make([Field::radio('choice')->options(['a' => 'A'])->required(), Field::display('summary')])->render()->render();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        self::assertSame(0, $xpath->query('//label[@for="fields-choice"]|//label[@for="fields-summary"]')->length);
        self::assertSame(1, $xpath->query('//*[@role="radiogroup"][@aria-labelledby="fields-choice-label"][@aria-required="true"]')->length);
        self::assertSame(1, $xpath->query('//p[@aria-labelledby="fields-summary-label"]')->length);
    }

    public function test_literal_dotted_input_keys_cannot_overwrite_registered_nested_values(): void
    {
        $schema = Schema::make([Field::text('profile.name')]);
        self::assertSame([], $schema->validate(['profile.name' => '']));
        self::assertSame([], $schema->validate(['profile.name' => 'Unregistered']));
        $values = ['profile' => ['name' => 'Avery'], 'profile.name' => ''];
        self::assertSame(['profile' => ['name' => 'Avery']], $schema->validate($values));
        self::assertSame(['profile.name' => 'Avery'], $schema->exportRow($values));
        self::assertStringContainsString('value="Avery"', $schema->render($values)->render());
    }
}
