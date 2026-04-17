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
<body class="min-h-screen bg-gradient-to-b from-slate-200 via-slate-100 to-slate-200 text-slate-900 antialiased">
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:py-14">
        <header class="rounded-2xl border border-slate-300 bg-gradient-to-br from-pink-50 via-white to-violet-50 p-8 shadow-lg shadow-slate-900/10 ring-1 ring-slate-900/[0.04] sm:p-10">
            <p class="text-sm font-semibold uppercase tracking-wide text-pink-700">Aesthetics Clinic</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 lg:text-4xl">BeautiSkin CRM</h1>
            <p class="mt-4 max-w-2xl text-base leading-relaxed text-slate-600">
                Manage clients, book appointments, track treatments, and monitor memberships in one place.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                @auth
                    <a href="{{ route('customers.index') }}" class="crm-btn-primary">Customers</a>
                    <a href="{{ route('appointments.index') }}" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900">Appointments</a>
                    <a href="{{ route('leads.index') }}" class="crm-btn-secondary">Leads</a>
                    <a href="{{ route('services.index') }}" class="crm-btn-secondary">Services</a>
                    <a href="{{ route('memberships.index') }}" class="crm-btn-secondary">Memberships</a>
                    @can('view-sales')
                        <a href="{{ route('sales.index') }}" class="crm-btn-secondary">Sales</a>
                    @endcan
                    @can('access-admin-board')
                        <a href="{{ route('admin.control-board') }}" class="crm-btn-secondary">Admin board</a>
                    @endcan
                @else
                    <a href="{{ route('login') }}" class="crm-btn-primary">Sign in</a>
                    <a href="{{ route('register') }}" class="crm-btn-secondary">Register</a>
                @endauth
            </div>
        </header>

        @auth
            <section class="mt-8" aria-labelledby="home-dashboard-heading">
                <h2 id="home-dashboard-heading" class="sr-only">Today at a glance</h2>
                <div class="grid gap-5 lg:grid-cols-3">
                    <article class="crm-panel relative overflow-hidden p-6">
                        <div class="absolute right-0 top-0 h-24 w-24 translate-x-6 -translate-y-6 rounded-full bg-pink-100/90" aria-hidden="true"></div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Appointments today</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $clinicTodayLabel }} <span class="text-slate-400">·</span> clinic calendar day</p>
                        <p class="mt-4 text-4xl font-bold tabular-nums tracking-tight text-slate-900">{{ number_format($todaysAppointmentCount) }}</p>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">Scheduled visits for today (cancelled excluded).</p>
                        <a
                            href="{{ $appointmentsTodayUrl }}"
                            class="mt-5 inline-flex text-sm font-semibold text-pink-700 hover:text-pink-800"
                        >
                            Open in appointments →
                        </a>
                    </article>
                    <div class="lg:col-span-2 flex flex-col gap-5">
                        @include('home.partials.lead-funnel-widget')
                        @include('home.partials.bestsellers-tabs')
                    </div>
                </div>
            </section>
        @endauth

        <section class="mt-8 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
            <article class="crm-panel p-5">
                <h2 class="text-lg font-semibold text-slate-900">Customers</h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">Save client contact info, notes, and treatment history.</p>
            </article>
            <article class="crm-panel p-5">
                <h2 class="text-lg font-semibold text-slate-900">Appointments</h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">Schedule, update, and review appointment timelines.</p>
            </article>
            <article class="crm-panel p-5">
                <h2 class="text-lg font-semibold text-slate-900">Services</h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">Manage treatment catalog, durations, pricing, and staff eligibility.</p>
                <a href="{{ route('services.index') }}" class="mt-4 inline-flex text-sm font-semibold text-pink-700 hover:text-pink-800">Open services →</a>
            </article>
            <article class="crm-panel p-5">
                <h2 class="text-lg font-semibold text-slate-900">Memberships</h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">Track active plans and customer subscriptions.</p>
                <a href="{{ route('memberships.index') }}" class="mt-4 inline-flex text-sm font-semibold text-pink-700 hover:text-pink-800">Open memberships →</a>
            </article>
        </section>
    </div>
</body>
</html>
