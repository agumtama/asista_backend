@extends('admin.layout')
@section('content')
<link rel="stylesheet" href="/agency-portal.css?v=3">
@if($tab === 'documents')<link rel="stylesheet" href="/verification.css?v=1">@endif
<aside><x-asista-logo /><p class="eyebrow">AGENCY MANAGEMENT</p><nav><a class="active" href="{{ route('agency.portal') }}">▣ Agency</a></nav><form method="post" action="/logout">@csrf<button class="secondary">Keluar</button></form></aside>
<main class="workspace agency-workspace"><header><div><p class="eyebrow">ASISTA / AGENCY</p><h1>Agency</h1><p>Kelola informasi agency, pengelola, dokumen, tarif, dan data pekerja Anda.</p></div><span class="badge">{{ auth()->user()->name }}</span></header>
@if(session('success'))<div class="notice" role="status">{{ session('success') }}</div>@endif
@foreach($errors->all() as $error)<p class="error" role="alert">{{ $error }}</p>@endforeach
<nav class="agency-tabs" aria-label="Pengaturan agency">@foreach(['profile'=>'Profil Agency','manager'=>'Pengelola Agency','documents'=>'Dokumen & Legalitas','rates'=>'Tarif & Fee','workers'=>'Data Pekerja'] as $key=>$label)<a class="{{ $key === $tab ? 'active' : '' }}" href="{{ route('agency.portal',['tab'=>$key]) }}" @if($key === $tab) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
@php($statuses = ['pending'=>'Belum Terverifikasi','verified'=>'Terverifikasi','rejected'=>'Ditolak'])
@if($tab === 'profile')
<div class="agency-profile-grid agency-profile-summary"><section class="panel agency-information">
<div class="agency-information-heading"><span class="agency-building" aria-hidden="true">@include('admin.nav-icon',['name'=>'agencies'])</span><div><h2>Informasi Agency</h2><div class="agency-name-line"><h3>{{ $agency->name }}</h3><span class="agency-state state-{{ $agency->verification }}">{{ $statuses[$agency->verification] ?? $agency->verification }}</span></div><small>Terdaftar {{ \Carbon\Carbon::parse($agency->created_at)->translatedFormat('d M Y') }}</small></div><a class="agency-edit-link" href="{{ route('agency.portal',['tab'=>'profile','edit'=>1]) }}">✎ Edit</a></div>
<dl class="agency-information-list">
@foreach(['company_email'=>'Email','company_phone'=>'Nomor Telepon','website'=>'Website','social_media'=>'Media Sosial','city'=>'Kota / Kabupaten','address'=>'Alamat','legal_number'=>'Nomor Legalitas','description'=>'Deskripsi Agency'] as $key=>$label)
<dt>{{ $label }}</dt><dd>{{ ($key === 'city' ? $agency->city : ($key === 'legal_number' ? $agency->legal_number : ($details[$key] ?? ''))) ?: 'Belum diisi' }}</dd>
@endforeach
</dl>
@if(request('edit') === '1' || $errors->any())
<div class="agency-profile-editor"><h3>Edit Profil Agency</h3>
<form class="agency-fields" method="post" action="{{ route('agency.profile') }}">@csrf
@foreach(['company_name'=>'Nama Agency','city'=>'Kota / Kabupaten','address'=>'Alamat Perusahaan','company_phone'=>'Telepon / WhatsApp','company_email'=>'Email Perusahaan','website'=>'Website (opsional)','social_media'=>'Media Sosial (opsional)'] as $key=>$label)<label>{{ $label }}<input name="{{ $key }}" value="{{ old($key,$details[$key] ?? ($key === 'company_name' ? $agency->name : ($key === 'city' ? $agency->city : ''))) }}" @required(!in_array($key,['website','social_media'])) type="{{ $key === 'company_email' ? 'email' : ($key === 'website' ? 'url' : 'text') }}"></label>@endforeach
<label class="wide">Deskripsi Agency<textarea name="description" maxlength="2000">{{ old('description',$details['description'] ?? '') }}</textarea></label><button>Simpan profil</button></form>
<a href="{{ route('agency.portal',['tab'=>'profile']) }}">Batal / tutup form</a></div>
@endif
</section><div class="agency-status-cards"><section class="panel"><h3>Status Agency</h3><span class="agency-state state-{{ $agency->verification }}">{{ $statuses[$agency->verification] ?? $agency->verification }}</span><p>{{ match($agency->verification) { 'verified'=>'Profil agency Anda sudah terverifikasi. Perubahan informasi atau dokumen akan ditinjau kembali oleh tim ASISTA.', 'rejected'=>'Pengajuan agency memerlukan perbaikan. Periksa catatan pada tab Dokumen & Legalitas dan lengkapi kembali data Anda.', default=>'Tim ASISTA sedang meninjau data dan dokumen agency Anda. Pantau status verifikasi melalui CMS ini.' } }}</p></section><section class="panel"><h3>Proses Verifikasi</h3><strong>Ditinjau oleh tim ASISTA</strong><p>Pastikan informasi dan dokumen legalitas lengkap agar pemeriksaan dapat diproses.</p><a href="{{ route('agency.portal',['tab'=>'documents']) }}">Lihat dokumen & legalitas →</a></section></div></div>
@elseif($tab === 'manager')
<section class="panel agency-managers"><div class="agency-manager-heading"><span class="agency-manager-icon" aria-hidden="true">@include('admin.nav-icon',['name'=>'users'])</span><div><h2>Data Pengelola Agency</h2><p>Kelola data pengelola yang memiliki akses ke akun agency.</p></div></div>
<div class="table-wrap"><table class="agency-manager-table"><thead><tr><th>No.</th><th>Nama</th><th>Jabatan</th><th>Email</th><th>Nomor Telepon</th><th>Akses</th><th>Status</th><th>Aksi</th></tr></thead><tbody><tr><td>1</td><td><strong>{{ auth()->user()->name }}</strong></td><td>{{ $details['position'] ?? 'Belum diisi' }}</td><td>{{ auth()->user()->email }}</td><td>{{ $details['phone'] ?? 'Belum diisi' }}</td><td>Owner</td><td><span class="agency-manager-status"><span aria-hidden="true">●</span> Aktif</span></td><td><a class="agency-manager-edit" href="{{ route('agency.portal',['tab'=>'manager','edit'=>1]) }}" aria-label="Edit pengelola {{ auth()->user()->name }}">✎ Edit</a></td></tr></tbody></table></div>
<p class="agency-manager-note">Saat ini akun agency memiliki satu pengelola utama. Akses staf tambahan belum tersedia.</p>
@if(request('edit') === '1' || $errors->any())
<div class="agency-profile-editor"><h3>Edit Pengelola Agency</h3>
<form class="agency-fields" method="post" action="{{ route('agency.manager') }}">@csrf
@foreach(['name'=>'Nama Pengelola','position'=>'Jabatan','phone'=>'Telepon','manager_address'=>'Alamat Domisili (opsional)'] as $key=>$label)<label>{{ $label }}<input name="{{ $key }}" value="{{ old($key,$key === 'name' ? auth()->user()->name : ($details[$key] ?? '')) }}" @required($key !== 'manager_address')></label>@endforeach<button>Simpan pengelola</button></form>
<a href="{{ route('agency.portal',['tab'=>'manager']) }}">Batal / tutup form</a></div>
@endif
</section>
@elseif($tab === 'documents')
@include('agency.documents')
@elseif($tab === 'rates')
<section class="panel"><h2>Pengaturan Tarif Pekerja</h2><p>Tarif pekerja per jam, hari, atau bulan. Fee agency berupa nominal rupiah sekali per booking.</p><form class="agency-fields" method="post" action="{{ route('agency.rates') }}">@csrf<label>Jenis Pekerjaan<select name="category"><option value="babysitter">Babysitter</option><option value="art">ART</option></select></label><label>Durasi<select name="rate_unit"><option value="hourly">Per Jam</option><option value="daily">Harian</option><option value="monthly">Bulanan</option></select></label><label>Pola Kerja<select name="arrangement"><option value="live_out">PP / Live-out</option><option value="live_in">Live-in</option></select></label><label>Tarif Pekerja (Rp)<input type="number" name="rate" min="10000" max="100000000" required></label><label>Fee Agency / Booking (Rp)<input type="number" name="agency_fee" min="0" max="100000000" required></label><button>Simpan / perbarui tarif</button></form><p><small>Kombinasi jenis, durasi, dan pola kerja yang sama akan diperbarui. Penerapan hanya tersedia setelah agency terverifikasi.</small></p><div class="table-wrap"><table><thead><tr><th>Jenis</th><th>Durasi</th><th>Pola</th><th>Tarif</th><th>Fee Agency</th><th>Tindakan</th></tr></thead><tbody>@forelse($rates as $rate)<tr><td>{{ strtoupper($rate->category) }}</td><td>{{ ['hourly'=>'Per Jam','daily'=>'Harian','monthly'=>'Bulanan'][$rate->rate_unit] }}</td><td>{{ $rate->arrangement }}</td><td>Rp {{ number_format($rate->rate,0,',','.') }}</td><td>Rp {{ number_format($rate->agency_fee,0,',','.') }}</td><td><form method="post" action="{{ route('agency.rates.apply',$rate->id) }}">@csrf<button @disabled($agency->verification !== 'verified')>Terapkan ke pekerja sesuai</button></form></td></tr>@empty<tr><td colspan="6">Belum ada pengaturan tarif.</td></tr>@endforelse</tbody></table></div></section>
@elseif($tab === 'workers')
<section class="panel"><h2>Data Pekerja Naungan Agency</h2><form class="agency-upload" method="get"><input type="hidden" name="tab" value="workers"><input name="q" value="{{ request('q') }}" placeholder="Cari nama atau kota pekerja" aria-label="Cari pekerja"><button>Cari</button><a href="{{ route('agency.portal',['tab'=>'workers']) }}">Reset</a></form><div class="table-wrap"><table><thead><tr><th>Nama</th><th>Jenis</th><th>Lokasi</th><th>Pengalaman</th><th>Status</th><th>Tarif / Fee</th><th>Detail</th></tr></thead><tbody>@forelse($workers as $worker)<tr><td><strong>{{ $worker->name }}</strong></td><td>{{ strtoupper($worker->category) }}</td><td>{{ $worker->city }}</td><td>{{ $worker->experience_years }} tahun</td><td>{{ $statuses[$worker->verification] ?? $worker->verification }}</td><td>Rp {{ number_format($worker->rate,0,',','.') }} / {{ $worker->rate_unit }}<br><small>Fee Rp {{ number_format($worker->agency_fee,0,',','.') }}</small></td><td><a class="button secondary" href="{{ route('agency.worker',$worker->id) }}">Lihat CV & video →</a></td></tr>@empty<tr><td colspan="7">Belum ada pekerja yang sesuai. Hubungan agency–pekerja mengikuti persetujuan pada aplikasi ASISTA.</td></tr>@endforelse</tbody></table></div>
@include('admin.pagination',['page'=>$workers,'anchor'=>''])
</section>@endif
</main>
@if($tab === 'documents')
<dialog id="document-modal" aria-labelledby="document-modal-title"><div class="document-modal-header"><h2 id="document-modal-title">Preview dokumen</h2><button type="button" id="document-modal-close" aria-label="Tutup preview">Tutup ×</button></div><img id="document-modal-image" alt=""><a id="document-modal-original" target="_blank" rel="noopener">Buka ukuran asli &nearr;</a></dialog>
<script src="/verification.js?v=1" defer></script>
@endif
@endsection
