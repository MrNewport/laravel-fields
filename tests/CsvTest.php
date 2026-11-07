<?php

declare(strict_types=1);

namespace MrNewport\LaravelFields\Tests;

use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use MrNewport\LaravelFields\Field;
use MrNewport\LaravelFields\Schema;
use MrNewport\LaravelFields\Support\Csv;

final class CsvTest extends TestCase
{
    public function test_exports_escaped_formulas_and_omits_secrets_and_files(): void
    {
        $schema = Schema::make([Field::text('name'), Field::password('password'), Field::file('file'), Field::text('hidden')->sensitive()]);
        foreach (['=SUM(1,2)', '+1', '-1', '@SUM(1)', "\t=1", '  =1', "\n=1"] as $value) {
            $csv = Csv::export($schema, [['name' => $value, 'password' => 'hash', 'file' => 'private/file', 'hidden' => 'token']]);
            $stream = fopen('php://memory', 'w+');
            fwrite($stream, $csv);
            rewind($stream);
            self::assertSame(['name'], fgetcsv($stream, null, ',', '"', ''));
            self::assertSame(["'".$value], fgetcsv($stream, null, ',', '"', ''));
            fclose($stream);
            self::assertStringNotContainsString('hash', $csv);
            self::assertStringNotContainsString('private/file', $csv);
            self::assertStringNotContainsString('token', $csv);
        }
    }

    public function test_csv_import_validates_every_row_and_preserves_exact_values(): void
    {
        $schema = Schema::make([Field::text('profile.name')->required(), Field::decimal('amount')->required(), Field::checkbox('active'), Field::multiSelect('tags')->options(['a' => 'A'])]);
        $rows = Csv::import($schema, "\xEF\xBB\xBFprofile.name,amount,active,tags\r\n\"Avery\nRiver\",99999999999999999.12,0,\"[\"\"a\"\"]\"\r\n");
        self::assertSame([['profile' => ['name' => "Avery\nRiver"], 'amount' => '99999999999999999.12', 'active' => false, 'tags' => ['a']]], $rows);
        $this->expectException(ValidationException::class);
        Csv::import($schema, "profile.name,amount\r\nValid,12.34\r\nInvalid,not-a-number\r\n");
    }

    public function test_import_rejects_unknown_duplicate_sensitive_headers_and_limits(): void
    {
        $schema = Schema::make([Field::text('name'), Field::password('password')]);
        foreach ([['bad\nvalue', 10, 100], ["name,name\nA,B", 10, 100], ["password\nhash", 10, 100], ["name\nA\nB", 1, 100], ["name\nA,B", 10, 100], ["name\nA", 10, 2], ['', 10, 100], ["\n", 10, 100]] as [$csv, $rows, $bytes]) {
            try {
                Csv::import($schema, $csv, $rows, $bytes);
                self::fail('Invalid CSV accepted.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }
}
