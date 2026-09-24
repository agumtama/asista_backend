@php($paths = [
 'overview'=>'M3 10 12 3l9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z',
 'workers'=>'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M17 4a4 4 0 0 1 0 8M22 21v-2a4 4 0 0 0-3-3.87',
 'agencies'=>'M3 21V3h12v18M15 9h6v12M7 7h4M7 11h4M7 15h4M7 21v-3h4v3M18 13h1M18 17h1',
 'users'=>'M20 21v-2a7 7 0 0 0-14 0v2M13 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8',
 'bookings'=>'M4 5h16v16H4ZM8 3v4M16 3v4M4 11h16M8 15h3M8 18h6',
 'verification_requests'=>'M4 4h16v16H4ZM8 9h8M8 14l3 3 5-6',
 'safety_reports'=>'M12 3 3 7v5c0 5 9 9 9 9s9-4 9-9V7ZM8 12l3 3 5-6',
 'audit_logs'=>'M8 4H4v18h16V4h-4M8 2h8v5H8ZM8 12h8M8 17h8'
])
<svg aria-hidden="true" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:10px;flex-shrink:0"><path d="{{ $paths[$name] ?? $paths['overview'] }}"/></svg>
