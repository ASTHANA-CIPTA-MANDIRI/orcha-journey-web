<?php

/**
 * Pesan validasi Bahasa Indonesia.
 *
 * Hanya memuat aturan yang benar-benar dipakai di aplikasi ini; aturan lain
 * otomatis jatuh ke pesan bawaan Laravel (Bahasa Inggris) lewat fallback locale.
 */
return [
    'accepted' => 'Kolom :attribute wajib disetujui.',
    'after' => 'Kolom :attribute harus berisi tanggal setelah :date.',
    'after_or_equal' => 'Kolom :attribute harus berisi tanggal :date atau setelahnya.',
    'array' => 'Kolom :attribute harus berupa daftar.',
    'before' => 'Kolom :attribute harus berisi tanggal sebelum :date.',
    'boolean' => 'Kolom :attribute hanya boleh berisi ya atau tidak.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'date' => 'Kolom :attribute bukan tanggal yang sah.',
    'email' => 'Kolom :attribute harus berupa alamat email yang sah.',
    'image' => 'Kolom :attribute harus berupa gambar.',
    'in' => 'Pilihan :attribute tidak sah.',
    'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
    'max' => [
        'array' => 'Kolom :attribute tidak boleh lebih dari :max item.',
        'file' => 'Ukuran :attribute tidak boleh lebih dari :max kilobita.',
        'numeric' => 'Kolom :attribute tidak boleh lebih dari :max.',
        'string' => 'Kolom :attribute tidak boleh lebih dari :max karakter.',
    ],
    'min' => [
        'array' => 'Kolom :attribute minimal berisi :min item.',
        'file' => 'Ukuran :attribute minimal :min kilobita.',
        'numeric' => 'Kolom :attribute minimal :min.',
        'string' => 'Kolom :attribute minimal :min karakter.',
    ],
    'numeric' => 'Kolom :attribute harus berupa angka.',
    'regex' => 'Format :attribute tidak sesuai.',
    'required' => 'Kolom :attribute wajib diisi.',
    'string' => 'Kolom :attribute harus berupa teks.',
    'unique' => 'Kolom :attribute sudah terdaftar.',
    'exists' => 'Pilihan :attribute tidak ditemukan.',

    'attributes' => [
        'nama' => 'nama',
        'nama_peserta' => 'nama peserta',
        'whatsapp' => 'nomor WhatsApp',
        'email' => 'email',
        'pesan' => 'pesan',
        'keperluan' => 'keperluan',
        'kode_pendaftaran' => 'kode pendaftaran',
        'jumlah_peserta' => 'jumlah peserta',
        'tanggal_berangkat' => 'tanggal berangkat',
        'titik_jemput' => 'titik jemput',
        'catatan' => 'catatan',
        'usia' => 'usia',
        'golongan_darah' => 'golongan darah',
        'riwayat_penyakit' => 'riwayat penyakit',
        'alergi' => 'alergi',
        'obat_rutin' => 'obat rutin',
        'pantangan_kegiatan' => 'pantangan kegiatan',
        'kontak_darurat_nama' => 'nama kontak darurat',
        'kontak_darurat_hp' => 'nomor kontak darurat',
        'kontak_darurat_hubungan' => 'hubungan kontak darurat',
        'setuju_data_kesehatan' => 'persetujuan data kesehatan',
        'paketId' => 'paket',
    ],
];
