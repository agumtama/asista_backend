@extends('admin.layout')
@section('content')
<main class="brand-page">
    <header><x-asista-logo /><a class="button secondary" href="/admin">Masuk CMS →</a></header>
    <section class="brand-intro">
        <p class="eyebrow">BANTUAN TERPERCAYA UNTUK KELUARGA</p>
        <h1>Membantu. Mendampingi.<br>Memberi dukungan.</h1>
        <p>ASISTA terinspirasi dari kata <em>assist</em>, yang berarti membantu, mendampingi, dan memberikan dukungan.</p>
        <p>ASISTA menjadi penghubung terpercaya antara keluarga, pekerja, dan agency agar setiap hubungan kerja dapat dimulai dengan informasi yang jelas, proses yang aman, dan rekam jejak yang transparan.</p>
    </section>
    <div class="brand-values">
        @foreach([
            ['A','Aman','Keamanan menjadi fondasi bagi keluarga maupun pekerja.'],
            ['S','Selektif','Keluarga, pekerja mandiri, dan agency melalui proses verifikasi sesuai standar platform.'],
            ['I','Integritas','Komunikasi, transaksi, review, dan riwayat pekerjaan tercatat sehingga membangun reputasi yang dapat dipertanggungjawabkan.'],
            ['S','Setara','Pekerja, majikan, dan agency sama-sama memiliki hak membangun reputasi dan mendapatkan perlindungan.'],
            ['T','Transparan','Job description, tarif, status pekerja, agency, pengalaman, serta biaya ditampilkan sejelas mungkin.'],
            ['A','Andal','Membantu keluarga menemukan bantuan yang sesuai kebutuhan—mulai dari beberapa jam, harian, PP, hingga live-in.'],
        ] as [$letter,$title,$description])
        <article class="panel"><span class="value-letter">{{ $letter }}</span><h2>{{ $title }}</h2><p>{{ $description }}</p></article>
        @endforeach
    </div>
    <footer>ASISTA · Bantuan terpercaya untuk keluarga</footer>
</main>
@endsection
