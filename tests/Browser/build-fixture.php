<?php

declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\Console\Kernel;
use Livewire\Component;
use MrNewport\LaravelFields\Field;
use MrNewport\LaravelFields\FieldsServiceProvider;
use MrNewport\LaravelFields\Schema;
use Orchestra\Testbench\Foundation\Application;

$app = Application::create(null, null, ['enabled_package_discoveries' => true]);
$app->register(FieldsServiceProvider::class);
$app->make(Kernel::class)->bootstrap();

$schema = Schema::make([
    Field::text('title')->label('Title')->required()->placeholder('Describe your request')->help('Use a descriptive title.')->group('Request'),
    Field::select('priority')->label('Priority')->options(['normal' => 'Normal', 'high' => 'High'])->group('Request'),
    Field::date('date')->label('Date')->group('Request'),
    Field::checkbox('active')->label('Active')->required()->group('Request'),
    Field::text('reference')->label('Reference')->readOnly()->default('REF-17')->group('Request'),
    Field::display('status')->label('Account status')->default('Ready')->group('Request'),
]);
$owner = new class extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public array $data = [];

    public function render(): string
    {
        return '<div></div>';
    }
};
$checkbox = Filament\Schemas\Schema::make($owner)
    ->components(Schema::make([Field::checkbox('enabled')->label('Enabled')->required()])->toFilament())
    ->statePath('data');
$checkbox->fill(['enabled' => false]);
$html = '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Fields browser checks</title><style>'
    .file_get_contents(__DIR__.'/../../dist/fields.css').'main{max-width:42rem;margin:auto;padding:1.25rem}form+form{margin-top:1.5rem}'
    .'</style><body><main class="mx-auto max-w-2xl space-y-6 p-5"><h1>Request</h1><form id="native">'
    .$schema->render(['date' => '0001-01-01', 'active' => false])->render()
    .'<button type="submit">Submit request</button></form><form id="filament">'
    .$checkbox->toHtml().'<button type="submit">Submit preference</button></form><output id="result"></output></main>'
    .'<script>document.querySelectorAll("form").forEach(form => form.addEventListener("submit", event => { event.preventDefault(); document.getElementById("result").textContent = form.id + " submitted"; }));</script></body></html>';
$directory = __DIR__.'/artifacts';
if (! is_dir($directory)) {
    mkdir($directory, 0777, true);
}
file_put_contents($directory.'/form.html', $html);
