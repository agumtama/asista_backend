<link rel="stylesheet" href="/workers.css?v=2">
<link rel="stylesheet" href="/verification.css?v=1">
<main class="workspace workers-workspace">
@if(session('success'))<div class="notice">{{ session('success') }}</div>@endif
@foreach($errors->all() as $error)<p class="error">{{ $error }}</p>@endforeach
<section class="panel records">
<div class="section-title"><h2>Verifikasi identitas & dokumen</h2><span class="badge">{{ $users->total() }} pengguna</span></div>
<form class="verification-search" method="get"><input type="hidden" name="section" value="verification_requests"><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama atau email pengguna..." aria-label="Cari pengguna"><button>Cari</button><a class="button secondary" href="/admin?section=verification_requests">Reset</a></form>
@php($labels = ['photo'=>'Foto diri','ktp'=>'KTP','deed'=>'Akta pendirian','nib'=>'NIB','npwp'=>'NPWP','business_license'=>'Legalitas badan usaha','supporting_document'=>'Dokumen pendukung','amendment'=>'Akta perubahan','domicile'=>'Bukti domisili usaha','bank_account'=>'Rekening perusahaan','manager_identity'=>'KTP / paspor pengelola'])
@php($statuses = ['pending'=>'Menunggu verifikasi','verified'=>'Terverifikasi','rejected'=>'Ditolak'])
@forelse($users as $user)
<section class="verification-user"><header class="verification-heading"><div><strong>{{ $user->name }}</strong><small>{{ $user->email }} · #{{ $user->id }}</small></div><span class="worker-tag tag-service">{{ ['worker'=>'Pekerja','agency'=>'Agency','family'=>'Keluarga'][$user->role] ?? $user->role }}</span><x-admin-document-button href="{{ route('admin.registrant',$user->id) }}" /></header>
<div class="verification-grid">@foreach($documents->get($user->id, collect()) as $document)
@php($title = $labels[$document->document_type] ?? 'Dokumen verifikasi')
<article class="verification-card">
@if(in_array($document->mime_type,['image/jpeg','image/png']))
<button type="button" class="document-preview" data-preview="{{ route('admin.document',$document->id) }}" data-title="{{ $title }} — {{ $user->name }}" aria-label="Perbesar {{ $title }} milik {{ $user->name }}"><img src="{{ route('admin.document',$document->id) }}" loading="lazy" alt="{{ $title }} milik {{ $user->name }}"><span>Klik untuk memperbesar ⤢</span></button>
@else<a class="document-file" target="_blank" rel="noopener" href="{{ route('admin.document',$document->id) }}"><strong>Dokumen</strong><span>Buka berkas &nearr;</span></a>@endif
<h3>{{ $title }}</h3><small class="document-filename">{{ $document->original_name ?? 'Dokumen unggahan' }}</small>
<p><span class="worker-tag status-{{ $document->status }}">{{ $statuses[$document->status] ?? $document->status }}</span></p>
<form class="worker-decision" action="/admin/verify/verification_requests/{{ $document->id }}" method="post">@csrf<select name="status" aria-label="Status {{ $title }} milik {{ $user->name }}">@foreach($statuses as $value=>$label)<option value="{{ $value }}" @selected($document->status === $value)>{{ $label }}</option>@endforeach</select><input name="note" aria-label="Alasan keputusan {{ $title }}" placeholder="Alasan keputusan" required minlength="5" maxlength="1000"><button>Simpan keputusan</button></form>
@if($document->note)<p class="document-note">{{ $document->note }}</p>@endif
<a class="document-download" href="{{ route('admin.document',['id'=>$document->id,'download'=>1]) }}">Unduh dokumen</a>
</article>@endforeach</div></section>
@empty<div class="empty">Tidak ada dokumen pengguna yang sesuai.</div>@endforelse
@include('admin.pagination',['page'=>$users,'anchor'=>''])
</section><footer>Dokumen bersifat privat. Akses berkas dan keputusan verifikasi dicatat dalam audit trail.</footer>
</main>
<dialog id="document-modal" aria-labelledby="document-modal-title"><div class="document-modal-header"><h2 id="document-modal-title">Preview dokumen</h2><button type="button" id="document-modal-close" aria-label="Tutup preview">Tutup ×</button></div><img id="document-modal-image" alt=""><a id="document-modal-original" target="_blank" rel="noopener">Buka ukuran asli &nearr;</a></dialog>
<script src="/verification.js?v=1" defer></script>
