<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($baris as [$alamat, $diubah, $ubah, $prioritas])
    <url>
        <loc>{{ $alamat }}</loc>
@if ($diubah)
        <lastmod>{{ $diubah->toAtomString() }}</lastmod>
@endif
        <changefreq>{{ $ubah }}</changefreq>
        <priority>{{ $prioritas }}</priority>
    </url>
@endforeach
</urlset>
