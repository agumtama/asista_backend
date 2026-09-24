<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        abort_unless($token, 401, 'Silakan masuk.');
        $record = DB::table('api_tokens')->where('hash', hash('sha256', $token))->where('expires_at', '>', now())->first();
        abort_unless($record, 401, 'Sesi berakhir. Silakan masuk kembali.');
        $user = User::findOrFail($record->user_id);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
