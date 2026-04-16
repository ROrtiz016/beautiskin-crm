@extends('layouts.guest')

@section('guest_footer')
    <a href="{{ route('register') }}" class="font-medium text-pink-700 hover:text-pink-800">Create an account</a>
@endsection

@section('content')
    <h1 class="text-xl font-bold">Sign in</h1>
    <p class="mt-1 text-sm text-slate-600">Use your staff account to access the CRM.</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
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
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-pink-500 focus:outline-none focus:ring-1 focus:ring-pink-500"
            >
            @error('email')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="password" class="mb-1 block text-sm font-medium">Password</label>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-pink-500 focus:outline-none focus:ring-1 focus:ring-pink-500"
            >
            @error('password')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="flex items-center gap-2">
            <input id="remember" name="remember" type="checkbox" value="1" class="rounded border-slate-300 text-pink-600 focus:ring-pink-500" {{ old('remember') ? 'checked' : '' }}>
            <label for="remember" class="text-sm text-slate-700">Remember me</label>
        </div>
        <button type="submit" class="w-full rounded-md bg-pink-600 px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-pink-700 focus:outline-none focus:ring-2 focus:ring-pink-500 focus:ring-offset-2">
            Sign in
        </button>
    </form>
@endsection
