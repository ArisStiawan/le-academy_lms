<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Institution;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use Tests\TestCase;

class OrderManageTest extends TestCase
{
    private function makeInstitution(): Institution
    {
        return Institution::create(['name' => 'Lembaga '.uniqid()]);
    }

    private function makeUser(string $role, ?Institution $institution = null): User
    {
        $user = User::factory()->create(['institution_id' => $institution?->id]);
        $user->assignRole($role);

        return $user;
    }

    private function makePackage(Institution $institution): Package
    {
        return Package::create([
            'institution_id' => $institution->id,
            'title' => 'Paket '.uniqid(),
            'price' => 100000,
            'is_active' => true,
        ]);
    }

    private function makePaidOrder(User $user, Package $package, float $price): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'original_price' => $price,
            'discount_amount' => 0,
            'final_price' => $price,
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function test_lembaga_hanya_melihat_ringkasan_lembaganya(): void
    {
        $instA = $this->makeInstitution();
        $instB = $this->makeInstitution();

        $lembaga = $this->makeUser('lembaga', $instA);
        $siswa = $this->makeUser('siswa', $instA);
        $siswaB = $this->makeUser('siswa', $instB);

        $pkgA = $this->makePackage($instA);
        $pkgB = $this->makePackage($instB);

        $this->makePaidOrder($siswa, $pkgA, 100000);
        $this->makePaidOrder($siswa, $pkgA, 50000);
        $this->makePaidOrder($siswaB, $pkgB, 999000);

        $res = $this->actingAs($lembaga)->getJson('/api/v1/orders/summary')
            ->assertOk()
            ->assertJsonPath('data.total_revenue', 150000)
            ->assertJsonPath('data.paid_orders', 2);

        $this->assertSame(2, $res->json('data.paid_orders'));
    }

    public function test_admin_melihat_ringkasan_global(): void
    {
        $instA = $this->makeInstitution();
        $instB = $this->makeInstitution();
        $admin = $this->makeUser('admin', null);

        $siswaA = $this->makeUser('siswa', $instA);
        $siswaB = $this->makeUser('siswa', $instB);

        $this->makePaidOrder($siswaA, $this->makePackage($instA), 200000);
        $this->makePaidOrder($siswaB, $this->makePackage($instB), 300000);

        $this->actingAs($admin)->getJson('/api/v1/orders/summary')
            ->assertOk()
            ->assertJsonPath('data.total_revenue', 500000);
    }

    public function test_siswa_tidak_boleh_lihat_transaksi_pengelola(): void
    {
        $siswa = $this->makeUser('siswa');

        $this->actingAs($siswa)->getJson('/api/v1/orders/manage')->assertStatus(403);
        $this->actingAs($siswa)->getJson('/api/v1/orders/summary')->assertStatus(403);
    }

    public function test_manage_packages_hanya_menampilkan_paket_lembaga(): void
    {
        $instA = $this->makeInstitution();
        $instB = $this->makeInstitution();
        $lembaga = $this->makeUser('lembaga', $instA);

        $this->makePackage($instA);
        $this->makePackage($instB);

        $res = $this->actingAs($lembaga)->getJson('/api/v1/manage/packages')->assertOk();

        $instIds = collect($res->json('data'))->pluck('institution_id')->unique()->values();
        $this->assertSame([$instA->id], $instIds->all());
    }

    public function test_manage_packages_menyertakan_paket_non_aktif(): void
    {
        $inst = $this->makeInstitution();
        $admin = $this->makeUser('admin', null);

        Package::create([
            'institution_id' => $inst->id,
            'title' => 'Paket Arsip',
            'price' => 10000,
            'is_active' => false,
        ]);

        $this->actingAs($admin)->getJson('/api/v1/manage/packages')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_enrollment_dapat_melihat_progres(): void
    {
        $inst = $this->makeInstitution();
        $siswa = $this->makeUser('siswa', $inst);
        $package = $this->makePackage($inst);

        Enrollment::create([
            'user_id' => $siswa->id,
            'package_id' => $package->id,
            'progress_pct' => 75,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($siswa)->getJson('/api/v1/my/enrollments')
            ->assertOk()
            ->assertJsonPath('data.0.progress_pct', 75);
    }
}
