<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Polymorph\Platform\Domain\AccessControl\Access\AccessControlCapabilities;
use Polymorph\Platform\Domain\AccessControl\Http\Controllers\AssignmentController;
use Polymorph\Platform\Domain\AccessControl\Http\Controllers\CapabilityCatalogController;
use Polymorph\Platform\Domain\AccessControl\Http\Controllers\PolicyController;
use Polymorph\Platform\Http\Middleware\RequireCapability;

/**
 * Управление политиками доступа и их назначениями.
 *
 * Две капабилити разделены намеренно: править сами политики (POLICY_MANAGE) и
 * раздавать их субъектам (POLICY_ASSIGN) — разные полномочия.
 */
Route::prefix('acl')->name('acl.')->whereNumber(['policyId', 'assignmentId'])->group(function (): void {
    Route::middleware(RequireCapability::forRoute(AccessControlCapabilities::POLICY_MANAGE))
        ->prefix('policies')
        ->name('policies.')
        ->group(function (): void {
            Route::get('/', [PolicyController::class, 'index'])->name('index');
            Route::post('/', [PolicyController::class, 'store'])->name('store');
            Route::put('/{policyId}', [PolicyController::class, 'update'])->name('update');
            Route::delete('/{policyId}', [PolicyController::class, 'destroy'])->name('destroy');
        });

    Route::middleware(RequireCapability::forRoute(AccessControlCapabilities::POLICY_ASSIGN))->group(function (): void {
        Route::prefix('policies/{policyId}/assignments')->name('policies.assignments.')->group(function (): void {
            Route::get('/', [PolicyController::class, 'listAssignments'])->name('index');
            Route::post('/', [PolicyController::class, 'assign'])->name('store');
            Route::delete('/{assignmentId}', [PolicyController::class, 'unassign'])->name('destroy');
        });

        Route::get('/capabilities', [CapabilityCatalogController::class, 'index'])->name('capabilities.index');

        Route::get('/assignments', [AssignmentController::class, 'indexBySubject'])->name('assignments.index');
        Route::put('/assignments', [AssignmentController::class, 'replace'])->name('assignments.replace');
    });
});
