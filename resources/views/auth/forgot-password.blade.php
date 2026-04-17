@extends('layouts.guest')

@section('guest_footer')
    <a href="{{ route('login') }}" class="font-medium text-pink-700 hover:text-pink-800">Back to sign in</a>
@endsection

@section('content')
    <h1 class="text-xl font-bold">Forgot password</h1>
    <p class="mt-1 text-sm text-slate-600">Enter your email and we will send a link to reset your password.</p>

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="email" class="mb-1 block text-sm font-medium">Email</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                autocomplete="username"
                required
                autofocus
                class="crm-input"
            >
            @error('email')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="crm-btn-primary w-full justify-center">
            Email reset link
        </button>
    </form>
@endsection
