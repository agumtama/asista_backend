<link rel="stylesheet" href="/admin-overview.css?v=1">
<main class="workspace owner-dashboard"><h1>Dashboard Admin Owner</h1><p class="overview-subtitle">Pantau aktivitas platform, verifikasi, transaksi, dan keamanan dalam satu tempat.</p>
<div class="overview-cards">@foreach($cards as $card)<a class="panel overview-card" href="/admin?section={{ $card['section'] }}"><span class="overview-icon icon-{{ $loop->index }}">@include('admin.nav-icon',['name'=>$card['section']])</span><div><small>{{ $card['label'] }}</small><strong>{{ number_format($card['total'],0,',','.') }}</strong><span>+{{ $card['new'] }} {{ $card['section'] === 'bookings' ? 'booking aktif dibuat bulan ini' : 'baru bulan ini' }}</span></div></a>@endforeach</div>
<div class="overview-grid"><section class="panel"><div class="overview-title"><h2>Tren Booking</h2><form method="get"><input type="hidden" name="section" value="overview"><select name="days" aria-label="Rentang tren booking" onchange="this.form.submit()">@foreach([7,30,90] as $period)<option value="{{ $period }}" @selected($days === $period)>{{ $period }} hari terakhir</option>@endforeach</select><noscript><button>Tampilkan</button></noscript></form></div><p class="chart-caption">Booking baru · {{ $trend->sum('total') }} transaksi aktual</p>
@php($maximum = max(1,$trend->max('total')))
@php($points = $trend->map(fn ($item,$index) => (45+$index*510/max(1,$trend->count()-1)).','. (190-$item['total']/$maximum*150))->implode(' '))
<svg class="booking-chart" viewBox="0 0 580 230" role="img" aria-label="Tren booking baru {{ $days }} hari terakhir, total {{ $trend->sum('total') }} booking">
@foreach([0,0.5,1] as $level)<line x1="45" x2="555" y1="{{ 190-$level*150 }}" y2="{{ 190-$level*150 }}" stroke="#e3ebe5"/><text x="5" y="{{ 194-$level*150 }}">{{ round($maximum*$level,1) }}</text>@endforeach
<polygon points="45,190 {{ $points }} 555,190" fill="#e7f2ea"/><polyline points="{{ $points }}" fill="none" stroke="#246c56" stroke-width="3"/>
@foreach($trend as $item)<circle cx="{{ 45+$loop->index*510/max(1,$trend->count()-1) }}" cy="{{ 190-$item['total']/$maximum*150 }}" r="2.5" fill="#246c56"><title>{{ $item['date'] }}: {{ $item['total'] }} booking</title></circle>@endforeach
<text x="45" y="218">{{ $trend->first()['date'] }}</text><text x="555" y="218" text-anchor="end">{{ $trend->last()['date'] }}</text></svg>
<details class="chart-data"><summary>Lihat data per hari</summary><div>@foreach($trend as $item)<p>{{ $item['date'] }} <strong>{{ $item['total'] }}</strong></p>@endforeach</div></details>
</section><section class="panel"><h2>Status Verifikasi</h2>
@php($total = $verification->sum())
@php($offset = 0)
<div class="verification-chart"><svg viewBox="0 0 120 120" aria-label="Status verifikasi pengguna" role="img"><circle cx="60" cy="60" r="46" fill="none" stroke="#edf0ec" stroke-width="13"/>
@foreach(['verified'=>'#37876c','pending'=>'#f0cd69','rejected'=>'#e78383'] as $state=>$color)
@php($percent = $total ? ($verification[$state] ?? 0)/$total*100 : 0)
<circle cx="60" cy="60" r="46" pathLength="100" fill="none" stroke="{{ $color }}" stroke-width="13" stroke-dasharray="{{ $percent }} {{ 100-$percent }}" stroke-dashoffset="{{ -$offset }}" transform="rotate(-90 60 60)"/>
@php($offset += $percent)
@endforeach
<text x="60" y="58" text-anchor="middle" class="donut-total">{{ number_format($total,0,',','.') }}</text><text x="60" y="73" text-anchor="middle">Pengguna</text></svg><ul>@foreach(['verified'=>'Terverifikasi','pending'=>'Menunggu','rejected'=>'Ditolak'] as $state=>$label)<li><span class="dot {{ $state }}"></span>{{ $label }}<strong>{{ $total ? round(($verification[$state] ?? 0)/$total*100,1) : 0 }}%</strong><small>{{ $verification[$state] ?? 0 }} pengguna</small></li>@endforeach</ul></div>
</section><section class="panel"><div class="overview-title"><h2>Aktivitas Terbaru</h2><a href="/admin?section=audit_logs">Lihat semua</a></div><div class="table-wrap"><table class="activity-table"><thead><tr><th>Waktu</th><th>Aktivitas</th><th>Pengguna</th></tr></thead><tbody>@forelse($activity as $event)<tr><td>{{ \Carbon\Carbon::parse($event->created_at)->format('d M H:i') }}</td><td>{{ $event->action }}<small>{{ $event->subject }}</small></td><td>{{ $event->actor ?? 'Sistem' }}</td></tr>@empty<tr><td colspan="3">Belum ada aktivitas.</td></tr>@endforelse</tbody></table></div></section>
<section class="panel"><h2>Notifikasi</h2><p class="chart-caption">Ringkasan data yang perlu ditindaklanjuti.</p>@foreach($notifications as $notification)<a class="overview-notification" href="/admin?section={{ $notification['section'] }}"><span class="overview-icon">@include('admin.nav-icon',['name'=>$notification['section']])</span><span>{{ $notification['label'] }}</span><strong>{{ $notification['count'] }}</strong></a>@endforeach</section></div>
<details class="overview-revenue"><summary>Rincian pendapatan platform</summary>@include('admin.revenue')</details>
</main>
