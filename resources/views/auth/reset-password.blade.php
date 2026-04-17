@extends('layouts.guest')

@section('guest_footer')
    <a href="{{ route('login') }}" class="font-medium text-pink-700 hover:text-pink-800">Back to sign in</a>
@endsection

@section('content')
    <h1 class="text-xl font-bold">Reset password</h1>
    <p class="mt-1 text-sm text-slate-600">Choose a new password for your account.</p>

    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label for="email" class="mb-1 block text-sm font-medium">Email</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email', $email) }}"
                autocomplete="username"
                required
                class="crm-input"
            >
            @error('email')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="password" class="mb-1 block text-sm font-medium">New password</label>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="new-password"
                required
                class="crm-input"
            >
            @error('password')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium">Confirm new password</label>
            <input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                required
                class="crm-input"
            >
        </div>
        <button type="submit" class="crm-btn-primary w-full justify-center">
            Reset password
        </button>
    </form>
@endsection
