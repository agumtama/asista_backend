<style>
.worker-filters{padding:0 0 12px;margin-bottom:8px;border-bottom:1px solid var(--line)}
.worker-filter-bar{display:flex;flex-wrap:wrap;align-items:center;gap:8px}
.worker-filter-bar input{flex:1 1 220px;width:auto;min-width:0}
.worker-filter-bar select{flex:0 1 190px;width:auto}
.worker-filters input,.worker-filters select{padding:8px 10px;font-size:13px;height:36px}
.worker-filters button{padding:8px 14px;height:36px;font-size:13px}
.worker-filters a{font-size:12px;padding:8px}
.worker-filters details{margin-top:8px}
.worker-filters summary{cursor:pointer;font-size:12px;color:var(--muted);width:fit-content}
.worker-filter-extra{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:10px}
.worker-filter-extra label{margin:0;font-size:12px;color:var(--muted)}
.worker-filter-extra select{display:block;margin-top:4px}
@media(max-width:650px){.worker-filter-extra{grid-template-columns:repeat(2,minmax(0,1fr))}.worker-filter-bar input{flex-basis:100%}.worker-filter-bar select{flex:1}}
</style>
<form method="get" action="/admin" aria-label="Filter pekerja" class="worker-filters">
<input type="hidden" name="section" value="workers">
<div class="worker-filter-bar">
<input aria-label="Nama atau kota" name="q" maxlength="100" value="{{ $workerFilters['q'] ?? '' }}" placeholder="Cari nama / kota…">
<select name="affiliation" aria-label="Hubungan agency">
<option value="">Semua pekerja</option>
<option value="independent" @selected(($workerFilters['affiliation'] ?? '') === 'independent')>Mandiri</option>
<option value="agency" @selected(($workerFilters['affiliation'] ?? '') === 'agency')>Melalui agency</option>
</select>
<button>Terapkan</button><a href="/admin?section=workers">Reset</a>
</div>
@php($extraCount = collect($workerFilters)->only(['agency_id','category','verification','available'])->filter(fn($value) => $value !== null && $value !== '')->count())
<details><summary>Filter lainnya @if($extraCount) · {{ $extraCount }} aktif @endif</summary>
<div class="worker-filter-extra">
@foreach([
 'agency_id'=>['Agency',$filterAgencies->pluck('name','id')->all()],
 'category'=>['Layanan',['art'=>'ART','babysitter'=>'Babysitter']],
 'verification'=>['Verifikasi',['pending'=>'Menunggu verifikasi','verified'=>'Terverifikasi','rejected'=>'Ditolak']],
 'available'=>['Ketersediaan',['1'=>'Tersedia','0'=>'Tidak tersedia']]
] as $field=>[$label,$options])
<label>{{ $label }}<select name="{{ $field }}"><option value="">Semua</option>@foreach($options as $value=>$text)<option value="{{ $value }}" @selected((string)($workerFilters[$field] ?? '') === (string)$value)>{{ $text }}</option>@endforeach</select></label>
@endforeach
</div></details>
</form>