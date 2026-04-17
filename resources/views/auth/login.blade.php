@extends('layouts.guest')

@section('guest_footer')
    <a href="{{ route('register') }}" class="font-medium text-pink-700 hover:text-pink-800">Create an account</a>
@endsection

@section('content')
    <h1 class="text-xl font-bold tracking-tight text-slate-900">Sign in</h1>
    <p class="mt-1 text-sm leading-relaxed text-slate-600">Use your staff account to access appointments, customers, and admin tools.</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-slate-800">Email</label>
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
        <div>
            <div class="mb-1 flex items-center justify-between">
                <label for="password" class="block text-sm font-medium text-slate-800">Password</label>
                <a href="{{ route('password.request') }}" class="text-xs font-semibold text-pink-700 hover:text-pink-800">Forgot password?</a>
            </div>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
                class="crm-input"
            >
            @error('password')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="flex items-center gap-2">
            <input id="remember" name="remember" type="checkbox" value="1" class="rounded border-slate-300 text-pink-600 focus:ring-pink-500" {{ old('remember') ? 'checked' : '' }}>
            <label for="remember" class="text-sm text-slate-700">Remember me</label>
        </div>
        <button type="submit" class="crm-btn-primary w-full justify-center">
            Sign in
        </button>
    </form>
@endsection
