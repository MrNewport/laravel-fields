<?php

declare(strict_types=1);

use MrNewport\LaravelFields\Field;
use MrNewport\LaravelFields\Schema;

// Include from a booted Laravel application. Definitions contain no external side effects.
return [
    'contact' => Schema::make([
        Field::text('contact.name')->label('Full name')->required()->rules(['max:120'])->group('Contact'),
        Field::email('contact.email')->required()->group('Contact'),
        Field::decimal('budget', 2)->rules(['min:0']),
        Field::checkbox('receive_updates')->required(),
        Field::password('password'),
        Field::display('account_number'),
    ]),
    'import' => Schema::make([
        Field::text('reference')->required()->rules(['max:64']),
        Field::email('contact.email')->required(),
        Field::decimal('limit', 2)->required()->rules(['min:0']),
        Field::multiSelect('channels')->options(['email' => 'Email', 'sms' => 'SMS']),
    ]),
    'approval' => Schema::make([
        Field::text('decision_note')->required()->rules(['max:2000']),
        Field::decimal('approved_limit', 2)->required()->rules(['min:0']),
    ]),
];
