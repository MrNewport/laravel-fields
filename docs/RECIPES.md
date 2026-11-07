# Application recipes

These recipes use server-owned schemas and ordinary Laravel actions. Replace the example model/policy names with your application's own. The executable definitions in [examples/schemas.php](../examples/schemas.php) are exercised by `DocumentationExamplesTest`.

## Account update with exact amounts and password changes

Define the form once:

```php
use Illuminate\Validation\Rules\Password;
use MrNewport\LaravelFields\{Field, Schema};

$schema = Schema::make([
    Field::text('contact.name')->required()->rules(['max:120']),
    Field::email('contact.email')->required(),
    Field::decimal('monthly_limit', 2)->rules(['min:0']),
    Field::checkbox('receive_updates')->required(),
    Field::password('password')->rules([Password::min(12)]),
    Field::display('account_number')->label('Account number'),
]);
```

The GET action authorizes the account and maps saved values explicitly. Use a decimal cast/storage column that returns a string. Do not send the saved password hash to the view. Display-only account numbers may be rendered separately without becoming writable.

```php
$values = [
    'contact' => ['name' => $account->name, 'email' => $account->email],
    'monthly_limit' => $account->monthly_limit,
    'receive_updates' => (bool) $account->receive_updates,
    'account_number' => $account->account_number,
];
```

The POST action resolves the account through an authorized ownership query, then validates and locks before saving:

```php
use Illuminate\Support\Facades\{DB, Gate, Hash};

$data = $schema->validate($request->all());
DB::transaction(function () use ($account, $data) {
    $current = $account->newQuery()->whereKey($account->getKey())
        ->lockForUpdate()->firstOrFail();
    Gate::authorize('update', $current);
    $current->name = $data['contact']['name'];
    $current->email = $data['contact']['email'];
    if (array_key_exists('monthly_limit', $data)) {
        $current->monthly_limit = $data['monthly_limit'];
    }
    $current->receive_updates = $data['receive_updates'];
    if (isset($data['password'])) {
        $current->password = Hash::make($data['password']);
    }
    $current->saveOrFail();
});
```

An absent monthly limit retains the saved value; null explicitly clears it. A false checkbox remains false. An empty optional password becomes null and leaves the hash unchanged. Add an application revision field/check when a stale form must fail rather than overwrite another edit. Reauthorize under the lock if ownership can change. Fields handles none of those domain decisions automatically.

## Private upload with compensation

```php
$schema = Schema::make([
    Field::text('title')->required()->rules(['max:120']),
    Field::file('attachment')->required()->rules(['mimes:pdf', 'max:10240']),
]);
```

Use `enctype="multipart/form-data"`. Resolve the record in its permitted workspace and authorize before accepting storage. The validated attachment is an UploadedFile, not a path:

```php
use Illuminate\Support\Facades\{DB, Gate, Storage};

Gate::authorize('attachEvidence', $record);
$data = $schema->validate($request->all());
$path = $data['attachment']->store('evidence', 'private');
if (! is_string($path)) {
    throw new RuntimeException('Upload storage failed.');
}
try {
    DB::transaction(function () use ($record, $path, $data) {
        $current = $record->newQuery()->whereKey($record->getKey())
            ->lockForUpdate()->firstOrFail();
        Gate::authorize('attachEvidence', $current);
        $current->evidence()->create([
            'title' => $data['title'], 'disk' => 'private', 'storage_key' => $path,
        ]);
    });
} catch (Throwable $exception) {
    Storage::disk('private')->delete($path);
    throw $exception;
}
```

Configure the private disk in the host. Database rollback does not undo filesystem writes, hence compensation. A production application can stage uploads and garbage-collect unreferenced objects after crashes. Serve authorized downloads through the host. Do not hydrate a stored path into the file input. The Filament adapter uses `storeFiles(false)` to preserve the same host storage boundary.

## Review a CSV before writing records

Use a narrow import schema whose options are selected for the authorized workspace:

```php
use MrNewport\LaravelFields\{Field, Schema};
use MrNewport\LaravelFields\Support\Csv;

$schema = Schema::make([
    Field::text('reference')->required()->rules(['max:64']),
    Field::email('contact.email')->required(),
    Field::decimal('limit', 2)->required()->rules(['min:0']),
    Field::multiSelect('channels')->options(['email' => 'Email', 'sms' => 'SMS']),
]);
$request->validate(['csv' => ['required', 'file', 'max:1024']]);
$contents = $request->file('csv')->get();
$rows = Csv::import($schema, $contents, maxRows: 200, maxBytes: 1_000_000);
```

A file such as the following yields a decimal string, nested email and choice list:

```csv
reference,contact.email,limit,channels
C-101,avery@example.test,125.5,"[""email""]"
```

Store the reviewed rows and original file hash in a host-owned preview record bound to the authenticated workspace and actor. Display the preview as escaped text, including normalized values and collisions with existing references. At approval, lock the preview, reauthorize, reject replay or a changed file/revision, and commit rows through the same domain action used by ordinary forms. `Csv::import()` performs all-row validation but does not implement approval, ownership, duplicate resolution or writes. For large inputs, choose explicit limits before accepting the entire string into memory.

Export only authorized records with `Csv::export($schema, $normalizedRows)` and deliver `text/csv` with an attachment filename. Formula prefixes deliberately survive reimport; use an independent canonical backup format for exact restoration.

## Optional scoped workflow action

Native [Flow](https://github.com/MrNewport/laravel-flow) can run without Fields. To validate a native action in your own service, authorize the subject, validate with Fields, then persist the domain change and call `FlowManager::actionStep()` on the same database transaction/connection. Keep replay and audit policy in the host or use Workflow.

With [Laravel Workflow](https://github.com/MrNewport/laravel-workflow), register action validation by definition/version/local step/action:

```php
use MrNewport\LaravelWorkflow\Contracts\ActionValidator;
use MrNewport\LaravelWorkflow\Validation\FieldsActionValidator;

$approval = Schema::make([
    Field::text('decision_note')->required()->rules(['max:2000']),
    Field::decimal('approved_limit', 2)->required()->rules(['min:0']),
]);
$validator = (new FieldsActionValidator)
    ->register('purchase-review', 1, 'review', 'approve', $approval);
$this->app->instance(ActionValidator::class, $validator);
```

Also bind Workflow's host authorization contracts; defaults deny. The full definition must declare the matching step/action. Use the manager on an authorized scoped run:

```php
$updated = $manager->act(
    $run, $step->id, 'approve', $trustedScope, $trustedActor, $requestKey,
    expectedVersion: $run->version,
    payload: $request->only(['decision_note', 'approved_limit']),
    withinTransaction: function ($lockedRun, $step, array $validated) use ($purchase) {
        $current = $purchase->newQuery()->whereKey($purchase->getKey())
            ->lockForUpdate()->firstOrFail();
        $current->approved_limit = $validated['approved_limit'];
        $current->decision_note = $validated['decision_note'];
        $current->saveOrFail();
    },
);
```

Workflow supplies only schema-validated values to the callback. Its finite-JSON payload contract refuses UploadedFile objects before validation. Stage authorized uploads separately and submit an approved reference string. Its callbacks can retry on deadlock, so perform only retry-safe database work and use a durable host outbox for mail/API delivery. See Workflow's handbook for its complete definition, policy, replay and history contracts; adding Fields does not grant permissions.

## Report from the same definitions

Create a report schema with only fields the reader is permitted to see. `exportable(false)` can suppress an internal reference while display fields still render in the form. Use normalized/saved values with `exportRow()`, CSV or the optional PDF adapter. Sensitive and file fields are always omitted, but a public label is not a record-authorization rule. Return PDFs after policy checks and choose private storage/download lifetime in the host.
