<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\ClinicSetting;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Promotion;
use App\Models\ScheduledPriceChange;
use App\Models\Service;
use App\Models\User;
use App\Notifications\BusinessSettingsTestNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminControlBoardController extends Controller
{
    private const PERMISSIONS = [
        'manage_users',
        'manage_user_permissions',
        'manage_service_prices',
        'manage_membership_prices',
        'manage_promotions',
        'seller',
    ];

    /**
     * Role preset key => permissions for the admin control board (subset of {@see self::PERMISSIONS}).
     *
     * @var array<string, list<string>>
     */
    private const ROLE_TEMPLATES = [
        'front_desk' => ['manage_service_prices', 'manage_promotions'],
        'provider' => [],
        'seller' => ['seller'],
        'manager' => [
            'manage_user_permissions',
            'manage_service_prices',
            'manage_membership_prices',
            'manage_promotions',
        ],
    ];

    /** @var array<string, string> */
    private const ROLE_TEMPLATE_LABELS = [
        'custom' => 'Custom (use checkboxes)',
        'front_desk' => 'Front desk',
        'provider' => 'Provider',
        'seller' => 'Seller',
        'manager' => 'Manager',
    ];

    /** @var array<string, string> */
    private const AUDIT_ACTION_OPTIONS = [
        'admin.user.created' => 'User created',
        'admin.user.access_updated' => 'User access updated',
        'admin.user.deactivated' => 'User deactivated',
        'admin.user.restored' => 'User restored',
        'admin.impersonation.started' => 'Impersonation started',
        'admin.impersonation.ended' => 'Impersonation ended',
        'admin.service.price_updated' => 'Service price updated',
        'admin.membership.price_updated' => 'Membership price updated',
        'admin.promotion.created' => 'Promotion created',
        'admin.promotion.status_updated' => 'Promotion status updated',
        'admin.promotion.rules_updated' => 'Promotion rules updated',
        'admin.scheduled_price.queued' => 'Scheduled price queued',
        'admin.scheduled_price.cancelled' => 'Scheduled price cancelled',
        'admin.scheduled_price.applied' => 'Scheduled price applied (job)',
        'admin.clinic_settings.updated' => 'Clinic tax / rounding updated',
        'admin.clinic_profile.updated' => 'Clinic profile updated',
        'admin.messaging_settings.updated' => 'Messaging settings updated',
        'admin.messaging_test_sent' => 'Messaging test sent',
        'admin.backup.exported' => 'Backup snapshot exported',
        'admin.customer.exported' => 'Customer data exported',
        'admin.customer.gdpr_deleted' => 'Customer GDPR delete applied',
        'admin.operations.appointment_policy_updated' => 'Operations appointment policy updated',
        'admin.operations.feature_flags_updated' => 'Operations feature flags updated',
    ];

    /** @var array<string, string> */
    private const AUDIT_ENTITY_TYPE_OPTIONS = [
        'user' => 'User',
        'service' => 'Service',
        'membership' => 'Membership',
        'promotion' => 'Promotion',
        'scheduled_price' => 'Scheduled price',
        'clinic_settings' => 'Clinic settings',
        'customer' => 'Customer',
        'backup' => 'Backup',
    ];

    public function index(Request $request): View
    {
        [$auditLogs, $auditHasFilters] = $this->filteredAuditLogs($request);

        return view('admin.control-board', [
            'users' => User::withTrashed()
                ->orderByRaw('deleted_at IS NULL DESC')
                ->orderBy('name')
                ->get(),
            'auditActorOptions' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'services' => Service::query()->orderBy('name')->get(['id', 'name', 'price']),
            'memberships' => Membership::query()->orderBy('name')->get(['id', 'name', 'monthly_price', 'billing_cycle_days']),
            'promotions' => Promotion::query()
                ->with(['targetedServices:id,name', 'targetedMemberships:id,name'])
                ->orderByDesc('created_at')
                ->get(),
            'scheduledPriceChangesPending' => ScheduledPriceChange::query()
                ->where('status', ScheduledPriceChange::STATUS_PENDING)
                ->with(['changeable'])
                ->orderBy('effective_at')
                ->get(),
            'scheduledPriceChangesRecent' => ScheduledPriceChange::query()
                ->where('status', ScheduledPriceChange::STATUS_APPLIED)
                ->with(['changeable'])
                ->orderByDesc('applied_at')
                ->limit(15)
                ->get(),
            'customersForRetention' => Customer::query()
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'email', 'phone']),
            'clinicSettings' => ClinicSetting::current(),
            'permissionOptions' => self::PERMISSIONS,
            'roleTemplateLabels' => self::ROLE_TEMPLATE_LABELS,
            'applyRoleTemplateLabels' => [
                'front_desk' => self::ROLE_TEMPLATE_LABELS['front_desk'],
                'provider' => self::ROLE_TEMPLATE_LABELS['provider'],
                'seller' => self::ROLE_TEMPLATE_LABELS['seller'],
                'manager' => self::ROLE_TEMPLATE_LABELS['manager'],
            ],
            'auditLogs' => $auditLogs,
            'auditHasFilters' => $auditHasFilters,
            'auditFilters' => $this->auditFilterParamsFromRequest($request),
            'auditActionOptions' => self::AUDIT_ACTION_OPTIONS,
            'auditEntityTypeOptions' => self::AUDIT_ENTITY_TYPE_OPTIONS,
            'controlBoardPanelOrder' => $request->user()->dashboardPanelOrder('control_board'),
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'is_admin' => ['nullable', 'boolean'],
            'role_template' => ['nullable', 'string', 'in:custom,front_desk,provider,seller,manager'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'in:'.implode(',', self::PERMISSIONS)],
        ]);

        $template = $validated['role_template'] ?? 'custom';
        if ($template !== 'custom' && isset(self::ROLE_TEMPLATES[$template])) {
            $permissions = $this->normalizePermissionList(self::ROLE_TEMPLATES[$template]);
        } else {
            $permissions = $this->normalizePermissionList($validated['permissions'] ?? []);
        }

        $newUser = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'is_admin' => $request->boolean('is_admin'),
            'permissions' => $permissions,
        ]);

        AdminAuditLog::record(
            $request,
            (int) $request->user()->id,
            'admin.user.created',
            'user',
            $newUser->id,
            null,
            [
                'name' => $newUser->name,
                'email' => $newUser->email,
                'is_admin' => $newUser->is_admin,
                'permissions' => $newUser->permissions ?? [],
                'role_template' => $template,
            ],
        );

        return redirect()->route('admin.control-board')->with('status', 'User created successfully.');
    }

    public function updateUserAccess(Request $request, User $adminUser): RedirectResponse
    {
        if ($adminUser->trashed()) {
            return redirect()
                ->route('admin.control-board')
                ->with('error', 'This account is deactivated. Restore it before changing access.');
        }

        $validated = $request->validate([
            'is_admin' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'in:'.implode(',', self::PERMISSIONS)],
            'apply_role_template' => ['nullable', 'string', 'in:front_desk,provider,seller,manager'],
        ]);

        $apply = trim((string) ($validated['apply_role_template'] ?? ''));
        if ($apply !== '' && isset(self::ROLE_TEMPLATES[$apply])) {
            $newPermissions = $this->normalizePermissionList(self::ROLE_TEMPLATES[$apply]);
        } else {
            $newPermissions = $this->normalizePermissionList($validated['permissions'] ?? []);
        }

        $oldValues = [
            'is_admin' => (bool) $adminUser->is_admin,
            'permissions' => $adminUser->permissions ?? [],
        ];

        $adminUser->update([
            'is_admin' => $request->boolean('is_admin'),
            'permissions' => $newPermissions,
        ]);

        $adminUser->refresh();

        AdminAuditLog::record(
            $request,
            (int) $request->user()->id,
            'admin.user.access_updated',
            'user',
            $adminUser->id,
            $oldValues,
            [
                'is_admin' => (bool) $adminUser->is_admin,
                'permissions' => $adminUser->permissions ?? [],
                'apply_role_template' => $apply !== '' ? $apply : null,
            ],
        );

        return redirect()->route('admin.control-board')->with('status', 'User access updated.');
    }

    public function deactivateUser(Request $request, User $adminUser): RedirectResponse
    {
        abort_unless(
            $request->user()->is_admin || $request->user()->hasAdminPermission('manage_users'),
            403
        );

        if ($adminUser->trashed()) {
            return redirect()->route('admin.control-board')->with('error', 'This account is already deactivated.');
        }

        if ((int) $adminUser->id === (int) $request->user()->id) {
            return redirect()->route('admin.control-board')->with('error', 'You cannot deactivate your own account from here.');
        }

        if ($adminUser->is_admin) {
            $otherActiveAdmins = User::query()
                ->where('is_admin', true)
                ->whereNull('deleted_at')
                ->where('id', '!=', $adminUser->id)
                ->count();
            if ($otherActiveAdmins === 0) {
                return redirect()
                    ->route('admin.control-board')
                    ->with('error', 'You cannot deactivate the only active administrator.');
            }
        }

        AdminAuditLog::record(
            $request,
            (int) $request->user()->id,
            'admin.user.deactivated',
            'user',
            $adminUser->id,
            [
                'email' => $adminUser->email,
                'name' => $adminUser->name,
                'is_admin' => $adminUser->is_admin,
            ],
            ['deactivated' => true],
        );

        $adminUser->delete();

        return redirect()->route('admin.control-board')->with('status', 'User deactivated. They can no longer sign in.');
    }

    public function restoreUser(Request $request, User $adminUser): RedirectResponse
    {
        abort_unless(
            $request->user()->is_admin || $request->user()->hasAdminPermission('manage_users'),
            403
        );

        if (! $adminUser->trashed()) {
            return redirect()->route('admin.control-board')->with('error', 'This account is already active.');
        }

        AdminAuditLog::record(
            $request,
            (int) $request->user()->id,
            'admin.user.restored',
            'user',
            $adminUser->id,
            [
                'email' => $adminUser->email,
                'name' => $adminUser->name,
                'deactivated' => true,
            ],
            ['restored' => true],
        );

        $adminUser->restore();

        return redirect()->route('admin.control-board')->with('status', 'User restored. They can sign in again.');
    }

    public function updateServicePrice(Request $request, Service $service): RedirectResponse
    {
        $validated = $request->validate([
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        $oldPrice = (string) $service->price;

        $service->update([
            'price' => number_format((float) $validated['price'], 2, '.', ''),
        ]);

        AdminAuditLog::record(
            $request,
            (int) $request->user()->id,
            'admin.service.price_updated',
            'service',
            $service->id,
            ['name' => $service->name, 'price' => $oldPrice],
            ['name' => $service->name, 'price' => (string) $service->price],
        );

        return redirect()->route('admin.control-board')->with('status', 'Service price updated.');
    }

    public function updateMembershipPrice(Request $request, Membership $membership): RedirectResponse
    {
        $validated = $request->validate([
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        $oldPrice = (string) $membership->monthly_price;

        $membership->update([
            'monthly_price' => number_format((float) $validated['price'], 2, '.', ''),
        ]);

        AdminAuditLog::record(
            $request,
            (int) $request->user()->id,
            'admin.membership.price_updated',
            'membership',
            $membership->id,
            ['name' => $membership->name, 'monthly_price' => $oldPrice],
            ['name' => $membership->name, 'monthly_price' => (string) $membership->monthly_price],
        );

        return redirect()->route('admin.control-board')->with('status', 'Membership price updated.');
    }

    public function storePromotion(Request $request): RedirectResponse
    {
        $validated = $this->validatedPromotionRules($request);

        $promotion = Promotion::query()->create($validated['attributes']);

        $this->syncPromotionTargets($promotion, $validated['service_ids'], $validated['membership_ids']);

        AdminAuditLog::record(
            $request,
            (int) $request->user()->id,
            'admin.promotion.created',
            'promotion',
            $promotion->id,
            null,
            $this->promotionAuditSnapshot($promotion),
        );

        return redirect()->route('admin.control-board')->with('status', 'Promotion added successfully.');
    }

    public function updatePromotionStatus(Request $request, Promotion $promotion): RedirectResponse
    {
        $oldActive = (bool) $promotion->is_active;

        $promotion->update([
            'is_active' => $request->boolean('is_active'),
        ]);

        $promotion->refresh();

        AdminAuditLog::record(
            $request,
            (int) $request->user()->id,
            'admin.promotion.status_updated',
            'promotion',
            $promotion->id,
            ['name' => $promotion->name, 'is_active' => $oldActive],
            ['name' => $promotion->name, 'is_active' => $promotion->is_active],
        );

        return redirect()->route('admin.control-board')->with('status', 'Promotion status updated.');
    }

    public function updatePromotionRules(Request $request, Promotion $promotion): RedirectResponse
    {
        $validated = $this->validatedPromotionRules($request);

        $before = $this->promotionAuditSnapshot($promotion->load(['targetedServices', 'targetedMemberships']));

        $promotion->update($validated['attributes']);

        $this->syncPromotionTargets($promotion, $validated['service_ids'], $validated['membership_ids']);

        $promotion->refresh();
        $promotion->load(['targetedServices', 'targetedMemberships']);

        AdminAuditLog::record(
            $request,
            (int) $request->user()->id,
            'admin.promotion.rules_updated',
            'promotion',
            $promotion->id,
            $before,
            $this->promotionAuditSnapshot($promotion),
        );

        return redirect()->route('admin.control-board')->with('status', 'Promotion rules updated.');
    }

    public function storeScheduledPriceChange(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'priceable' => ['required', 'regex:/^(service|membership):[1-9][0-9]*$/'],
            'new_price' => ['required', 'numeric', 'min:0'],
            'effective_at' => ['required', 'date', 'after:now'],
        ]);

        [$kind, $id] = explode(':', $validated['priceable'], 2);
        $id = (int) $id;

        $model = match ($kind) {
            'service' => Service::query()->find($id),
            'membership' => Membership::query()->find($id),
            default => null,
        };

        if (! $model) {
            return redirect()
                ->route('admin.control-board')
                ->with('error', 'Service or membership not found for this scheduled change.');
        }

        $change = ScheduledPriceChange::query()->create([
            'changeable_type' => $model::class,
            'changeable_id' => $model->getKey(),
            'new_price' => number_format((float) $validated['new_price'], 2, '.', ''),
            'effective_at' => Carbon::parse($validated['effective_at']),
            'status' => ScheduledPriceChange::STATUS_PENDING,
            'requested_by_user_id' => $request->user()->id,
        ]);

        AdminAuditLog::record(
            $request,
            (int) $request->user()->id,
            'admin.scheduled_price.queued',
            'scheduled_price',
            $change->id,
            null,
            [
                'target' => class_basename($model).' #'.$model->getKey(),
                'new_price' => (string) $change->new_price,
                'effective_at' => $change->effective_at->toIso8601String(),
            ],
        );

        return redirect()
            ->route('admin.control-board')
            ->with('status', 'Price change scheduled. Run `php artisan clinic:apply-scheduled-prices` (or your scheduler) when the effective time passes.');
    }

    public function cancelScheduledPriceChange(Request $request, ScheduledPriceChange $scheduledPriceChange): RedirectResponse
    {
        if ($scheduledPriceChange->status !== ScheduledPriceChange::STATUS_PENDING) {
            return redirect()
                ->route('admin.control-board')
                ->with('error', 'Only pending scheduled changes can be cancelled.');
        }

        AdminAuditLog::record(
            $request,
            (int) $request->user()->id,
            'admin.scheduled_price.cancelled',
            'scheduled_price',
            $scheduledPriceChange->id,
            [
                'new_price' => (string) $scheduledPriceChange->new_price,
                'effective_at' => $scheduledPriceChange->effective_at?->toIso8601String(),
            ],
            ['cancelled' => true],
        );

        $scheduledPriceChange->update(['status' => ScheduledPriceChange::STATUS_CANCELLED]);

        return redirect()->route('admin.control-board')->with('status', 'Scheduled price change cancelled.');
    }

    public function updateClinicTaxSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'default_tax_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'price_rounding_rule' => ['required', 'in:half_up,floor,ceil'],
        ]);

        $settings = ClinicSetting::current();
        $before = [
            'default_tax_rate' => (string) $settings->default_tax_rate,
            'price_rounding_rule' => $settings->price_rounding_rule,
        ];

        $settings->update([
            'default_tax_rate' => number_format((float) $validated['default_tax_rate'], 6, '.', ''),
            'price_rounding_rule' => $validated['price_rounding_rule'],
        ]);

        $settings->refresh();

        AdminAuditLog::record(
            $request,
            (int) $request->user()->id,
            'admin.clinic_settings.updated',
            'clinic_settings',
            $settings->id,
            $before,
            [
                'default_tax_rate' => (string) $settings->default_tax_rate,
                'price_rounding_rule' => $settings->price_rounding_rule,
            ],
        );

        return redirect()->route('admin.control-board')->with('status', 'Tax and rounding settings saved.');
    }

    public function updateClinicProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'clinic_name' => ['required', 'string', 'max:255'],
            'clinic_timezone' => ['required', 'timezone'],
            'business_hours' => ['nullable', 'string'],
            'default_appointment_length_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'reminder_email_lead_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'reminder_sms_lead_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
        ]);

        $settings = ClinicSetting::current();
        $before = [
            'clinic_name' => $settings->clinic_name,
            'clinic_timezone' => $settings->clinic_timezone,
            'business_hours' => $settings->business_hours,
            'default_appointment_length_minutes' => $settings->default_appointment_length_minutes,
            'reminder_email_lead_minutes' => $settings->reminder_email_lead_minutes,
            'reminder_sms_lead_minutes' => $settings->reminder_sms_lead_minutes,
        ];

        $settings->update([
            'clinic_name' => $validated['clinic_name'],
            'clinic_timezone' => $validated['clinic_timezone'],
            'business_hours' => $validated['business_hours'] ?? null,
            'default_appointment_length_minutes' => (int) $validated['default_appointment_length_minutes'],
            'reminder_email_lead_minutes' => (int) $validated['reminder_email_lead_minutes'],
            'reminder_sms_lead_minutes' => (int) $validated['reminder_sms_lead_minutes'],
        ]);

        AdminAuditLog::record(
            $request,
            (int) $request->user()->id,
            'admin.clinic_profile.updated',
            'clinic_settings',
            $settings->id,
            $before,
            [
                'clinic_name' => $settings->clinic_name,
                'clinic_timezone' => $settings->clinic_timezone,
                'business_hours' => $settings->business_hours,
                'default_appointment_length_minutes' => $settings->default_appointment_length_minutes,
                'reminder_email_lead_minutes' => $settings->reminder_email_lead_minutes,
                'reminder_sms_lead_minutes' => $settings->reminder_sms_lead_minutes,
            ],
        );

        return redirect()->route('admin.control-board')->with('status', 'Clinic profile updated.');
    }

    public function updateMessagingSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email_from_address' => ['nullable', 'email', 'max:255'],
            'email_from_name' => ['nullable', 'string', 'max:255'],
            'email_templates_enabled' => ['nullable', 'boolean'],
            'sms_templates_enabled' => ['nullable', 'boolean'],
            'reminder_email_subject_template' => ['nullable', 'string', 'max:255'],
            'reminder_email_body_template' => ['nullable', 'string'],
            'reminder_sms_template' => ['nullable', 'string', 'max:500'],
        ]);

        $settings = ClinicSetting::current();
        $before = [
            'email_from_address' => $settings->email_from_address,
            'email_from_name' => $settings->email_from_name,
            'email_templates_enabled' => $settings->email_templates_enabled,
            'sms_templates_enabled' => $settings->sms_templates_enabled,
            'reminder_email_subject_template' => $settings->reminder_email_subject_template,
            'reminder_email_body_template' => $settings->reminder_email_body_template,
            'reminder_sms_template' => $settings->reminder_sms_template,
        ];

        $settings->update([
            'email_from_address' => $validated['email_from_address'] ?? null,
            'email_from_name' => $validated['email_from_name'] ?? null,
            'email_templates_enabled' => $request->boolean('email_templates_enabled'),
            'sms_templates_enabled' => $request->boolean('sms_templates_enabled'),
            'reminder_email_subject_template' => $validated['reminder_email_subject_template'] ?? null,
            'reminder_email_body_template' => $validated['reminder_email_body_template'] ?? null,
            'reminder_sms_template' => $validated['reminder_sms_template'] ?? null,
        ]);

        AdminAuditLog::record(
            $request,
            (int) $request->user()->id,
            'admin.messaging_settings.updated',
            'clinic_settings',
            $settings->id,
            $before,
            [
                'email_from_address' => $settings->email_from_address,
                'email_from_name' => $settings->email_from_name,
                'email_templates_enabled' => $settings->email_templates_enabled,
                'sms_templates_enabled' => $settings->sms_templates_enabled,
                'reminder_email_subject_template' => $settings->reminder_email_subject_template,
                'reminder_email_body_template' => $settings->reminder_email_body_template,
                'reminder_sms_template' => $settings->reminder_sms_template,
            ],
        );

        return redirect()->route('admin.control-board')->with('status', 'Email/SMS settings updated.');
    }

    public function sendMessagingTest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        $settings = ClinicSetting::current();

        $testUser = new User([
            'name' => 'Test Recipient',
            'email' => $validated['test_email'],
        ]);

        $testUser->notify(new BusinessSettingsTestNotification($settings));

        AdminAuditLog::record(
            $request,
            (int) $request->user()->id,
            'admin.messaging_test_sent',
            'clinic_settings',
            $settings->id,
            null,
            ['test_email' => $validated['test_email']],
        );

        return redirect()->route('admin.control-board')->with('status', 'Test email sent using current messaging settings.');
    }

    public function exportBackupSnapshot(Request $request): StreamedResponse
    {
        $timestamp = now()->format('Ymd_His');
        $payload = [
            'exported_at' => now()->toIso8601String(),
            'clinic_settings' => ClinicSetting::current()->toArray(),
            'customers' => Customer::withTrashed()->get()->toArray(),
            'services' => Service::query()->get()->toArray(),
            'memberships' => Membership::query()->get()->toArray(),
            'appointments' => DB::table('appointments')->get()->toArray(),
            'appointment_services' => DB::table('appointment_services')->get()->toArray(),
            'promotions' => Promotion::query()->with(['targetedServices:id', 'targetedMemberships:id'])->get()->toArray(),
            'scheduled_price_changes' => ScheduledPriceChange::query()->get()->toArray(),
            'users' => User::withTrashed()->get(['id', 'name', 'email', 'is_admin', 'permissions', 'deleted_at'])->toArray(),
        ];

        AdminAuditLog::record(
            $request,
            (int) $request->user()->id,
            'admin.backup.exported',
            'backup',
            null,
            null,
            ['filename' => "beautiskin-backup-{$timestamp}.json"],
        );

        return response()->streamDownload(function () use ($payload): void {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, "beautiskin-backup-{$timestamp}.json", ['Content-Type' => 'application/json']);
    }

    public function exportCustomerData(Request $request, Customer $customer): StreamedResponse
    {
        $customer->load([
            'appointments.services',
            'appointments.staffUser:id,name,email',
            'memberships.membership',
            'waitlistEntries.service',
            'waitlistEntries.staffUser:id,name,email',
        ]);

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'customer' => $customer->toArray(),
            'appointments' => $customer->appointments->toArray(),
            'memberships' => $customer->memberships->toArray(),
            'waitlist_entries' => $customer->waitlistEntries->toArray(),
        ];

        AdminAuditLog::record(
            $request,
            (int) $request->user()->id,
            'admin.customer.exported',
            'customer',
            $customer->id,
            null,
            ['customer_email' => $customer->email],
        );

        $slug = preg_replace('/[^a-z0-9]+/i', '-', trim($customer->first_name.'-'.$customer->last_name)) ?: 'customer';

        return response()->streamDownload(function () use ($payload): void {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, "customer-export-{$slug}-{$customer->id}.json", ['Content-Type' => 'application/json']);
    }

    public function gdprDeleteCustomer(Request $request, Customer $customer): RedirectResponse
    {
        $request->validate([
            'confirm_gdpr_delete' => ['required', 'accepted'],
        ]);

        $customer->loadMissing(['memberships', 'waitlistEntries']);

        $before = [
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
        ];

        DB::transaction(function () use ($customer): void {
            $customer->memberships()->each(function ($membership): void {
                $membership->update([
                    'status' => 'cancelled',
                    'notes' => trim((string) ($membership->notes ? $membership->notes."\n" : '').'Cancelled during GDPR delete workflow.'),
                ]);
            });

            $customer->waitlistEntries()->delete();

            $customer->update([
                'first_name' => 'Deleted',
                'last_name' => 'Customer #'.$customer->id,
                'email' => null,
                'phone' => null,
                'date_of_birth' => null,
                'gender' => null,
                'notes' => 'Personal data removed via GDPR delete workflow on '.now()->toDateTimeString(),
                'gdpr_deleted_at' => now(),
            ]);

            $customer->delete();
        });

        AdminAuditLog::record(
            $request,
            (int) $request->user()->id,
            'admin.customer.gdpr_deleted',
            'customer',
            $customer->id,
            $before,
            ['gdpr_deleted_at' => now()->toIso8601String()],
        );

        return redirect()->route('admin.control-board')->with('status', 'Customer anonymized and soft-deleted under the GDPR workflow.');
    }

    /**
     * @return array{attributes: array<string, mixed>, service_ids: list<int>, membership_ids: list<int>}
     */
    private function validatedPromotionRules(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'discount_type' => ['required', 'in:percent,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'applies_to' => ['required', 'in:all,services,memberships'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'stackable' => ['nullable', 'boolean'],
            'max_discount_cap' => ['nullable', 'numeric', 'min:0'],
            'minimum_purchase' => ['nullable', 'numeric', 'min:0'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'membership_ids' => ['nullable', 'array'],
            'membership_ids.*' => ['integer', 'exists:memberships,id'],
        ]);

        foreach (['max_discount_cap', 'minimum_purchase', 'starts_on', 'ends_on'] as $key) {
            if (array_key_exists($key, $validated) && $validated[$key] === '') {
                $validated[$key] = null;
            }
        }

        if ($validated['discount_type'] === 'percent' && (float) $validated['discount_value'] > 100) {
            throw ValidationException::withMessages([
                'discount_value' => ['Percent discounts cannot exceed 100.'],
            ]);
        }

        $serviceIds = array_values(array_unique(array_map('intval', $validated['service_ids'] ?? [])));
        $membershipIds = array_values(array_unique(array_map('intval', $validated['membership_ids'] ?? [])));

        $attributes = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'discount_type' => $validated['discount_type'],
            'discount_value' => number_format((float) $validated['discount_value'], 2, '.', ''),
            'applies_to' => $validated['applies_to'],
            'starts_on' => $validated['starts_on'] ?? null,
            'ends_on' => $validated['ends_on'] ?? null,
            'stackable' => $request->boolean('stackable'),
            'max_discount_cap' => isset($validated['max_discount_cap']) && $validated['max_discount_cap'] !== null && $validated['max_discount_cap'] !== ''
                ? number_format((float) $validated['max_discount_cap'], 2, '.', '')
                : null,
            'minimum_purchase' => isset($validated['minimum_purchase']) && $validated['minimum_purchase'] !== null && $validated['minimum_purchase'] !== ''
                ? number_format((float) $validated['minimum_purchase'], 2, '.', '')
                : null,
            'is_active' => $request->boolean('is_active', true),
        ];

        return [
            'attributes' => $attributes,
            'service_ids' => $serviceIds,
            'membership_ids' => $membershipIds,
        ];
    }

    /**
     * @param  list<int>  $serviceIds
     * @param  list<int>  $membershipIds
     */
    private function syncPromotionTargets(Promotion $promotion, array $serviceIds, array $membershipIds): void
    {
        if ($promotion->applies_to === 'all') {
            $promotion->targetedServices()->sync([]);
            $promotion->targetedMemberships()->sync([]);

            return;
        }

        if ($promotion->applies_to === 'services') {
            $promotion->targetedServices()->sync($serviceIds);
            $promotion->targetedMemberships()->sync([]);

            return;
        }

        $promotion->targetedServices()->sync([]);
        $promotion->targetedMemberships()->sync($membershipIds);
    }

    /**
     * @return array<string, mixed>
     */
    private function promotionAuditSnapshot(Promotion $promotion): array
    {
        return [
            'name' => $promotion->name,
            'discount_type' => $promotion->discount_type,
            'discount_value' => (string) $promotion->discount_value,
            'applies_to' => $promotion->applies_to,
            'stackable' => (bool) $promotion->stackable,
            'max_discount_cap' => $promotion->max_discount_cap !== null ? (string) $promotion->max_discount_cap : null,
            'minimum_purchase' => $promotion->minimum_purchase !== null ? (string) $promotion->minimum_purchase : null,
            'starts_on' => $promotion->starts_on?->toDateString(),
            'ends_on' => $promotion->ends_on?->toDateString(),
            'is_active' => (bool) $promotion->is_active,
            'service_ids' => $promotion->relationLoaded('targetedServices')
                ? $promotion->targetedServices->pluck('id')->values()->all()
                : [],
            'membership_ids' => $promotion->relationLoaded('targetedMemberships')
                ? $promotion->targetedMemberships->pluck('id')->values()->all()
                : [],
        ];
    }

    /**
     * @return array{0: Collection<int, AdminAuditLog>, 1: bool}
     */
    private function filteredAuditLogs(Request $request): array
    {
        $hasFilters = false;

        $query = AdminAuditLog::query()->with('actor:id,name,email');

        $actorId = (int) $request->query('audit_actor_id', 0);
        if ($actorId > 0) {
            $query->where('actor_user_id', $actorId);
            $hasFilters = true;
        }

        $action = trim((string) $request->query('audit_action', ''));
        if ($action !== '' && array_key_exists($action, self::AUDIT_ACTION_OPTIONS)) {
            $query->where('action', $action);
            $hasFilters = true;
        }

        $entityType = trim((string) $request->query('audit_entity_type', ''));
        if ($entityType !== '' && array_key_exists($entityType, self::AUDIT_ENTITY_TYPE_OPTIONS)) {
            $query->where('entity_type', $entityType);
            $hasFilters = true;
        }

        $entityId = (int) $request->query('audit_entity_id', 0);
        if ($entityId > 0) {
            $query->where('entity_id', $entityId);
            $hasFilters = true;
        }

        $dateFrom = $this->parseAuditDate((string) $request->query('audit_date_from', ''));
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
            $hasFilters = true;
        }

        $dateTo = $this->parseAuditDate((string) $request->query('audit_date_to', ''));
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
            $hasFilters = true;
        }

        $search = trim((string) $request->query('audit_search', ''));
        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $like = '%'.$escaped.'%';
            $query->where(function (Builder $q) use ($like, $search) {
                $q->where('action', 'like', $like)
                    ->orWhere('entity_type', 'like', $like)
                    ->orWhere('ip_address', 'like', $like);
                if (ctype_digit($search)) {
                    $q->orWhere('entity_id', (int) $search);
                }
            });
            $hasFilters = true;
        }

        $limit = $hasFilters ? 100 : 50;

        return [$query->orderByDesc('created_at')->limit($limit)->get(), $hasFilters];
    }

    /**
     * @return array{
     *     audit_actor_id: int,
     *     audit_action: string,
     *     audit_entity_type: string,
     *     audit_entity_id: int,
     *     audit_date_from: string,
     *     audit_date_to: string,
     *     audit_search: string
     * }
     */
    private function auditFilterParamsFromRequest(Request $request): array
    {
        return [
            'audit_actor_id' => (int) $request->query('audit_actor_id', 0),
            'audit_action' => trim((string) $request->query('audit_action', '')),
            'audit_entity_type' => trim((string) $request->query('audit_entity_type', '')),
            'audit_entity_id' => (int) $request->query('audit_entity_id', 0),
            'audit_date_from' => trim((string) $request->query('audit_date_from', '')),
            'audit_date_to' => trim((string) $request->query('audit_date_to', '')),
            'audit_search' => trim((string) $request->query('audit_search', '')),
        ];
    }

    private function parseAuditDate(string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function normalizePermissionList(array $permissions): array
    {
        $unique = array_unique($permissions);

        return array_values(array_filter(
            $unique,
            fn ($permission) => is_string($permission) && in_array($permission, self::PERMISSIONS, true)
        ));
    }
}
