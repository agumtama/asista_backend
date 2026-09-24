@php($unit = ['hourly'=>'jam','daily'=>'hari','monthly'=>'bulan'][$worker->rate_unit] ?? $worker->rate_unit)
<p><strong>Tarif pekerja: Rp {{ number_format($worker->rate,0,',','.') }} / {{ $unit }}</strong><br>
Fee agency: Rp {{ number_format($worker->agency_fee,0,',','.') }} / booking<br>
<small>Tarif dasar diatur pekerja. Fee agency dikenakan sekali per booking, bukan dikalikan durasi.</small></p>
