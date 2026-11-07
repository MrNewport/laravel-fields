<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: sans-serif; color: #172033; font-size: 11pt; line-height: 1.5; }
        h1 { font-size: 21pt; margin-bottom: 24pt; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { padding: 10pt; border-bottom: 1pt solid #d7dde6; text-align: left; vertical-align: top; word-wrap: break-word; }
        th { width: 30%; background: #f5f7fa; font-weight: bold; }
        td { white-space: pre-wrap; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <table>
        @foreach ($row as $name => $value)
            @php
                $text = is_array($value) ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)
                    : (is_bool($value) ? ($value ? __('laravel-fields::messages.yes') : __('laravel-fields::messages.no')) : ((is_scalar($value) || $value instanceof \Stringable) ? (string) $value : ''));
            @endphp
            <tr><th>{{ $labels[$name] }}</th><td>{{ $text }}</td></tr>
        @endforeach
    </table>
</body>
</html>
