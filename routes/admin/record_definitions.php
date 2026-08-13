<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\DisplayViews\Http\Controllers\DisplayTemplateController;
use Polymorph\Platform\Domain\EntryView\Http\Controllers\EntryViewController;
use Polymorph\Platform\Domain\RecordDefinitions\Http\Controllers\RecordDefinitionController;
use Polymorph\Platform\Domain\SchemaModel\Access\SchemaCapabilities;

/**
 * Определения записей и конфигурация формы записи.
 *
 * Чтение доступно любому аутентифицированному: без определений админ-SPA не
 * нарисует ни одного экрана. Правка — только по schema/manage.
 */
Route::prefix('record-definitions')
    ->name('record-definitions.')
    ->whereNumber(['id', 'record_definition_id'])
    ->group(function (): void {
        Route::get('/', [RecordDefinitionController::class, 'index'])->name('index');
        Route::get('/{id}', [RecordDefinitionController::class, 'show'])->name('show');
        Route::get('/{record_definition_id}/form-config/{schema}', [EntryViewController::class, 'show'])
            ->name('form-config.show');

        Route::middleware(SchemaCapabilities::requireManage())->group(function (): void {
            Route::post('/', [RecordDefinitionController::class, 'store'])->name('store');
            Route::put('/{id}', [RecordDefinitionController::class, 'update'])->name('update');
            Route::post('/{id}/display-template/validate', [DisplayTemplateController::class, 'validateTemplate'])
                ->name('display-template.validate');
            Route::delete('/{id}', [RecordDefinitionController::class, 'destroy'])->name('destroy');
            Route::put('/{record_definition_id}/form-config/{schema}', [EntryViewController::class, 'update'])
                ->name('form-config.update');
        });
    });
