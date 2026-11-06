<?php

use App\Http\Controllers\AiSchedulerV2Controller;
use App\Http\Controllers\ClassProposalApprovalController;
use App\Http\Controllers\InstructorController;
use Illuminate\Support\Facades\Route;

// Homepage is the login screen for guests; authenticated users go to their dashboard.
// Keep the existing Fortify /login route and its authentication handlers intact.
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return view('livewire.auth.login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    // F-08/P1: BAAK dashboard V2 preview; preserve operational dashboard and its Livewire component.
    Route::view('dashboard/v2-preview', 'pages.dashboard-v2-preview')->name('dashboard.v2-preview');
    Route::view('ai-scheduler', 'pages.ai-scheduler-v2-preview')->name('ai-scheduler');
    Route::redirect('ai-scheduler/v2-preview', '/ai-scheduler')->name('ai-scheduler.v2-preview');
    Route::view('activity-log', 'pages.activity-log')->name('activity-log');
    Route::view('approval-review', 'pages.approval-review')->name('approval-review');
    // F-07/P1: approval V2 preview. Preserve the existing operational approval route.
    Route::view('approval-review/v2-preview', 'pages.approval-review-v2-preview')->name('approval-review.v2-preview');
    Route::view('schedules', 'pages.schedules')->name('schedules.index');
    Route::view('instructors', 'pages.instructors')->name('instructors.index');
    Route::get('instructors/create', [InstructorController::class, 'create'])->name('instructors.create');
    Route::post('instructors', [InstructorController::class, 'store'])->name('instructors.store');
    Route::get('instructors/{instructor}/edit', [InstructorController::class, 'edit'])->whereNumber('instructor')->name('instructors.edit');
    Route::put('instructors/{instructor}', [InstructorController::class, 'update'])->whereNumber('instructor')->name('instructors.update');
    Route::view('rooms', 'pages.rooms')->name('rooms.index');
    Route::view('course-classes', 'pages.course-classes')->name('course-classes.index');
    Route::view('instructor-leaves', 'pages.instructor-leaves')->name('instructor-leaves.index');
});

// SCHEDORA V2: agentic AI recommendations, manual G3 recommendations, and AO pending proposals.
// Legacy leave-scheduler endpoints remain unchanged.
Route::middleware(['auth', 'verified', 'throttle:20,1'])->group(function () {
    Route::post('ai-scheduler/v2/interpret', [AiSchedulerV2Controller::class, 'interpret'])->name('ai-scheduler.v2-interpret');
    Route::post('ai-scheduler/v2/agent', [AiSchedulerV2Controller::class, 'agent'])->name('ai-scheduler.v2-agent');
    Route::post('ai-scheduler/v2/agent/submit', [AiSchedulerV2Controller::class, 'submitAgent'])->name('ai-scheduler.v2-agent-submit');
    Route::post('ai-scheduler/v2/preview', [AiSchedulerV2Controller::class, 'preview'])->name('ai-scheduler.v2-recommendations');
    Route::post('ai-scheduler/v2/submit', [AiSchedulerV2Controller::class, 'submit'])->name('ai-scheduler.v2-submit');
});

Route::middleware(['auth', 'verified', 'can:manage-class-proposal-approval', 'throttle:30,1'])
    ->prefix('class-proposal-approvals')
    ->name('class-proposal-approvals.')
    ->group(function () {
        Route::get('/', [ClassProposalApprovalController::class, 'index'])->name('index');
        Route::get('proposals', [ClassProposalApprovalController::class, 'list'])->name('list');
        Route::get('{proposal}', [ClassProposalApprovalController::class, 'show'])->whereNumber('proposal')->name('show');
        Route::get('{proposal}/validation', [ClassProposalApprovalController::class, 'preview'])->whereNumber('proposal')->name('preview');
        Route::post('{proposal}/approve', [ClassProposalApprovalController::class, 'approve'])->whereNumber('proposal')->name('approve');
        Route::post('{proposal}/reject', [ClassProposalApprovalController::class, 'reject'])->whereNumber('proposal')->name('reject');
    });

require __DIR__.'/settings.php';
