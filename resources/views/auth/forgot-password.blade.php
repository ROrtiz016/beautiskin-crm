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
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-pink-500 focus:outline-none focus:ring-1 focus:ring-pink-500"
            >
            @error('email')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="w-full rounded-md bg-pink-600 px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-pink-700 focus:outline-none focus:ring-2 focus:ring-pink-500 focus:ring-offset-2">
            Email reset link
        </button>
    </form>
@endsection
