<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminWorkerFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliation_labels_and_combined_filters_match_worker_data(): void
    {
        $this->seed();
        $this->actingAs(User::where('role', 'admin')->first());
        $this->get('/admin?section=workers')->assertOk()
            ->assertSee('class="asista-pagination"', false)
            ->assertSee('aria-current="page"', false)
            ->assertDontSee("@include('admin.pagination'", false);
        $this->get('/admin?section=workers')->assertOk()->assertSee('Melalui agency')->assertSee('Tidak melalui agency')->assertSee('Mitra Keluarga');
        $this->get('/admin?section=workers&affiliation=independent')->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 2 && $rows->every(fn ($row) => $row->agency_id === null));
        $agency = DB::table('agencies')->value('id');
        $this->get('/admin?section=workers&affiliation=agency&agency_id='.$agency.'&q=Siti&category=babysitter&verification=verified&available=1')
            ->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 1 && $rows->first()->name === 'Siti Aminah');
        DB::table('workers')->where('name', 'Rina Wulandari')->update(['available' => false]);
        $this->get('/admin?section=workers&available=0')->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 1 && $rows->first()->name === 'Rina Wulandari');
        $this->get('/admin?section=workers&affiliation=independent&agency_id='.$agency)->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 0);
        $this->getJson('/admin?section=workers&affiliation=invalid')->assertUnprocessable();
        $this->get('/admin?'.http_build_query(['section' => 'workers', 'rate_units' => ['hourly', 'daily'], 'arrangements' => ['live_out'], 'sort' => 'name']))
            ->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 3 && $rows->first()->name === 'Dewi Lestari');
        $this->get('/admin?section=workers&city=Depok&rate_units[]=monthly&arrangements[]=live_in')
            ->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 1 && $rows->first()->name === 'Nur Aisyah');
        $this->get('/admin?section=workers&q=MPASI')->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 1 && $rows->first()->name === 'Siti Aminah');
        $this->getJson('/admin?section=workers&sort=invalid')->assertUnprocessable();
        $this->getJson('/admin?section=workers&rate_units[]=invalid')->assertUnprocessable();
        $this->get('/admin?section=workers&q=Siti')->assertOk()->assertViewHas('rows', fn ($rows) => str_contains($rows->url(2), 'q=Siti') && str_contains($rows->url(2), 'section=workers'));
    }
}
