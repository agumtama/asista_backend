@extends('admin.layout')
@section('content')
<main class="login"><x-asista-logo /><h1>Kata sandi diperbarui</h1><p>Kembali ke aplikasi ASISTA dan masuk menggunakan kata sandi baru. Jika memakai Ingat saya, perbarui kata sandi yang tersimpan saat login berikutnya.</p><a href="{{ route('login') }}">Login CMS</a></main>
@endsection
