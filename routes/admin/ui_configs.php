<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\UiConfig\Core\UiConfigDomain;
use Polymorph\Platform\Domain\UiConfig\Http\Controllers\UiConfigController;

// Чтение адресуется путём: домен плюс непрозрачный ключ, в котором закодирован
// вид конфигурации (`entry_view:12`, `table:records.posts`, `menu:primary`).
// Личное чтение подменяет общее: нет личной строки — вернётся общая.
//
// Запись и удаление адреса в пути не имеют: ключ, домен и заявленная ревизия
// приходят телом, а проверяет их слой валидации внутри UiConfigService.
Route::prefix('ui-configs')->name('ui-configs.')->group(function (): void {
    Route::put('/', [UiConfigController::class, 'update'])->name('update');
    Route::delete('/', [UiConfigController::class, 'destroy'])->name('destroy');

    Route::get('/{domain}/{key}', [UiConfigController::class, 'show'])
        ->where([
            'domain' => implode('|', array_column(UiConfigDomain::cases(), 'value')),
            'key' => '[A-Za-z0-9._:-]{1,191}',
        ])
        ->name('show');
});
