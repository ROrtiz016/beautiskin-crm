@php
    $p = max(0, min(100, (int) round((float) $percent)));
@endphp
<td class="min-w-[11rem] px-3 py-2 align-middle">
    <div class="flex items-center gap-2">
        {{-- Block track + % width: table cells + flex-1 often collapse the track to 0px --}}
        <div
            class="h-2.5 w-full min-w-[6.5rem] overflow-hidden rounded-full bg-slate-100"
            role="img"
            aria-label="{{ $p }}% of the top seller in this list"
        >
            <div class="h-full rounded-full bg-pink-500" style="width: {{ $p }}%"></div>
        </div>
        <span class="w-11 shrink-0 text-right text-xs font-medium tabular-nums text-slate-600">{{ $p }}%</span>
    </div>
</td>
