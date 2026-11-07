<?php

declare(strict_types=1);

namespace MrNewport\LaravelFields\Tests;

use MrNewport\LaravelDocSign\Services\Pdf\DomPdfRenderer;
use MrNewport\LaravelDocSign\Services\Pdf\PdfRendererInterface;
use MrNewport\LaravelFields\Adapters\Pdf;
use MrNewport\LaravelFields\Field;
use MrNewport\LaravelFields\Schema;

final class PdfTest extends TestCase
{
    public function test_exports_actual_pdf_through_the_published_docsign_renderer(): void
    {
        $pdf = (new Pdf(new DomPdfRenderer))->export(
            Schema::make([Field::text('name')]), ['name' => 'Avery River'], 'Review report',
        );
        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertGreaterThan(1000, strlen($pdf));
    }

    public function test_pdf_redacts_sensitive_fields_files_and_escapes_untrusted_html(): void
    {
        $renderer = new class implements PdfRendererInterface
        {
            public string $html;

            public function renderPdf(string $html): string
            {
                $this->html = $html;

                return 'rendered';
            }
        };
        $result = (new Pdf($renderer))->export(Schema::make([
            Field::text('name'), Field::password('password'), Field::file('attachment'),
        ]), ['name' => '<img src="https://example.test/track">', 'password' => 'SECRET', 'attachment' => 'private/file'], '<script>title</script>');
        self::assertSame('rendered', $result);
        self::assertStringNotContainsString('SECRET', $renderer->html);
        self::assertStringNotContainsString('private/file', $renderer->html);
        self::assertStringNotContainsString('<img ', $renderer->html);
        self::assertStringNotContainsString('<script>', $renderer->html);
        self::assertStringContainsString('&lt;img', $renderer->html);
    }
}
