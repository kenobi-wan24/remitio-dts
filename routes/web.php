<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentAttachmentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentMovementController;
use App\Http\Controllers\LegalCaseController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Profile (account self-deletion removed on purpose — users are deactivated, not deleted)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // ── Phase 7: Global search ──
    Route::get('/search', SearchController::class)->name('search');

    // ── Phase 3: Clients ──
    Route::patch('/clients/{client}/restore', [ClientController::class, 'restore'])
        ->withTrashed()
        ->name('clients.restore');
    Route::resource('clients', ClientController::class);

    // ── Phase 4: Cases (model is LegalCase — `case` is reserved in PHP) ──
    Route::patch('/cases/{legal_case}/restore', [LegalCaseController::class, 'restore'])
        ->withTrashed()
        ->name('cases.restore');
    Route::resource('cases', LegalCaseController::class)
        ->parameters(['cases' => 'legal_case']);

    // ── Phase 5: Documents & attachments ──
    Route::patch('/documents/{document}/restore', [DocumentController::class, 'restore'])
        ->withTrashed()
        ->name('documents.restore');
    Route::resource('documents', DocumentController::class);

    Route::post('/documents/{document}/attachments', [DocumentAttachmentController::class, 'store'])
        ->name('documents.attachments.store');
    Route::get('/attachments/{attachment}/download', [DocumentAttachmentController::class, 'download'])
        ->name('attachments.download');
    Route::delete('/attachments/{attachment}', [DocumentAttachmentController::class, 'destroy'])
        ->name('attachments.destroy');
    Route::patch('/attachments/{attachment}/final', [DocumentAttachmentController::class, 'markFinal'])
        ->name('attachments.final'); // Phase 8: version control

    // ── Phase 6: Tracking (append-only — no edit/delete routes on purpose) ──
    Route::post('/documents/{document}/movements', [DocumentMovementController::class, 'store'])
        ->name('documents.movements.store');

    // ── Phase 10: Reports (print / save as PDF, or ?format=csv) ──
    Route::prefix('reports')->name('reports.')->controller(ReportController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/documents', 'documents')->name('documents');
        Route::get('/movements', 'movements')->name('movements');
        Route::get('/notarial-register', 'notarial')->name('notarial');
        Route::get('/client-summary', 'clientSummary')->name('client-summary');
    });

    // ── Phase 9: Admin only ──
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', Admin\UserController::class)->except(['show', 'destroy']);
        Route::patch('users/{user}/toggle', [Admin\UserController::class, 'toggle'])->name('users.toggle');
        Route::put('users/{user}/password', [Admin\UserController::class, 'resetPassword'])->name('users.password');

        Route::resource('document-types', Admin\DocumentTypeController::class)
            ->except(['show', 'create'])
            ->parameters(['document-types' => 'document_type']);
        Route::patch('document-types/{document_type}/toggle', [Admin\DocumentTypeController::class, 'toggle'])
            ->name('document-types.toggle');

        Route::get('activity-logs', Admin\ActivityLogController::class)->name('activity-logs.index');
    });
});

require __DIR__.'/auth.php';
