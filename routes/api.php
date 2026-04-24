<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AppointmentPaymentEntryController;
use App\Http\Controllers\Api\AppointmentReminderController;
use App\Http\Controllers\Api\AppointmentRescheduleController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerCommunicationController;
use App\Http\Controllers\Api\CustomerTimelineNoteController;
use App\Http\Controllers\Api\CustomerMembershipController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\OpportunityController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TreatmentPackageController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\AdminControlBoardController;
use App\Http\Controllers\Api\WaitlistEntryController;
use App\Http\Controllers\OperationsDashboardController;
use App\Http\Controllers\ReportingController;
use App\Http\Controllers\Spa\ActivitySpaController;
use App\Http\Controllers\Spa\AdminControlBoardSpaController;
use App\Http\Controllers\Spa\AppointmentsSpaController;
use App\Http\Controllers\Spa\CustomersSpaController;
use App\Http\Controllers\Spa\HomeSpaController;
use App\Http\Controllers\Spa\InventorySpaController;
use App\Http\Controllers\Spa\LeadsSpaController;
use App\Http\Controllers\Spa\MembershipsSpaController;
use App\Http\Controllers\Spa\OperationsSpaController;
use App\Http\Controllers\Spa\PackagesSpaController;
use App\Http\Controllers\Spa\PipelineSpaController;
use App\Http\Controllers\Spa\QuotesSpaController;
use App\Http\Controllers\Spa\ReportsSpaController;
use App\Http\Controllers\Spa\SalesOverviewSpaController;
use App\Http\Controllers\Spa\ServicesSpaController;
use App\Http\Controllers\Spa\SpaAuthController;
use App\Http\Controllers\Spa\TasksSpaController;
use App\Http\Controllers\UserDashboardLayoutController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [SpaAuthController::class, 'login']);
Route::post('auth/register', [SpaAuthController::class, 'register']);
Route::post('auth/forgot-password', [SpaAuthController::class, 'forgotPassword']);
Route::post('auth/reset-password', [SpaAuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('auth/logout', [SpaAuthController::class, 'logout']);
    Route::get('auth/user', [SpaAuthController::class, 'user']);

    Route::prefix('spa')->group(function (): void {
        Route::get('home', [HomeSpaController::class, 'show']);
        Route::get('customers', [CustomersSpaController::class, 'index']);
        Route::get('customers/{customer}/edit', [CustomersSpaController::class, 'edit']);
        Route::get('customers/{customer}/timeline', [ActivitySpaController::class, 'customerTimeline']);
        Route::get('customers/{customer}', [CustomersSpaController::class, 'show']);
        Route::get('activities', [ActivitySpaController::class, 'index']);
        Route::get('tasks', [TasksSpaController::class, 'index']);
        Route::get('appointments', [AppointmentsSpaController::class, 'index']);
        Route::get('leads', [LeadsSpaController::class, 'index']);
        Route::get('sales/pipeline', [PipelineSpaController::class, 'index']);
        Route::get('sales', [SalesOverviewSpaController::class, 'index'])->middleware('can:view-sales');
        Route::get('services', [ServicesSpaController::class, 'index']);
        Route::get('inventory', [InventorySpaController::class, 'index']);
        Route::get('packages', [PackagesSpaController::class, 'index']);
        Route::get('quotes', [QuotesSpaController::class, 'index']);
        Route::get('quotes/{quote}', [QuotesSpaController::class, 'show']);
        Route::get('memberships', [MembershipsSpaController::class, 'index']);

        Route::middleware('can:access-admin-board')->group(function (): void {
            Route::get('admin/operations', [OperationsSpaController::class, 'index']);
            Route::get('admin/reports', [ReportsSpaController::class, 'index']);
            Route::get('admin/control-board', [AdminControlBoardSpaController::class, 'index']);
        });
    });

    Route::name('api.')->group(function (): void {
        Route::patch('user/dashboard-layout', [UserDashboardLayoutController::class, 'update'])
            ->name('user.dashboard-layout.update');
        Route::post('customers/{customer}/timeline-notes', [CustomerTimelineNoteController::class, 'store'])
            ->name('customers.timeline-notes.store');
        Route::post('customers/{customer}/communications', [CustomerCommunicationController::class, 'store'])
            ->name('customers.communications.store');
        Route::post('customers/{customer}/communications/templated', [CustomerCommunicationController::class, 'storeTemplated'])
            ->name('customers.communications.templated.store');

        Route::middleware('can:access-admin-board')->prefix('admin')->group(function (): void {
            Route::patch('operations/appointment-policy', [OperationsDashboardController::class, 'updateAppointmentPolicy'])
                ->name('admin.operations.appointment-policy');
            Route::patch('operations/feature-flags', [OperationsDashboardController::class, 'updateFeatureFlags'])
                ->name('admin.operations.feature-flags');
            Route::get('reports/export', [ReportingController::class, 'exportCsv'])->name('admin.reports.export');
            Route::post('users', [AdminControlBoardController::class, 'storeUser'])->name('admin.users.store');
            Route::patch('users/{adminUser}/access', [AdminControlBoardController::class, 'updateUserAccess'])
                ->name('admin.users.access.update');
            Route::post('users/{adminUser}/deactivate', [AdminControlBoardController::class, 'deactivateUser'])
                ->name('admin.users.deactivate');
            Route::post('users/{adminUser}/restore', [AdminControlBoardController::class, 'restoreUser'])
                ->name('admin.users.restore');
            Route::post('promotions', [AdminControlBoardController::class, 'storePromotion'])->name('admin.promotions.store');
            Route::put('promotions/{promotion}', [AdminControlBoardController::class, 'updatePromotionRules'])
                ->name('admin.promotions.update');
            Route::patch('clinic-settings', [AdminControlBoardController::class, 'updateClinicTaxSettings'])
                ->name('admin.clinic-settings.update');
            Route::patch('clinic-profile', [AdminControlBoardController::class, 'updateClinicProfile'])
                ->name('admin.clinic-profile.update');
            Route::patch('messaging-settings', [AdminControlBoardController::class, 'updateMessagingSettings'])
                ->name('admin.messaging-settings.update');
            Route::post('messaging-settings/test-send', [AdminControlBoardController::class, 'sendMessagingTest'])
                ->name('admin.messaging-settings.test-send');
            Route::get('backup-export', [AdminControlBoardController::class, 'exportBackupSnapshot'])->name('admin.backup.export');
            Route::get('customers/{customer}/export', [AdminControlBoardController::class, 'exportCustomerData'])
                ->name('admin.customers.export');
            Route::post('customers/{customer}/gdpr-delete', [AdminControlBoardController::class, 'gdprDeleteCustomer'])
                ->name('admin.customers.gdpr-delete');
            Route::patch('services/{service}/price', [AdminControlBoardController::class, 'updateServicePrice'])->name('admin.services.price');
            Route::patch('memberships/{membership}/price', [AdminControlBoardController::class, 'updateMembershipPrice'])
                ->name('admin.memberships.price');
            Route::patch('promotions/{promotion}/status', [AdminControlBoardController::class, 'updatePromotionStatus'])
                ->name('admin.promotions.status');
            Route::post('scheduled-price-changes', [AdminControlBoardController::class, 'storeScheduledPriceChange'])
                ->name('admin.scheduled-prices.store');
            Route::post('scheduled-price-changes/{scheduledPriceChange}/cancel', [AdminControlBoardController::class, 'cancelScheduledPriceChange'])
                ->name('admin.scheduled-prices.cancel');
        });

        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('services', ServiceController::class);
        Route::apiResource('memberships', MembershipController::class);
        Route::post('packages', [TreatmentPackageController::class, 'store'])->name('packages.store');
        Route::patch('packages/{treatmentPackage}', [TreatmentPackageController::class, 'update'])->name('packages.update');
        Route::delete('packages/{treatmentPackage}', [TreatmentPackageController::class, 'destroy'])->name('packages.destroy');
        Route::apiResource('customer-memberships', CustomerMembershipController::class);
        Route::post('appointments/{appointment}/payment-entries', [AppointmentPaymentEntryController::class, 'store'])
            ->name('appointments.payment-entries.store');
        Route::delete('appointment-payment-entries/{appointmentPaymentEntry}', [AppointmentPaymentEntryController::class, 'destroy'])
            ->name('appointment-payment-entries.destroy');
        Route::post('appointments/{appointment}/reminder-email', [AppointmentReminderController::class, 'store'])
            ->name('appointments.reminder-email.store');
        Route::patch('appointments/{appointment}/reschedule', AppointmentRescheduleController::class)
            ->name('appointments.reschedule');
        Route::post('waitlist-entries', [WaitlistEntryController::class, 'store'])->name('waitlist-entries.store');
        Route::post('waitlist-entries/{waitlistEntry}/contact', [WaitlistEntryController::class, 'recordContact'])
            ->name('waitlist-entries.contact.store');
        Route::patch('waitlist-entries/{waitlistEntry}/status', [WaitlistEntryController::class, 'updateStatus'])
            ->name('waitlist-entries.status.update');
        Route::post('quotes', [QuoteController::class, 'store'])->name('quotes.store');
        Route::patch('quotes/{quote}', [QuoteController::class, 'update'])->name('quotes.update');
        Route::patch('quotes/{quote}/status', [QuoteController::class, 'updateStatus'])->name('quotes.status.update');
        Route::post('quotes/{quote}/lines', [QuoteController::class, 'storeLine'])->name('quotes.lines.store');
        Route::post('quotes/{quote}/link-appointment', [QuoteController::class, 'linkAppointment'])->name('quotes.link-appointment');
        Route::delete('quote-lines/{quoteLine}', [QuoteController::class, 'destroyLine'])->name('quote-lines.destroy');
        Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::patch('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::patch('tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
        Route::patch('tasks/{task}/reopen', [TaskController::class, 'reopen'])->name('tasks.reopen');
        Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
        Route::post('opportunities', [OpportunityController::class, 'store'])->name('opportunities.store');
        Route::patch('opportunities/{opportunity}', [OpportunityController::class, 'update'])->name('opportunities.update');
        Route::patch('opportunities/{opportunity}/stage', [OpportunityController::class, 'updateStage'])->name('opportunities.stage');
        Route::delete('opportunities/{opportunity}', [OpportunityController::class, 'destroy'])->name('opportunities.destroy');
        Route::post('appointments/{appointment}/retail-lines', [AppointmentController::class, 'storeRetailLine'])
            ->name('appointments.retail-lines.store');
        Route::apiResource('appointments', AppointmentController::class);
    });
});
