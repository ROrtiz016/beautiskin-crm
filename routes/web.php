<?php

use App\Http\Controllers\AdminControlBoardController;
use App\Http\Controllers\AdminImpersonationController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerMembershipController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\ActivityFeedController;
use App\Http\Controllers\AppointmentWebController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CustomerCommunicationLogController;
use App\Http\Controllers\CustomerTemplatedCommunicationController;
use App\Http\Controllers\CustomerTimelineNoteController;
use App\Http\Controllers\CustomerWebController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InventoryWebController;
use App\Http\Controllers\QuoteWebController;
use App\Http\Controllers\TreatmentPackageWebController;
use App\Http\Controllers\LeadsController;
use App\Http\Controllers\MembershipWebController;
use App\Http\Controllers\OperationsDashboardController;
use App\Http\Controllers\ReportingController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SalesOpportunityController;
use App\Http\Controllers\ServiceWebController;
use App\Http\Controllers\TaskWebController;
use App\Http\Controllers\UserDashboardLayoutController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])->name('register.store');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::patch('user/dashboard-layout', [UserDashboardLayoutController::class, 'update'])->name('user.dashboard-layout.update');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::post('admin/impersonate/leave', [AdminImpersonationController::class, 'leave'])->name('admin.impersonate.leave');

    Route::get('activity', [ActivityFeedController::class, 'index'])->name('activity.index');
    Route::get('customers/{customer}/timeline', [ActivityFeedController::class, 'customer'])->name('customers.timeline.show');
    Route::post('customers/{customer}/communications', [CustomerCommunicationLogController::class, 'store'])->name('customers.communications.store');
    Route::post('customers/{customer}/communications/templated', [CustomerTemplatedCommunicationController::class, 'store'])->name('customers.communications.templated');

    Route::resource('customers', CustomerWebController::class)
        ->only(['index', 'show', 'store', 'edit', 'update', 'destroy']);
    Route::resource('services', ServiceWebController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::get('inventory', [InventoryWebController::class, 'index'])->name('inventory.index');
    Route::get('packages', [TreatmentPackageWebController::class, 'index'])->name('packages.index');
    Route::post('packages', [TreatmentPackageWebController::class, 'store'])->name('packages.store');
    Route::patch('packages/{treatmentPackage}', [TreatmentPackageWebController::class, 'update'])->name('packages.update');
    Route::delete('packages/{treatmentPackage}', [TreatmentPackageWebController::class, 'destroy'])->name('packages.destroy');
    Route::get('quotes', [QuoteWebController::class, 'index'])->name('quotes.index');
    Route::post('quotes', [QuoteWebController::class, 'store'])->name('quotes.store');
    Route::get('quotes/{quote}', [QuoteWebController::class, 'show'])->name('quotes.show');
    Route::patch('quotes/{quote}', [QuoteWebController::class, 'update'])->name('quotes.update');
    Route::patch('quotes/{quote}/status', [QuoteWebController::class, 'updateStatus'])->name('quotes.status.update');
    Route::post('quotes/{quote}/lines', [QuoteWebController::class, 'storeLine'])->name('quotes.lines.store');
    Route::delete('quote-lines/{quoteLine}', [QuoteWebController::class, 'destroyLine'])->name('quote-lines.destroy');
    Route::post('quotes/{quote}/link-appointment', [QuoteWebController::class, 'linkAppointment'])->name('quotes.link-appointment');
    Route::resource('memberships', MembershipWebController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::get('appointments/day', [AppointmentWebController::class, 'dayFragment'])->name('appointments.day');
    Route::get('appointments', [AppointmentWebController::class, 'index'])->name('appointments.index');
    Route::post('appointments', [AppointmentWebController::class, 'store'])->name('appointments.store');
    Route::post('appointments/waitlist', [AppointmentWebController::class, 'storeWaitlist'])->name('appointments.waitlist.store');
    Route::patch('appointments/{appointment}', [AppointmentWebController::class, 'update'])->name('appointments.update');
    Route::patch('appointments/{appointment}/reschedule', [AppointmentWebController::class, 'reschedule'])->name('appointments.reschedule');
    Route::post('appointments/{appointment}/reminders/email', [AppointmentWebController::class, 'sendEmailReminder'])->name('appointments.reminders.email');
    Route::patch('appointments/{appointment}/status', [AppointmentWebController::class, 'updateStatus'])->name('appointments.status.update');
    Route::patch('appointments/{appointment}/arrival', [AppointmentWebController::class, 'updateArrival'])->name('appointments.arrival.update');
    Route::patch('appointments/{appointment}/staff', [AppointmentWebController::class, 'updateStaff'])->name('appointments.staff.update');
    Route::post('appointments/{appointment}/payment-entries', [AppointmentWebController::class, 'storePaymentEntry'])->name('appointments.payment-entries.store');
    Route::delete('appointment-payment-entries/{appointmentPaymentEntry}', [AppointmentWebController::class, 'destroyPaymentEntry'])->name('appointments.payment-entries.destroy');
    Route::post('appointments/waitlist/{waitlistEntry}/contact', [AppointmentWebController::class, 'recordWaitlistContact'])->name('appointments.waitlist.contact');
    Route::patch('appointments/waitlist/{waitlistEntry}/status', [AppointmentWebController::class, 'updateWaitlistStatus'])->name('appointments.waitlist.status.update');
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
    Route::post(
        'customers/{customer}/appointments/{appointment}/retail-lines',
        [CustomerWebController::class, 'storeAppointmentRetailLine']
    )->name('customers.appointments.retail-lines.store');
    Route::post('customers/{customer}/timeline-notes', [CustomerTimelineNoteController::class, 'store'])->name('customers.timeline-notes.store');

    Route::get('sales', [SalesController::class, 'index'])
        ->middleware('can:view-sales')
        ->name('sales.index');

    Route::get('sales/pipeline', [SalesOpportunityController::class, 'index'])->name('sales.pipeline.index');
    Route::post('sales/opportunities', [SalesOpportunityController::class, 'store'])->name('sales.opportunities.store');
    Route::patch('sales/opportunities/{opportunity}', [SalesOpportunityController::class, 'update'])->name('sales.opportunities.update');
    Route::patch('sales/opportunities/{opportunity}/stage', [SalesOpportunityController::class, 'updateStage'])->name('sales.opportunities.stage');
    Route::delete('sales/opportunities/{opportunity}', [SalesOpportunityController::class, 'destroy'])->name('sales.opportunities.destroy');

    Route::get('leads', [LeadsController::class, 'index'])->name('leads.index');

    Route::get('tasks', [TaskWebController::class, 'index'])->name('tasks.index');
    Route::post('tasks', [TaskWebController::class, 'store'])->name('tasks.store');
    Route::patch('tasks/{task}', [TaskWebController::class, 'update'])->name('tasks.update');
    Route::patch('tasks/{task}/complete', [TaskWebController::class, 'complete'])->name('tasks.complete');
    Route::patch('tasks/{task}/reopen', [TaskWebController::class, 'reopen'])->name('tasks.reopen');
    Route::delete('tasks/{task}', [TaskWebController::class, 'destroy'])->name('tasks.destroy');
});

Route::middleware(['auth', 'can:access-admin-board'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('control-board', [AdminControlBoardController::class, 'index'])->name('control-board');
    Route::post('users', [AdminControlBoardController::class, 'storeUser'])->name('users.store');
    Route::post('impersonate/{adminUser}', [AdminImpersonationController::class, 'start'])->name('impersonate.start');
    Route::patch('users/{adminUser}/access', [AdminControlBoardController::class, 'updateUserAccess'])->name('users.access.update');
    Route::post('users/{adminUser}/deactivate', [AdminControlBoardController::class, 'deactivateUser'])->name('users.deactivate');
    Route::post('users/{adminUser}/restore', [AdminControlBoardController::class, 'restoreUser'])->name('users.restore');
    Route::patch('services/{service}/price', [AdminControlBoardController::class, 'updateServicePrice'])->name('services.price.update');
    Route::patch('memberships/{membership}/price', [AdminControlBoardController::class, 'updateMembershipPrice'])->name('memberships.price.update');
    Route::post('promotions', [AdminControlBoardController::class, 'storePromotion'])->name('promotions.store');
    Route::patch('promotions/{promotion}/status', [AdminControlBoardController::class, 'updatePromotionStatus'])->name('promotions.status.update');
    Route::put('promotions/{promotion}', [AdminControlBoardController::class, 'updatePromotionRules'])->name('promotions.update');
    Route::post('scheduled-price-changes', [AdminControlBoardController::class, 'storeScheduledPriceChange'])->name('scheduled-prices.store');
    Route::post('scheduled-price-changes/{scheduledPriceChange}/cancel', [AdminControlBoardController::class, 'cancelScheduledPriceChange'])->name('scheduled-prices.cancel');
    Route::get('reports', [ReportingController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportingController::class, 'exportCsv'])->name('reports.export');
    Route::get('operations', [OperationsDashboardController::class, 'index'])->name('operations.index');
    Route::patch('operations/appointment-policy', [OperationsDashboardController::class, 'updateAppointmentPolicy'])->name('operations.appointment-policy.update');
    Route::patch('operations/feature-flags', [OperationsDashboardController::class, 'updateFeatureFlags'])->name('operations.feature-flags.update');
    Route::patch('clinic-settings', [AdminControlBoardController::class, 'updateClinicTaxSettings'])->name('clinic-settings.update');
    Route::patch('clinic-profile', [AdminControlBoardController::class, 'updateClinicProfile'])->name('clinic-profile.update');
    Route::patch('messaging-settings', [AdminControlBoardController::class, 'updateMessagingSettings'])->name('messaging-settings.update');
    Route::post('messaging-settings/test-send', [AdminControlBoardController::class, 'sendMessagingTest'])->name('messaging-settings.test-send');
    Route::get('backup-export', [AdminControlBoardController::class, 'exportBackupSnapshot'])->name('backup.export');
    Route::get('customers/{customer}/export', [AdminControlBoardController::class, 'exportCustomerData'])->name('customers.export');
    Route::post('customers/{customer}/gdpr-delete', [AdminControlBoardController::class, 'gdprDeleteCustomer'])->name('customers.gdpr-delete');
});

Route::prefix('api')->name('api.')->group(function () {
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('services', ServiceController::class);
    Route::apiResource('memberships', MembershipController::class);
    Route::apiResource('customer-memberships', CustomerMembershipController::class);
    Route::apiResource('appointments', AppointmentController::class);
});
