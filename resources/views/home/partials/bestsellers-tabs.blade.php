<div class="crm-panel flex flex-col overflow-hidden p-0" data-home-bestsellers>
    <div class="border-b border-slate-200 bg-slate-50/90 px-3 pt-3 sm:px-4">
        <p class="px-2 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Best sellers</p>
        <div class="flex flex-wrap gap-1" role="tablist" aria-label="Best sellers by category">
            <button
                type="button"
                role="tab"
                aria-selected="true"
                data-home-bestseller-tab="services"
                class="home-bestseller-tab rounded-t-lg border border-b-0 border-slate-200 border-b-white bg-white px-3 py-2.5 text-sm font-semibold text-pink-800 transition sm:px-4"
            >
                Services
            </button>
            <button
                type="button"
                role="tab"
                aria-selected="false"
                data-home-bestseller-tab="memberships"
                class="home-bestseller-tab rounded-t-lg border border-b-0 border-transparent px-3 py-2.5 text-sm font-semibold text-slate-600 transition hover:text-slate-900 sm:px-4"
            >
                Memberships
            </button>
            <button
                type="button"
                role="tab"
                aria-selected="false"
                data-home-bestseller-tab="products"
                class="home-bestseller-tab rounded-t-lg border border-b-0 border-transparent px-3 py-2.5 text-sm font-semibold text-slate-600 transition hover:text-slate-900 sm:px-4"
            >
                Products
            </button>
        </div>
    </div>

    <div class="p-4 sm:p-6">
        <p class="mb-4 text-xs text-slate-500">
            Services and products use appointment line totals from the last <span class="font-semibold text-slate-700">{{ $bestsellerDays }} days</span> (non-cancelled appointments, clinic timezone window). Memberships rank new subscriptions by <span class="font-semibold text-slate-700">created</span> date in that window. <span class="font-semibold text-slate-700">Share</span> bars show each row as a percentage of the top entry in that tab’s table (not dollar amounts).
        </p>

        <div data-home-bestseller-panel="services" role="tabpanel">
            <p class="mb-3 text-xs text-slate-500">Treatments and services (excludes categories tagged as retail products).</p>
            @if ($topServices->isEmpty())
                <p class="text-sm text-slate-600">No service sales in this period.</p>
            @else
                @php
                    $maxServiceRevenue = max($topServices->max(fn ($r) => (float) $r->revenue), 1e-9);
                @endphp
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-white text-xs font-semibold uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-3 py-2">#</th>
                                <th class="px-3 py-2">Service</th>
                                <th class="min-w-[11rem] px-3 py-2" title="Share of the top seller in this list (100%)">Share</th>
                                <th class="px-3 py-2 text-right">Units</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($topServices as $i => $row)
                                @php
                                    $serviceSharePct = 100 * (float) $row->revenue / $maxServiceRevenue;
                                @endphp
                                <tr>
                                    <td class="px-3 py-2 text-slate-500">{{ $i + 1 }}</td>
                                    <td class="px-3 py-2 font-medium text-slate-900">{{ $row->name }}</td>
                                    @include('home.partials.bestseller-share-cell', ['percent' => $serviceSharePct])
                                    <td class="px-3 py-2 text-right tabular-nums text-slate-600">{{ number_format((int) $row->units) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div data-home-bestseller-panel="memberships" class="hidden" role="tabpanel" hidden>
            <p class="mb-3 text-xs text-slate-500">Plans ranked by combined list value of new subscriptions in this window (sum of each plan’s monthly price × new subs). Share bars compare each row to the leader in this list.</p>
            @if ($topMemberships->isEmpty())
                <p class="text-sm text-slate-600">No new memberships in this period.</p>
            @else
                @php
                    $maxMembershipRevenue = max($topMemberships->max(fn ($r) => (float) $r->revenue), 1e-9);
                @endphp
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-white text-xs font-semibold uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-3 py-2">#</th>
                                <th class="px-3 py-2">Membership</th>
                                <th class="px-3 py-2 text-right">New subs</th>
                                <th class="min-w-[11rem] px-3 py-2" title="Share of the top plan in this list (100%)">Share</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($topMemberships as $i => $row)
                                @php
                                    $membershipSharePct = 100 * (float) $row->revenue / $maxMembershipRevenue;
                                @endphp
                                <tr>
                                    <td class="px-3 py-2 text-slate-500">{{ $i + 1 }}</td>
                                    <td class="px-3 py-2 font-medium text-slate-900">{{ $row->name }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-slate-800">{{ number_format((int) $row->sold_count) }}</td>
                                    @include('home.partials.bestseller-share-cell', ['percent' => $membershipSharePct])
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div data-home-bestseller-panel="products" class="hidden" role="tabpanel" hidden>
            <p class="mb-3 text-xs text-slate-500">Retail and home-care items: services whose category is <span class="font-medium text-slate-700">Product</span>, <span class="font-medium text-slate-700">Products</span>, or <span class="font-medium text-slate-700">Retail</span> (case-insensitive).</p>
            @if ($topProducts->isEmpty())
                <p class="text-sm text-slate-600">No product-category sales in this period. Tag retail SKUs in Services with one of those categories.</p>
            @else
                @php
                    $maxProductRevenue = max($topProducts->max(fn ($r) => (float) $r->revenue), 1e-9);
                @endphp
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-white text-xs font-semibold uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-3 py-2">#</th>
                                <th class="px-3 py-2">Product</th>
                                <th class="min-w-[11rem] px-3 py-2" title="Share of the top seller in this list (100%)">Share</th>
                                <th class="px-3 py-2 text-right">Units</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($topProducts as $i => $row)
                                @php
                                    $productSharePct = 100 * (float) $row->revenue / $maxProductRevenue;
                                @endphp
                                <tr>
                                    <td class="px-3 py-2 text-slate-500">{{ $i + 1 }}</td>
                                    <td class="px-3 py-2 font-medium text-slate-900">{{ $row->name }}</td>
                                    @include('home.partials.bestseller-share-cell', ['percent' => $productSharePct])
                                    <td class="px-3 py-2 text-right tabular-nums text-slate-600">{{ number_format((int) $row->units) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    (function () {
        const root = document.querySelector('[data-home-bestsellers]');
        if (!root) return;

        const tabs = root.querySelectorAll('[data-home-bestseller-tab]');
        const panels = root.querySelectorAll('[data-home-bestseller-panel]');

        function activate(key) {
            tabs.forEach((btn) => {
                const on = btn.getAttribute('data-home-bestseller-tab') === key;
                btn.setAttribute('aria-selected', on ? 'true' : 'false');
                btn.classList.toggle('border-slate-200', on);
                btn.classList.toggle('border-b-white', on);
                btn.classList.toggle('bg-white', on);
                btn.classList.toggle('text-pink-800', on);
                btn.classList.toggle('text-slate-600', !on);
                btn.classList.toggle('border-transparent', !on);
            });
            panels.forEach((panel) => {
                const on = panel.getAttribute('data-home-bestseller-panel') === key;
                panel.classList.toggle('hidden', !on);
                panel.toggleAttribute('hidden', !on);
            });
        }

        tabs.forEach((btn) => {
            btn.addEventListener('click', () => activate(btn.getAttribute('data-home-bestseller-tab')));
        });
    })();
</script>
