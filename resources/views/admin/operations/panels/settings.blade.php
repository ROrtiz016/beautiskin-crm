<div class="grid gap-8 lg:grid-cols-2">
    <section class="crm-panel p-5">
        <h2 class="text-lg font-semibold text-slate-900">Appointment policy</h2>
        <p class="mt-1 text-sm text-slate-600">Enforced when creating, rescheduling, or cancelling appointments.</p>
        <form method="POST" action="{{ route('admin.operations.appointment-policy.update') }}" class="mt-4 space-y-4">
            @csrf
            @method('PATCH')
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Cancellation notice (hours)</label>
                <input
                    type="number"
                    name="appointment_cancellation_hours"
                    min="0"
                    max="8760"
                    value="{{ old('appointment_cancellation_hours', $clinicSettings->appointment_cancellation_hours) }}"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
                <p class="mt-1 text-xs text-slate-500">Minimum hours before start time to allow cancellation. Use 0 to disable.</p>
                @error('appointment_cancellation_hours') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Max active bookings per clinic day</label>
                <input
                    type="number"
                    name="max_bookings_per_day"
                    min="1"
                    max="500"
                    value="{{ old('max_bookings_per_day', $clinicSettings->max_bookings_per_day) }}"
                    placeholder="Unlimited"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
                <p class="mt-1 text-xs text-slate-500">Leave empty for no limit. Counts booked, completed, and no-shows (excludes cancelled).</p>
                @error('max_bookings_per_day') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-center gap-2">
                <input
                    type="checkbox"
                    name="deposit_required"
                    value="1"
                    id="deposit_required"
                    class="rounded border-slate-300"
                    @checked($clinicSettings->deposit_required)
                >
                <label for="deposit_required" class="text-sm font-medium text-slate-700">Deposit required for new appointments</label>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Default deposit amount (optional)</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="default_deposit_amount"
                    value="{{ old('default_deposit_amount', $clinicSettings->default_deposit_amount) }}"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
                @error('default_deposit_amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">
                Save policy
            </button>
        </form>
    </section>

    @can('manage-feature-flags')
        <section class="crm-panel p-5">
            <h2 class="text-lg font-semibold text-slate-900">Feature flags</h2>
            <p class="mt-1 text-sm text-slate-600">Experimental UI is visible only to full administrators when enabled.</p>
            <form method="POST" action="{{ route('admin.operations.feature-flags.update') }}" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')
                <div class="flex items-center gap-2">
                    <input
                        type="checkbox"
                        name="experimental_ui"
                        value="1"
                        id="experimental_ui"
                        class="rounded border-slate-300"
                        @checked($clinicSettings->experimentalUiEnabled())
                    >
                    <label for="experimental_ui" class="text-sm font-medium text-slate-700">Experimental UI (admins only)</label>
                </div>
                <button type="submit" class="rounded-md bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">
                    Save feature flags
                </button>
            </form>
        </section>
    @else
        <section class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-5">
            <h2 class="text-lg font-semibold text-slate-700">Feature flags</h2>
            <p class="mt-2 text-sm text-slate-600">Only full administrators can change experimental UI flags.</p>
        </section>
    @endcan
</div>
