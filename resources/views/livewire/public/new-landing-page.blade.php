<div class="overflow-hidden font-sans text-gray-800 bg-slate-50">
    <section class="relative flex items-center justify-center w-full min-h-screen px-4 bg-blue-950 intro-section">
        <div class="absolute inset-0 z-10 flex flex-col items-center justify-center pointer-events-none">
            <h1 class="hero-title text-[15vw] font-black leading-none text-sky-500 uppercase">ORCHA</h1>
            <h1 class="hero-title-outline text-[15vw] font-black leading-none uppercase text-outline-white">JOURNEY</h1>
        </div>
        <img src="https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?auto=format&fit=crop&q=80&w=800"
            class="hero-img absolute z-0 w-3/4 md:w-1/3 h-auto aspect-[9/14] object-cover opacity-60 rounded-3xl shadow-2xl"
            alt="Travel Hero">
        <div class="absolute z-20 bottom-10">
            <a href="#bundling"
                class="px-8 py-4 font-bold text-blue-950 transition bg-amber-400 rounded-full shadow-lg hover:bg-amber-300 hover:scale-105">
                Mulai Petualangan
            </a>
        </div>
    </section>

    <section id="bundling" class="min-h-screen flex flex-col items-center justify-center py-24 bg-slate-50">
        <div class="container px-4 mx-auto max-w-7xl">
            <h2 class="mb-16 text-4xl font-bold text-center text-blue-950 font-heading">
                Paket Bundling
            </h2>

            <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                @foreach ($bundlings as $bundle)
                    <div
                        class="flex flex-col p-8 border border-gray-100 shadow-xl rounded-2xl bundle-card {{ $bundle['color'] }}">
                        <h3 class="text-2xl font-bold text-blue-950 font-heading">{{ $bundle['name'] }}</h3>
                        <p class="mt-4 text-4xl font-black text-sky-500">{{ $bundle['price'] }}</p>
                        <ul class="flex-1 mt-6 space-y-3 text-slate-600">
                            @foreach ($bundle['locations'] as $loc)
                                <li class="flex items-center"><span class="mr-2 text-amber-500">✔</span>
                                    {{ $loc }}</li>
                            @endforeach
                        </ul>
                        <button
                            class="w-full py-3 mt-8 font-bold text-white transition rounded-xl bg-blue-950 hover:bg-sky-500">Pesan
                            Sekarang</button>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="relative w-full h-screen overflow-hidden bg-black dest-wrapper">
        @foreach ($destinations as $index => $dest)
            <div class="absolute inset-0 w-full h-full dest-item" style="z-index: {{ $index === 0 ? 1 : 0 }};">
                <div class="absolute inset-0 w-full h-full dest-inner"
                    style="{{ $index === 0 ? '' : 'visibility: hidden; opacity: 0;' }}">
                    <div class="absolute inset-0 bg-center bg-cover image-bg"
                        style="background-image: url('{{ $dest['bg'] }}'); filter: brightness(0.5);"></div>
                    <div class="relative flex flex-col items-center justify-between w-full h-full p-8 md:flex-row">
                        <div class="relative z-10 w-full content md:w-2/3">
                            <h2
                                class="text-white text-[12vw] md:text-[6.5vw] font-black uppercase leading-tight drop-shadow-[0_10px_10px_rgba(0,0,0,0.8)]">
                                {{ $dest['title'] }}
                            </h2>
                        </div>

                        <div
                            class="relative z-0 flex flex-row justify-end w-full gap-4 py-10 md:py-16 list-image md:flex-col md:w-auto md:h-full">
                            @foreach ($dest['thumbs'] as $thumb)
                                <img src="{{ $thumb }}"
                                    class="object-cover border-4 border-white shadow-2xl rounded-xl w-24 h-32 md:w-40 lg:w-48 md:h-1/3"
                                    alt="Thumb">
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </section>

    <section class="py-24 bg-white car-section">
        <div class="container px-4 mx-auto max-w-7xl">
            <h2 class="mb-16 text-4xl font-bold text-center text-blue-950">Armada Tersedia</h2>
            <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                @foreach ($cars as $car)
                    <div class="overflow-hidden border border-gray-100 shadow-lg car-card rounded-2xl group">
                        <div class="relative h-48 overflow-hidden bg-gray-200">
                            <img src="{{ $car['image'] }}"
                                class="object-cover w-full h-full transition duration-500 group-hover:scale-110"
                                alt="{{ $car['name'] }}">
                        </div>
                        <div class="p-6">
                            <h3 class="text-xl font-bold text-blue-950">{{ $car['name'] }}</h3>
                            <p class="text-sm font-semibold tracking-wide uppercase text-sky-500">{{ $car['type'] }}
                            </p>

                            <div
                                class="flex items-center justify-between py-3 mb-4 mt-4 text-sm text-gray-600 border-t border-b border-gray-100">
                                <span class="flex items-center"><svg class="w-4 h-4 mr-1 text-amber-500"
                                        fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z">
                                        </path>
                                    </svg> {{ $car['seats'] }}</span>
                                <span class="flex items-center"><svg class="w-4 h-4 mr-1 text-amber-500" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                                        </path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg> {{ $car['trans'] }}</span>
                            </div>

                            <div class="flex items-center justify-between">
                                <p class="text-xl font-bold text-blue-950">{{ $car['price'] }}</p>
                                <button
                                    class="px-4 py-2 font-semibold text-white transition rounded-lg bg-blue-950 hover:bg-amber-400">Sewa</button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="py-24 overflow-hidden bg-slate-50 gallery-section">
        <div class="container px-4 mx-auto mb-12 text-center max-w-7xl">
            <h2 class="mb-4 text-4xl font-bold text-blue-950">Momen Pelanggan</h2>
            <p class="italic tracking-widest text-gray-500">Kisah perjalanan bersama kami</p>
        </div>

        <div class="relative flex w-full mb-6 overflow-hidden">
            <div class="flex items-center w-max gap-4 px-2 gallery-track-left">
                @foreach (array_merge($galleries, $galleries) as $img)
                    <div
                        class="relative flex-shrink-0 w-64 md:w-80 h-48 md:h-56 overflow-hidden shadow-sm cursor-pointer rounded-2xl group hover:shadow-xl transition-all">
                        <img src="{{ $img }}"
                            class="object-cover w-full h-full transition duration-700 transform group-hover:scale-110"
                            alt="Gallery">
                    </div>
                @endforeach
            </div>
        </div>

        <div class="relative flex w-full overflow-hidden">
            <div class="flex items-center w-max gap-4 px-2 gallery-track-right">
                @foreach (array_merge($galleries, $galleries) as $img)
                    <div
                        class="relative flex-shrink-0 w-64 md:w-80 h-48 md:h-56 overflow-hidden shadow-sm cursor-pointer rounded-2xl group hover:shadow-xl transition-all">
                        <img src="{{ $img }}"
                            class="object-cover w-full h-full transition duration-700 transform group-hover:scale-110"
                            alt="Gallery">
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</div>
<script src="https://unpkg.com/split-type"></script>
