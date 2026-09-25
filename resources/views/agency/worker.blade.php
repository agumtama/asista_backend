@extends('admin.layout')
@section('content')
<link rel="stylesheet" href="/agency-worker-cv.css?v=1">
<main class="worker-dossier"><a href="{{ route('agency.portal',['tab'=>'workers']) }}">← Kembali ke Data Pekerja</a>
@if(session('success'))<p class="notice">{{ session('success') }}</p>@endif
@foreach($errors->all() as $error)<p class="error">{{ $error }}</p>@endforeach
@php($tab = in_array(request('view'),['experience','skills','documents','video','availability']) ? request('view') : 'profile')
@php($statusLabels = ['verified'=>'Terverifikasi','pending'=>'Menunggu verifikasi','rejected'=>'Ditolak'])
<div class="dossier-layout"><div class="dossier-media">
@if($hasPhoto)<a href="{{ route('agency.worker.photo',$worker->id) }}" target="_blank" rel="noopener"><img class="dossier-photo" src="{{ route('agency.worker.photo',$worker->id) }}" alt="Foto {{ $worker->name }}"></a>@else<div class="dossier-placeholder">{{ mb_strtoupper(mb_substr($worker->name,0,1)) }}<small>Foto belum tersedia</small></div>@endif
@if($worker->video_path)<video controls preload="metadata" src="{{ route('agency.video',$worker->id) }}" aria-label="Video perkenalan {{ $worker->name }}"></video>@else<div class="dossier-video-empty">▷<small>Video belum diunggah</small></div>@endif
</div><div class="dossier-main"><div class="dossier-heading"><div><h1>{{ $worker->name }}</h1><span class="dossier-status {{ $worker->verification }}">{{ $statusLabels[$worker->verification] ?? $worker->verification }}</span></div><a class="dossier-contact" href="mailto:{{ $workerUser->email }}">Hubungi via email</a></div>
<p class="dossier-rating">★ {{ $rating !== null ? number_format($rating,1).' ('.$reviewCount.' ulasan)' : 'Belum ada penilaian' }} <small>Transaksi melalui agency Anda</small></p>
<div class="dossier-tags"><span>{{ $worker->category === 'art' ? 'ART' : 'Babysitter' }}</span><span>{{ $worker->city }}</span><span>{{ $worker->experience_years }} tahun pengalaman</span></div>
<nav class="dossier-tabs" aria-label="Detail pekerja">@foreach(['profile'=>'Profil','experience'=>'Pengalaman','skills'=>'Keahlian','documents'=>'Dokumen','video'=>'Video','availability'=>'Ketersediaan'] as $key=>$label)<a class="{{ $tab === $key ? 'active' : '' }}" href="{{ route('agency.worker',['id'=>$worker->id,'view'=>$key]) }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
@if($tab === 'profile')
<div class="dossier-columns"><section class="panel"><h2>Informasi Pribadi</h2><dl>
@foreach(['Nama lengkap'=>$worker->name,'Tanggal lahir'=>$registration['birth_date'] ?? null,'Jenis kelamin'=>$registration['gender'] ?? null,'Pendidikan'=>$registration['education'] ?? null,'Domisili'=>$worker->city,'Nomor handphone'=>$registration['phone'] ?? null,'Email'=>$workerUser->email] as $label=>$value)<dt>{{ $label }}</dt><dd>{{ $value ?: 'Belum diisi' }}</dd>@endforeach
</dl></section><section class="panel"><h2>Tentang Saya</h2><p>{{ $worker->bio ?: 'Belum diisi' }}</p><h3>Tarif Pekerja</h3><strong>Rp {{ number_format($worker->rate,0,',','.') }} / {{ ['hourly'=>'jam','daily'=>'hari','monthly'=>'bulan'][$worker->rate_unit] ?? $worker->rate_unit }}</strong><p>Fee agency: Rp {{ number_format($worker->agency_fee,0,',','.') }} / booking</p><a href="{{ route('agency.worker',['id'=>$worker->id,'view'=>'video']) }}">Lihat / kelola video perkenalan →</a></section></div>
@elseif($tab === 'experience')
<section class="panel"><h2>Pengalaman Kerja</h2><p>{{ $worker->experience_years }} tahun pengalaman sesuai profil pekerja.</p><small>Riwayat booking selesai melalui agency Anda, tidak termasuk transaksi demo.</small>@forelse($history as $booking)<article class="dossier-history"><strong>{{ \Carbon\Carbon::parse($booking->starts_at)->format('d M Y') }} – {{ \Carbon\Carbon::parse($booking->ends_at)->format('d M Y') }}</strong><p>{{ $booking->scope }}</p></article>@empty<p>Belum ada riwayat pekerjaan selesai yang tercatat.</p>@endforelse
@include('admin.cv-pagination',['page'=>$history,'anchor'=>''])
</section>
@elseif($tab === 'skills')
<section class="panel"><h2>Keahlian</h2><div class="dossier-tags">@forelse(json_decode($worker->skills,true) ?? [] as $skill)<span>{{ $skill }}</span>@empty<p>Belum diisi.</p>@endforelse</div><h3>Sertifikasi</h3>@forelse(json_decode($worker->certifications ?? '[]',true) ?? [] as $certificate)<p>{{ $certificate }}</p>@empty<p>Belum ada sertifikasi.</p>@endforelse</section>
@elseif($tab === 'documents')
<section class="panel"><h2>Status Dokumen</h2><p>Ringkasan pemeriksaan dokumen pekerja. Berkas identitas privat hanya dapat dibuka oleh admin platform.</p>@forelse($documentStatuses as $document)<div class="dossier-document"><strong>{{ ['photo'=>'Foto diri','ktp'=>'KTP','family_card'=>'Kartu Keluarga','supporting_document'=>'Dokumen pendukung','training_certificate'=>'Sertifikat pelatihan'][$document->document_type] ?? 'Dokumen verifikasi' }}</strong><span class="dossier-status {{ $document->status }}">{{ $statusLabels[$document->status] ?? $document->status }}</span></div>@empty<p>Belum ada dokumen yang diunggah.</p>@endforelse</section>
@elseif($tab === 'video')
<section class="panel"><h2>Video Perkenalan</h2><p>{{ $worker->video_path ? 'Video tersedia di panel foto. Gunakan kontrol pemutar untuk memperbesar atau memutar video.' : 'Belum ada video perkenalan pekerja.' }}</p><form method="post" enctype="multipart/form-data" action="{{ route('agency.video.upload',$worker->id) }}">@csrf<label>Unggah / ganti video (MP4 atau WebM, maks. 20 MB)<input type="file" name="video" accept=".mp4,.webm" required></label><button>Simpan video</button></form><p><small>Unggah video yang telah disetujui pekerja untuk digunakan sebagai portofolio.</small></p></section>
@else
<section class="panel"><h2>Ketersediaan</h2><dl><dt>Status</dt><dd>{{ $worker->available ? 'Tersedia' : 'Tidak tersedia' }}</dd><dt>Pola kerja</dt><dd>{{ $worker->arrangement === 'live_in' ? 'Live-in / menginap' : 'PP / Live-out' }}</dd><dt>Durasi layanan</dt><dd>{{ ['hourly'=>'Per jam','daily'=>'Harian','monthly'=>'Bulanan'][$worker->rate_unit] ?? $worker->rate_unit }}</dd></dl><p>Status mengikuti informasi profil pekerja.</p></section>
@endif
</div></div></main>
@endsection