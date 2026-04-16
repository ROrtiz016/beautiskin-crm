<?php

namespace App\Support;

final class DashboardLayoutRegistry
{
    /** @var list<string> */
    public const OPERATIONS_PANELS = [
        'ops-kpis',
        'ops-staff',
        'ops-settings',
    ];

    /** @var list<string> */
    public const CONTROL_BOARD_PANELS = [
        'ctrl-audit',
        'ctrl-user-create',
        'ctrl-user-permissions',
        'ctrl-clinic-profile',
        'ctrl-messaging',
        'ctrl-data-retention',
        'ctrl-tax',
        'ctrl-scheduled-prices',
        'ctrl-service-prices',
        'ctrl-membership-prices',
        'ctrl-promotions',
    ];

    /**
     * @param  list<string>  $defaults
     * @param  list<string>|null  $saved
     * @return list<string>
     */
    public static function normalizeOrder(array $defaults, ?array $saved): array
    {
        if ($saved === null || $saved === []) {
            return $defaults;
        }

        $allowed = array_flip($defaults);
        $seen = [];
        $out = [];

        foreach ($saved as $id) {
            if (! is_string($id) || ! isset($allowed[$id]) || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $id;
        }

        foreach ($defaults as $id) {
            if (! isset($seen[$id])) {
                $out[] = $id;
            }
        }

        return $out;
    }
}
