@extends('admin.layout')
@section('content')
<main class="workspace" style="margin-left:0;max-width:1100px;margin-inline:auto">
<a href="/admin?section={{ $table ?? 'users' }}">← Kembali ke daftar</a>
<header><div><p class="eyebrow">ASISTA / DATA PENDAFTAR</p><h1>{{ $profile->name ?? $user->name }}</h1><p>{{ $user->email }} · {{ $user->role }} · {{ $user->verification }}</p></div></header>
<section class="panel"><h2>Profil</h2>
@if($profile)
<p>Kota: {{ $profile->city }} · Verifikasi profil: {{ $profile->verification }}</p>
@if($table === 'workers')<p>{{ $profile->bio }}</p><p>{{ $profile->category }} · Pengalaman {{ $profile->experience_years }} tahun</p>
@else<p>Nomor legalitas: {{ $profile->legal_number }}</p>@endif
@else<p>Pendaftar belum melengkapi profil.</p>@endif
</section>
<section class="panel"><h2>Dokumen privat</h2><p>Hanya admin yang dapat mengakses dokumen. Setiap akses berkas dicatat dalam audit trail.</p>
@php($labels = ['ktp'=>'KTP','photo'=>'Foto diri','deed'=>'Akta badan usaha','nib'=>'NIB','npwp'=>'NPWP','business_license'=>'Legalitas badan usaha','supporting_document'=>'Dokumen pendukung'])
@forelse($documents as $document)
<article style="padding:20px 0;border-bottom:1px solid #ddd">
<h3>{{ $labels[$document->document_type] ?? 'Dokumen verifikasi' }}</h3>
<p>{{ $document->original_name ?? 'Dokumen lama' }} · {{ $document->status }} · {{ $document->created_at }}</p>
<a class="button" target="_blank" rel="noopener" href="{{ route('admin.document', $document->id) }}">Lihat dokumen</a>
<a href="{{ route('admin.document', ['id'=>$document->id, 'download'=>1]) }}">Unduh</a>
</article>
@empty<p>Belum ada dokumen yang diunggah. Akun lama perlu melengkapi dokumennya.</p>@endforelse
</section></main>
@endsection
