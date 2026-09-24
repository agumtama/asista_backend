<form method="get" action="/admin" aria-label="Filter pekerja" class="worker-filters">
<input type="hidden" name="section" value="workers">
<div class="worker-search">
<input aria-label="Cari pekerja" name="q" maxlength="100" value="{{ $workerFilters['q'] ?? '' }}" placeholder="Cari nama / kota / layanan / skill…">
<select name="affiliation" aria-label="Hubungan agency"><option value="">Semua pekerja</option><option value="independent" @selected(($workerFilters['affiliation'] ?? '') === 'independent')>Mandiri</option><option value="agency" @selected(($workerFilters['affiliation'] ?? '') === 'agency')>Melalui agency</option></select>
<button>Terapkan</button><a class="reset" href="/admin?section=workers">Reset</a>
</div>
<div class="worker-filter-middle">
<label>Agency<select name="agency_id"><option value="">Semua</option>@foreach($filterAgencies as $agency)<option value="{{ $agency->id }}" @selected((string)($workerFilters['agency_id'] ?? '') === (string)$agency->id)>{{ $agency->name }}</option>@endforeach</select></label>
<label>Jenis Pekerjaan<select name="category"><option value="">Semua</option>@foreach(['art'=>'ART','babysitter'=>'Babysitter'] as $value=>$label)<option value="{{ $value }}" @selected(($workerFilters['category'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
<fieldset class="worker-service-options"><legend>Tipe Layanan / Durasi Kerja</legend><div>
@foreach(['hourly'=>'Per Jam','daily'=>'Harian','monthly'=>'Bulanan'] as $value=>$label)<label><input type="checkbox" name="rate_units[]" value="{{ $value }}" @checked(in_array($value,$workerFilters['rate_units'] ?? []))>{{ $label }}</label>@endforeach
@foreach(['live_out'=>'PP / Live-out','live_in'=>'Live-in'] as $value=>$label)<label><input type="checkbox" name="arrangements[]" value="{{ $value }}" @checked(in_array($value,$workerFilters['arrangements'] ?? []))>{{ $label }}</label>@endforeach
<label class="unsupported" title="Booking task-based belum tersedia"><input type="checkbox" disabled>Task-based (belum tersedia)</label>
</div></fieldset>
</div>
<div class="worker-filter-bottom">
@foreach([
 'verification'=>['Verifikasi',['pending'=>'Menunggu verifikasi','verified'=>'Terverifikasi','rejected'=>'Ditolak']],
 'available'=>['Ketersediaan',['1'=>'Tersedia','0'=>'Tidak tersedia']],
 'city'=>['Kota',$filterCities->mapWithKeys(fn($city)=>[$city=>$city])->all()],
 'sort'=>['Urutkan',['newest'=>'Terbaru','oldest'=>'Terlama','name'=>'Nama A–Z']]
] as $field=>[$label,$options])
<label>{{ $label }}<select name="{{ $field }}">@if($field !== 'sort')<option value="">Semua</option>@endif @foreach($options as $value=>$text)<option value="{{ $value }}" @selected((string)($workerFilters[$field] ?? ($field === 'sort' ? 'newest' : '')) === (string)$value)>{{ $text }}</option>@endforeach</select></label>
@endforeach
</div></form>
