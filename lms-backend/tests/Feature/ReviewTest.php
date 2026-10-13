<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Module;
use App\Models\Package;
use App\Models\Review;
use App\Models\User;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    private function makePackage(): Package
    {
        $module = Module::create(['title' => 'Modul', 'is_active' => true]);
        $package = Package::create([
            'title' => 'Paket Belajar',
            'price' => 100000,
            'is_active' => true,
        ]);
        $package->modules()->attach($module->id, ['sort_order' => 1]);

        return $package;
    }

    private function makeSiswa(): User
    {
        $user = User::factory()->create();
        $user->assignRole('siswa');

        return $user;
    }

    public function test_daftar_ulasan_dapat_diakses_publik(): void
    {
        $package = $this->makePackage();
        $user = $this->makeSiswa();

        Review::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'rating' => 5,
            'comment' => 'Materinya bagus',
        ]);

        $this->getJson("/api/v1/reviews?package_id={$package->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.rating', 5);
    }

    public function test_hanya_siswa_yang_pernah_enroll_yang_bisa_memberi_ulasan(): void
    {
        $package = $this->makePackage();
        $user = $this->makeSiswa();

        $this->actingAs($user)->postJson('/api/v1/reviews', [
            'package_id' => $package->id,
            'rating' => 4,
        ])->assertStatus(403);
    }

    public function test_user_terdaftar_bisa_memberi_ulasan(): void
    {
        $package = $this->makePackage();
        $user = $this->makeSiswa();

        Enrollment::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'progress_pct' => 0,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($user)->postJson('/api/v1/reviews', [
            'package_id' => $package->id,
            'rating' => 4,
            'comment' => 'Lumayan',
        ])->assertStatus(201)
            ->assertJsonPath('data.rating', 4);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'package_id' => $package->id,
            'rating' => 4,
        ]);
    }

    public function test_user_tidak_bisa_memberi_ulasan_ganda(): void
    {
        $package = $this->makePackage();
        $user = $this->makeSiswa();

        Enrollment::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'progress_pct' => 0,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($user)->postJson('/api/v1/reviews', [
            'package_id' => $package->id,
            'rating' => 5,
        ])->assertStatus(201);

        $this->actingAs($user)->postJson('/api/v1/reviews', [
            'package_id' => $package->id,
            'rating' => 3,
        ])->assertStatus(409);

        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_pemilik_ulasan_bisa_menghapus(): void
    {
        $package = $this->makePackage();
        $user = $this->makeSiswa();

        $review = Review::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'rating' => 3,
        ]);

        $this->actingAs($user)->deleteJson("/api/v1/reviews/{$review->id}")
            ->assertOk();

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_user_lain_tidak_bisa_menghapus_ulasan_orang(): void
    {
        $package = $this->makePackage();
        $owner = $this->makeSiswa();
        $other = $this->makeSiswa();

        $review = Review::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'rating' => 5,
        ]);

        $this->actingAs($other)->deleteJson("/api/v1/reviews/{$review->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }
}
