<?php

namespace Tests\Feature\Masters;

use App\Models\CalculationBasis;
use App\Models\Category;
use App\Models\GstRate;
use App\Models\PriceBand;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->category = Category::forceCreate([
            'code' => 'CAT001', 'name' => "Men's Shirts", 'status' => 'active',
        ]);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create(['status' => true]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category_id'     => $this->category->id,
            'item_group_code' => 'PRD01',
            'name'            => 'Cotton Casual Shirt',
            'status'          => 'active',
        ], $overrides);
    }

    /*
    |--------------------------------------------------------------------------
    | Create — every field on the sheet
    |--------------------------------------------------------------------------
    */

    public function test_a_product_can_be_created_with_every_sheet_field(): void
    {
        $band       = PriceBand::where('code', 'AA')->first();
        $gst        = GstRate::where('rate', 5)->first();
        $user       = $this->actingAsRole('Super Admin');

        $this->actingAs($user)->post(route('masters.products.store'), $this->payload([
            'name_on_export_document' => 'MENS COTTON SHIRT',
            'barcode'                 => 'BC-1234',
            'unit_po'                 => 'PCS',
            'unit_export'             => 'KGS',
            'hsn_code'                => '620520',
            'drawback_sr_no'          => 'B001',
            'price_band_id'           => $band->id,
            'gst_rate_id'             => $gst->id,
            'fabric_length_mtr'       => 2.5,
            'fabric_width_inch'       => 44,
            'description'             => '100% Cotton',
            'remarks'                 => 'Bestseller',
        ]))->assertRedirect(route('masters.products.index'));

        $product = Product::first();

        $this->assertSame('PRD01', $product->item_group_code);
        $this->assertSame('MENS COTTON SHIRT', $product->name_on_export_document);
        $this->assertSame('620520', $product->hsn_code);
        // Unit is typed, not an FK — the client cancelled the Unit Master.
        $this->assertSame('PCS', $product->unit_po);
        $this->assertSame('KGS', $product->unit_export);
        $this->assertSame($gst->id, $product->gst_rate_id);
        $this->assertSame($user->id, $product->created_by);
    }

    /*
    |--------------------------------------------------------------------------
    | Sheet col X — "autocalculated based on inputted values"
    |--------------------------------------------------------------------------
    */

    public function test_sq_mtr_per_unit_is_calculated_by_the_database(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.products.store'), $this->payload([
                'fabric_length_mtr' => 2.5,
                'fabric_width_inch' => 44,
            ]));

        // 2.5 * 44 / 39.3701 = 2.7940
        $this->assertSame('2.7940', Product::first()->sq_mtr_per_unit);
    }

    public function test_sq_mtr_is_null_when_the_fabric_fields_are_blank(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.products.store'), $this->payload());

        $this->assertNull(Product::first()->sq_mtr_per_unit);
    }

    public function test_sq_mtr_cannot_be_written_directly(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.products.store'), $this->payload([
                'fabric_length_mtr' => 2,
                'fabric_width_inch' => 39.3701,
                'sq_mtr_per_unit'   => 999,
            ]));

        $this->assertSame('2.0000', Product::first()->sq_mtr_per_unit);
    }

    /*
    |--------------------------------------------------------------------------
    | Incentives — sheet cols L to U
    |--------------------------------------------------------------------------
    */

    public function test_incentives_are_stored_one_row_per_scheme(): void
    {
        $fob = CalculationBasis::where('name', 'FOB Value')->first();
        $qty = CalculationBasis::where('name', 'Quantity')->first();

        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.products.store'), $this->payload([
                'incentives' => [
                    'drawback' => ['percent_1' => 2.5, 'cap_value' => 40, 'calculation_basis_id' => $fob->id],
                    'rosctl'   => ['percent_1' => 1.5, 'percent_2' => 0.5, 'calculation_basis_id' => $qty->id],
                    'rodtep'   => [],   // not claimed
                ],
            ]))->assertRedirect();

        $product = Product::with('incentives')->first();

        $this->assertCount(2, $product->incentives);
        $this->assertSame('2.500', $product->incentive('drawback')->percent_1);
        $this->assertSame('40.0000', $product->incentive('drawback')->cap_value);
        $this->assertSame('0.500', $product->incentive('rosctl')->percent_2);

        // A scheme with no rate is absent, not a row of nulls.
        $this->assertNull($product->incentive('rodtep'));
    }

    public function test_clearing_an_incentive_rate_removes_its_row(): void
    {
        $fob  = CalculationBasis::where('name', 'FOB Value')->first();
        $user = $this->actingAsRole('Super Admin');

        $this->actingAs($user)->post(route('masters.products.store'), $this->payload([
            'incentives' => ['drawback' => ['percent_1' => 2.5, 'calculation_basis_id' => $fob->id]],
        ]));

        $product = Product::first();
        $this->assertSame(1, $product->incentives()->count());

        $this->actingAs($user)->put(route('masters.products.update', $product), $this->payload([
            'incentives' => ['drawback' => ['percent_1' => '', 'calculation_basis_id' => $fob->id]],
        ]));

        $this->assertSame(0, $product->incentives()->count());
    }

    public function test_an_incentive_rate_without_a_basis_is_rejected(): void
    {
        // A percentage with nothing to apply it to cannot be computed later.
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.products.store'), $this->payload([
                'incentives' => ['drawback' => ['percent_1' => 2.5]],
            ]))
            ->assertSessionHasErrors('incentives.drawback.calculation_basis_id');

        $this->assertSame(0, Product::count());
    }

    public function test_a_second_percentage_is_rejected_for_schemes_that_have_only_one(): void
    {
        $fob = CalculationBasis::where('name', 'FOB Value')->first();

        // Only RoSCTL is quoted as two percentages (sheet cols O and P).
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.products.store'), $this->payload([
                'incentives' => [
                    'drawback' => ['percent_1' => 2.5, 'percent_2' => 1, 'calculation_basis_id' => $fob->id],
                ],
            ]))
            ->assertSessionHasErrors('incentives.drawback.percent_2');
    }

    public function test_incentives_are_deleted_with_the_product(): void
    {
        $fob = CalculationBasis::where('name', 'FOB Value')->first();

        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.products.store'), $this->payload([
                'incentives' => ['drawback' => ['percent_1' => 2.5, 'calculation_basis_id' => $fob->id]],
            ]));

        $product = Product::first();
        $product->forceDelete();

        $this->assertSame(0, \App\Models\ProductIncentive::count());
    }

    /*
    |--------------------------------------------------------------------------
    | Uniqueness — sheet cols B and C, "I should get an alert"
    |--------------------------------------------------------------------------
    */

    public function test_duplicate_item_group_code_is_rejected(): void
    {
        $user = $this->actingAsRole('Super Admin');

        $this->actingAs($user)->post(route('masters.products.store'), $this->payload());

        $this->actingAs($user)
            ->post(route('masters.products.store'), $this->payload(['name' => 'Another name']))
            ->assertSessionHasErrors('item_group_code');

        $this->assertSame(1, Product::count());
    }

    public function test_duplicate_product_name_is_rejected(): void
    {
        $user = $this->actingAsRole('Super Admin');

        $this->actingAs($user)->post(route('masters.products.store'), $this->payload());

        $this->actingAs($user)
            ->post(route('masters.products.store'), $this->payload(['item_group_code' => 'PRD02']))
            ->assertSessionHasErrors('name');
    }

    public function test_a_product_can_keep_its_own_code_when_updated(): void
    {
        $user = $this->actingAsRole('Super Admin');
        $this->actingAs($user)->post(route('masters.products.store'), $this->payload());

        $product = Product::first();

        $this->actingAs($user)
            ->put(route('masters.products.update', $product), $this->payload(['description' => 'Edited']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Edited', $product->refresh()->description);
    }

    public function test_the_code_availability_endpoint_reports_taken_and_free(): void
    {
        $user = $this->actingAsRole('Super Admin');
        $this->actingAs($user)->post(route('masters.products.store'), $this->payload());
        $product = Product::first();

        $this->actingAs($user)
            ->getJson(route('masters.products.check-code', ['field' => 'item_group_code', 'value' => 'PRD01']))
            ->assertOk()->assertJson(['available' => false]);

        $this->actingAs($user)
            ->getJson(route('masters.products.check-code', ['field' => 'item_group_code', 'value' => 'PRD99']))
            ->assertOk()->assertJson(['available' => true]);

        // Editing a product must not report its own code as taken.
        $this->actingAs($user)
            ->getJson(route('masters.products.check-code', [
                'field' => 'item_group_code', 'value' => 'PRD01', 'ignore' => $product->id,
            ]))
            ->assertOk()->assertJson(['available' => true]);
    }

    public function test_the_code_availability_endpoint_reports_taken_even_for_soft_deleted_products(): void
    {
        $user = $this->actingAsRole('Super Admin');
        $product = Product::create($this->payload());
        $product->delete();

        $this->actingAs($user)
            ->getJson(route('masters.products.check-code', ['field' => 'item_group_code', 'value' => 'PRD01']))
            ->assertOk()->assertJson(['available' => false]);
    }

    public function test_the_code_availability_endpoint_rejects_an_arbitrary_column(): void
    {
        // `field` lands in a where() — it must be whitelisted.
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->getJson(route('masters.products.check-code', ['field' => 'created_by', 'value' => '1']))
            ->assertStatus(422);
    }

    public function test_inactive_category_relation_validation_fails_on_edit(): void
    {
        $user = $this->actingAsRole('Super Admin');
        $category = Category::forceCreate(['code' => 'CAT800', 'name' => 'To Be Inactive', 'status' => 'active']);
        $product = Product::create([
            'category_id'     => $category->id,
            'item_group_code' => 'PRD80',
            'name'            => 'Inactive Cat Product',
            'status'          => 'active',
        ]);

        $category->update(['status' => 'inactive']);

        $response = $this->actingAs($user)->get(route('masters.products.edit', $product));
        $response->assertSee($category->name);
    }


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    public function test_hsn_code_must_be_digits(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.products.store'), $this->payload(['hsn_code' => 'ABC12']))
            ->assertSessionHasErrors('hsn_code');
    }

    public function test_category_is_required(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.products.store'), $this->payload(['category_id' => null]))
            ->assertSessionHasErrors('category_id');
    }

    /*
    |--------------------------------------------------------------------------
    | List, delete, toggle
    |--------------------------------------------------------------------------
    */

    public function test_the_list_filters_by_category_and_search(): void
    {
        $other = Category::forceCreate(['code' => 'CAT002', 'name' => 'Bottoms', 'status' => 'active']);

        Product::create($this->payload());
        Product::create([
            'category_id' => $other->id, 'item_group_code' => 'PRD02',
            'name' => 'Denim Jeans', 'status' => 'active',
        ]);

        $user = $this->actingAsRole('Super Admin');

        $this->actingAs($user)->get(route('masters.products.index', ['category_id' => $other->id]))
            ->assertSee('Denim Jeans')->assertDontSee('Cotton Casual Shirt');

        $this->actingAs($user)->get(route('masters.products.index', ['search' => 'PRD01']))
            ->assertSee('Cotton Casual Shirt')->assertDontSee('Denim Jeans');
    }

    public function test_the_product_list_accepts_a_larger_page_size(): void
    {
        $user = $this->actingAsRole('Super Admin');

        $this->actingAs($user)->get(route('masters.products.index', ['per_page' => 50]))
            ->assertOk()
            ->assertSee('Per page');
    }

    public function test_a_product_is_soft_deleted_and_status_toggles(): void
    {
        $user    = $this->actingAsRole('Super Admin');
        $product = Product::create($this->payload());

        $this->actingAs($user)->patch(route('masters.products.toggle-status', $product));
        $this->assertSame('inactive', $product->refresh()->status);

        $this->actingAs($user)->delete(route('masters.products.destroy', $product))->assertRedirect();
        $this->assertSoftDeleted($product);
    }

    public function test_every_screen_renders(): void
    {
        $user    = $this->actingAsRole('Super Admin');
        $product = Product::create($this->payload());

        $this->actingAs($user)->get(route('masters.products.index'))->assertOk();
        $this->actingAs($user)->get(route('masters.products.create'))->assertOk();
        $this->actingAs($user)->get(route('masters.products.show', $product))->assertOk();
        $this->actingAs($user)->get(route('masters.products.edit', $product))->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */

    public function test_a_role_without_product_view_is_denied(): void
    {
        // Jobworker has no product.* permission at all.
        $this->actingAs($this->actingAsRole('Jobworker'))
            ->get(route('masters.products.index'))
            ->assertForbidden();
    }

    public function test_a_view_only_role_cannot_create_or_delete(): void
    {
        // Accounts has product.view only.
        $user    = $this->actingAsRole('Accounts');
        $product = Product::create($this->payload());

        $this->actingAs($user)->get(route('masters.products.index'))->assertOk();
        $this->actingAs($user)->get(route('masters.products.create'))->assertForbidden();
        $this->actingAs($user)->post(route('masters.products.store'), $this->payload(['item_group_code' => 'PRD77', 'name' => 'X']))->assertForbidden();
        $this->actingAs($user)->get(route('masters.products.edit', $product))->assertForbidden();
        $this->actingAs($user)->delete(route('masters.products.destroy', $product))->assertForbidden();
    }
}
