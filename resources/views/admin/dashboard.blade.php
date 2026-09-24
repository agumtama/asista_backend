@extends('admin.layout')
@section('content')
<aside><x-asista-logo /><p class="eyebrow">PLATFORM MANAGEMENT</p><nav>@foreach(['overview'=>'Ringkasan','workers'=>'Pekerja & portfolio','agencies'=>'Agency','users'=>'Pengguna','bookings'=>'Booking & pembayaran','verification_requests'=>'Verifikasi identitas','safety_reports'=>'Trust & safety','audit_logs'=>'Audit trail'] as $key=>$label)<a class="{{ $section === $key ? 'active' : '' }}" href="/admin?section={{ $key }}">{{ $label }}</a>@endforeach</nav><form method="post" action="/logout">@csrf<button class="secondary">Keluar</button></form></aside>
<main class="workspace"><header><div><p class="eyebrow">ASISTA / CMS</p><h1>{{ $section === 'overview' ? 'Selamat datang, tim ASISTA.' : ucwords(str_replace('_',' ',$section)) }}</h1><p>Kepercayaan dibangun dari proses yang bisa ditelusuri.</p></div><div class="avatar">{{ substr(auth()->user()->name,0,1) }}</div></header>
@if(session('success'))<div class="notice">{{ session('success') }}</div>@endif
@foreach($errors->all() as $error)<p class="error">{{ $error }}</p>@endforeach
@include('admin.revenue')
<div class="stats">@foreach(['workers'=>'Profil pekerja','agencies'=>'Partner agency','bookings'=>'Total booking','safety_reports'=>'Laporan privat'] as $key=>$label)<div class="panel"><span>{{ $label }}</span><strong>{{ $stats[$key] }}</strong><small>Data tersimpan di platform</small></div>@endforeach</div>
@if($section === 'overview')<div class="banner"><div><p class="eyebrow">AMAN · SELEKTIF · INTEGRITAS · SETARA · TRANSPARAN · ANDAL</p><h2>Rasa tenang dimulai<br>dari proses yang baik.</h2><p>Tinjau identitas dan kelola hubungan kerja dengan adil.</p><a class="button" href="/admin?section=verification_requests">Tinjau verifikasi →</a></div><div class="seal">✓<small>TRUSTED<br>ECOSYSTEM</small></div></div>@endif
@if($registrations->count())<section class="panel"><h2>Pendaftar belum melengkapi profil</h2>@foreach($registrations as $registrant)<p><strong>{{ $registrant->name }}</strong> · {{ $registrant->email }} · <a href="{{ route('admin.registrant', $registrant->id) }}">Lihat data & dokumen</a></p>@endforeach<div class="pagination">@if($registrations->previousPageUrl())<a href="{{ $registrations->previousPageUrl() }}">Sebelumnya</a>@endif @if($registrations->nextPageUrl())<a href="{{ $registrations->nextPageUrl() }}">Berikutnya</a>@endif</div></section>@endif
<section class="panel records"><div class="section-title"><h2>{{ $section === 'overview' ? 'Booking terbaru' : ($section === 'workers' ? 'Daftar data Pekerja' : 'Daftar data') }}</h2><span class="badge">{{ $rows->total() }} data</span></div>
@if($section === 'workers')@include('admin.worker-filters')@endif
<div class="table-wrap"><table><thead><tr><th>ID</th><th>Informasi</th><th>Status</th><th>Tindakan / detail</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td>#{{ $row->id }}</td><td>
@if(in_array($section,['workers','agencies','users']))<strong>{{ $row->name }}</strong><br><small>{{ $row->city ?? $row->email }} {{ isset($row->category) ? ' · '.$row->category : '' }}</small>
@if($section === 'workers')
<p>@if($row->agency_id)<span class="badge" style="background:#dcece5;color:#154d3c">Melalui agency</span><br><strong>{{ $row->agency_name }}</strong>
@else<span class="badge" style="background:#fff1ce;color:#70500b">Mandiri</span><br><small>Tidak melalui agency</small>@endif</p>
<small>{{ $row->available ? 'Tersedia' : 'Tidak tersedia' }}</small>
@include('admin.worker-pricing',['worker'=>$row])@endif
@if($section === 'agencies')@forelse($agencyWorkers->get($row->id, collect()) as $worker)<details><summary>{{ $worker->name }} — fee Rp {{ number_format($worker->agency_fee,0,',','.') }} / booking</summary>@include('admin.worker-pricing',['worker'=>$worker])</details>@empty<p>Belum ada pekerja naungan.</p>@endforelse @endif
@elseif($section === 'verification_requests')<strong>Pengguna #{{ $row->user_id }}</strong><br><a href="/admin/document/{{ $row->id }}">Unduh dokumen privat</a>
@elseif($section === 'safety_reports')<strong>{{ $row->category }} · Booking #{{ $row->booking_id }}</strong><p>{{ $row->description }}</p><small>Bukti: {{ $row->evidence ?: 'Belum ada' }}</small>@if($row->appeal)<p>Banding: {{ $row->appeal }}</p>@endif
@elseif($section === 'audit_logs')<strong>{{ $row->action }}</strong><br><small>{{ $row->subject }} · Aktor #{{ $row->user_id }}</small>
@else<strong>Booking #{{ $row->id }} · {{ $row->worker_name }}</strong>
@if($row->is_demo)<span class="badge">DEMO — bukan transaksi nyata</span>@endif
<p>Keluarga: {{ $row->family_name }}<br>Agency: {{ $row->agency_name ?? 'Pekerja mandiri' }}</p>
<small>{{ $row->starts_at }} · {{ $row->units }} {{ ['hourly'=>'jam','daily'=>'hari','monthly'=>'bulan'][$row->rate_unit] ?? $row->rate_unit }}</small><p>{{ $row->scope }}</p>
<details open><summary>Perhitungan booking</summary>
<p>Tarif saat booking Rp {{ number_format($row->worker_pay / max(1,$row->units),0,',','.') }} × {{ $row->units }} = <strong>Rp {{ number_format($row->worker_pay,0,',','.') }}</strong><br>
Fee agency / booking: Rp {{ number_format($row->agency_fee,0,',','.') }}<br>
Fee ASISTA / booking: Rp {{ number_format($row->platform_fee,0,',','.') }}<br>
<strong>Total pelanggan: Rp {{ number_format($row->total,0,',','.') }}</strong></p>
<p>{{ $row->payment_status === 'paid' && $row->status !== 'cancelled' ? 'Pendapatan kotor ASISTA' : 'Fee ASISTA (belum diakui dalam ringkasan pendapatan)' }}: <strong>Rp {{ number_format($row->platform_fee,0,',','.') }}</strong></p>
<small>Nominal mengikuti kesepakatan saat booking; perubahan tarif profil tidak mengubah booking ini.</small></details>@endif
</td><td><span class="badge">{{ $row->verification ?? $row->status ?? 'Tercatat' }}</span>@if(isset($row->payment_status))<p class="badge">{{ $row->payment_status }}</p>@endif</td><td>
@if(in_array($section,['overview','bookings']))@foreach($payments->get($row->id, collect()) as $payment)<p><strong>Midtrans: {{ $payment->status }}</strong><br><small>{{ $payment->order_id }}<br>{{ $payment->payment_type ?? 'Metode belum dipilih' }} · Rp {{ number_format($payment->amount,0,',','.') }}</small></p>@endforeach @endif
@if(in_array($section,['workers','agencies','users']))<p><a href="{{ route('admin.registrant', $section === 'users' ? $row->id : $row->user_id) }}">Lihat data & dokumen</a></p>@endif
@if(in_array($section,['workers','agencies','users','verification_requests']))<form class="inline-form" method="post" action="/admin/verify/{{ $section }}/{{ $row->id }}">@csrf<select name="status"><option value="verified">Terverifikasi</option><option value="rejected">Ditolak</option><option value="pending">Tinjau ulang</option></select><input name="note" placeholder="Alasan keputusan" required minlength="5" maxlength="1000"><button>Simpan keputusan</button></form>
@elseif($section === 'safety_reports')<form class="inline-form" method="post" action="/admin/reports/{{ $row->id }}">@csrf<select name="status"><option value="investigating">Investigasi</option><option value="decided">Keputusan</option></select><textarea name="decision" required minlength="20" placeholder="Catatan investigasi / keputusan">{{ $row->decision }}</textarea><button>Simpan penanganan</button></form>
@elseif($section === 'audit_logs')<small>{{ $row->created_at }}</small><p class="metadata">{{ $row->metadata }}</p>
@elseif($row->status === 'accepted' && $row->payment_status === 'unpaid' && !config('services.midtrans.enabled'))<form class="inline-form" method="post" action="/admin/bookings/{{ $row->id }}/payment">@csrf<input name="reference" required minlength="5" placeholder="Referensi transfer terverifikasi"><button>Konfirmasi pembayaran manual</button></form>
@else<small>{{ $row->created_at }}</small>@endif
</td></tr>@empty<tr><td colspan="4"><div class="empty">Belum ada data. Aktivitas platform akan tampil di sini.</div></td></tr>@endforelse
</tbody></table></div><div class="pagination">@if($rows->previousPageUrl())<a href="{{ $rows->previousPageUrl() }}">← Sebelumnya</a>@endif<span>Halaman {{ $rows->currentPage() }} / {{ $rows->lastPage() }}</span>@if($rows->nextPageUrl())<a href="{{ $rows->nextPageUrl() }}">Berikutnya →</a>@endif</div></section><footer>Laporan keselamatan bersifat privat. Keputusan verifikasi dan pembayaran dicatat dalam audit trail.</footer></main>
@endsection
