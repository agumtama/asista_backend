@extends('admin.layout')
@section('content')
<link rel="stylesheet" href="/registrant-cv.css?v=1"><main class="registrant-page">
<a class="registrant-back" href="/admin?section={{ $table ?? 'users' }}">← Kembali ke daftar</a>
@include('admin.registrant-hero')
<div class="registrant-body">
<section class="panel"><h2>Profil</h2>
<p>Email: {{ $user->email_verified_at ? 'Terverifikasi' : 'Belum terverifikasi' }}</p>
@if($profile)
<p>Kota: {{ $profile->city }} · Verifikasi profil: {{ $profile->verification }}</p>
@if($table === 'workers')<p>{{ $profile->bio }}</p><p>{{ $profile->category }} · Pengalaman {{ $profile->experience_years }} tahun</p>@include('admin.worker-pricing',['worker'=>$profile])
@else<p>Nomor legalitas: {{ $profile->legal_number }}</p><h3>Tarif & fee pekerja naungan</h3>@forelse($agencyWorkers as $worker)<h4>{{ $worker->name }}</h4>@include('admin.worker-pricing',['worker'=>$worker])@empty<p>Belum ada pekerja naungan.</p>@endforelse @endif
@else<p>Pendaftar belum melengkapi profil.</p>@endif
</section>
@if($user->registration_data)
<section class="panel"><h2>Data pendaftaran</h2><dl>
@foreach(json_decode($user->registration_data, true) ?? [] as $key=>$value)
<dt>{{ ['phone'=>'Nomor handphone','province'=>'Provinsi','city'=>'Kota / Kabupaten','district'=>'Kecamatan','address'=>'Alamat lengkap','postal_code'=>'Kode pos','birth_date'=>'Tanggal lahir','gender'=>'Jenis kelamin','education'=>'Pendidikan','experience'=>'Punya pengalaman kerja','category'=>'Layanan utama','rate_unit'=>'Satuan tarif','arrangement'=>'Pola kerja','skills'=>'Keahlian','occupation'=>'Pekerjaan','referral'=>'Sumber informasi','company_name'=>'Badan usaha','legal_number'=>'Nomor legalitas','business_type'=>'Bentuk badan usaha','kbli'=>'Bidang usaha / KBLI','company_npwp'=>'NPWP badan usaha','company_phone'=>'Telepon perusahaan','company_email'=>'Email perusahaan','website'=>'Website','social_media'=>'Media sosial','position'=>'Jabatan pengelola','identity_number'=>'NIK / paspor pengelola','manager_npwp'=>'NPWP pengelola','manager_address'=>'Alamat pengelola','name'=>'Nama pengelola','email'=>'Email pengelola'][$key] ?? $key }}</dt>
<dd>{{ is_array($value) ? implode(', ', $value) : (is_bool($value) ? ($value ? 'Ya' : 'Tidak') : ($value ?: '—')) }}</dd>
@endforeach</dl></section>
@endif
<section class="panel"><h2>Dokumen privat</h2><p>Hanya admin yang dapat mengakses dokumen. Setiap akses berkas dicatat dalam audit trail.</p>
@php($labels = ['ktp'=>'KTP','photo'=>'Foto diri','deed'=>'Akta badan usaha','nib'=>'NIB','npwp'=>'NPWP','business_license'=>'Legalitas badan usaha','supporting_document'=>'Dokumen pendukung','amendment'=>'Akta perubahan','domicile'=>'Bukti domisili usaha','bank_account'=>'Rekening perusahaan','manager_identity'=>'KTP / paspor pengelola'])
@forelse($documents as $document)
<article style="padding:20px 0;border-bottom:1px solid #ddd">
<h3>{{ $labels[$document->document_type] ?? 'Dokumen verifikasi' }}</h3>
<p>{{ $document->original_name ?? 'Dokumen lama' }} · {{ $document->status }} · {{ $document->created_at }}</p>
<a class="button" target="_blank" rel="noopener" href="{{ route('admin.document', $document->id) }}">Lihat dokumen</a>
<a href="{{ route('admin.document', ['id'=>$document->id, 'download'=>1]) }}">Unduh</a>
</article>
@empty<p>Belum ada dokumen yang diunggah. Akun lama perlu melengkapi dokumennya.</p>@endforelse
</section></div></main>
@endsection
