@extends('admin.layout')
@section('content')
<main class="login"><x-asista-logo /><p class="eyebrow">BANTUAN TERPERCAYA UNTUK KELUARGA</p><h1>Ruang kerja untuk<br>kepercayaan keluarga.</h1><p>Kelola verifikasi, layanan, dan keselamatan dalam satu tempat.</p><form method="post" action="/login" class="panel">@csrf<h2>Masuk ke CMS</h2>@foreach($errors->all() as $error)<p class="error">{{ $error }}</p>@endforeach<label>Email admin<input name="email" type="email" required autocomplete="username" value="{{ old('email') }}"></label><label>Kata sandi<input name="password" type="password" required autocomplete="current-password"></label><button>Masuk dashboard →</button></form><small>Akses terbatas untuk tim operasional platform.</small></main>
@endsection
