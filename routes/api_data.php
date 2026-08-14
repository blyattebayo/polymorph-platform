<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\DataPlatform\Access\SchemaCapabilities;
use Polymorph\Platform\Domain\DataPlatform\Http\DataControlController;
use Polymorph\Platform\Domain\DataPlatform\Http\DataRecordController;

Route::middleware(['api', 'auth:session'])
    ->prefix('api/v2/data')
    ->name('api.v2.data.')
    ->where(['definitionId' => '[1-9][0-9]*', 'recordId' => '[1-9][0-9]*'])
    ->group(function (): void {
        Route::post('/definitions/{definitionId}/records/query', [DataRecordController::class, 'query'])->name('records.query');
        Route::post('/definitions/{definitionId}/records', [DataRecordController::class, 'store'])->name('records.store');
        Route::put('/definitions/{definitionId}/records/{recordId}', [DataRecordController::class, 'update'])->name('records.update');
        Route::patch('/definitions/{definitionId}/records/{recordId}', [DataRecordController::class, 'update'])->name('records.patch');
        Route::get('/records/{recordId}', [DataRecordController::class, 'show'])->name('records.show');
        Route::delete('/records/{recordId}', [DataRecordController::class, 'destroy'])->name('records.destroy');
        Route::post('/hydrate', [DataRecordController::class, 'hydrate'])->name('records.hydrate');

        Route::prefix('control')->name('control.')->group(function (): void {
            Route::middleware(SchemaCapabilities::requireRead())->group(function (): void {
                Route::get('/definitions', [DataControlController::class, 'index'])->name('definitions.index');
                Route::get('/definitions/{definitionId}', [DataControlController::class, 'show'])->name('definitions.show');
                Route::get('/definitions/{definitionId}/form-config', [DataControlController::class, 'showFormConfig'])->name('definitions.form-config.show');
            });
            Route::middleware(SchemaCapabilities::requireManage())->group(function (): void {
                Route::post('/definitions', [DataControlController::class, 'store'])->name('definitions.store');
                Route::patch('/definitions/{definitionId}', [DataControlController::class, 'updateDefinition'])->name('definitions.update');
                Route::delete('/definitions/{definitionId}', [DataControlController::class, 'destroyDefinition'])->name('definitions.destroy');
                Route::post('/definitions/{definitionId}/display-template/validate', [DataControlController::class, 'validateDisplayTemplate'])->name('definitions.display-template.validate');
                Route::put('/definitions/{definitionId}/form-config', [DataControlController::class, 'updateFormConfig'])->name('definitions.form-config.update');
                Route::post('/definitions/{definitionId}/drafts', [DataControlController::class, 'createDraft'])->name('definitions.drafts.store');
                Route::put('/schema-versions/{schemaVersionId}/fields', [DataControlController::class, 'replaceFields'])->name('schema-versions.fields.replace');
                Route::post('/schema-versions/{schemaVersionId}/transition', [DataControlController::class, 'transition'])->name('schema-versions.transition');
                Route::post('/migration-plans', [DataControlController::class, 'createMigrationPlan'])->name('migration-plans.store');
                Route::post('/migration-plans/{planId}/run', [DataControlController::class, 'runMigration'])->name('migration-plans.run');
                Route::post('/definitions/{definitionId}/projections/rebuild', [DataControlController::class, 'rebuildProjections'])->name('definitions.projections.rebuild');
            });
        });
    });
