<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('ai-scheduler', 'pages.ai-scheduler')->name('ai-scheduler');
    Route::view('activity-log', 'pages.activity-log')->name('activity-log');
    Route::view('approval-review', 'pages.approval-review')->name('approval-review');
    Route::view('schedules', 'pages.schedules')->name('schedules.index');
    Route::view('instructors', 'pages.instructors')->name('instructors.index');
    Route::view('rooms', 'pages.rooms')->name('rooms.index');
    Route::view('course-classes', 'pages.course-classes')->name('course-classes.index');
    Route::view('instructor-leaves', 'pages.instructor-leaves')->name('instructor-leaves.index');
});

require __DIR__.'/settings.php';
