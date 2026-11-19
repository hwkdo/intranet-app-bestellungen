<!DOCTYPE html>
<html lang="de">
<head>
    @include('partials.head', ['title' => 'Lieferanten-Cache'])
</head>
<body class="min-h-screen bg-[#d0e3f9] text-slate-900 antialiased dark:bg-[#04214e] dark:text-white">
    <main class="mx-auto flex min-h-screen max-w-xl flex-col justify-center px-4 py-16">
        <section class="hwk-hero relative overflow-hidden rounded-2xl px-6 py-8 text-white shadow-xl shadow-[#073070]/25 ring-1 ring-white/10">
            <div class="pointer-events-none absolute -top-16 -right-10 size-48 rounded-full bg-[#a0c7f2]/40 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-20 -left-10 size-48 rounded-full bg-[#d0e3f9]/30 blur-3xl"></div>
            <p class="text-xs font-semibold tracking-[0.2em] text-[#d0e3f9] uppercase">Bestellungen</p>
            <h1 class="mt-2 text-2xl font-semibold">Lieferanten-Cache</h1>
            <p class="mt-2 text-[#d0e3f9]/90">Übernimmt die Lieferanten aus der BÜ ins Intranet.</p>
        </section>

        <section class="glass-card mt-6 p-6" style="--glass-opacity: 0.92">
            @if ($fehler)
                <p class="text-slate-600 dark:text-white/70">{{ $fehler }}</p>
            @elseif ($erneuert)
                <h2 class="text-lg font-semibold text-[#073070] dark:text-white">Cache aktualisiert</h2>
                <p class="mt-2 text-slate-600 dark:text-white/70">
                    @if ($anzahl > 0)
                        {{ $anzahl }} Lieferanten wurden übernommen. In neuen Bestellungen ist der angelegte Lieferant über die Suche auffindbar.
                    @else
                        Die BÜ hat keine Lieferanten geliefert. Der bisherige Cache bleibt unverändert.
                    @endif
                </p>
                @if ($informiert)
                    <p class="mt-2 text-slate-600 dark:text-white/70">Die meldende Person wurde informiert.</p>
                @endif
            @else
                <h2 class="text-lg font-semibold text-[#073070] dark:text-white">Cache aktualisieren</h2>
                <p class="mt-2 text-slate-600 dark:text-white/70">
                    Bestätigen Sie, dass der Lieferant in der BÜ angelegt ist. Die betroffene Bestellung wird dabei nicht verändert.
                </p>
                <form method="POST" action="{{ request()->fullUrl() }}" class="mt-6">
                    @csrf
                    <button type="submit" class="rounded-lg bg-[#073070] px-4 py-2 text-sm font-medium text-white shadow-md shadow-[#073070]/25">
                        Lieferanten-Cache aktualisieren
                    </button>
                </form>
            @endif
        </section>
    </main>
</body>
</html>
