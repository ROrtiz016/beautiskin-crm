<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BeautiSkin CRM</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <div class="mx-auto max-w-6xl px-6 py-10 lg:py-14">
        <header class="rounded-2xl bg-gradient-to-br from-pink-100 via-white to-purple-100 p-8 shadow-sm ring-1 ring-slate-200">
            <p class="text-sm font-semibold uppercase tracking-wide text-pink-700">Aesthetics Clinic</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 lg:text-4xl">BeautiSkin CRM</h1>
            <p class="mt-4 max-w-2xl text-slate-600">
                Manage clients, book appointments, track treatments, and monitor memberships in one place.
            </p>
            <div class="mt-6 flex flex-wrap gap-3">
                @auth
                    <a href="{{ route('customers.index') }}" class="rounded-lg bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">Customers</a>
                    <a href="{{ route('appointments.index') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Appointments</a>
                    <a href="{{ route('services.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Services</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">Sign in</a>
                    <a href="{{ route('register') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Register</a>
                @endauth
            </div>
        </header>

        <section class="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-lg font-semibold">Customers</h2>
                <p class="mt-2 text-sm text-slate-600">Save client contact info, notes, and treatment history.</p>
            </article>
            <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-lg font-semibold">Appointments</h2>
                <p class="mt-2 text-sm text-slate-600">Schedule, update, and review appointment timelines.</p>
            </article>
            <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-lg font-semibold">Services</h2>
                <p class="mt-2 text-sm text-slate-600">Manage treatment catalog, durations, pricing, and staff eligibility.</p>
                <a href="{{ route('services.index') }}" class="mt-3 inline-block text-sm font-semibold text-pink-700 hover:text-pink-800">Open Services →</a>
            </article>
            <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-lg font-semibold">Memberships</h2>
                <p class="mt-2 text-sm text-slate-600">Track active plans and customer subscriptions.</p>
            </article>
        </section>

        <section class="mt-8 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h3 class="text-xl font-semibold">Quick Start</h3>
            <ol class="mt-4 list-decimal space-y-2 pl-5 text-slate-700">
                <li>Create your first customer via <code>/api/customers</code>.</li>
                <li>Add services in <code>/api/services</code>.</li>
                <li>Create memberships in <code>/api/memberships</code>.</li>
                <li>Book appointments in <code>/api/appointments</code> with service line items.</li>
            </ol>
        </section>
    </div>
</body>
</html>
