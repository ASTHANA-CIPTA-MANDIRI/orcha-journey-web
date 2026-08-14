{{--
    Paginasi bergaya brand Orcha.
    Dipakai dengan: {{ $daftar->links('partials.paginasi-orcha') }}
--}}
@php
    $namaHalaman = $paginator->getPageName();
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navigasi halaman"
        class="flex flex-col items-center gap-4 sm:flex-row sm:justify-between">

        <p class="text-sm text-slate-500">
            Menampilkan <span class="font-bold text-orcha-navy">{{ $paginator->firstItem() }}</span>–<span
                class="font-bold text-orcha-navy">{{ $paginator->lastItem() }}</span>
            dari <span class="font-bold text-orcha-navy">{{ $paginator->total() }}</span>
        </p>

        <div class="flex items-center gap-1.5">
            {{-- Sebelumnya --}}
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" class="page-orcha page-orcha-nav page-orcha-mati">
                    <x-heroicon-o-chevron-left class="w-4 h-4" />
                    <span class="hidden sm:inline">Sebelumnya</span>
                </span>
            @else
                <button type="button" wire:click="previousPage('{{ $namaHalaman }}')" wire:loading.attr="disabled"
                    rel="prev" class="page-orcha page-orcha-nav">
                    <x-heroicon-o-chevron-left class="w-4 h-4" />
                    <span class="hidden sm:inline">Sebelumnya</span>
                </button>
            @endif

            {{-- Nomor halaman --}}
            <div class="flex items-center gap-1.5">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="page-orcha page-orcha-jeda">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="page-orcha page-orcha-aktif">{{ $page }}</span>
                            @else
                                <button type="button" wire:click="gotoPage({{ $page }}, '{{ $namaHalaman }}')"
                                    wire:loading.attr="disabled" wire:key="hal-{{ $namaHalaman }}-{{ $page }}"
                                    class="page-orcha">{{ $page }}</button>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            {{-- Berikutnya --}}
            @if ($paginator->hasMorePages())
                <button type="button" wire:click="nextPage('{{ $namaHalaman }}')" wire:loading.attr="disabled"
                    rel="next" class="page-orcha page-orcha-nav">
                    <span class="hidden sm:inline">Berikutnya</span>
                    <x-heroicon-o-chevron-right class="w-4 h-4" />
                </button>
            @else
                <span aria-disabled="true" class="page-orcha page-orcha-nav page-orcha-mati">
                    <span class="hidden sm:inline">Berikutnya</span>
                    <x-heroicon-o-chevron-right class="w-4 h-4" />
                </span>
            @endif
        </div>
    </nav>
@endif
