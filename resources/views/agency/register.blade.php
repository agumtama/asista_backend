@extends('admin.layout')
@section('content')
<link rel="stylesheet" href="/agency-registration.css?v=1">
<main class="agency-registration"><x-asista-logo /><h1>Buat Akun Agency</h1><p class="muted">Lengkapi informasi agency untuk membuat akun admin agency.</p>
<div class="agency-account"><strong>▣ Admin Agency</strong><span>Mengelola agency dan pekerja naungan. Akun Admin Owner hanya dibuat oleh pengelola ASISTA.</span></div>
@if($errors->any())<div class="error" role="alert"><strong>Periksa kembali data berikut:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><p>Untuk keamanan, pilih ulang dokumen dan masukkan kembali kata sandi.</p></div>@endif
<ol class="registration-steps">@foreach(['Informasi Agency','Data Pengelola','Dokumen Legalitas','Konfirmasi'] as $step)<li><span>{{ $loop->iteration }}</span>{{ $step }}</li>@endforeach</ol>
<form id="agency-form" method="post" action="{{ route('agency.register.store') }}" enctype="multipart/form-data">@csrf
<section class="panel registration-step" data-step="0"><h2>Informasi Agency</h2><p class="muted">Data perusahaan / badan usaha sesuai dengan dokumen resmi.</p><div class="registration-fields">
@include('agency.field',['name'=>'company_name','label'=>'Nama Perusahaan / Agency'])
<label>Bentuk Badan Usaha *<select name="business_type" required><option value="">Pilih bentuk usaha</option>@foreach(['PT','CV','Koperasi','Yayasan','Lainnya'] as $type)<option @selected(old('business_type') === $type)>{{ $type }}</option>@endforeach</select></label>
@include('agency.field',['name'=>'kbli','label'=>'Bidang Usaha / KBLI'])
@include('agency.field',['name'=>'company_npwp','label'=>'NPWP Badan Usaha'])
@include('agency.field',['name'=>'city','label'=>'Kota / Kabupaten'])
@include('agency.field',['name'=>'legal_number','label'=>'Nomor Induk Berusaha (NIB)'])
<label class="wide">Alamat Lengkap Perusahaan *<textarea name="address" required maxlength="500" placeholder="Jalan, RT/RW, Kelurahan, Kecamatan, Kota/Kabupaten, Kode Pos">{{ old('address') }}</textarea></label>
@include('agency.field',['name'=>'company_phone','label'=>'Telepon Kantor / WA Resmi','type'=>'tel'])
@include('agency.field',['name'=>'company_email','label'=>'Email Resmi Perusahaan','type'=>'email'])
@include('agency.field',['name'=>'website','label'=>'Website Resmi','type'=>'url','optional'=>true])
@include('agency.field',['name'=>'social_media','label'=>'Media Sosial Resmi','optional'=>true])
</div></section>
<section class="panel registration-step" data-step="1"><h2>Data Pengelola</h2><p class="muted">Penanggung jawab / pengurus / PIC yang mengelola akun agency.</p><div class="registration-fields">
@include('agency.field',['name'=>'name','label'=>'Nama Lengkap Penanggung Jawab'])
@include('agency.field',['name'=>'position','label'=>'Jabatan'])
@include('agency.field',['name'=>'identity_number','label'=>'NIK / Nomor KTP / Paspor'])
@include('agency.field',['name'=>'manager_npwp','label'=>'NPWP Pribadi Pengurus'])
@include('agency.field',['name'=>'phone','label'=>'Nomor Telepon / Handphone','type'=>'tel'])
@include('agency.field',['name'=>'email','label'=>'Email Pengurus (akun masuk)','type'=>'email'])
@include('agency.field',['name'=>'password','label'=>'Kata Sandi (minimal 10 karakter)','type'=>'password'])
@include('agency.field',['name'=>'password_confirmation','label'=>'Ulangi Kata Sandi','type'=>'password'])
<label class="wide">Alamat Domisili Pengurus (opsional)<textarea name="manager_address" maxlength="500">{{ old('manager_address') }}</textarea></label>
</div></section>
<section class="panel registration-step" data-step="2"><h2>Dokumen Legalitas</h2><p class="muted">Unggah PDF, JPG, atau PNG, maksimal 5 MB per file. Dokumen disimpan privat untuk pemeriksaan admin.</p>
@foreach($documents as $key=>$label)<label class="registration-upload"><span><strong>{{ $label }}{{ $key !== 'amendment' ? ' *' : '' }}</strong><small>PDF / JPG / PNG · Maks. 5 MB</small></span><input type="file" name="{{ $key }}" accept=".pdf,.jpg,.jpeg,.png" @required($key !== 'amendment')></label>@endforeach
</section>
<section class="panel registration-step" data-step="3"><div class="registration-confirm"><span>✓</span><h2>Periksa kembali data Anda</h2><p class="muted">Pastikan informasi sesuai sebelum mengirim registrasi untuk diverifikasi.</p></div>
<div id="registration-review"></div><label class="registration-consent"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))> Saya menyatakan data dan dokumen benar serta menyetujui pemeriksaan legalitas oleh tim ASISTA.</label><p class="muted">Kode verifikasi akan dikirim ke email pengurus. Verifikasi email tidak otomatis menyetujui legalitas agency.</p></section>
<div class="registration-navigation"><a href="{{ route('login') }}" id="registration-login">← Kembali ke login</a><button type="button" class="secondary" id="registration-back" hidden>← Kembali</button><button type="button" id="registration-next" hidden>Lanjutkan →</button><button type="submit" id="registration-submit">Submit Registrasi</button></div>
</form></main><script src="/agency-registration.js?v=1" defer></script>
@endsection
