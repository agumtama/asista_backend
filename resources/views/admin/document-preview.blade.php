@if(in_array($document->mime_type, ['image/jpeg', 'image/png', 'application/pdf']))
<button type="button" class="document-preview" data-preview="{{ route('admin.document', $document->id) }}" data-kind="{{ $document->mime_type === 'application/pdf' ? 'pdf' : 'image' }}" data-title="{{ $document->original_name ?: 'Dokumen verifikasi' }}" aria-label="Preview {{ $document->original_name ?: 'dokumen verifikasi' }}">
    @if($document->mime_type === 'application/pdf')
        <strong>PDF · Lihat dokumen</strong>
    @else
        <img src="{{ route('admin.document', $document->id) }}" loading="lazy" alt="{{ $document->original_name ?: 'Dokumen verifikasi' }}">
    @endif
    <span>Klik untuk memperbesar</span>
</button>
@endif
