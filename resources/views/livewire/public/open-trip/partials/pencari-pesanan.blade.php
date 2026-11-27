{{--
    Dua isian yang membuka sebuah pesanan.

    Dipisahkan ke sini bukan demi kerapian. Kode pesanan saja TIDAK BOLEH cukup
    untuk membuka kotak di bawah — kotak itu menyebut nama pemesan, trip yang
    diikutinya, dan sisa utangnya, sementara kodenya sendiri bisa ditebak
    (lihat App\Support\PemilikPesanan). Selama aturan itu ditulis di lebih dari
    satu tempat, cukup satu tempat yang lupa diperbarui untuk membocorkannya.
--}}

<div>
    {{-- label-bagian + judul-panel-kecil: kepala panel yang sama dengan
         beranda dan halaman paket. Judul telanjang tanpa label di atasnya
         adalah satu-satunya kepala panel yang berbeda bentuk di halaman ini,
         dan beda sendiri itu yang terbaca "belum dirapikan". --}}
    <p class="label-bagian"><span class="w-6 h-px bg-orcha-sun"></span> Pesanan Anda</p>
    <h2 class="mt-3 judul-panel-kecil">Pesanan yang dibayar.</h2>
    <p class="mt-1.5 text-sm text-slate-500">
        Dua isian ini membuka pesanan Anda. Keduanya harus cocok.
    </p>

    {{-- DUA KOLOM, lebarnya sesuai isinya.

         Bertumpuk, kotak selebar panel untuk empat angka terbaca seperti
         isian panjang yang entah kenapa kosong, dan dua baris yang
         sama-sama selebar panel membuat matanya tidak punya tempat
         berhenti. Di layar sempit keduanya tetap bertumpuk — di sana
         memang tidak ada lebar untuk dibagi. --}}
    <div class="grid gap-4 mt-5 sm:grid-cols-5">
        <div class="sm:col-span-3">
            {{-- Ubin ikonnya DI LUAR <label>, dan barisnya yang diberi flex.

                 .label-orcha memasang display: block, dan aturan itu berada di
                 LUAR @layer sedangkan utilitas Tailwind di dalamnya — jadi
                 `flex` yang ditempel pada <label> kalah tanpa satu pun galat,
                 dan ubinnya berdiri sendiri di baris atas seperti gambar yang
                 lupa ditaruh. --}}
            <div class="flex items-center gap-2 mb-1.5">
                <span class="grid w-6 h-6 shrink-0 place-items-center rounded-lg ubin-toska">
                    <x-heroicon-o-ticket class="w-3.5 h-3.5" />
                </span>
                <label for="kb-kode" class="label-orcha !mb-0">Kode pesanan <x-wajib /></label>
            </div>
            <input id="kb-kode" type="text" required maxlength="30" wire:model.live.debounce.500ms="kode"
                placeholder="OT-1508-A7K3 atau SK-1508-B2M9"
                class="isian-orcha uppercase @error('kode') isian-galat @enderror">
            <p class="catatan-isian">
                Kode yang Anda terima saat mendaftar open trip atau memesan sewa kendaraan.
            </p>
            @error('kode')
                <p class="galat-orcha">{{ $message }}</p>
            @enderror
        </div>

        {{-- Kunci kedua. Alasannya di kepala berkas ini. --}}
        <div class="sm:col-span-2">
            <div class="flex items-center gap-2 mb-1.5">
                <span class="grid w-6 h-6 shrink-0 place-items-center rounded-lg ubin-toska">
                    <x-heroicon-o-device-phone-mobile class="w-3.5 h-3.5" />
                </span>
                <label for="kb-digit" class="label-orcha !mb-0">4 digit WhatsApp <x-wajib /></label>
            </div>
            <input id="kb-digit" type="text" inputmode="numeric" required maxlength="4"
                wire:model.live.debounce.500ms="empatDigit" placeholder="7890"
                class="isian-orcha tracking-[.4em] font-bold text-center @error('empatDigit') isian-galat @enderror">
            <p class="catatan-isian">
                Nomor yang Anda pakai saat memesan. Untuk 0812-3456-<strong>7890</strong>,
                isi <strong>7890</strong>.
            </p>
            @error('empatDigit')
                <p class="galat-orcha">{{ $message }}</p>
            @enderror
        </div>
    </div>

    @if ($pesanan)
        {{-- border-orcha-sky/40 dan text-orcha-ocean di sini memakai peubah palet
             LAMA yang biru; palet hangat beranda menimpanya jadi emas pucat dan
             abu kehijauan, sehingga kotak "ketemu" terbaca sewarna kotak "belum
             ketemu" di bawahnya. Dua kabar yang berlawanan dengan warna yang
             sama adalah kabar yang tidak menyampaikan apa-apa. --}}
        <div class="mt-5 kotak-temuan">
            <p class="flex items-center gap-2 text-sm font-bold text-orcha-toska">
                <x-heroicon-s-check-circle class="w-5 h-5" />
                <span>Pesanan ditemukan</span>
            </p>
            <p class="mt-2 font-bold text-orcha-navy">{{ $pesanan->nama }}</p>
            <p class="text-sm text-slate-600">
                @if ($pesanan instanceof App\Models\OpenTrip\PendaftaranOpenTrip)
                    {{ $pesanan->nama_paket }} · {{ $pesanan->jumlah_peserta }} peserta
                @else
                    {{ $pesanan->nama_kendaraan }} · {{ $pesanan->durasi_label }}
                @endif
            </p>

            @if ($tagihan)
                {{-- "Sudah dibayar", bukan "Sudah dilaporkan".

                     Kata keduanya dipakai selama pembayaran masih berupa bukti
                     unggahan yang belum dicek siapa pun. Sejak seluruh pembayaran
                     publik lewat gerbang, tiap rupiah di kolom ini sudah dipastikan
                     notifikasi bertanda tangan — dan "dilaporkan" justru terdengar
                     seperti masih diragukan. --}}
                <dl class="grid grid-cols-3 gap-3 pisah-temuan">
                    @foreach ([['Total tagihan', $tagihan['total_teks'], 'text-orcha-navy'], ['Sudah dibayar', $tagihan['sudah_teks'], 'text-orcha-toska'], ['Sisa', $tagihan['sisa_teks'], $tagihan['lunas'] ? 'text-emerald-600' : 'text-orcha-navy']] as [$label, $nilai, $warna])
                        <div>
                            <dt class="label-fakta">{{ $label }}</dt>
                            <dd class="text-sm font-bold {{ $warna }}">{{ $nilai }}</dd>
                        </div>
                    @endforeach
                </dl>

                <p class="mt-3 text-sm {{ $tagihan['lunas'] ? 'text-emerald-700' : 'text-slate-600' }}">
                    @if ($tagihan['lunas'])
                        Seluruh pembayaran Anda sudah tercatat. Tidak ada yang perlu dibayar lagi.
                    @elseif ($tagihan['sudah'] > 0)
                        Uang muka Anda sudah tercatat, jadi yang tersisa tinggal pelunasannya.
                    @else
                        {{-- Sebagian pelanggan lebih suka sekali bayar dan selesai. Tanpa
                             keterangan ini mereka mengira DP itu wajib, lalu membayar dua
                             kali untuk sesuatu yang bisa sekali. --}}
                        Anda boleh membayar uang muka {{ $tagihan['dp_persen'] }}% dulu, atau
                        langsung lunas sekaligus. Pilih di bawah.
                    @endif
                </p>
            @endif
        </div>
    {{-- "Belum ketemu" hanya setelah KEDUA isian terisi.

         Dengan syarat lama (kode saja >= 6 huruf), kotak itu muncul begitu
         kode selesai diketik — sementara empat digitnya memang belum
         diisi. Orang yang baru mengetik separuh formulir langsung diberi
         tahu pesanannya tidak ada, padahal kodenya benar: yang kurang
         kunci keduanya, dan sebagian berhenti di situ menyangka
         pendaftarannya gagal. --}}
    @elseif (strlen(trim($kode)) >= 6 && strlen(trim((string) $empatDigit)) === 4)
        {{-- Emas berikut ikon kaca pembesar, bukan kotak kabar buruk tanpa rupa.

             Dan diberi JALAN KELUAR: yang sampai di sini biasanya salah ketik,
             tetapi sebagian memang belum pernah mendaftar — tanpa tautan itu,
             satu-satunya pilihan mereka adalah menutup tab. --}}
        <div class="mt-5 kotak-nihil">
            <span class="grid w-9 h-9 shrink-0 place-items-center rounded-xl ubin-toska">
                <x-heroicon-o-magnifying-glass class="w-5 h-5" />
            </span>

            <div>
                <p class="text-sm font-bold text-orcha-navy">Kode ini belum kami temukan</p>
                <p class="mt-1 text-sm text-slate-600">
                    Periksa lagi kode pesanan dan 4 digit terakhir nomor WhatsApp Anda —
                    keduanya harus cocok.
                </p>

                <div class="flex flex-wrap gap-x-5 gap-y-2 mt-3">
                    <a href="{{ route('lacak-pesanan') }}" class="tautan-lanjut">
                        <span>Lacak pesanan saya</span>
                        <x-heroicon-s-arrow-right class="w-4 h-4" />
                    </a>
                    <a href="{{ route('pendaftaran-open-trip') }}" class="tautan-lanjut">
                        <span>Belum punya pesanan? Daftar di sini</span>
                        <x-heroicon-s-arrow-right class="w-4 h-4" />
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>
