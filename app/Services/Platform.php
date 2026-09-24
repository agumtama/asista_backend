<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class Platform
{
    public static function audit(int $user, string $action, string $subject, array $metadata = []): void
    {
        DB::table('audit_logs')->insert(['user_id' => $user, 'action' => $action, 'subject' => $subject, 'metadata' => json_encode($metadata), 'created_at' => now(), 'updated_at' => now()]);
    }

    public static function participant($booking, $user): bool
    {
        $worker = DB::table('workers')->find($booking->worker_id);
        $agency = $booking->agency_id ? DB::table('agencies')->find($booking->agency_id) : null;

        return in_array($user->id, array_filter([$booking->family_id, $worker->user_id, $agency?->user_id]), true);
    }

    public static function worker($worker): array
    {
        $data = (array) $worker;
        unset($data['user_id']);
        $data['skills'] = json_decode($worker->skills, true);
        $data['certifications'] = json_decode($worker->certifications ?? '[]', true);
        $data['agency_name'] = $worker->agency_id ? DB::table('agencies')->where('id', $worker->agency_id)->value('name') : null;
        $data['rating'] = round((float) DB::table('reviews')->where('target_id', $worker->user_id)->avg('rating'), 1);
        $data['completed_jobs'] = DB::table('bookings')->where('worker_id', $worker->id)->where('is_demo', false)->where('status', 'completed')->count();

        return $data;
    }
}
