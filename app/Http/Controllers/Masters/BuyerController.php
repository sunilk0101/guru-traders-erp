<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\StoreBuyerRequest;
use App\Http\Requests\Masters\UpdateBuyerRequest;
use App\Models\Agent;
use App\Models\Buyer;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Designation;
use App\Models\Incoterm;
use App\Models\PaymentTerm;
use App\Models\Port;
use App\Models\ShipmentMethod;
use App\Models\State;
use App\Services\Masters\BuyerService;
use App\Services\NumberSeriesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class BuyerController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly BuyerService $buyers,
        private readonly NumberSeriesService $numbers,
    ) {
    }

    /**
     * Per-action permissions live here rather than on the route, so a new
     * action cannot be added without also deciding what guards it.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:buyer.view', only: ['index', 'show']),
            new Middleware('permission:buyer.create', only: ['create', 'store']),
            new Middleware('permission:buyer.edit', only: ['edit', 'update', 'toggleStatus']),
            new Middleware('permission:buyer.delete', only: ['destroy']),
            // Reachable from either the create or the edit form.
            new Middleware('permission:buyer.create|buyer.edit', only: ['storePaymentTerm', 'storeDesignation', 'storeShipmentMethod']),
        ];
    }

    public function index(Request $request): View
    {
        $buyers = Buyer::query()
            ->with(['country:id,name,iso_code', 'city:id,name', 'agent:id,name,display_code'])
            ->withCount('categories')
            ->when(
                $request->filled('category_id'),
                fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereKey($request->integer('category_id')))
            )
            ->search($request->string('search')->toString())
            ->status($request->string('status')->toString())
            ->sort($request->string('sort')->toString(), $request->string('direction')->toString())
            ->paginate(15)
            ->withQueryString();

        return view('masters.buyers.index', [
            'buyers'     => $buyers,
            'categories' => Category::active()->orderBy('name')->pluck('name', 'id'),
            'filters'    => $request->only('search', 'status', 'category_id', 'sort', 'direction'),
        ]);
    }

    public function create(): View
    {
        // old() rather than null: a failed validation round-trip must come back
        // with the state and city lists still populated for the country the
        // user had picked, or their choices appear to have been thrown away.
        //
        // Change request #4 — "Default the Country to India". Falling back to
        // it here too, not just in the select's own default, so the State list
        // is pre-loaded for India rather than sitting empty until the user
        // touches the Country field.
        return view('masters.buyers.create', $this->formData(
            old('country_id') ? (int) old('country_id') : $this->indiaCountryId(),
            old('state_id') ? (int) old('state_id') : null,
        ) + [
            // Shown read-only so the user knows what they are about to get.
            // Not reserved — the real code is assigned at insert time.
            'nextCode' => $this->numbers->preview('buyer'),
        ]);
    }

    public function store(StoreBuyerRequest $request): RedirectResponse
    {
        $buyer = $this->buyers->create($request->validated());

        return redirect()
            ->route('masters.buyers.index')
            ->with('success', "Buyer {$buyer->display_code} created successfully.");
    }

    public function show(Buyer $buyer): View
    {
        return view('masters.buyers.show', [
            'buyer' => $buyer->load([
                'categories:id,name', 'cartonMarkings', 'country', 'state', 'city', 'port',
                'agent', 'contactDesignation', 'paymentTerm', 'incoterm', 'currency',
                'currencies', 'incoterms', 'shipmentMethod', 'shipmentMethods', 'contacts.designation',
                'creator', 'updater',
            ]),
        ]);
    }

    public function edit(Buyer $buyer): View
    {
        return view('masters.buyers.edit', $this->formData(
            old('country_id', $buyer->country_id) ? (int) old('country_id', $buyer->country_id) : null,
            old('state_id', $buyer->state_id) ? (int) old('state_id', $buyer->state_id) : null,
        ) + [
            'buyer' => $buyer->load(
                'categories:id', 'cartonMarkings', 'contacts',
                'currencies:id', 'incoterms:id', 'shipmentMethods:id',
            ),
        ]);
    }

    public function update(UpdateBuyerRequest $request, Buyer $buyer): RedirectResponse
    {
        $this->buyers->update($buyer, $request->validated());

        return redirect()
            ->route('masters.buyers.index')
            ->with('success', "Buyer {$buyer->display_code} updated successfully.");
    }

    public function destroy(Buyer $buyer): RedirectResponse
    {
        $check = $this->buyers->canDelete($buyer);

        if (! $check['allowed']) {
            return back()->with('error', $check['reason']);
        }

        $buyer->delete();

        return redirect()
            ->route('masters.buyers.index')
            ->with('success', "Buyer {$buyer->display_code} deleted successfully.");
    }

    public function toggleStatus(Buyer $buyer): RedirectResponse
    {
        $buyer->update([
            'status' => $buyer->status === 'active' ? 'inactive' : 'active',
        ]);

        return back()->with('success', "Buyer {$buyer->display_code} marked {$buyer->status}.");
    }

    /**
     * Sheet col Q: "drop down menu, add more in the future". Lets a new term
     * be typed straight into the Payment Terms field instead of leaving the
     * form to add one elsewhere — there is no separate Payment Terms screen.
     *
     * `firstOrCreate` on name: two users typing the same new term at once end
     * up pointing at one row rather than a duplicate-name error.
     */
    public function storePaymentTerm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $term = PaymentTerm::firstOrCreate(
            ['name' => trim($data['name'])],
            ['applies_to' => 'buyer', 'status' => 'active']
        );

        return response()->json(['id' => $term->id, 'name' => $term->name]);
    }

    /**
     * Quick-add for the contact's Designation field, same shape as
     * storePaymentTerm(). `firstOrCreate` on name: two users typing
     * "Merchandiser" at once end up pointing at one row, not a duplicate.
     */
    public function storeDesignation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $designation = Designation::firstOrCreate(
            ['name' => trim($data['name'])],
            ['status' => 'active']
        );

        return response()->json(['id' => $designation->id, 'name' => $designation->name]);
    }

    /**
     * Quick-add for Shipment Method — same shape as storePaymentTerm().
     * Returns the lookup id so Default / Accepted selects (keyed by id) can
     * pick the new option immediately.
     */
    public function storeShipmentMethod(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $method = ShipmentMethod::firstOrCreate(
            ['name' => trim($data['name'])],
            ['status' => 'active']
        );

        return response()->json(['id' => $method->id, 'name' => $method->name]);
    }

    /**
     * Dropdown sources shared by the create and edit forms.
     *
     * Only active rows: a retired port or incoterm must not be selectable on a
     * new buyer, while buyers already pointing at it keep rendering.
     *
     * State and city are the two dependent lists. They are rendered server-side
     * for whatever country and state are already chosen, so an edit form is
     * correct before any JavaScript runs and a user without it can still read
     * the saved value. GeoController takes over once a parent is changed.
     *
     * @return array<string, mixed>
     */
    private function formData(?int $countryId = null, ?int $stateId = null): array
    {
        return [
            'categories' => Category::active()->orderBy('name')->pluck('name', 'id'),

            /*
             * Col O: "only those which are a part of buyer side selected in
             * agent master should show here". The same filter is re-applied as
             * a validation rule in BuyerRequest — this select narrows the list,
             * it does not enforce it.
             */
            'agents' => Agent::active()->ofType('buyer')->orderBy('name')->get()->pluck('label', 'id'),

            /*
             * Change request #9 — "selecting an Agent should auto-fill the
             * Commission value onto the record". The agent's first commission
             * entry is the one the costing panel treats as the one that
             * applies (see Agent::commissions()) — mapped onto this form's
             * type/value pair the same way agent_commission_label() reads it
             * back. Filled once on selection; the fields stay editable
             * afterwards, same as every other auto-filled field on this form.
             */
            'agentCommissions' => Agent::active()->ofType('buyer')
                ->with('commissions')
                ->get()
                ->mapWithKeys(function (Agent $agent) {
                    $first = $agent->commissions->first();

                    return [$agent->id => $first ? [
                        'type'  => $first->commission_type === 'percent' ? 'percent' : 'amount',
                        'value' => (float) $first->amount,
                    ] : null];
                })
                ->all(),

            'countries'       => Country::active()->orderBy('name')->get()->pluck('label', 'id'),

            // Change request #4 — "Default the Country to India".
            'indiaCountryId'  => $this->indiaCountryId(),

            'states' => $countryId
                ? State::active()->where('country_id', $countryId)->orderBy('name')->pluck('name', 'id')
                : collect(),

            'cities' => $stateId
                ? City::active()->where('state_id', $stateId)->orderBy('name')->pluck('name', 'id')
                : collect(),

            'ports'           => Port::active()->with('country:id,iso_code')->orderBy('name')->get()->pluck('label', 'id'),
            'designations'    => Designation::active()->orderBy('name')->pluck('name', 'id'),
            'paymentTerms'    => PaymentTerm::active()->forSide('buyer')->orderBy('name')->pluck('name', 'id'),
            'incoterms'       => Incoterm::active()->orderBy('code')->get()->pluck('label', 'id'),
            'currencies'      => Currency::active()->orderBy('iso_code')->get()->pluck('label', 'id'),
            'shipmentMethods' => ShipmentMethod::active()->orderBy('name')->pluck('name', 'id'),

            /*
             * Which payment terms open the advance / at-sight boxes. Sent to the
             * form as ids so the toggle reads the same `has_split` column
             * BuyerRequest validates against — a JS list of term names would be
             * a second answer to the same question, and the two would drift the
             * first time a term is renamed.
             */
            'splitTermIds' => PaymentTerm::active()->forSide('buyer')
                ->where('has_split', true)->pluck('id')->all(),
        ];
    }

    /**
     * Change request #4 — "Default the Country to India". Read by iso code
     * rather than name, same lookup GeoSeeder uses to seed it.
     */
    private function indiaCountryId(): ?int
    {
        return Country::where('iso_code', 'IN')->value('id');
    }
}
