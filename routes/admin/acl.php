<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\AccessControl\Access\AccessControlCapabilities;
use Polymorph\Platform\Domain\AccessControl\Http\Controllers\AssignmentController;
use Polymorph\Platform\Domain\AccessControl\Http\Controllers\CapabilityCatalogController;
use Polymorph\Platform\Domain\AccessControl\Http\Controllers\PolicyController;

/**
 * Управление политиками доступа и их назначениями.
 *
 * Две капабилити разделены намеренно: править сами политики (POLICY_MANAGE) и
 * раздавать их субъектам (POLICY_ASSIGN) — разные полномочия.
 */
Route::prefix('acl')->name('acl.')->whereNumber('policyId')->group(function (): void {
    Route::middleware(AccessControlCapabilities::requirePolicyManage())
        ->prefix('policies')
        ->name('policies.')
        ->group(function (): void {
            Route::get('/', [PolicyController::class, 'index'])->name('index');
            Route::post('/', [PolicyController::class, 'store'])->name('store');
            Route::put('/{policyId}', [PolicyController::class, 'update'])->name('update');
            Route::delete('/{policyId}', [PolicyController::class, 'destroy'])->name('destroy');
        });

    Route::middleware(AccessControlCapabilities::requireAssignmentManage())->group(function (): void {
        Route::get('/capabilities', [CapabilityCatalogController::class, 'index'])->name('capabilities.index');

        Route::get('/assignments', [AssignmentController::class, 'index'])->name('assignments.index');
        Route::put('/assignments', [AssignmentController::class, 'replace'])->name('assignments.replace');
    });
});
