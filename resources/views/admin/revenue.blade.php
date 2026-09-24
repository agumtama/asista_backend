@foreach($revenue as $summary)
<section class="panel" style="margin-bottom:20px"><h2>{{ $summary['label'] }}</h2><p>{{ $summary['count'] }} booking berstatus dibayar · seluruh periode</p>
<div class="stats">
@foreach(['total'=>'Total pembayaran pelanggan','worker'=>'Alokasi pekerja','agency'=>'Alokasi agency','platform'=>'Pendapatan kotor ASISTA'] as $key=>$label)
<div><span>{{ $label }}</span><strong style="display:block;font-size:24px">Rp {{ number_format($summary[$key],0,',','.') }}</strong></div>
@endforeach
</div><p>Potensi fee ASISTA dari booking belum dibayar: <strong>Rp {{ number_format($summary['pending'],0,',','.') }}</strong></p>
<small>Pendapatan kotor = total pembayaran − alokasi pekerja − alokasi agency. Belum dikurangi biaya Midtrans, pajak, dan operasional; bukan laba bersih atau bukti pencairan dana. Booking batal, refund, dan sengketa tidak dimasukkan.</small></section>
@endforeach
