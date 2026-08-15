<?php

/**
 * Orcha tidak lagi punya halaman login sendiri.
 *
 * Pengelolaannya pindah ke dashboard lemon supaya admin cukup satu akun. Uji
 * ini menjaga supaya halaman-halaman itu tidak diam-diam hidup lagi — misalnya
 * karena paket auth dipasang ulang.
 */
test('halaman login dan daftar sudah tidak ada', function (string $url) {
    $this->get($url)->assertNotFound();
})->with([
    '/login',
    '/register',
    '/forgot-password',
    '/two-factor-challenge',
]);

test('panel admin bawaan sudah tidak ada', function (string $url) {
    $this->get($url)->assertNotFound();
})->with([
    '/admin/dashboard',
    '/admin/pendaftaran',
    '/admin/penyewaan',
    '/admin/pembatalan',
    '/admin/pesan',
    '/admin/paket-wisata',
    '/admin/sewa-kendaraan',
    '/admin/destinasi',
    '/admin/testimoni',
    '/admin/partner',
    '/settings/profile',
]);

test('halaman publik tetap hidup seperti biasa', function (string $url) {
    $this->get($url)->assertOk();
})->with([
    '/',
    '/paket-wisata',
    '/sewa-kendaraan',
    '/destinasi',
    '/kontak',
    '/pendaftaran-open-trip',
    '/faq',
]);

test('api dashboard tetap jalan meski panel bawaan mati', function () {
    config()->set('orcha.api.kunci', 'kunci-uji-mati');

    $this->getJson('/api/v1/ping', [
        'X-Orcha-Key' => 'kunci-uji-mati',
        'Accept' => 'application/json',
    ])->assertOk();
});
