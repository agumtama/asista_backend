<link rel="stylesheet" href="/agency-documents.css?v=1">
<section class="panel agency-legal"><div class="legal-heading"><div><h2>Dokumen & Legalitas</h2><p>Unggah dan kelola dokumen untuk verifikasi agency. Semua akses dicatat dalam audit trail.</p></div><div class="legal-status"><small>Status Verifikasi</small><strong>{{ $agency->verification === 'verified' ? 'Terverifikasi' : 'Belum Terverifikasi' }}</strong><a href="{{ route('agency.portal',['tab'=>'documents','upload'=>'nib']) }}">Upload Dokumen</a></div></div>
@if(request('upload') || $errors->any())
<form class="agency-upload" method="post" enctype="multipart/form-data" action="{{ route('agency.documents.upload') }}">@csrf<label>Jenis dokumen<select name="document_type">@foreach(\App\Http\Controllers\AgencyRegistrationController::DOCUMENTS as $key=>$label)<option value="{{ $key }}" @selected(old('document_type',request('upload')) === $key)>{{ $label }}</option>@endforeach</select></label><label>PDF / JPG / PNG, maks. 5 MB<input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required></label><button>Simpan dokumen</button><a href="{{ route('agency.portal',['tab'=>'documents']) }}">Batal</a></form>
@endif
<div class="legal-grid">
@foreach(\App\Http\Controllers\AgencyRegistrationController::DOCUMENTS as $type=>$label)
@php($document = $documents->firstWhere('document_type',$type))
<article class="legal-card"><div class="legal-card-title"><span class="legal-icon" aria-hidden="true">▤</span><h3>{{ $label }}</h3><span class="legal-badge {{ $document?->status === 'verified' ? 'verified' : '' }}">{{ $document ? ['verified'=>'Terverifikasi','pending'=>'Pending','rejected'=>'Ditolak'][$document->status] : 'Belum diunggah' }}</span></div>
<div class="legal-card-body">
@if($document)
@if(in_array($document->mime_type,['image/jpeg','image/png']))
<button type="button" class="legal-thumbnail" data-preview="{{ route('agency.document',$document->id) }}" data-title="{{ $label }}" aria-label="Perbesar {{ $label }}"><img src="{{ route('agency.document',$document->id) }}" alt="{{ $label }}" loading="lazy"></button>
@else<a class="legal-thumbnail legal-pdf" target="_blank" rel="noopener" href="{{ route('agency.document',$document->id) }}">PDF ↗</a>@endif
<div><small>Diunggah {{ \Carbon\Carbon::parse($document->created_at)->format('d M Y, H:i') }}</small><div class="legal-actions">
@if(in_array($document->mime_type,['image/jpeg','image/png']))<button type="button" data-preview="{{ route('agency.document',$document->id) }}" data-title="{{ $label }}">Lihat</button>@else<a target="_blank" rel="noopener" href="{{ route('agency.document',$document->id) }}">Lihat ↗</a>@endif
<a href="{{ route('agency.portal',['tab'=>'documents','upload'=>$type]) }}">✎ Ganti</a></div></div>
@else<a class="legal-empty" href="{{ route('agency.portal',['tab'=>'documents','upload'=>$type]) }}">+ Unggah dokumen</a>@endif
</div>@if($document?->note)<p class="legal-note">{{ $document->note }}</p>@endif</article>
@endforeach</div><p class="legal-footnote">Mengganti dokumen membatalkan status verifikasi agency sampai semua dokumen terbaru disetujui. Versi lama tetap tersimpan untuk audit.</p></section>
