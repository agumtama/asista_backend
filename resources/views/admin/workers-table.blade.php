<div class="table-wrap"><table class="workers-table"><thead><tr><th>ID</th><th>Informasi</th><th>Jenis & layanan</th><th>Tipe layanan</th><th>Status</th><th>Tindakan / detail</th></tr></thead><tbody>
@forelse($rows as $row)
@php($skills = json_decode($row->skills, true) ?? [])
<tr><td>#{{ $row->id }}</td><td><div class="worker-identity">
@if($row->photo_id)<img class="worker-photo" src="{{ route('admin.document',$row->photo_id) }}" alt="Foto {{ $row->name }}" loading="lazy">
@else<div class="worker-photo worker-initial" aria-label="Foto belum tersedia">{{ mb_strtoupper(mb_substr($row->name,0,1)) }}</div>@endif
<div><a class="worker-name" href="{{ route('admin.registrant',$row->user_id) }}">{{ $row->name }}</a><div class="worker-meta">{{ $row->city }} · {{ $row->category === 'art' ? 'ART' : 'Babysitter' }}</div>
<span class="worker-tag {{ $row->agency_id ? 'tag-agency' : 'tag-independent' }}">{{ $row->agency_id ? 'Agency' : 'Mandiri' }}</span>
<div class="worker-meta">{{ $row->agency_id ? 'Melalui agency · '.$row->agency_name : 'Tidak melalui agency' }}</div>
<div class="worker-meta">{{ $row->available ? 'Tersedia' : 'Tidak tersedia' }}</div></div></div></td>
<td><span class="worker-tag tag-service">{{ $row->category === 'art' ? 'ART' : 'Babysitter' }}</span>
@foreach(array_slice($skills,0,1) as $skill)<span class="worker-tag tag-service">{{ $skill }}</span>@endforeach
@if(count($skills)>1)<details class="worker-skills"><summary>+{{ count($skills)-1 }}</summary>@foreach(array_slice($skills,1) as $skill)<span class="worker-tag tag-service">{{ $skill }}</span>@endforeach</details>@endif</td>
<td><span class="worker-tag tag-duration">{{ ['hourly'=>'Per jam','daily'=>'Harian','monthly'=>'Bulanan'][$row->rate_unit] ?? $row->rate_unit }}</span><span class="worker-tag tag-duration">{{ $row->arrangement === 'live_in' ? 'Live-in' : 'PP / Live-out' }}</span>
<div class="worker-rate">Tarif pekerja: Rp {{ number_format($row->rate,0,',','.') }} / {{ ['hourly'=>'jam','daily'=>'hari','monthly'=>'bulan'][$row->rate_unit] ?? $row->rate_unit }}</div><div class="worker-meta">Fee agency: Rp {{ number_format($row->agency_fee,0,',','.') }} / booking</div></td>
<td><span class="worker-tag status-{{ $row->verification }}">{{ ['verified'=>'Terverifikasi','pending'=>'Menunggu','rejected'=>'Ditolak'][$row->verification] ?? $row->verification }}</span></td>
<td><a class="worker-detail" href="{{ route('admin.registrant',$row->user_id) }}"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg>Lihat data & dokumen <span aria-hidden="true">&rarr;</span></a>
<form class="worker-decision" method="post" action="/admin/verify/workers/{{ $row->id }}">@csrf
<select name="status" aria-label="Status verifikasi {{ $row->name }}">@foreach(['verified'=>'Terverifikasi','pending'=>'Menunggu verifikasi','rejected'=>'Ditolak'] as $value=>$label)<option value="{{ $value }}" @selected($row->verification === $value)>{{ $label }}</option>@endforeach</select>
<div><input name="note" aria-label="Alasan keputusan {{ $row->name }}" placeholder="Alasan keputusan" required minlength="5" maxlength="1000"><button>Simpan keputusan</button></div></form></td></tr>
@empty<tr><td colspan="6"><div class="empty">Tidak ada pekerja yang sesuai filter. <a href="/admin?section=workers">Reset filter</a></div></td></tr>@endforelse
</tbody></table></div>
