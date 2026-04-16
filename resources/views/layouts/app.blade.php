<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'BeautiSkin CRM' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-col gap-4 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ url('/') }}" class="text-lg font-bold">BeautiSkin CRM</a>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-8">
                <nav class="flex flex-wrap items-center gap-4 text-sm font-medium text-slate-600 sm:gap-5">
                    <a href="{{ url('/') }}" class="hover:text-slate-900">Home</a>
                    <a href="{{ route('customers.index') }}" class="hover:text-slate-900">Customers</a>
                    <a href="{{ route('appointments.index') }}" class="hover:text-slate-900">Appointments</a>
                    <a href="{{ route('services.index') }}" class="hover:text-slate-900">Services</a>
                </nav>
                <div class="flex items-center gap-3 border-t border-slate-100 pt-3 text-sm sm:border-t-0 sm:pt-0">
                    <span class="text-slate-600">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            Log out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-6 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
