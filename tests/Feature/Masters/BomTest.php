<?php

namespace Tests\Feature\Masters;

use App\Models\Bom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BomTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['bom.view', 'bom.create', 'bom.edit', 'bom.delete'] as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['bom.view', 'bom.create', 'bom.edit', 'bom.delete']);
    }

    public function test_edit_screen_renders_existing_lines(): void
    {
        $bom = Bom::query()->where('key', 'labels')->firstOrFail();

        $response = $this->actingAs($this->admin)->get(route('masters.boms.edit', $bom));

        $response->assertOk()
            ->assertSee('Main Label');
    }

    public function test_can_list_seeded_boms(): void
    {
        $response = $this->actingAs($this->admin)->get(route('masters.boms.index'));

        $response->assertOk()
            ->assertSee('Standard garment BOM');
    }

    public function test_can_create_bom_with_line_image(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin)->post(route('masters.boms.store'), [
            'name'   => 'Buttons pack',
            'status' => 'active',
            'lines'  => [
                [
                    'component_name' => 'Buttons',
                    'size'           => '28L',
                    'qty'            => 1,
                    'rate'           => 1.25,
                    'image'          => UploadedFile::fake()->image('button.jpg', 40, 40),
                ],
            ],
        ]);

        $response->assertRedirect(route('masters.boms.index'));
        $this->assertDatabaseHas('boms', ['name' => 'Buttons pack']);
        $this->assertDatabaseHas('bom_lines', [
            'component_name' => 'Buttons',
            'size'           => '28L',
        ]);

        $line = Bom::query()->where('name', 'Buttons pack')->first()?->lines()->first();
        $this->assertNotNull($line?->image_path);
        Storage::disk('public')->assertExists($line->image_path);
    }

    public function test_prevents_duplicate_bom_name(): void
    {
        Bom::create(['name' => 'Labels pack', 'status' => 'active', 'key' => 'labels-pack-test']);

        $response = $this->actingAs($this->admin)->post(route('masters.boms.store'), [
            'name'   => 'Labels pack',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('name');
    }
}
