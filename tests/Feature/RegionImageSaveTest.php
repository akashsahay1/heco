<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\User;
use App\Services\ImageUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * #25 — saveRegion must persist the region image (uploaded + resized, year/month
 * path), anchor_points, and sort_order (on update too — previously create-only).
 */
class RegionImageSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_region_saves_image_anchors_and_sort_order(): void
    {
        $portal = config('app.portal_domain');
        $admin = User::create([
            'full_name' => 'Admin', 'email' => 'admin@region.test',
            'password' => 'password', 'user_role' => 'hct_admin', 'status' => 'active',
        ]);

        $img = UploadedFile::fake()->image('region.jpg', 300, 200);
        $this->actingAs($admin)
            ->post("http://{$portal}/ajax", [
                'save_region' => 1, 'name' => 'Tirthan', 'continent' => 'Asia', 'country' => 'India',
                'anchor_points' => json_encode([['lat' => 31.5, 'lng' => 77.3]]),
                'image' => $img,
            ])
            ->assertStatus(200);

        $region = Region::where('name', 'Tirthan')->first();
        $this->assertNotNull($region);
        $this->assertStringStartsWith('/uploads/regions/', (string) $region->image);
        $this->assertSame([['lat' => 31.5, 'lng' => 77.3]], $region->anchor_points);

        // sort_order editable on update (was create-only).
        $this->actingAs($admin)
            ->post("http://{$portal}/ajax", [
                'save_region' => 1, 'region_id' => $region->id, 'name' => 'Tirthan',
                'continent' => 'Asia', 'country' => 'India', 'sort_order' => 7,
            ])
            ->assertStatus(200);
        $this->assertSame(7, $region->fresh()->sort_order);

        // Clean up the file written under public/uploads.
        ImageUploadService::deleteLocal($region->image);
    }
}
