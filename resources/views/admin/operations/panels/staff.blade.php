<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="text-lg font-semibold text-slate-900">Staff utilization (today)</h2>
    <p class="mt-1 text-sm text-slate-600">Utilization compares booked duration to an 8-hour working day per staff member.</p>
    <div class="mt-4 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                    <th class="py-2 pr-4 font-semibold">Staff</th>
                    <th class="py-2 pr-4 font-semibold">Appointments</th>
                    <th class="py-2 pr-4 font-semibold">Booked minutes</th>
                    <th class="py-2 font-semibold">Utilization</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($staffUtilizationRows as $row)
                    <tr class="border-b border-slate-100">
                        <td class="py-2 pr-4 font-medium text-slate-900">{{ $row['staff_name'] }}</td>
                        <td class="py-2 pr-4 text-slate-700">{{ $row['appointment_count'] }}</td>
                        <td class="py-2 pr-4 text-slate-700">{{ $row['booked_minutes'] }}</td>
                        <td class="py-2 text-slate-900">{{ $row['utilization_percent'] }}%</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-4 text-slate-500">No active appointments scheduled for today.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
