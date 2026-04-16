<?php

namespace App\Console\Commands;

use App\Models\Membership;
use App\Models\ScheduledPriceChange;
use App\Models\Service;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApplyScheduledPrices extends Command
{
    protected $signature = 'clinic:apply-scheduled-prices';

    protected $description = 'Apply pending service/membership price changes whose effective time has passed';

    public function handle(): int
    {
        $pending = ScheduledPriceChange::query()
            ->where('status', ScheduledPriceChange::STATUS_PENDING)
            ->where('effective_at', '<=', now())
            ->orderBy('effective_at')
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No pending scheduled price changes to apply.');

            return self::SUCCESS;
        }

        $systemActorId = (int) (User::query()->where('is_admin', true)->whereNull('deleted_at')->value('id') ?? 0);

        foreach ($pending as $change) {
            DB::transaction(function () use ($change, $systemActorId): void {
                $model = $change->changeable;
                if (! $model instanceof Service && ! $model instanceof Membership) {
                    $change->update(['status' => ScheduledPriceChange::STATUS_CANCELLED]);

                    return;
                }

                $oldPrice = $model instanceof Service
                    ? (string) $model->price
                    : (string) $model->monthly_price;

                if ($model instanceof Service) {
                    $model->update(['price' => number_format((float) $change->new_price, 2, '.', '')]);
                } else {
                    $model->update(['monthly_price' => number_format((float) $change->new_price, 2, '.', '')]);
                }

                $change->update([
                    'status' => ScheduledPriceChange::STATUS_APPLIED,
                    'applied_at' => now(),
                ]);

                if ($systemActorId > 0) {
                    $entityType = $model instanceof Membership ? 'membership' : 'service';

                    \App\Models\AdminAuditLog::query()->create([
                        'actor_user_id' => $systemActorId,
                        'action' => 'admin.scheduled_price.applied',
                        'entity_type' => $entityType,
                        'entity_id' => $change->changeable_id,
                        'old_values' => ['price' => $oldPrice],
                        'new_values' => [
                            'scheduled_price_change_id' => $change->id,
                            'price' => (string) $change->new_price,
                            'effective_at' => $change->effective_at?->toIso8601String(),
                            'source' => 'scheduler',
                        ],
                        'ip_address' => null,
                        'user_agent' => 'clinic:apply-scheduled-prices',
                    ]);
                }
            });
        }

        $this->info('Applied ' . $pending->count() . ' scheduled price change(s).');

        return self::SUCCESS;
    }
}
