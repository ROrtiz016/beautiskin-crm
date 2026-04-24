<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class SpaAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        if ($resp = $this->registerThrottleResponse($request)) {
            return $resp;
        }

        RateLimiter::hit($this->registerThrottleKey($request), 3600);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        event(new Registered($user));

        $user->tokens()->where('name', 'spa')->delete();
        $token = $user->createToken('spa', ['*'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        if ($resp = $this->forgotPasswordThrottleResponse($request)) {
            return $resp;
        }

        RateLimiter::hit($this->forgotPasswordThrottleKey($request), 3600);

        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'status' => $status,
            'message' => __($status),
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        if ($resp = $this->resetPasswordThrottleResponse($request)) {
            return $resp;
        }

        RateLimiter::hit($this->resetPasswordThrottleKey($request), 3600);

        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request): void {
                $user->forceFill([
                    'password' => $request->password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'status' => $status,
            'message' => __($status),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        if ($resp = $this->loginThrottleResponse($request)) {
            return $resp;
        }

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            RateLimiter::hit($this->loginThrottleKey($request), 60);

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        RateLimiter::clear($this->loginThrottleKey($request));

        /** @var User $user */
        $user = Auth::user();
        $user->tokens()->where('name', 'spa')->delete();
        $token = $user->createToken('spa', ['*'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    public function user(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->userPayload($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'can' => [
                'view_sales' => $user->can('view-sales'),
                'access_admin_board' => $user->can('access-admin-board'),
                'view_experimental_ui' => $user->can('view-experimental-ui'),
                'manage_feature_flags' => $user->can('manage-feature-flags'),
                'manage_users' => $user->is_admin || $user->hasAdminPermission('manage_users'),
            ],
            'is_admin' => (bool) $user->is_admin,
            'permissions' => $user->permissions ?? [],
        ];
    }

    private function loginThrottleKey(Request $request): string
    {
        $email = strtolower((string) $request->input('email', ''));

        return 'spa-login:'.sha1($email.'|'.$request->ip());
    }

    private function loginThrottleResponse(Request $request): ?JsonResponse
    {
        $key = $this->loginThrottleKey($request);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Too many login attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ], 429);
        }

        return null;
    }

    private function registerThrottleKey(Request $request): string
    {
        return 'spa-register:'.sha1($request->ip());
    }

    private function registerThrottleResponse(Request $request): ?JsonResponse
    {
        $key = $this->registerThrottleKey($request);

        if (RateLimiter::tooManyAttempts($key, 20)) {
            return response()->json([
                'message' => 'Too many registration attempts from this network. Try again later.',
            ], 429);
        }

        return null;
    }

    private function forgotPasswordThrottleKey(Request $request): string
    {
        return 'spa-forgot-password:'.sha1($request->ip());
    }

    private function forgotPasswordThrottleResponse(Request $request): ?JsonResponse
    {
        $key = $this->forgotPasswordThrottleKey($request);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Too many password reset requests. Try again later.',
            ], 429);
        }

        return null;
    }

    private function resetPasswordThrottleKey(Request $request): string
    {
        return 'spa-reset-password:'.sha1($request->ip());
    }

    private function resetPasswordThrottleResponse(Request $request): ?JsonResponse
    {
        $key = $this->resetPasswordThrottleKey($request);

        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json([
                'message' => 'Too many password reset attempts. Try again later.',
            ], 429);
        }

        return null;
    }
}
