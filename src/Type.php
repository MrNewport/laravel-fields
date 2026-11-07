<?php

declare(strict_types=1);

namespace MrNewport\LaravelFields;

enum Type: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Email = 'email';
    case Url = 'url';
    case Phone = 'tel';
    case Password = 'password';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Date = 'date';
    case DateTime = 'datetime-local';
    case Month = 'month';
    case Time = 'time';
    case Select = 'select';
    case MultiSelect = 'multiselect';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case Json = 'json';
    case File = 'file';
    case Hidden = 'hidden';
    case Display = 'display';
}
