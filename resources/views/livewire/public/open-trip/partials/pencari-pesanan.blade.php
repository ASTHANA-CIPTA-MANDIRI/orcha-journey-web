{{--
    Dua isian yang membuka sebuah pesanan.

    Dipisahkan ke sini bukan demi kerapian. Kode pesanan saja TIDAK BOLEH cukup
    untuk membuka kotak di bawah — kotak itu menyebut nama pemesan, trip yang
    diikutinya, dan sisa utangnya, sementara kodenya sendiri bisa ditebak
    (lihat App\Support\PemilikPesanan). Selama aturan itu ditulis di lebih dari
    satu tempat, cukup satu tempat yang lupa diperbarui untuk membocorkannya.
--}}

<div>
    <h2 class="text-xl font-bold font-heading text-orcha-navy">Pesanan yang Dibayar</h2>

    <div class="mt-4">
        <label for="kb-kode" class="label-orcha">Kode pesanan <x-wajib /></label>
        <input id="kb-kode" type="text" required maxlength="30" wire:model.live.debounce.500ms="kode"
            placeholder="OT-1508-A7K3 atau SK-1508-B2M9"
            class="isian-orcha uppercase @error('kode') isian-galat @enderror">
        <p class="mt-1.5 text-sm text-slate-500">
            Kode yang Anda terima saat mendaftar open trip atau memesan sewa kendaraan.
        </p>
        @error('kode')
            <p class="galat-orcha">{{ $message }}</p>
        @enderror
    </div>

    {{-- Kunci kedua. Alasannya di kepala berkas ini. --}}
    <div class="mt-4">
        <label for="kb-digit" class="label-orcha">
            4 digit terakhir WhatsApp Anda <x-wajib />
        </label>
        <input id="kb-digit" type="text" inputmode="numeric" required maxlength="4"
            wire:model.live.debounce.500ms="empatDigit" placeholder="7890"
            class="isian-orcha tracking-[.5em] font-bold max-w-[9rem] @error('empatDigit') isian-galat @enderror">
        <p class="mt-1.5 text-sm text-slate-500">
            Nomor yang Anda pakai saat memesan. Untuk 0812-3456-<strong>7890</strong>,
            isi <strong>7890</strong>.
        </p>
        @error('empatDigit')
            <p class="galat-orcha">{{ $message }}</p>
        @enderror
    </div>

    @if ($pesanan)
        <div class="p-4 mt-4 border rounded-2xl border-orcha-sky/40 bg-orcha-foam/50">
            <p class="text-sm font-bold text-orcha-ocean">Pesanan ditemukan</p>
            <p class="mt-1 font-bold text-orcha-navy">{{ $pesanan->nama }}</p>
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
                <dl class="grid grid-cols-3 gap-3 pt-4 mt-4 border-t border-white/70">
                    @foreach ([['Total tagihan', $tagihan['total_teks'], 'text-orcha-navy'], ['Sudah dibayar', $tagihan['sudah_teks'], 'text-orcha-ocean'], ['Sisa', $tagihan['sisa_teks'], $tagihan['lunas'] ? 'text-emerald-600' : 'text-orcha-navy']] as [$label, $nilai, $warna])
                        <div>
                            <dt class="text-[0.68rem] font-semibold tracking-wide uppercase text-slate-500">
                                {{ $label }}</dt>
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
    @elseif (strlen(trim($kode)) >= 6)
        <div class="p-4 mt-4 text-sm border rounded-2xl border-orcha-sun/50 bg-orcha-sun/10 text-slate-700">
            Kode ini belum kami temukan. Periksa lagi kode pesanan dan 4 digit terakhir
            nomor WhatsApp Anda — keduanya harus cocok.
        </div>
    @endif
</div>
