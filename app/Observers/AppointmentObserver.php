<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Services\AppointmentPolicyEnforcer;
use App\Services\InventoryStockService;
use App\Support\CustomerTimeline;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AppointmentObserver
{
    public function created(Appointment $appointment): void
    {
        $customer = Customer::query()->find($appointment->customer_id);
        if (! $customer) {
            return;
        }

        $tz = AppointmentPolicyEnforcer::clinicTimezone();
        $when = $appointment->scheduled_at
            ? $appointment->scheduled_at->copy()->timezone($tz)->format('M j, Y g:i A')
            : 'TBD';

        CustomerTimeline::record(
            $customer,
            CustomerActivity::EVENT_APPOINTMENT_CREATED,
            sprintf('Appointment booked for %s · %s', $when, ucfirst(str_replace('_', ' ', (string) $appointment->status))),
            Auth::id(),
            null,
            CustomerActivity::CATEGORY_APPOINTMENT,
            ['appointment_id' => $appointment->id],
        );
    }

    public function updated(Appointment $appointment): void
    {
        $customer = Customer::query()->find($appointment->customer_id);
        if (! $customer) {
            return;
        }

        $tz = AppointmentPolicyEnforcer::clinicTimezone();
        $uid = Auth::id();

        if ($appointment->wasChanged('status')) {
            $previousStatus = (string) $appointment->getOriginal('status');
            $currentStatus = (string) $appointment->status;

            if ($currentStatus === 'completed' && $previousStatus !== 'completed') {
                InventoryStockService::deductForCompletedAppointment($appointment);
            }

            if ($previousStatus === 'completed' && $currentStatus !== 'completed') {
                InventoryStockService::restoreForCompletedAppointment($appointment);
            }

            $when = $appointment->scheduled_at
                ? $appointment->scheduled_at->copy()->timezone($tz)->format('M j, Y g:i A')
                : 'TBD';
            $summary = sprintf(
                'Appointment status → %s · %s',
                ucfirst(str_replace('_', ' ', (string) $appointment->status)),
                $when,
            );

            if ($appointment->status === 'cancelled' && $appointment->cancellation_reason) {
                $summary .= ' · Reason: '.Str::limit((string) $appointment->cancellation_reason, 120);
            }

            CustomerTimeline::record(
                $customer,
                CustomerActivity::EVENT_APPOINTMENT_STATUS,
                $summary,
                $uid,
                null,
                CustomerActivity::CATEGORY_APPOINTMENT,
                ['appointment_id' => $appointment->id],
            );

            if ($appointment->status === 'completed' && (float) $appointment->total_amount > 0) {
                CustomerTimeline::record(
                    $customer,
                    CustomerActivity::EVENT_PAYMENT_COMPLETED_VISIT,
                    sprintf(
                        'Completed visit total: $%s',
                        number_format((float) $appointment->total_amount, 2),
                    ),
                    $uid,
                    null,
                    CustomerActivity::CATEGORY_PAYMENT,
                    ['appointment_id' => $appointment->id],
                );
            }
        }

        if ($appointment->wasChanged('scheduled_at') && ! $appointment->wasChanged('status')) {
            $new = $appointment->scheduled_at
                ? $appointment->scheduled_at->copy()->timezone($tz)->format('M j, Y g:i A')
                : 'TBD';
            CustomerTimeline::record(
                $customer,
                CustomerActivity::EVENT_APPOINTMENT_RESCHEDULED,
                sprintf('Appointment rescheduled · now %s', $new),
                $uid,
                null,
                CustomerActivity::CATEGORY_APPOINTMENT,
                ['appointment_id' => $appointment->id],
            );
        }
    }
}
