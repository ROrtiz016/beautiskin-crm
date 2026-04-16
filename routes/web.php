<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerMembershipController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\AppointmentWebController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CustomerWebController;
use App\Http\Controllers\ServiceWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::resource('customers', CustomerWebController::class)
        ->only(['index', 'show', 'store', 'edit', 'update', 'destroy']);
    Route::resource('services', ServiceWebController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::get('appointments', [AppointmentWebController::class, 'index'])->name('appointments.index');
    Route::post('appointments', [AppointmentWebController::class, 'store'])->name('appointments.store');
    Route::patch('appointments/{appointment}/arrival', [AppointmentWebController::class, 'updateArrival'])->name('appointments.arrival.update');
    Route::patch('appointments/{appointment}/staff', [AppointmentWebController::class, 'updateStaff'])->name('appointments.staff.update');
    Route::patch(
        'customers/{customer}/contact-details',
        [CustomerWebController::class, 'updateContactDetails']
    )->name('customers.contact.update');
    Route::post(
        'customers/{customer}/appointments',
        [CustomerWebController::class, 'storeAppointment']
    )->name('customers.appointments.store');
    Route::patch(
        'customers/{customer}/appointments/{appointment}',
        [CustomerWebController::class, 'updateAppointment']
    )->name('customers.appointments.update');
    Route::patch(
        'customers/{customer}/appointments/{appointment}/status',
        [CustomerWebController::class, 'updateAppointmentStatus']
    )->name('customers.appointments.status');
});

Route::prefix('api')->name('api.')->group(function () {
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('services', ServiceController::class);
    Route::apiResource('memberships', MembershipController::class);
    Route::apiResource('customer-memberships', CustomerMembershipController::class);
    Route::apiResource('appointments', AppointmentController::class);
});
