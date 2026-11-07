<?php

declare(strict_types=1);

namespace MrNewport\LaravelFields\Tests;

use Closure;
use DOMDocument;
use DOMXPath;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use MrNewport\LaravelFields\Field;
use MrNewport\LaravelFields\Schema;
use MrNewport\LaravelFields\Type;

final class AdversarialRegressionTest extends TestCase
{
    private function form(Schema $schema): \Filament\Schemas\Schema
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

        return \Filament\Schemas\Schema::make($owner)->components($schema->toFilament())->statePath('data');
    }

    public function test_oversized_multiselect_has_one_bounded_validation_error_in_core_and_filament(): void
    {
        $schema = Schema::make([Field::multiSelect('tags')->options(['a' => 'A'])]);
        $data = ['tags' => array_fill(0, 32000, 'a')];
        $form = $this->form($schema);
        $form->fill($data);
        foreach ([fn () => $schema->validate($data), fn () => $form->getState()] as $validate) {
            try {
                $validate();
                self::fail('An oversized list must fail validation.');
            } catch (ValidationException $exception) {
                self::assertCount(1, $exception->errors());
                self::assertCount(1, array_values($exception->errors())[0]);
            }
        }
    }

    public function test_choices_preserve_the_maximum_valid_list_and_reject_wrong_types_duplicates_and_case(): void
    {
        $choices = array_map(fn (int $id): string => 'choice-'.$id, range(1, 1000));
        $schema = Schema::make([Field::multiSelect('tags')->options(array_combine($choices, $choices))]);
        self::assertSame(['tags' => $choices], $schema->validate(['tags' => $choices]));
        foreach ([['choice-1', 'choice-1'], ['CHOICE-1'], [1], ['key' => 'choice-1']] as $invalid) {
            try {
                $schema->validate(['tags' => $invalid]);
                self::fail('Invalid choice list accepted.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('tags', $exception->errors());
            }
        }
        $form = $this->form($schema);
        foreach ([['choice-1', 'choice-1'], ['CHOICE-1']] as $invalid) {
            $form->fill(['tags' => $invalid]);
            try {
                $form->getState();
                self::fail('Filament accepted invalid choice membership.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('data.tags', $exception->errors());
            }
        }
    }

    public function test_filament_resolves_nested_dependent_and_data_aware_rules_against_the_complete_form(): void
    {
        $dataRule = new class implements DataAwareRule, ValidationRule
        {
            private array $data = [];

            public function setData(array $data): static
            {
                $this->data = $data;

                return $this;
            }

            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                if ($value !== ($this->data['account']['first'] ?? null)) {
                    $fail('Sibling does not match.');
                }
            }
        };
        $schema = Schema::make([
            Field::text('account.first')->required(),
            Field::text('account.second')->rules(['same:account.first', $dataRule]),
            Field::text('reason')->rules(['required_if:account.first,yes']),
        ]);
        $form = $this->form($schema);
        $form->fill(['account' => ['first' => 'same', 'second' => 'same']]);
        self::assertSame(['account' => ['first' => 'same', 'second' => 'same'], 'reason' => null], $form->getState());
        $form->fill(['account' => ['first' => 'yes', 'second' => 'yes']]);
        try {
            $form->getState();
            self::fail('Conditional presence rule did not run.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('data.reason', $exception->errors());
        }
        $form->fill(['account' => ['first' => 'same', 'second' => 'different']]);
        $this->expectException(ValidationException::class);
        $form->getState();
    }

    public function test_required_checkbox_accepts_false_without_native_required_but_still_requires_server_presence(): void
    {
        $schema = Schema::make([Field::checkbox('active')->required()]);
        $form = $this->form($schema);
        $form->fill(['active' => false]);
        self::assertSame(['active' => false], $schema->validate($form->getState()));
        $html = $form->toHtml();
        $document = new DOMDocument;
        @$document->loadHTML($html);
        $xpath = new DOMXPath($document);
        self::assertSame(1, $xpath->query('//input[@type="checkbox"]')->length);
        self::assertSame(0, $xpath->query('//input[@type="checkbox"][@required]')->length);
        $this->expectException(ValidationException::class);
        $schema->validate([]);
    }

    public function test_checkbox_acceptance_is_enforced_by_filament_validation(): void
    {
        $schema = Schema::make([Field::checkbox('consent')->rules(['accepted'])]);
        $form = $this->form($schema);
        $form->fill(['consent' => false]);
        $this->expectException(ValidationException::class);
        $form->getState();
    }

    public function test_invalid_utf8_arrays_and_recursive_arrays_can_be_redisplayed_after_validation_failure(): void
    {
        $schema = Schema::make([Field::text('name'), Field::json('metadata')]);
        $recursive = [];
        $recursive['recursive'] = &$recursive;
        $html = $schema->render(['name' => ['invalid' => "\xC3\x28"], 'metadata' => $recursive], ['name' => ['Please enter a name.']])->render();
        self::assertStringContainsString('Please enter a name.', $html);
        self::assertStringContainsString('textarea', $html);
        self::assertStringContainsString('null', $html);
    }

    public function test_year_zero_is_rejected_and_native_year_boundaries_round_trip(): void
    {
        foreach ([Field::date('value'), Field::month('value'), Field::dateTime('value')] as $field) {
            $suffix = match ($field->type) {
                Type::Month => '-01',
                Type::DateTime => '-01-01T00:00',
                default => '-01-01',
            };
            $schema = Schema::make([$field]);
            foreach (['0001', '9999'] as $year) {
                self::assertSame(['value' => $year.$suffix], $schema->validate(['value' => $year.$suffix]));
            }
            try {
                $schema->validate(['value' => '0000'.$suffix]);
                self::fail('Year zero must not pass temporal validation.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('value', $exception->errors());
            }
        }
    }

    public function test_builtin_text_uses_translations_in_form_validation_and_report(): void
    {
        app('translator')->addLines([
            'messages.choose_option' => 'Choisissez une option',
            'messages.choices' => 'Choix non valides pour :attribute.',
            'messages.yes' => 'Oui',
            'messages.no' => 'Non',
        ], 'fr', 'laravel-fields');
        app()->setLocale('fr');
        $schema = Schema::make([Field::select('choice')->options(['a' => 'A'])]);
        self::assertStringContainsString('Choisissez une option', $schema->render()->render());
        $html = view('laravel-fields::report', ['title' => 'Rapport', 'row' => ['active' => false], 'labels' => ['active' => 'Actif']])->render();
        self::assertStringContainsString('lang="fr"', $html);
        self::assertStringContainsString('Non', $html);
        try {
            Schema::make([Field::multiSelect('choices')->options(['a' => 'A'])])->validate(['choices' => ['a', 'a']]);
            self::fail('Duplicate choices accepted.');
        } catch (ValidationException $exception) {
            self::assertSame(['Choix non valides pour choices.'], $exception->errors()['choices']);
        }
    }
}
