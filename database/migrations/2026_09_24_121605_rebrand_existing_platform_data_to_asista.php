<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('asista', function (Blueprint $table) {
            DB::transaction(function (): void {
                foreach (Schema::getTables() as $table) {
                    $name = $table['name'];
                    if (in_array($name, ['migrations', 'cache', 'cache_locks', 'sessions', 'api_tokens', 'password_reset_tokens', 'jobs', 'job_batches', 'failed_jobs'])) {
                        continue;
                    }
                    foreach (Schema::getColumns($name) as $column) {
                        if (! in_array($column['type_name'], ['varchar', 'char', 'text', 'longtext', 'mediumtext', 'tinytext', 'json'])) {
                            continue;
                        }
                        $field = $column['name'];
                        foreach (DB::table($name)->where($field, 'like', '%rumah%')->pluck($field)->unique() as $value) {
                            $updated = preg_replace('/rumah[ _.-]?percaya/i', $field === 'name' ? 'ASISTA' : 'asista', $value);
                            if ($updated !== $value) {
                                DB::table($name)->where($field, $value)->update([$field => $updated]);
                            }
                        }
                    }
                }
                foreach (['admin', 'family', 'agency', 'worker1', 'worker2', 'worker3', 'worker4'] as $account) {
                    $user = DB::table('users')->where('email', $account.'@asista.test')->first();
                    if ($user && Hash::check('DemoRumah123!', $user->password)) {
                        DB::table('users')->where('id', $user->id)->update(['password' => Hash::make('DemoAsista123!'), 'updated_at' => now()]);
                    }
                }
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asista', function (Blueprint $table) {
            // Branding data is intentionally preserved on rollback to avoid reverting user edits.
        });
    }
};
