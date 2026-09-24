@extends('admin.layout')
@section('content')
<aside><x-asista-logo/><p class="eyebrow">PLATFORM MANAGEMENT</p><nav>@foreach(['overview'=>'Ringkasan','workers'=>'Pekerja & portfolio','agencies'=>'Agency','users'=>'Pengguna','bookings'=>'Booking & pembayaran','verification_requests'=>'Verifikasi identitas','safety_reports'=>'Trust & safety','audit_logs'=>'Audit trail'] as $key=>$label)<a class="{{ $key === 'agencies' ? 'active' : '' }}" href="/admin?section={{ $key }}">@include('admin.nav-icon',['name'=>$key]){{ $label }}</a>@endforeach</nav><form method="post" action="/logout">@csrf<button class="secondary">Keluar</button></form></aside>
<main class="workspace agency-workspace">
<header><div><p class="eyebrow">ASISTA / CMS / Agency</p><h1>Agency Management</h1><p>Kelola partner agency, verifikasi legalitas, subscription, pekerja, dan aktivitas booking.</p></div><div class="agency-admin"><span class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span><div><strong>{{ auth()->user()->name }}</strong><br><small>Administrator</small></div></div></header>
@if(session('success'))<div class="notice">{{ session('success') }}</div>@endif
@foreach($errors->all() as $error)<p class="error">{{ $error }}</p>@endforeach
<div class="agency-stats">
@foreach(['total'=>['Total Agency','agencies'],'verified'=>['Agency Terverifikasi','verification_requests'],'pending'=>['Menunggu Verifikasi','bookings'],'rejected'=>['Ditolak','safety_reports'],'active'=>['Subscription Aktif','audit_logs']] as $key=>[$label,$icon])
@php($percent = $agencySummary['total'] ? round($agencySummary[$key] / $agencySummary['total'] * 100) : 0)
<section class="panel agency-stat"><span class="agency-stat-icon tone-{{ $key }}">@include('admin.nav-icon',['name'=>$icon])</span><div><span>{{ $label }}</span><strong>{{ $agencySummary[$key] }}</strong>
@if($key === 'total')<small class="agency-growth">+{{ $agencySummary['new'] }} terdaftar bulan ini</small>@else<small>{{ $percent }}% dari total</small><progress class="tone-{{ $key }}" value="{{ $percent }}" max="100" aria-label="Persentase {{ $label }}"></progress>@endif
</div></section>@endforeach</div>
<div class="agency-tabs" role="navigation" aria-label="Kategori agency">@foreach(['list'=>'Daftar Agency','pending'=>'Permohonan Baru','subscription'=>'Subscription Aktif'] as $tab=>$label)<a class="{{ ($agencyFilters['tab'] ?? 'list') === $tab ? 'selected' : '' }}" href="{{ url('/admin').'?'.http_build_query(['section'=>'agencies','tab'=>$tab]) }}">{{ $label }}</a>@endforeach<a href="/admin?section=workers&affiliation=agency">Pekerja Agency</a></div>
<section class="panel agency-records">
<form method="get" action="/admin" class="agency-filter" aria-label="Filter agency"><input type="hidden" name="section" value="agencies"><input type="hidden" name="tab" value="{{ $agencyFilters['tab'] ?? 'list' }}">
<div class="agency-search"><input name="q" aria-label="Cari agency" maxlength="100" value="{{ $agencyFilters['q'] ?? '' }}" placeholder="Cari nama agency, kota, atau nomor legalitas…"><button name="export" value="csv" class="secondary">↓ Export CSV</button></div>
<div class="agency-filter-grid">
@foreach(['verification'=>['Status',['verified'=>'Terverifikasi','pending'=>'Menunggu verifikasi','rejected'=>'Ditolak']], 'subscription'=>['Subscription',$agencySubscriptions->mapWithKeys(fn($s)=>[$s=>match($s){'active'=>'Aktif','inactive'=>'Belum aktif','demo'=>'Demo',default=>$s}])->all()], 'city'=>['Kota',$agencyCities->mapWithKeys(fn($city)=>[$city=>$city])->all()]] as $field=>[$label,$options])
<label>{{ $label }}<select name="{{ $field }}"><option value="">Semua</option>@foreach($options as $value=>$text)<option value="{{ $value }}" @selected((string)($agencyFilters[$field] ?? '') === (string)$value)>{{ $text }}</option>@endforeach</select></label>@endforeach
<fieldset><legend>Tanggal terdaftar</legend><div><input type="date" name="registered_from" aria-label="Terdaftar mulai" value="{{ $agencyFilters['registered_from'] ?? '' }}"><input type="date" name="registered_to" aria-label="Terdaftar sampai" value="{{ $agencyFilters['registered_to'] ?? '' }}"></div></fieldset>
<div class="agency-filter-actions"><button>Terapkan</button><a class="secondary button" href="/admin?section=agencies">Reset</a></div></div></form>
<div class="table-wrap"><table class="agency-table"><thead><tr><th>ID</th><th>Nama Agency</th><th>Kota</th><th>Worker</th><th>Booking</th><th>Subscription</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
@forelse($rows as $agency)<tr>
<td>#A{{ str_pad($agency->id,3,'0',STR_PAD_LEFT) }}</td><td><strong>{{ $agency->name }}</strong><br><small>{{ $agency->legal_number }}</small></td><td>{{ $agency->city }}</td>
<td><strong class="agency-count">{{ $agency->worker_count }}</strong><a class="agency-count-link" href="{{ url('/admin').'?'.http_build_query(['section'=>'workers','agency_id'=>$agency->id]) }}">Lihat detail</a></td>
<td><strong class="agency-count">{{ $agency->booking_count }}</strong><a class="agency-count-link" href="{{ url('/admin').'?'.http_build_query(['section'=>'bookings','agency_id'=>$agency->id]) }}">Lihat detail</a></td>
<td><strong>{{ ['active'=>'Aktif','inactive'=>'Belum berlangganan','demo'=>'Demo'][$agency->subscription] ?? $agency->subscription }}</strong><br><small>Terdaftar {{ \Carbon\Carbon::parse($agency->created_at)->format('d M Y') }}</small></td>
<td><span class="agency-status status-{{ $agency->verification }}">{{ ['verified'=>'✓ Terverifikasi','pending'=>'◷ Menunggu verifikasi','rejected'=>'× Ditolak'][$agency->verification] ?? $agency->verification }}</span></td>
<td><a class="agency-view" href="{{ route('admin.registrant',$agency->user_id) }}">Lihat</a><details class="agency-actions"><summary aria-label="Tindakan {{ $agency->name }}">•••</summary><div>
<a href="{{ route('admin.registrant',$agency->user_id) }}">Lihat data & dokumen</a>
<form class="inline-form" method="post" action="/admin/verify/agencies/{{ $agency->id }}">@csrf<select name="status" aria-label="Status {{ $agency->name }}">@foreach(['verified'=>'Terverifikasi','pending'=>'Menunggu verifikasi','rejected'=>'Ditolak'] as $value=>$label)<option value="{{ $value }}" @selected($agency->verification === $value)>{{ $label }}</option>@endforeach</select><input name="note" placeholder="Alasan keputusan" required minlength="5" maxlength="1000" aria-label="Alasan keputusan {{ $agency->name }}"><button>Simpan keputusan</button></form>
<details><summary>Tarif & fee pekerja</summary>@forelse($agencyWorkers->get($agency->id,collect()) as $worker)<p>{{ $worker->name }} — fee Rp {{ number_format($worker->agency_fee,0,',','.') }} / booking</p>@include('admin.worker-pricing',['worker'=>$worker])@empty<p>Belum ada pekerja.</p>@endforelse</details>
</div></details></td></tr>@empty<tr><td colspan="8"><div class="empty">Tidak ada agency yang sesuai filter.</div></td></tr>@endforelse
</tbody></table></div>
@include('admin.pagination',['page'=>$rows,'anchor'=>''])
</section>
@if($registrations->count())<details class="panel agency-registrations"><summary>Pendaftar belum melengkapi profil ({{ $registrations->total() }})</summary>@foreach($registrations as $registrant)<p><a href="{{ route('admin.registrant',$registrant->id) }}">{{ $registrant->name }} · {{ $registrant->email }}</a></p>@endforeach@include('admin.pagination',['page'=>$registrations,'anchor'=>''])</details>@endif
<footer>Jumlah booking menunjukkan transaksi aktual, tidak termasuk demo. Subscription mengikuti status tersimpan; paket dan masa aktif berlangganan belum dikelola di aplikasi.</footer>
</main>
@endsection
