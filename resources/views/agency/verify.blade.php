@extends('admin.layout')
@section('content')
<link rel="stylesheet" href="/agency-registration.css?v=1">
<main class="agency-registration verification-email"><x-asista-logo /><section class="panel">
@if($user->email_verified_at)<div class="registration-confirm"><span>✓</span><h1>Email terverifikasi</h1><p>Pendaftaran agency berhasil diterima. Dokumen legalitas Anda sedang menunggu pemeriksaan tim ASISTA.</p><p>Akun agency dapat digunakan pada aplikasi ASISTA dan CMS website. Masuk dengan email dan kata sandi pendaftaran Anda.</p></div>
@else<h1>Verifikasi Email</h1><p>Masukkan kode yang dikirim ke <strong>{{ $user->email }}</strong>.</p>
@if(session('success'))<p class="notice" role="status">{{ session('success') }}</p>@endif
@foreach($errors->all() as $error)<p class="error" role="alert">{{ $error }}</p>@endforeach
<form method="post" action="{{ route('agency.verify') }}">@csrf<label>Kode Verifikasi<input class="otp-input" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required aria-label="Kode verifikasi 6 digit"></label><button>Verifikasi →</button></form><form method="post" action="{{ route('agency.resend') }}">@csrf<p class="muted">Kode berlaku 10 menit. Pengiriman ulang tersedia setiap 60 detik.</p><button class="secondary">Kirim ulang kode</button></form>@endif
<p><a href="{{ route('login') }}">Kembali ke halaman login</a></p></section></main>
@endsection
