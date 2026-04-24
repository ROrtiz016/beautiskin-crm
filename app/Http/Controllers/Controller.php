<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * @param  array<string, mixed>  $jsonPayload
     * @param  array<string, mixed>  $redirectRouteParameters
     */
    protected function respondAdminAction(
        Request $request,
        string $message,
        string $redirectRoute,
        array $redirectRouteParameters = [],
        array $jsonPayload = [],
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson()) {
            return response()->json(array_merge(['message' => $message], $jsonPayload));
        }

        return redirect()->route($redirectRoute, $redirectRouteParameters)->with('status', $message);
    }

    /**
     * Validation-style errors for admin JSON clients; Blade requests get a flash + redirect.
     */
    protected function respondAdminError(
        Request $request,
        string $message,
        string $redirectRoute = 'admin.control-board',
        array $redirectRouteParameters = [],
        int $jsonStatus = 422,
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $jsonStatus);
        }

        return redirect()->route($redirectRoute, $redirectRouteParameters)->with('error', $message);
    }
}
