<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Module;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use App\Models\Voucher;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    private function makePackage(array $attrs = []): Package
    {
        $module = Module::create(['title' => 'Modul', 'is_active' => true]);
        $package = Package::create(array_merge([
            'title' => 'Paket Belajar',
            'price' => 500000,
            'is_active' => true,
        ], $attrs));
        $package->modules()->attach($module->id, ['sort_order' => 1]);

        return $package;
    }

    private function makeSiswa(): User
    {
        $user = User::factory()->create();
        $user->assignRole('siswa');

        return $user;
    }

    // ─── Packages ──────────────────────────────────────────────────────────────

    public function test_daftar_paket_dapat_diakses_tanpa_login(): void
    {
        $this->makePackage();

        $this->getJson('/api/v1/packages')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'title', 'price']]]);
    }

    public function test_paket_tidak_aktif_tidak_muncul_di_daftar(): void
    {
        $this->makePackage(['is_active' => false]);

        $this->getJson('/api/v1/packages')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    // ─── Preview ───────────────────────────────────────────────────────────────

    public function test_preview_tanpa_voucher_menampilkan_harga_asli(): void
    {
        $user = $this->makeSiswa();
        $package = $this->makePackage(['price' => 200000]);

        $this->actingAs($user)->getJson(
            "/api/v1/checkout/preview?package_id={$package->id}"
        )->assertOk()
            ->assertJsonPath('data.original_price', 200000)
            ->assertJsonPath('data.discount_amount', 0)
            ->assertJsonPath('data.final_price', 200000);
    }

    public function test_preview_dengan_voucher_persen(): void
    {
        $user = $this->makeSiswa();
        $package = $this->makePackage(['price' => 200000]);
        $voucher = Voucher::create([
            'code' => 'DISKON20',
            'discount_type' => 'percent',
            'discount_value' => 20,
            'is_active' => true,
        ]);

        $this->actingAs($user)->getJson(
            "/api/v1/checkout/preview?package_id={$package->id}&voucher_code=DISKON20"
        )->assertOk()
            ->assertJsonPath('data.discount_amount', 40000)
            ->assertJsonPath('data.final_price', 160000);
    }

    public function test_preview_dengan_voucher_fixed(): void
    {
        $user = $this->makeSiswa();
        $package = $this->makePackage(['price' => 200000]);
        Voucher::create([
            'code' => 'POTONGAN50K',
            'discount_type' => 'fixed',
            'discount_value' => 50000,
            'is_active' => true,
        ]);

        $this->actingAs($user)->getJson(
            "/api/v1/checkout/preview?package_id={$package->id}&voucher_code=POTONGAN50K"
        )->assertOk()
            ->assertJsonPath('data.final_price', 150000);
    }

    public function test_preview_dengan_voucher_tidak_valid_ditolak(): void
    {
        $user = $this->makeSiswa();
        $package = $this->makePackage();

        $this->actingAs($user)->getJson(
            "/api/v1/checkout/preview?package_id={$package->id}&voucher_code=TIDAKADA"
        )->assertStatus(422)
            ->assertJsonValidationErrors('voucher_code');
    }

    // ─── Store (Buat Order) ────────────────────────────────────────────────────

    public function test_dapat_membuat_order(): void
    {
        $user = $this->makeSiswa();
        $package = $this->makePackage(['price' => 300000]);

        $res = $this->actingAs($user)->postJson('/api/v1/checkout', [
            'package_id' => $package->id,
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.original_price', 300000);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'pending',
        ]);
    }

    public function test_tidak_bisa_buat_order_duplikat_pending(): void
    {
        $user = $this->makeSiswa();
        $package = $this->makePackage();

        $this->actingAs($user)->postJson('/api/v1/checkout', ['package_id' => $package->id])
            ->assertStatus(201);

        $this->actingAs($user)->postJson('/api/v1/checkout', ['package_id' => $package->id])
            ->assertStatus(409);
    }

    public function test_tidak_bisa_order_jika_sudah_enrolled(): void
    {
        $user = $this->makeSiswa();
        $package = $this->makePackage();

        Enrollment::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'progress_pct' => 0,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($user)->postJson('/api/v1/checkout', [
            'package_id' => $package->id,
        ])->assertStatus(422);
    }

    public function test_buat_order_dengan_voucher_hitung_diskon(): void
    {
        $user = $this->makeSiswa();
        $package = $this->makePackage(['price' => 100000]);
        Voucher::create([
            'code' => 'HEMAT10',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'is_active' => true,
        ]);

        $res = $this->actingAs($user)->postJson('/api/v1/checkout', [
            'package_id' => $package->id,
            'voucher_code' => 'HEMAT10',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.discount_amount', 10000)
            ->assertJsonPath('data.final_price', 90000);
    }

    // ─── Pay (Simulasi Pembayaran) ─────────────────────────────────────────────

    public function test_bayar_order_ubah_status_jadi_paid(): void
    {
        $user = $this->makeSiswa();
        $package = $this->makePackage();

        $order = Order::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'original_price' => 100000,
            'discount_amount' => 0,
            'final_price' => 100000,
            'status' => 'pending',
        ]);

        $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/pay")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
    }

    public function test_bayar_order_trigger_enrollment_otomatis(): void
    {
        $user = $this->makeSiswa();
        $package = $this->makePackage();

        $order = Order::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'original_price' => 100000,
            'discount_amount' => 0,
            'final_price' => 100000,
            'status' => 'pending',
        ]);

        $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/pay")
            ->assertOk();

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $user->id,
            'package_id' => $package->id,
        ]);
    }

    public function test_bayar_order_increment_used_count_voucher(): void
    {
        $user = $this->makeSiswa();
        $package = $this->makePackage(['price' => 100000]);
        $voucher = Voucher::create([
            'code' => 'VOUCHER1',
            'discount_type' => 'fixed',
            'discount_value' => 10000,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'voucher_id' => $voucher->id,
            'original_price' => 100000,
            'discount_amount' => 10000,
            'final_price' => 90000,
            'status' => 'pending',
        ]);

        $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/pay")->assertOk();

        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id, 'used_count' => 1]);
    }

    public function test_tidak_bisa_bayar_order_yang_sudah_paid(): void
    {
        $user = $this->makeSiswa();
        $package = $this->makePackage();

        $order = Order::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'original_price' => 0,
            'discount_amount' => 0,
            'final_price' => 0,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/pay")
            ->assertStatus(422);
    }

    public function test_tidak_bisa_bayar_order_orang_lain(): void
    {
        $user1 = $this->makeSiswa();
        $user2 = $this->makeSiswa();
        $package = $this->makePackage();

        $order = Order::create([
            'user_id' => $user1->id,
            'package_id' => $package->id,
            'original_price' => 0,
            'discount_amount' => 0,
            'final_price' => 0,
            'status' => 'pending',
        ]);

        $this->actingAs($user2)->postJson("/api/v1/orders/{$order->id}/pay")
            ->assertStatus(403);
    }

    // ─── My Orders ─────────────────────────────────────────────────────────────

    public function test_user_dapat_lihat_riwayat_order_sendiri(): void
    {
        $user = $this->makeSiswa();
        $package = $this->makePackage();

        Order::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'original_price' => 100000,
            'discount_amount' => 0,
            'final_price' => 100000,
            'status' => 'pending',
        ]);

        $this->actingAs($user)->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }
}
