<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email|max:150']);
        ResetPassword::createUrlUsing(fn (User $user, string $token) => rtrim(config('app.url'), '/').'/reset-password/'.$token.'?'.http_build_query(['email' => $user->email]));
        Password::sendResetLink($data);

        return response()->json(['message' => 'Jika email terdaftar, tautan reset kata sandi akan dikirim. Periksa inbox dan folder spam.']);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email', 'token' => 'required|string', 'password' => 'required|string|min:10|max:128|confirmed']);
        $status = Password::reset($data, function (User $user, string $password): void {
            DB::transaction(function () use ($user, $password): void {
                $user->password = $password;
                $user->setRememberToken(Str::random(60));
                $user->save();
                DB::table('api_tokens')->where('user_id', $user->id)->delete();
                DB::table('sessions')->where('user_id', $user->id)->delete();
            });
        });
        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'Tautan reset tidak valid atau sudah kedaluwarsa. Minta tautan baru dari aplikasi.'])->withInput($request->only('email'));
        }

        return redirect()->route('password.reset.success');
    }
}
