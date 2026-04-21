@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Administrator Control Board</h1>
        <p class="mt-1 text-sm text-slate-600">Manage users, permissions, prices, and promotions from one place.</p>
        <p class="mt-2 text-xs text-slate-500">
            <span class="font-semibold text-slate-600">Tip:</span> Drag any panel using the ⋮⋮ handle to reorder. Your layout is saved for this account.
        </p>
    </div>

    <div
        id="control-board-dashboard-sortable"
        class="grid gap-6"
        data-sortable-dashboard="control_board"
        data-save-dashboard-url="{{ route('user.dashboard-layout.update') }}"
        data-initial-order='@json($controlBoardPanelOrder)'
    >
        <section data-dashboard-panel="ctrl-audit" class="relative crm-panel p-5">
            <h2 class="text-lg font-semibold">Recent activity</h2>
            <p class="mt-1 text-xs text-slate-500">
                @if ($auditHasFilters)
                    Filtered results (up to 100 rows). Passwords are never logged.
                @else
                    Latest 50 changes from this control board (who, what, old vs new). Passwords are never logged.
                @endif
            </p>

            <form method="GET" action="{{ route('admin.control-board') }}" class="mt-4 grid gap-3 rounded-lg border border-slate-100 bg-slate-50/80 p-4 md:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Actor</label>
                    <select name="audit_actor_id" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="0">Anyone</option>
                        @foreach ($auditActorOptions as $u)
                            <option value="{{ $u->id }}" @selected((int) ($auditFilters['audit_actor_id'] ?? 0) === (int) $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Action</label>
                    <select name="audit_action" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Any action</option>
                        @foreach ($auditActionOptions as $value => $label)
                            <option value="{{ $value }}" @selected(($auditFilters['audit_action'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Target type</label>
                    <select name="audit_entity_type" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Any type</option>
                        @foreach ($auditEntityTypeOptions as $value => $label)
                            <option value="{{ $value }}" @selected(($auditFilters['audit_entity_type'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Target ID</label>
                    <input
                        name="audit_entity_id"
                        type="number"
                        min="1"
                        value="{{ ($auditFilters['audit_entity_id'] ?? 0) > 0 ? $auditFilters['audit_entity_id'] : '' }}"
                        placeholder="e.g. 12"
                        class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
                    >
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">From date</label>
                    <input name="audit_date_from" type="date" value="{{ $auditFilters['audit_date_from'] ?? '' }}" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">To date</label>
                    <input name="audit_date_to" type="date" value="{{ $auditFilters['audit_date_to'] ?? '' }}" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
                    <input
                        name="audit_search"
                        value="{{ $auditFilters['audit_search'] ?? '' }}"
                        placeholder="Action text, entity type, IP, or numeric ID"
                        class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
                    >
                </div>
                <div class="flex flex-wrap items-end gap-2 md:col-span-2 lg:col-span-2">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Apply filters</button>
                    @if ($auditHasFilters)
                        <a href="{{ route('admin.control-board') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Clear filters</a>
                    @endif
                </div>
            </form>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-slate-500">
                        <tr>
                            <th class="py-2 pr-3 font-medium">When</th>
                            <th class="py-2 pr-3 font-medium">Actor</th>
                            <th class="py-2 pr-3 font-medium">Action</th>
                            <th class="py-2 pr-3 font-medium">Target</th>
                            <th class="py-2 pr-3 font-medium">Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($auditLogs as $log)
                            <tr class="border-b border-slate-100 align-top">
                                <td class="py-2 pr-3 whitespace-nowrap text-slate-600">{{ $log->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                                <td class="py-2 pr-3">
                                    <span class="font-medium text-slate-800">{{ $log->actor?->name ?? '—' }}</span>
                                    <span class="block text-xs text-slate-500">{{ $log->actor?->email }}</span>
                                </td>
                                <td class="py-2 pr-3 font-mono text-xs text-slate-700">{{ $log->action }}</td>
                                <td class="py-2 pr-3 text-xs text-slate-600">{{ $log->entity_type }}{{ $log->entity_id ? ' #' . $log->entity_id : '' }}</td>
                                <td class="py-2 pr-3 max-w-md">
                                    @if ($log->old_values || $log->new_values)
                                        <details class="text-xs">
                                            <summary class="cursor-pointer font-medium text-pink-700 hover:text-pink-800">View JSON</summary>
                                            <pre class="mt-2 max-h-40 overflow-auto rounded bg-slate-50 p-2 text-[11px] text-slate-700">{{ json_encode(['old' => $log->old_values, 'new' => $log->new_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </details>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-slate-500">
                                    @if ($auditHasFilters)
                                        No entries match these filters.
                                    @else
                                        No audit entries yet. Actions you take here will appear in this log.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('admin.partials.dashboard-drag-handle')
        </section>

        <section data-dashboard-panel="ctrl-user-create" class="relative crm-panel p-5">
            <h2 class="text-lg font-semibold">Add New User</h2>
            <form method="POST" action="{{ route('admin.users.store') }}" class="mt-4 grid gap-3 md:grid-cols-2">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium">Name</label>
                    <input name="name" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Email</label>
                    <input name="email" type="email" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Password</label>
                    <input name="password" type="password" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Confirm Password</label>
                    <input name="password_confirmation" type="password" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Role template</label>
                    <select name="role_template" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($roleTemplateLabels as $key => $label)
                            <option value="{{ $key }}" @selected(old('role_template', 'custom') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Choosing a preset fills permissions for you. Pick Custom to use the checkboxes below.</p>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium">Initial Permissions</label>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($permissionOptions as $permission)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="permissions[]" value="{{ $permission }}">
                                <span>{{ str_replace('_', ' ', $permission) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input type="hidden" name="is_admin" value="0">
                        <input type="checkbox" name="is_admin" value="1">
                        Administrator
                    </label>
                </div>
                <div class="md:col-span-2">
                    <button class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">Create User</button>
                </div>
            </form>
            @include('admin.partials.dashboard-drag-handle')
        </section>

        <section data-dashboard-panel="ctrl-user-permissions" class="relative crm-panel p-5">
            <h2 class="text-lg font-semibold">User Permissions</h2>
            <p class="mt-1 text-xs text-slate-500">Deactivated users cannot sign in. Use “View as” only for non-admin accounts; all actions are written to the audit log.</p>
            <div class="mt-4 space-y-4">
                @foreach ($users as $adminUser)
                    <div class="rounded-lg border border-slate-200 p-4 {{ $adminUser->trashed() ? 'bg-slate-50 opacity-90' : '' }}">
                        <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="font-medium">{{ $adminUser->name }}</p>
                                <p class="text-xs text-slate-500">{{ $adminUser->email }}</p>
                                @if ($adminUser->trashed())
                                    <span class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Deactivated</span>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @if (auth()->user()->is_admin && ! $adminUser->trashed() && ! $adminUser->is_admin && (int) $adminUser->id !== (int) auth()->id() && ! session()->has('impersonator_id'))
                                    <form method="POST" action="{{ route('admin.impersonate.start', $adminUser) }}" class="inline" onsubmit="return confirm(@json('Start viewing the app as ' . $adminUser->name . '? You can return from the banner at the top.'));">
                                        @csrf
                                        <button type="submit" class="rounded-md border border-pink-300 bg-pink-50 px-3 py-1.5 text-xs font-semibold text-pink-800 hover:bg-pink-100">View as</button>
                                    </form>
                                @endif
                                @if (! $adminUser->trashed() && ((int) $adminUser->id !== (int) auth()->id()) && (auth()->user()->is_admin || auth()->user()->hasAdminPermission('manage_users')))
                                    <form method="POST" action="{{ route('admin.users.deactivate', $adminUser) }}" class="inline" onsubmit="return confirm(@json('Deactivate ' . $adminUser->name . '? They will no longer be able to sign in.'));">
                                        @csrf
                                        <button type="submit" class="rounded-md border border-rose-300 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Deactivate</button>
                                    </form>
                                @endif
                                @if ($adminUser->trashed() && (auth()->user()->is_admin || auth()->user()->hasAdminPermission('manage_users')))
                                    <form method="POST" action="{{ route('admin.users.restore', $adminUser) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="rounded-md border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100">Restore</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        @if (! $adminUser->trashed())
                            <form method="POST" action="{{ route('admin.users.access.update', $adminUser) }}" class="space-y-3">
                                @csrf
                                @method('PATCH')
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="hidden" name="is_admin" value="0">
                                        <input type="checkbox" name="is_admin" value="1" @checked($adminUser->is_admin)>
                                        Administrator
                                    </label>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Apply preset (optional)</label>
                                    <select name="apply_role_template" class="w-full max-w-md rounded-md border border-slate-300 px-3 py-2 text-sm">
                                        <option value="">Use checkboxes below</option>
                                        @foreach ($applyRoleTemplateLabels as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-slate-500">If you pick a preset and save, it replaces the permission checkboxes for that user.</p>
                                </div>
                                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($permissionOptions as $permission)
                                        <label class="flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                name="permissions[]"
                                                value="{{ $permission }}"
                                                @checked(in_array($permission, $adminUser->permissions ?? [], true))
                                            >
                                            <span>{{ str_replace('_', ' ', $permission) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <div>
                                    <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Save Access</button>
                                </div>
                            </form>
                        @else
                            <p class="text-sm text-slate-600">Restore this user to edit permissions or impersonate.</p>
                        @endif
                    </div>
                @endforeach
            </div>
            @include('admin.partials.dashboard-drag-handle')
        </section>

        <section data-dashboard-panel="ctrl-clinic-profile" class="relative crm-panel p-5">
            <h2 class="text-lg font-semibold">Clinic profile</h2>
            <p class="mt-1 text-xs text-slate-500">Core business settings used for reminders, scheduling defaults, and clinic-facing branding.</p>
            <form method="POST" action="{{ route('admin.clinic-profile.update') }}" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')
                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Clinic name</label>
                        <input name="clinic_name" value="{{ $clinicSettings->clinic_name }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Timezone</label>
                        <input name="clinic_timezone" value="{{ $clinicSettings->clinic_timezone }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Default appointment length (min)</label>
                        <input name="default_appointment_length_minutes" type="number" min="5" max="480" value="{{ $clinicSettings->default_appointment_length_minutes }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Email reminder lead (min)</label>
                        <input name="reminder_email_lead_minutes" type="number" min="0" max="10080" value="{{ $clinicSettings->reminder_email_lead_minutes }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">SMS reminder lead (min)</label>
                        <input name="reminder_sms_lead_minutes" type="number" min="0" max="10080" value="{{ $clinicSettings->reminder_sms_lead_minutes }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Business hours</label>
                    <textarea name="business_hours" rows="4" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ $clinicSettings->business_hours }}</textarea>
                </div>
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Save clinic profile</button>
            </form>
            @include('admin.partials.dashboard-drag-handle')
        </section>

        <section data-dashboard-panel="ctrl-messaging" class="relative crm-panel p-5">
            <h2 class="text-lg font-semibold">Email / SMS settings</h2>
            <p class="mt-1 text-xs text-slate-500">Templates drive appointment reminders, staff-initiated follow-ups, and &ldquo;we missed you&rdquo; messages from the customer timeline. Outbound SMS uses Twilio when <code class="rounded bg-slate-100 px-1">TWILIO_*</code> is set in <code class="rounded bg-slate-100 px-1">.env</code>. Inbound email (SendGrid Inbound Parse) and inbound SMS (Twilio) append to the customer timeline when the sender matches a customer.</p>
            @if ($clinicSettings->webhook_inbound_token)
                <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4 text-xs text-slate-700">
                    <p class="font-semibold text-slate-900">Inbound webhook URLs (append your secret token)</p>
                    <p class="mt-2 break-all"><span class="font-medium">Twilio SMS:</span> <code>{{ url('/webhooks/twilio/sms?token='.urlencode($clinicSettings->webhook_inbound_token)) }}</code></p>
                    <p class="mt-2 break-all"><span class="font-medium">SendGrid Inbound Parse:</span> <code>{{ url('/webhooks/sendgrid/inbound?token='.urlencode($clinicSettings->webhook_inbound_token)) }}</code></p>
                    <p class="mt-2 text-slate-600">Alternatively send header <code class="rounded bg-white px-1 ring-1 ring-slate-200">X-Beautiskin-Webhook-Token: {{ $clinicSettings->webhook_inbound_token }}</code> instead of a query parameter.</p>
                </div>
            @endif
            <form method="POST" action="{{ route('admin.messaging-settings.update') }}" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Email from address</label>
                        <input name="email_from_address" type="email" value="{{ $clinicSettings->email_from_address }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Email from name</label>
                        <input name="email_from_name" value="{{ $clinicSettings->email_from_name }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input type="hidden" name="email_templates_enabled" value="0">
                        <input type="checkbox" name="email_templates_enabled" value="1" @checked($clinicSettings->email_templates_enabled)>
                        Enable custom email templates
                    </label>
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input type="hidden" name="sms_templates_enabled" value="0">
                        <input type="checkbox" name="sms_templates_enabled" value="1" @checked($clinicSettings->sms_templates_enabled)>
                        Enable custom SMS templates
                    </label>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Reminder email subject template</label>
                    <input name="reminder_email_subject_template" value="{{ $clinicSettings->reminder_email_subject_template }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Reminder email body template</label>
                    <textarea name="reminder_email_body_template" rows="5" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ $clinicSettings->reminder_email_body_template }}</textarea>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Reminder SMS template</label>
                    <textarea name="reminder_sms_template" rows="3" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ $clinicSettings->reminder_sms_template }}</textarea>
                </div>
                <p class="text-xs font-semibold text-slate-700">Follow-up templates (timeline &ldquo;Send using template&rdquo;)</p>
                <div>
                    <label class="mb-1 block text-sm font-medium">Follow-up email subject</label>
                    <input name="followup_email_subject_template" value="{{ $clinicSettings->followup_email_subject_template }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Follow-up email body</label>
                    <textarea name="followup_email_body_template" rows="4" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ $clinicSettings->followup_email_body_template }}</textarea>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Follow-up SMS</label>
                    <textarea name="followup_sms_template" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ $clinicSettings->followup_sms_template }}</textarea>
                </div>
                <p class="text-xs font-semibold text-slate-700">We missed you (no-show) templates</p>
                <div>
                    <label class="mb-1 block text-sm font-medium">No-show email subject</label>
                    <input name="no_show_email_subject_template" value="{{ $clinicSettings->no_show_email_subject_template }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">No-show email body</label>
                    <textarea name="no_show_email_body_template" rows="4" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ $clinicSettings->no_show_email_body_template }}</textarea>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">No-show SMS</label>
                    <textarea name="no_show_sms_template" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ $clinicSettings->no_show_sms_template }}</textarea>
                </div>
                <p class="text-xs text-slate-500">Available placeholders: <code>{{ '{' }}{clinic_name}{{ '}' }}</code>, <code>{{ '{' }}{customer_name}{{ '}' }}</code>, <code>{{ '{' }}{first_name}{{ '}' }}</code>, <code>{{ '{' }}{last_name}{{ '}' }}</code>, <code>{{ '{' }}{date}{{ '}' }}</code>, <code>{{ '{' }}{start_time}{{ '}' }}</code>, <code>{{ '{' }}{end_time}{{ '}' }}</code>, <code>{{ '{' }}{staff_name}{{ '}' }}</code>, <code>{{ '{' }}{services}{{ '}' }}</code>. Appointment-specific fields are filled when you choose an appointment for the send (or when sending a reminder from the calendar).</p>
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Save messaging settings</button>
            </form>
            <form method="POST" action="{{ route('admin.messaging-settings.test-send') }}" class="mt-4 flex flex-col gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 md:flex-row md:items-end">
                @csrf
                <div class="flex-1">
                    <label class="mb-1 block text-sm font-medium">Send test email to</label>
                    <input name="test_email" type="email" placeholder="you@example.com" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                </div>
                <button type="submit" class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">Send test email</button>
            </form>
            @include('admin.partials.dashboard-drag-handle')
        </section>

        <section data-dashboard-panel="ctrl-data-retention" class="relative crm-panel p-5">
            <h2 class="text-lg font-semibold">Data retention & exports</h2>
            <p class="mt-1 text-xs text-slate-500">Create a JSON backup snapshot, export a single customer record bundle, or apply a GDPR-style delete that anonymizes personal data while preserving linked appointments.</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('admin.backup.export') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Download backup snapshot</a>
            </div>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <form method="GET" action="{{ route('admin.customers.export', $customersForRetention->first() ?? 0) }}" class="rounded-lg border border-slate-200 p-4" onsubmit="this.action = '{{ url('/admin/customers') }}/' + this.querySelector('[name=customer_id]').value + '/export';">
                    <label class="mb-1 block text-sm font-medium">Export customer data</label>
                    <select name="customer_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                        @foreach ($customersForRetention as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->first_name }} {{ $customer->last_name }}{{ $customer->email ? ' (' . $customer->email . ')' : '' }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="mt-3 rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Download customer JSON</button>
                </form>
                <form method="POST" action="{{ route('admin.customers.gdpr-delete', $customersForRetention->first() ?? 0) }}" class="rounded-lg border border-rose-200 bg-rose-50/40 p-4" onsubmit="this.action = '{{ url('/admin/customers') }}/' + this.querySelector('[name=customer_id]').value + '/gdpr-delete'; return confirm('Apply GDPR delete to this customer? This anonymizes personal data and soft-deletes the customer record.');">
                    @csrf
                    <label class="mb-1 block text-sm font-medium">GDPR delete customer</label>
                    <select name="customer_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                        @foreach ($customersForRetention as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->first_name }} {{ $customer->last_name }}{{ $customer->email ? ' (' . $customer->email . ')' : '' }}</option>
                        @endforeach
                    </select>
                    <label class="mt-3 flex items-center gap-2 text-sm font-medium text-rose-900">
                        <input type="checkbox" name="confirm_gdpr_delete" value="1" required>
                        I understand this anonymizes personal data and cannot be automatically undone.
                    </label>
                    <button type="submit" class="mt-3 rounded-md bg-rose-700 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-800">Run GDPR delete</button>
                </form>
            </div>
            @include('admin.partials.dashboard-drag-handle')
        </section>

        <section data-dashboard-panel="ctrl-tax" class="relative crm-panel p-5">
            <h2 class="text-lg font-semibold">Tax & price rounding</h2>
            <p class="mt-1 text-xs text-slate-500">Default sales tax rate as a decimal (e.g. 0.0825 for 8.25%). Used for future checkout / quotes; list prices stay tax-exclusive unless you document otherwise.</p>
            <form method="POST" action="{{ route('admin.clinic-settings.update') }}" class="mt-4 grid gap-3 md:grid-cols-3">
                @csrf
                @method('PATCH')
                <div>
                    <label class="mb-1 block text-sm font-medium">Default tax rate</label>
                    <input name="default_tax_rate" type="number" step="0.000001" min="0" max="1" value="{{ number_format((float) $clinicSettings->default_tax_rate, 6, '.', '') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Rounding rule</label>
                    <select name="price_rounding_rule" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="half_up" @selected($clinicSettings->price_rounding_rule === 'half_up')>Half up (nearest cent)</option>
                        <option value="floor" @selected($clinicSettings->price_rounding_rule === 'floor')>Floor (down)</option>
                        <option value="ceil" @selected($clinicSettings->price_rounding_rule === 'ceil')>Ceil (up)</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Save settings</button>
                </div>
            </form>
            @include('admin.partials.dashboard-drag-handle')
        </section>

        <section data-dashboard-panel="ctrl-scheduled-prices" class="relative crm-panel p-5">
            <h2 class="text-lg font-semibold">Scheduled price changes</h2>
            <p class="mt-1 text-xs text-slate-500">Queue a new price with an effective date/time. Run <code class="rounded bg-slate-100 px-1">php artisan schedule:run</code> every minute in production, or run <code class="rounded bg-slate-100 px-1">php artisan clinic:apply-scheduled-prices</code> manually. The app registers this command every 15 minutes in <code class="rounded bg-slate-100 px-1">routes/console.php</code>.</p>
            <form method="POST" action="{{ route('admin.scheduled-prices.store') }}" class="mt-4 grid gap-3 md:grid-cols-3">
                @csrf
                <div class="md:col-span-3">
                    <label class="mb-1 block text-sm font-medium">Service or membership</label>
                    <select name="priceable" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                        <optgroup label="Services">
                            @foreach ($services as $service)
                                <option value="service:{{ $service->id }}">{{ $service->name }} ({{ number_format((float) $service->price, 2) }})</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Memberships">
                            @foreach ($memberships as $membership)
                                <option value="membership:{{ $membership->id }}">{{ $membership->name }} ({{ number_format((float) $membership->monthly_price, 2) }})</option>
                            @endforeach
                        </optgroup>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">New price (USD)</label>
                    <input name="new_price" type="number" step="0.01" min="0" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Effective at</label>
                    <input name="effective_at" type="datetime-local" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                </div>
                <div class="md:col-span-3">
                    <button type="submit" class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">Queue change</button>
                </div>
            </form>

            <div class="mt-6 grid gap-6 md:grid-cols-2">
                <div>
                    <h3 class="text-sm font-semibold text-slate-800">Pending</h3>
                    <ul class="mt-2 space-y-2 text-sm">
                        @forelse ($scheduledPriceChangesPending as $change)
                            @php
                                $target = $change->changeable;
                                $targetLabel = $target instanceof \App\Models\Service
                                    ? 'Service: ' . $target->name
                                    : ($target instanceof \App\Models\Membership ? 'Membership: ' . $target->name : 'Unknown');
                            @endphp
                            <li class="rounded-md border border-slate-200 p-2">
                                <div class="font-medium">{{ $targetLabel }}</div>
                                <div class="text-xs text-slate-600">${{ number_format((float) $change->new_price, 2) }} · {{ $change->effective_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</div>
                                <form method="POST" action="{{ route('admin.scheduled-prices.cancel', $change) }}" class="mt-2" onsubmit="return confirm(@json('Cancel this scheduled change?'));">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700">Cancel</button>
                                </form>
                            </li>
                        @empty
                            <li class="text-xs text-slate-500">No pending changes.</li>
                        @endforelse
                    </ul>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-slate-800">Recently applied</h3>
                    <ul class="mt-2 space-y-2 text-sm">
                        @forelse ($scheduledPriceChangesRecent as $change)
                            @php
                                $target = $change->changeable;
                                $targetLabel = $target instanceof \App\Models\Service
                                    ? 'Service: ' . $target->name
                                    : ($target instanceof \App\Models\Membership ? 'Membership: ' . $target->name : 'Unknown');
                            @endphp
                            <li class="rounded-md border border-slate-100 bg-slate-50 p-2 text-xs text-slate-600">
                                <span class="font-medium text-slate-800">{{ $targetLabel }}</span>
                                · ${{ number_format((float) $change->new_price, 2) }}
                                · {{ $change->applied_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                            </li>
                        @empty
                            <li class="text-xs text-slate-500">Nothing applied yet.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
            @include('admin.partials.dashboard-drag-handle')
        </section>

        <section data-dashboard-panel="ctrl-service-prices" class="relative crm-panel p-5">
            <h2 class="text-lg font-semibold">Service prices (live)</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                @foreach ($services as $service)
                    <form method="POST" action="{{ route('admin.services.price.update', $service) }}" class="rounded-lg border border-slate-200 p-3">
                        @csrf
                        @method('PATCH')
                        <label class="mb-1 block text-sm font-medium">{{ $service->name }}</label>
                        <div class="flex gap-2">
                            <input name="price" type="number" step="0.01" min="0" value="{{ number_format((float) $service->price, 2, '.', '') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                            <button class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Save</button>
                        </div>
                    </form>
                @endforeach
            </div>
            @include('admin.partials.dashboard-drag-handle')
        </section>

        <section data-dashboard-panel="ctrl-membership-prices" class="relative crm-panel p-5">
            <h2 class="text-lg font-semibold">Membership prices (live)</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                @foreach ($memberships as $membership)
                    <form method="POST" action="{{ route('admin.memberships.price.update', $membership) }}" class="rounded-lg border border-slate-200 p-3">
                        @csrf
                        @method('PATCH')
                        <label class="mb-1 block text-sm font-medium">
                            {{ $membership->name }} ({{ (int) $membership->billing_cycle_days >= 365 ? 'Yearly' : 'Monthly' }})
                        </label>
                        <div class="flex gap-2">
                            <input name="price" type="number" step="0.01" min="0" value="{{ number_format((float) $membership->monthly_price, 2, '.', '') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                            <button class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Save</button>
                        </div>
                    </form>
                @endforeach
            </div>
            @include('admin.partials.dashboard-drag-handle')
        </section>

        <section data-dashboard-panel="ctrl-promotions" class="relative crm-panel p-5">
            <h2 class="text-lg font-semibold">Promotions & discounts</h2>
            <p class="mt-1 text-xs text-slate-500">Stackable promotions can combine with others (enforced at checkout later). Max discount cap is in USD per application. Minimum purchase is cart subtotal before discount. For “Services” or “Memberships”, pick specific items below or leave all unchecked to mean the whole catalog in that group.</p>
            <form method="POST" action="{{ route('admin.promotions.store') }}" class="mt-4 space-y-4">
                @csrf
                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Name</label>
                        <input name="name" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Discount type</label>
                        <select name="discount_type" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="percent">Percent</option>
                            <option value="fixed">Fixed amount</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Value</label>
                        <input name="discount_value" type="number" step="0.01" min="0" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Applies to</label>
                        <select name="applies_to" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="all">All</option>
                            <option value="services">Services</option>
                            <option value="memberships">Memberships</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Starts on</label>
                        <input name="starts_on" type="date" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Ends on</label>
                        <input name="ends_on" type="date" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Max discount cap (USD)</label>
                        <input name="max_discount_cap" type="number" step="0.01" min="0" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="No cap">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Minimum purchase (USD)</label>
                        <input name="minimum_purchase" type="number" step="0.01" min="0" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="None">
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 text-sm font-medium">
                            <input type="hidden" name="stackable" value="0">
                            <input type="checkbox" name="stackable" value="1" class="rounded border-slate-300">
                            Stackable with other promotions
                        </label>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Description</label>
                    <textarea name="description" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Target services (optional)</p>
                        <div class="max-h-40 space-y-2 overflow-y-auto rounded-md border border-slate-200 p-3">
                            @foreach ($services as $service)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="service_ids[]" value="{{ $service->id }}">
                                    <span>{{ $service->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Target memberships (optional)</p>
                        <div class="max-h-40 space-y-2 overflow-y-auto rounded-md border border-slate-200 p-3">
                            @foreach ($memberships as $membership)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="membership_ids[]" value="{{ $membership->id }}">
                                    <span>{{ $membership->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" checked>
                        Active
                    </label>
                </div>
                <div>
                    <button type="submit" class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">Add promotion</button>
                </div>
            </form>

            <div class="mt-8 space-y-4">
                @forelse ($promotions as $promotion)
                    <div class="rounded-lg border border-slate-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">{{ $promotion->name }} ({{ $promotion->discount_type === 'percent' ? rtrim(rtrim((string) $promotion->discount_value, '0'), '.') . '%' : '$' . number_format((float) $promotion->discount_value, 2) }})</p>
                                <p class="text-xs text-slate-500">
                                    {{ $promotion->applies_to }}
                                    @if ($promotion->stackable) · stackable @else · not stackable @endif
                                    @if ($promotion->max_discount_cap) · cap ${{ number_format((float) $promotion->max_discount_cap, 2) }} @endif
                                    @if ($promotion->minimum_purchase) · min ${{ number_format((float) $promotion->minimum_purchase, 2) }} @endif
                                    · {{ $promotion->starts_on?->format('Y-m-d') ?: 'No start' }} → {{ $promotion->ends_on?->format('Y-m-d') ?: 'No end' }}
                                </p>
                                @if ($promotion->applies_to === 'services' && $promotion->targetedServices->isNotEmpty())
                                    <p class="mt-1 text-xs text-slate-600">Services: {{ $promotion->targetedServices->pluck('name')->implode(', ') }}</p>
                                @elseif ($promotion->applies_to === 'services')
                                    <p class="mt-1 text-xs text-slate-600">Services: all</p>
                                @endif
                                @if ($promotion->applies_to === 'memberships' && $promotion->targetedMemberships->isNotEmpty())
                                    <p class="mt-1 text-xs text-slate-600">Memberships: {{ $promotion->targetedMemberships->pluck('name')->implode(', ') }}</p>
                                @elseif ($promotion->applies_to === 'memberships')
                                    <p class="mt-1 text-xs text-slate-600">Memberships: all</p>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('admin.promotions.status.update', $promotion) }}" class="flex flex-wrap items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" @checked($promotion->is_active)>
                                    Active
                                </label>
                                <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Save</button>
                            </form>
                        </div>
                        <details class="mt-3 border-t border-slate-100 pt-3">
                            <summary class="cursor-pointer text-sm font-semibold text-pink-700 hover:text-pink-800">Edit rules</summary>
                            <form method="POST" action="{{ route('admin.promotions.update', $promotion) }}" class="mt-3 space-y-3">
                                @csrf
                                @method('PUT')
                                <div class="grid gap-3 md:grid-cols-3">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium">Name</label>
                                        <input name="name" value="{{ $promotion->name }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium">Discount type</label>
                                        <select name="discount_type" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                            <option value="percent" @selected($promotion->discount_type === 'percent')>Percent</option>
                                            <option value="fixed" @selected($promotion->discount_type === 'fixed')>Fixed amount</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium">Value</label>
                                        <input name="discount_value" type="number" step="0.01" min="0" value="{{ number_format((float) $promotion->discount_value, 2, '.', '') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium">Applies to</label>
                                        <select name="applies_to" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                            <option value="all" @selected($promotion->applies_to === 'all')>All</option>
                                            <option value="services" @selected($promotion->applies_to === 'services')>Services</option>
                                            <option value="memberships" @selected($promotion->applies_to === 'memberships')>Memberships</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium">Starts on</label>
                                        <input name="starts_on" type="date" value="{{ $promotion->starts_on?->format('Y-m-d') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium">Ends on</label>
                                        <input name="ends_on" type="date" value="{{ $promotion->ends_on?->format('Y-m-d') }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium">Max discount cap (USD)</label>
                                        <input name="max_discount_cap" type="number" step="0.01" min="0" value="{{ $promotion->max_discount_cap !== null ? number_format((float) $promotion->max_discount_cap, 2, '.', '') : '' }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium">Minimum purchase (USD)</label>
                                        <input name="minimum_purchase" type="number" step="0.01" min="0" value="{{ $promotion->minimum_purchase !== null ? number_format((float) $promotion->minimum_purchase, 2, '.', '') : '' }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                    </div>
                                    <div class="flex items-end">
                                        <label class="flex items-center gap-2 text-xs font-medium">
                                            <input type="hidden" name="stackable" value="0">
                                            <input type="checkbox" name="stackable" value="1" class="rounded border-slate-300" @checked($promotion->stackable)>
                                            Stackable
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium">Description</label>
                                    <textarea name="description" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ $promotion->description }}</textarea>
                                </div>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <p class="mb-2 text-xs font-semibold uppercase text-slate-500">Target services</p>
                                        <div class="max-h-36 space-y-2 overflow-y-auto rounded-md border border-slate-200 p-2">
                                            @foreach ($services as $service)
                                                <label class="flex items-center gap-2 text-sm">
                                                    <input type="checkbox" name="service_ids[]" value="{{ $service->id }}" @checked($promotion->targetedServices->contains(fn ($s) => (int) $s->id === (int) $service->id))>
                                                    <span>{{ $service->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div>
                                        <p class="mb-2 text-xs font-semibold uppercase text-slate-500">Target memberships</p>
                                        <div class="max-h-36 space-y-2 overflow-y-auto rounded-md border border-slate-200 p-2">
                                            @foreach ($memberships as $membership)
                                                <label class="flex items-center gap-2 text-sm">
                                                    <input type="checkbox" name="membership_ids[]" value="{{ $membership->id }}" @checked($promotion->targetedMemberships->contains(fn ($m) => (int) $m->id === (int) $membership->id))>
                                                    <span>{{ $membership->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="flex items-center gap-2 text-sm font-medium">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" @checked($promotion->is_active)>
                                        Active
                                    </label>
                                </div>
                                <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-700">Update promotion rules</button>
                            </form>
                        </details>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No promotions yet.</p>
                @endforelse
            </div>
            @include('admin.partials.dashboard-drag-handle')
        </section>
    </div>
@endsection
