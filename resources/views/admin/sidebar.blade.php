<aside><x-asista-logo /><p class="eyebrow">PLATFORM MANAGEMENT</p>
<nav aria-label="Main navigation">
@foreach(['overview'=>'Dashboard','workers'=>'Workers & Portfolio','agencies'=>'Agencies','users'=>'Users','bookings'=>'Bookings & Payments','verification_requests'=>'Identity Verification','safety_reports'=>'Trust & Safety','audit_logs'=>'Audit Trail'] as $key=>$label)
<a class="{{ ($section ?? request('section')) === $key ? 'active' : '' }}" href="/admin?section={{ $key }}">@include('admin.nav-icon',['name'=>$key])<span>{{ $label }}</span></a>
@endforeach
</nav><form method="post" action="/logout">@csrf<button class="secondary">Log Out</button></form></aside>
