@extends('admin.layout')
@section('content')
<aside><x-asista-logo /><p class="eyebrow">PLATFORM MANAGEMENT</p><nav>@foreach(['overview'=>'Ringkasan','workers'=>'Pekerja & portfolio','agencies'=>'Agency','users'=>'Pengguna','bookings'=>'Booking & pembayaran','verification_requests'=>'Verifikasi identitas','safety_reports'=>'Trust & safety','audit_logs'=>'Audit trail'] as $key=>$label)<a class="{{ $section === $key ? 'active' : '' }}" href="/admin?section={{ $key }}">@include('admin.nav-icon',['name'=>$key]){{ $label }}</a>@endforeach</nav><form method="post" action="/logout">@csrf<button class="secondary">Keluar</button></form></aside>
@include('admin.verification-content')
@endsection
