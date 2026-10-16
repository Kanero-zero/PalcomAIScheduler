<?php

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
    Route::view('ai-scheduler', 'pages.ai-scheduler')->name('ai-scheduler');
    // F-06/P1: V2 UI preview only; keep existing MVP scheduler untouched.
    Route::view('ai-scheduler/v2-preview', 'pages.ai-scheduler-v2-preview')->name('ai-scheduler.v2-preview');
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

require __DIR__.'/settings.php';
