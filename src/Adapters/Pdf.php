<?php

declare(strict_types=1);

namespace MrNewport\LaravelFields\Adapters;

use MrNewport\LaravelDocSign\Services\Pdf\PdfRendererInterface;
use MrNewport\LaravelFields\Schema;

/** Reuses the maintained document package; storage and authorization remain with the host. */
final class Pdf
{
    public function __construct(private readonly PdfRendererInterface $renderer) {}

    public function export(Schema $schema, array $values, ?string $title = null): string
    {
        $labels = [];
        foreach ($schema->fields() as $field) {
            $labels[$field->name] = $field->getLabel();
        }
        $html = view('laravel-fields::report', [
            'title' => $title ?? __('laravel-fields::messages.report_title'), 'row' => $schema->exportRow($values), 'labels' => $labels,
        ])->render();

        return $this->renderer->renderPdf($html);
    }
}
