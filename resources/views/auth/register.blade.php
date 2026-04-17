@extends('layouts.guest')

@section('guest_footer')
    <a href="{{ route('login') }}" class="font-medium text-pink-700 hover:text-pink-800">Already registered? Sign in</a>
@endsection

@section('content')
    <h1 class="text-xl font-bold">Create account</h1>
    <p class="mt-1 text-sm text-slate-600">Register a staff user for BeautiSkin CRM.</p>

    <form method="POST" action="{{ route('register.store') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="name" class="mb-1 block text-sm font-medium">Name</label>
            <input
                id="name"
                name="name"
                type="text"
                value="{{ old('name') }}"
                autocomplete="name"
                required
                autofocus
                class="crm-input"
            >
            @error('name')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="email" class="mb-1 block text-sm font-medium">Email</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                autocomplete="username"
                required
                class="crm-input"
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
                autocomplete="new-password"
                required
                class="crm-input"
            >
            @error('password')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium">Confirm password</label>
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
            Register
        </button>
    </form>
@endsection
