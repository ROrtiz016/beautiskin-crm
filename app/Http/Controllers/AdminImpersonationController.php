<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminImpersonationController extends Controller
{
    public function start(Request $request, User $adminUser): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);
        abort_if(session()->has('impersonator_id'), 403);
        abort_if($adminUser->trashed(), 403);
        abort_if($adminUser->is_admin, 403);
        abort_if((int) $adminUser->id === (int) $request->user()->id, 403);

        AdminAuditLog::record(
            $request,
            (int) $request->user()->id,
            'admin.impersonation.started',
            'user',
            $adminUser->id,
            null,
            [
                'target_email' => $adminUser->email,
                'target_name' => $adminUser->name,
            ],
        );

        $request->session()->put('impersonator_id', (int) $request->user()->id);

        Auth::login($adminUser);
        $request->session()->regenerate();

        return redirect()
            ->route('customers.index')
            ->with('status', 'You are now viewing the app as ' . $adminUser->name . '.');
    }

    public function leave(Request $request): RedirectResponse
    {
        $adminId = (int) session('impersonator_id');
        abort_if($adminId === 0, 403);

        $impersonatedId = (int) $request->user()->id;

        AdminAuditLog::record(
            $request,
            $adminId,
            'admin.impersonation.ended',
            'user',
            $impersonatedId,
            ['impersonated_user_id' => $impersonatedId],
            ['restored_actor_id' => $adminId],
        );

        $admin = User::query()->findOrFail($adminId);

        session()->forget('impersonator_id');

        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()
            ->route('admin.control-board')
            ->with('status', 'You have left impersonation and are signed in as yourself again.');
    }
}
