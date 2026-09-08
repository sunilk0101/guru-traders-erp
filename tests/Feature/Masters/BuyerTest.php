<?php

namespace Tests\Feature\Masters;

use App\Models\Agent;
use App\Models\Buyer;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Incoterm;
use App\Models\PaymentTerm;
use App\Models\Port;
use App\Models\ShipmentMethod;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create(['status' => true]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Category::$code is outside $fillable — it is assigned by
     * NumberSeriesService — so fixtures use forceCreate.
     */
    private function category(string $name, string $code): Category
    {
        return Category::forceCreate(['code' => $code, 'name' => $name, 'status' => 'active']);
    }

    private function countryId(string $iso): int
    {
        return Country::where('iso_code', $iso)->value('id');
    }

    private function stateId(string $iso, string $state): int
    {
        return State::where('country_id', $this->countryId($iso))->where('name', $state)->value('id');
    }

    private function cityId(string $iso, string $state, string $city): int
    {
        return City::where('state_id', $this->stateId($iso, $state))->where('name', $city)->value('id');
    }

    /**
     * A complete, valid payload. Individual tests override the one field they
     * are about, so a rule change never silently passes because the field under
     * test was the only one present.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'display_code'           => 'BUY01',
            'company_name'           => 'ABC Fashion Ltd',
            'name_on_export_invoice' => 'ABC Fashion Limited',
            'category_ids'           => [],
            'contact_person'         => 'John Smith',
            'email'                  => 'john@abcfashion.com',
            'mobile'                 => '+44 987654321',
            'address'                => '12 Fashion Street',
            'country_id'             => $this->countryId('GB'),
            'state_id'               => $this->stateId('GB', 'England'),
            'city_id'                => $this->cityId('GB', 'England', 'London'),
            'pincode'                => 'EC1A1AA',
            'port_id'                => Port::where('code', 'GBLON')->value('id'),
            'agent_id'               => null,
            'agent_commission_type'  => null,
            'agent_commission_value' => null,
            'payment_term_id'        => PaymentTerm::where('name', '30 Days')->value('id'),
            'incoterm_id'            => Incoterm::where('code', 'FOB')->value('id'),
            'shipment_method_id'     => ShipmentMethod::where('name', 'Sea')->value('id'),
            'shipment_method_ids'    => array_filter([ShipmentMethod::where('name', 'Sea')->value('id')]),
            'currency_id'            => Currency::where('iso_code', 'GBP')->value('id'),
            'bank_name'              => 'HSBC UK',
            'account_number'         => '12345678',
            'swift_code'             => 'HBUKGB4B',
            'carton_markings'        => [],
            'status'                 => 'active',
            'remarks'                => null,
        ], $overrides);
    }

    private function createBuyer(array $overrides = []): Buyer
    {
        $payload = $this->payload($overrides);
        unset($payload['category_ids'], $payload['carton_markings'], $payload['shipment_method_ids']);

        return Buyer::forceCreate($payload);
    }

    /*
    |--------------------------------------------------------------------------
    | CRUD — every column on the sheet
    |--------------------------------------------------------------------------
    */

    public function test_a_buyer_can_be_created_with_every_sheet_field(): void
    {
        $shirts   = $this->category("Men's Shirts", 'CAT901');
        $trousers = $this->category("Men's Trousers", 'CAT902');
        $agent    = Agent::ofType('buyer')->first();
        $user     = $this->actingAsRole('Super Admin');

        $this->actingAs($user)->post(route('masters.buyers.store'), $this->payload([
            'category_ids'           => [$shirts->id, $trousers->id],
            'agent_id'               => $agent->id,
            'agent_commission_type'  => 'percent',
            'agent_commission_value' => '2.5',
            'remarks'                => 'Repeat customer',
        ]))->assertRedirect(route('masters.buyers.index'));

        $buyer = Buyer::first();

        $this->assertSame('BUY01', $buyer->display_code);
        $this->assertSame('ABC Fashion Ltd', $buyer->company_name);
        $this->assertSame('ABC Fashion Limited', $buyer->name_on_export_invoice);
        $this->assertSame('John Smith', $buyer->contact_person);
        $this->assertSame('john@abcfashion.com', $buyer->email);
        $this->assertSame('London', $buyer->city->name);
        $this->assertSame('England', $buyer->state->name);
        $this->assertSame('United Kingdom', $buyer->country->name);
        $this->assertSame($agent->id, $buyer->agent_id);
        $this->assertSame('percent', $buyer->agent_commission_type);
        $this->assertSame('HBUKGB4B', $buyer->swift_code);
        $this->assertSame('active', $buyer->status);

        // Col D — the multi-select
        $this->assertEqualsCanonicalizing(
            [$shirts->id, $trousers->id],
            $buyer->categories->pluck('id')->all()
        );

        // HasAuditColumns fills these without the service being asked to.
        $this->assertSame($user->id, $buyer->created_by);
        $this->assertSame($user->id, $buyer->updated_by);
    }

    public function test_a_buyer_can_be_updated(): void
    {
        $a    = $this->category('A', 'CAT901');
        $b    = $this->category('B', 'CAT902');
        $user = $this->actingAsRole('Super Admin');

        $this->actingAs($user)->post(route('masters.buyers.store'), $this->payload([
            'category_ids' => [$a->id, $b->id],
        ]));

        $buyer = Buyer::first();

        $this->actingAs($user)->put(route('masters.buyers.update', $buyer), $this->payload([
            'company_name' => 'ABC Fashion PLC',
            'category_ids' => [$b->id],
            'city_id'      => $this->cityId('GB', 'England', 'Manchester'),
        ]))->assertRedirect();

        $buyer->refresh();
        $this->assertSame('ABC Fashion PLC', $buyer->company_name);
        $this->assertSame('Manchester', $buyer->city->name);
        $this->assertSame([$b->id], $buyer->categories->pluck('id')->all());
    }

    public function test_status_toggles(): void
    {
        $user  = $this->actingAsRole('Super Admin');
        $buyer = $this->createBuyer(['category_ids' => null, 'carton_markings' => null]);

        $this->actingAs($user)->patch(route('masters.buyers.toggle-status', $buyer));
        $this->assertSame('inactive', $buyer->refresh()->status);

        $this->actingAs($user)->patch(route('masters.buyers.toggle-status', $buyer));
        $this->assertSame('active', $buyer->refresh()->status);
    }

    public function test_a_buyer_is_soft_deleted(): void
    {
        $buyer = $this->createBuyer();

        $this->actingAs($this->actingAsRole('Super Admin'))
            ->delete(route('masters.buyers.destroy', $buyer))
            ->assertRedirect();

        $this->assertSoftDeleted($buyer);
    }

    /*
    |--------------------------------------------------------------------------
    | Col A — Auto-generated display code (BUY01, BUY02, ...)
    |--------------------------------------------------------------------------
    */

    public function test_display_codes_are_generated_sequentially(): void
    {
        $user = $this->actingAsRole('Super Admin');

        $this->actingAs($user)->post(route('masters.buyers.store'), $this->payload(['company_name' => 'First Buyer']));
        $this->assertSame('BUY01', Buyer::first()->display_code);

        $this->actingAs($user)->post(route('masters.buyers.store'), $this->payload(['company_name' => 'Second Buyer']));
        $this->assertSame('BUY02', Buyer::latest('id')->first()->display_code);
    }

    /*
    |--------------------------------------------------------------------------
    | Cols L, K, J — cascading Country -> State -> City
    |--------------------------------------------------------------------------
    */

    public function test_the_state_endpoint_returns_only_that_countrys_states(): void
    {
        $response = $this->actingAs($this->actingAsRole('Super Admin'))
            ->getJson(route('masters.geo.states', ['country_id' => $this->countryId('IN')]))
            ->assertOk();

        $names = collect($response->json())->pluck('name');

        $this->assertTrue($names->contains('Tamil Nadu'));
        $this->assertFalse($names->contains('England'));
    }

    public function test_the_city_endpoint_returns_only_that_states_cities(): void
    {
        $response = $this->actingAs($this->actingAsRole('Super Admin'))
            ->getJson(route('masters.geo.cities', ['state_id' => $this->stateId('IN', 'Tamil Nadu')]))
            ->assertOk();

        $names = collect($response->json())->pluck('name');

        $this->assertTrue($names->contains('Tirupur'));
        $this->assertFalse($names->contains('Mumbai'));
    }

    public function test_the_cascade_endpoints_return_nothing_without_a_parent(): void
    {
        // An absent parent must mean "no options", not "every option".
        $user = $this->actingAsRole('Super Admin');

        $this->actingAs($user)->getJson(route('masters.geo.states'))->assertOk()->assertExactJson([]);
        $this->actingAs($user)->getJson(route('masters.geo.cities'))->assertOk()->assertExactJson([]);
    }

    public function test_the_cascade_endpoints_reject_a_guest(): void
    {
        $this->getJson(route('masters.geo.states', ['country_id' => 1]))->assertUnauthorized();
    }

    public function test_a_state_from_a_different_country_is_rejected(): void
    {
        // The browser narrows the list; this is what actually enforces it.
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.buyers.store'), $this->payload([
                'country_id' => $this->countryId('GB'),
                'state_id'   => $this->stateId('IN', 'Tamil Nadu'),
                'city_id'    => null,
            ]))
            ->assertSessionHasErrors('state_id');

        $this->assertSame(0, Buyer::count());
    }

    public function test_a_city_from_a_different_state_is_rejected(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.buyers.store'), $this->payload([
                'country_id' => $this->countryId('IN'),
                'state_id'   => $this->stateId('IN', 'Tamil Nadu'),
                'city_id'    => $this->cityId('IN', 'Maharashtra', 'Mumbai'),
            ]))
            ->assertSessionHasErrors('city_id');

        $this->assertSame(0, Buyer::count());
    }

    public function test_a_city_without_a_state_is_dropped_rather_than_rejected(): void
    {
        // Consistent with clearing the country: an orphaned child is discarded,
        // not turned into an error message about a field the cascade had
        // already emptied in front of the user.
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.buyers.store'), $this->payload([
                'country_id' => $this->countryId('GB'),
                'state_id'   => null,
                'city_id'    => $this->cityId('GB', 'England', 'London'),
            ]))
            ->assertSessionHasNoErrors();

        $buyer = Buyer::first();

        $this->assertNull($buyer->state_id);
        $this->assertNull($buyer->city_id);
        $this->assertSame($this->countryId('GB'), $buyer->country_id);
    }

    public function test_clearing_the_country_clears_the_state_and_city_rather_than_erroring(): void
    {
        $user  = $this->actingAsRole('Super Admin');
        $buyer = $this->createBuyer();

        $this->assertNotNull($buyer->city_id);

        $this->actingAs($user)->put(route('masters.buyers.update', $buyer), $this->payload([
            'country_id' => null,
        ]))->assertSessionHasNoErrors();

        $buyer->refresh();
        $this->assertNull($buyer->country_id);
        $this->assertNull($buyer->state_id);
        $this->assertNull($buyer->city_id);
    }

    public function test_the_edit_form_renders_the_saved_state_and_city_without_javascript(): void
    {
        // The options are server-rendered, so the saved value is visible before
        // the cascade runs — and readable to a user without JavaScript at all.
        $buyer = $this->createBuyer([
            'country_id' => $this->countryId('IN'),
            'state_id'   => $this->stateId('IN', 'Tamil Nadu'),
            'city_id'    => $this->cityId('IN', 'Tamil Nadu', 'Tirupur'),
        ]);

        $this->actingAs($this->actingAsRole('Super Admin'))
            ->get(route('masters.buyers.edit', $buyer))
            ->assertOk()
            ->assertSee('Tamil Nadu')
            ->assertSee('Tirupur')
            // A different country's states must not be in the initial markup.
            ->assertDontSee('Northern Ireland');
    }

    public function test_the_form_carries_the_cascade_wiring(): void
    {
        // The cascade is declared in markup and read by app.js. If these
        // attributes stop being emitted the dropdowns silently go static, which
        // no other assertion here would catch.
        $html = $this->actingAs($this->actingAsRole('Super Admin'))
            ->get(route('masters.buyers.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-cascade-parent="#country_id"', $html);
        $this->assertStringContainsString('data-cascade-parent="#state_id"', $html);
        $this->assertStringContainsString('data-cascade-child="#state_id"', $html);
        $this->assertStringContainsString('data-cascade-child="#city_id"', $html);
        $this->assertStringContainsString('data-cascade-key="country_id"', $html);
        $this->assertStringContainsString('data-cascade-key="state_id"', $html);
        $this->assertStringContainsString(route('masters.geo.states'), $html);
        $this->assertStringContainsString(route('masters.geo.cities'), $html);
    }

    public function test_the_create_form_defaults_country_to_india_and_starts_with_empty_city_list(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->get(route('masters.buyers.create'))
            ->assertOk()
            ->assertSee('India (IN)')
            ->assertSee('Tamil Nadu')
            ->assertDontSee('Tirupur');
    }

    /*
    |--------------------------------------------------------------------------
    | Col O — "only those which are a part of buyer side"
    |--------------------------------------------------------------------------
    */

    public function test_the_agent_dropdown_lists_only_buyer_side_agents(): void
    {
        Agent::create(['display_code' => 'AGB', 'name' => 'Buyer Side Agent', 'agent_type' => 'buyer']);
        Agent::create(['display_code' => 'AGS', 'name' => 'Supplier Side Agent', 'agent_type' => 'supplier']);

        $this->actingAs($this->actingAsRole('Super Admin'))
            ->get(route('masters.buyers.create'))
            ->assertOk()
            ->assertSee('Buyer Side Agent')
            ->assertDontSee('Supplier Side Agent');
    }

    public function test_a_supplier_side_agent_is_rejected_even_if_posted_directly(): void
    {
        // The filtered select is a UI convenience; the rule is what enforces it.
        $supplierSide = Agent::create(['display_code' => 'AGS', 'name' => 'Supplier Side Agent', 'agent_type' => 'supplier']);

        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.buyers.store'), $this->payload(['agent_id' => $supplierSide->id]))
            ->assertSessionHasErrors('agent_id');

        $this->assertSame(0, Buyer::count());
    }

    public function test_an_inactive_agent_is_not_offered(): void
    {
        Agent::create(['display_code' => 'AGX', 'name' => 'Retired Agent', 'status' => 'inactive', 'agent_type' => 'buyer']);

        $this->actingAs($this->actingAsRole('Super Admin'))
            ->get(route('masters.buyers.create'))
            ->assertOk()
            ->assertDontSee('Retired Agent');
    }

    /*
    |--------------------------------------------------------------------------
    | Col P — agent commission
    |--------------------------------------------------------------------------
    */

    public function test_a_commission_value_without_a_type_is_rejected(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.buyers.store'), $this->payload([
                'agent_commission_value' => '2.5',
                'agent_commission_type'  => null,
            ]))
            ->assertSessionHasErrors('agent_commission_type');
    }

    public function test_a_commission_type_without_a_value_is_blanked_rather_than_rejected(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.buyers.store'), $this->payload([
                'agent_commission_value' => null,
                'agent_commission_type'  => 'percent',
            ]))
            ->assertSessionHasNoErrors();

        // Leaving a stray type behind would make the label render a bare "%".
        $this->assertNull(Buyer::first()->agent_commission_type);
    }

    public function test_the_commission_label_renders_from_type_and_value(): void
    {
        $percent = $this->createBuyer([
            'agent_commission_type'  => 'percent',
            'agent_commission_value' => 2.5,
        ]);

        $this->assertSame('2.5%', $percent->agent_commission_label);

        $amount = $this->createBuyer([
            'display_code'           => 'BUY02',
            'agent_commission_type'  => 'amount',
            'agent_commission_value' => 1.5,
        ]);

        $this->assertSame('1.5 GBP', $amount->load('currency')->agent_commission_label);
    }

    /*
    |--------------------------------------------------------------------------
    | Col X — carton marking details
    |--------------------------------------------------------------------------
    */

    public function test_carton_markings_are_saved_in_order_with_blanks_dropped(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.buyers.store'), $this->payload([
                'carton_markings' => [
                    ['label' => 'BUYER NAME',  'value' => 'ABC CORP'],
                    ['label' => 'DESTINATION', 'value' => 'LONDON'],
                    ['label' => 'ORDER REF',   'value' => 'C/NO'],
                    ['label' => 'MADE IN',     'value' => ''],   // no value, kept — label is meaningful
                    ['label' => '',            'value' => ''],   // wholly empty, dropped
                ],
            ]))->assertRedirect();

        $lines = Buyer::first()->cartonMarkings;

        $this->assertCount(4, $lines);
        $this->assertSame([1, 2, 3, 4], $lines->pluck('line_no')->all());
        $this->assertSame(['BUYER NAME', 'DESTINATION', 'ORDER REF', 'MADE IN'], $lines->pluck('label')->all());
        $this->assertNull($lines->last()->value);

        // The sheet stars the first three lines.
        $this->assertSame([true, true, true, false], $lines->pluck('is_required')->all());
    }

    public function test_the_carton_preview_joins_only_filled_lines(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.buyers.store'), $this->payload([
                'carton_markings' => [
                    ['label' => 'BUYER NAME',  'value' => 'ABC CORP'],
                    ['label' => 'DESTINATION', 'value' => 'LONDON'],
                    ['label' => 'MADE IN',     'value' => ''],
                ],
            ]));

        $this->assertSame("ABC CORP\nLONDON", Buyer::first()->carton_marking_preview);
    }

    public function test_carton_markings_are_rewritten_on_update_not_appended(): void
    {
        $user = $this->actingAsRole('Super Admin');

        $this->actingAs($user)->post(route('masters.buyers.store'), $this->payload([
            'carton_markings' => [
                ['label' => 'BUYER NAME',  'value' => 'ABC CORP'],
                ['label' => 'DESTINATION', 'value' => 'LONDON'],
                ['label' => 'ORDER REF',   'value' => 'C/NO'],
            ],
        ]));

        $buyer = Buyer::first();

        $this->actingAs($user)->put(route('masters.buyers.update', $buyer), $this->payload([
            'carton_markings' => [
                ['label' => 'BUYER NAME',  'value' => 'XYZ CORP'],
                ['label' => 'DESTINATION', 'value' => 'MANCHESTER'],
            ],
        ]));

        $lines = $buyer->refresh()->cartonMarkings;

        $this->assertCount(2, $lines);
        $this->assertSame([1, 2], $lines->pluck('line_no')->all());
        $this->assertSame('XYZ CORP', $lines->first()->value);
    }

    public function test_carton_markings_are_removed_with_the_buyer(): void
    {
        $buyer = $this->createBuyer();
        $buyer->cartonMarkings()->create(['line_no' => 1, 'label' => 'BUYER NAME', 'value' => 'ABC']);

        $buyer->forceDelete();

        $this->assertDatabaseCount('buyer_carton_markings', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Col D — category link
    |--------------------------------------------------------------------------
    */

    public function test_a_category_in_use_by_a_buyer_cannot_be_deleted(): void
    {
        $category = $this->category('In use by a buyer', 'CAT901');
        $user     = $this->actingAsRole('Super Admin');

        $this->actingAs($user)->post(route('masters.buyers.store'), $this->payload([
            'category_ids' => [$category->id],
        ]));

        $this->actingAs($user)
            ->delete(route('masters.categories.destroy', $category))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($category);
    }

    public function test_an_unknown_category_id_is_rejected(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.buyers.store'), $this->payload(['category_ids' => [99999]]))
            ->assertSessionHasErrors('category_ids.0');
    }

    /*
    |--------------------------------------------------------------------------
    | Screens and filters
    |--------------------------------------------------------------------------
    */

    public function test_every_screen_renders(): void
    {
        $user  = $this->actingAsRole('Super Admin');
        $buyer = $this->createBuyer();
        $buyer->cartonMarkings()->create(['line_no' => 1, 'label' => 'BUYER NAME', 'value' => 'ABC CORP']);

        $this->actingAs($user)->get(route('masters.buyers.index'))->assertOk()->assertSee('ABC Fashion Ltd');
        $this->actingAs($user)->get(route('masters.buyers.create'))->assertOk();
        $this->actingAs($user)->get(route('masters.buyers.show', $buyer))->assertOk()->assertSee('ABC CORP');
        $this->actingAs($user)->get(route('masters.buyers.edit', $buyer))->assertOk()->assertSee('BUY01');
    }

    public function test_the_list_can_be_searched_and_filtered(): void
    {
        $shirts = $this->category('Shirts', 'CAT901');
        $user   = $this->actingAsRole('Super Admin');

        $one = $this->createBuyer([
            'company_name' => 'Cotton Traders Ltd',
            'city_id'      => $this->cityId('GB', 'England', 'Manchester'),
        ]);
        $one->categories()->attach($shirts);

        $this->createBuyer([
            'display_code' => 'BUY02',
            'company_name' => 'Denim World Inc',
            'status'       => 'inactive',
        ]);

        $this->actingAs($user)->get(route('masters.buyers.index', ['search' => 'Cotton']))
            ->assertSee('Cotton Traders Ltd')->assertDontSee('Denim World Inc');

        $this->actingAs($user)->get(route('masters.buyers.index', ['status' => 'inactive']))
            ->assertSee('Denim World Inc')->assertDontSee('Cotton Traders Ltd');

        $this->actingAs($user)->get(route('masters.buyers.index', ['category_id' => $shirts->id]))
            ->assertSee('Cotton Traders Ltd')->assertDontSee('Denim World Inc');

        // City moved to its own table; the same search box must still find it.
        $this->actingAs($user)->get(route('masters.buyers.index', ['search' => 'Manchester']))
            ->assertSee('Cotton Traders Ltd')->assertDontSee('Denim World Inc');
    }

    public function test_sorting_ignores_a_column_that_is_not_whitelisted(): void
    {
        $this->createBuyer();

        $this->actingAs($this->actingAsRole('Super Admin'))
            ->get(route('masters.buyers.index', ['sort' => 'created_by); drop table buyers; --']))
            ->assertOk();

        $this->assertSame(1, Buyer::count());
    }

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */

    public function test_a_role_without_buyer_view_is_denied(): void
    {
        // Quality Checker has product.view and supplier.view but no buyer.*.
        $this->actingAs($this->actingAsRole('Quality Checker'))
            ->get(route('masters.buyers.index'))
            ->assertForbidden();
    }

    public function test_a_view_only_role_cannot_create_or_delete(): void
    {
        // Packing has buyer.view only.
        $user  = $this->actingAsRole('Packing');
        $buyer = $this->createBuyer();

        $this->actingAs($user)->get(route('masters.buyers.index'))->assertOk();
        $this->actingAs($user)->get(route('masters.buyers.create'))->assertForbidden();
        $this->actingAs($user)->post(route('masters.buyers.store'), $this->payload())->assertForbidden();
        $this->actingAs($user)->get(route('masters.buyers.edit', $buyer))->assertForbidden();
        $this->actingAs($user)->delete(route('masters.buyers.destroy', $buyer))->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('masters.buyers.index'))->assertRedirect(route('login'));
    }
}
