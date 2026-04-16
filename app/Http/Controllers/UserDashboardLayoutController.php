<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\DashboardLayoutRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserDashboardLayoutController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dashboard' => ['required', 'string', Rule::in(['operations', 'control_board'])],
            'order' => ['required', 'array'],
            'order.*' => ['required', 'string', 'max:64'],
        ]);

        $allowed = match ($validated['dashboard']) {
            'operations' => DashboardLayoutRegistry::OPERATIONS_PANELS,
            'control_board' => DashboardLayoutRegistry::CONTROL_BOARD_PANELS,
        };

        $normalized = DashboardLayoutRegistry::normalizeOrder($allowed, $validated['order']);

        $user = $request->user();
        $layouts = $user->dashboard_layouts ?? [];
        $layouts[$validated['dashboard']] = $normalized;

        User::query()->whereKey((int) $user->id)->update([
            'dashboard_layouts' => json_encode($layouts, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'order' => $normalized]);
    }
}
