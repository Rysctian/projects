<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProjectAccessController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectLabelController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect('/projects') : redirect('/login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/projects')->name('dashboard');
    Route::get('admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::post('admin/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::patch('admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::post('admin/users/{user}/invitation', [UserController::class, 'invitation'])->middleware('throttle:6,1')->name('admin.users.invitation');
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::get('projects/{project}/settings', [ProjectController::class, 'settings'])->name('projects.settings');
    Route::post('projects/{project}/modules', [ProjectController::class, 'storeModule'])->name('modules.store');
    Route::patch('projects/{project}/modules/{module}', [ProjectController::class, 'updateModule'])->name('modules.update');
    Route::post('projects/{project}/roles', [ProjectAccessController::class, 'role'])->name('roles.store');
    Route::patch('projects/{project}/roles/{role}', [ProjectAccessController::class, 'role'])->name('roles.update');
    Route::post('projects/{project}/members', [ProjectAccessController::class, 'member'])->name('members.store');
    Route::patch('projects/{project}/members/{member}', [ProjectAccessController::class, 'member'])->name('members.update');
    Route::put('projects/{project}/members/{member}/overrides', [ProjectAccessController::class, 'overrides'])->name('members.overrides');
    Route::get('projects/{project}/workflows', [WorkflowController::class, 'index'])->name('workflows.index');
    Route::post('projects/{project}/workflows/draft', [WorkflowController::class, 'draft'])->name('workflows.draft');
    Route::post('projects/{project}/workflows/{workflow}/publish', [WorkflowController::class, 'publish'])->name('workflows.publish');
    Route::post('projects/{project}/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('projects/{project}/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::patch('projects/{project}/tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
    Route::post('projects/{project}/tickets/{ticket}/transitions', [TicketController::class, 'transition'])->name('tickets.transition');
    Route::post('projects/{project}/tickets/{ticket}/signoffs', [TicketController::class, 'signoff'])->name('tickets.signoff');
    Route::post('projects/{project}/tickets/{ticket}/actions', [TicketController::class, 'action'])->name('tickets.action');
    Route::post('projects/{project}/tickets/{ticket}/comments', [TicketController::class, 'comment'])->name('tickets.comment');
    Route::post('projects/{project}/tickets/{ticket}/attachments', [TicketAttachmentController::class, 'store'])->name('attachments.store');
    Route::get('projects/{project}/tickets/{ticket}/attachments/{attachment}', [TicketAttachmentController::class, 'download'])->name('attachments.download');
    Route::post('projects/{project}/labels', [ProjectLabelController::class, 'store'])->name('labels.store');
});

require __DIR__.'/settings.php';
