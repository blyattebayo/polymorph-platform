<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\UiConfig\Core\ConfigNamespace;
use Polymorph\Platform\Domain\UiConfig\Http\Controllers\ConfigController;

// Чтение адресуется путём: вид конфига — закрытый словарь, ключ внутри вида
// непрозрачен (составные адреса склеивает клиент — пару «определение записи +
// схема» у entry view, владельца у персональных настроек).
//
// Запись и удаление адреса в пути не имеют: вид, ключ и заявленная ревизия
// приходят телом, см. UiConfigWriteRequest.
Route::prefix('ui-configs')->name('ui-configs.')->group(function (): void {
    Route::put('/', [ConfigController::class, 'update'])->name('update');
    Route::delete('/', [ConfigController::class, 'destroy'])->name('destroy');

    Route::get('/{namespace}/{key}', [ConfigController::class, 'show'])
        ->where([
            'namespace' => implode('|', array_column(ConfigNamespace::cases(), 'value')),
            'key' => '[A-Za-z0-9._:-]{1,191}',
        ])
        ->name('show');
});
