<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Feeds the cascading Country -> State -> City dropdowns on the Buyer form.
 *
 * Reference data only — no per-module permission beyond the `auth` middleware
 * the route group already applies. A list of Indian states is not information
 * a logged-in user could be denied; gating it behind buyer.view would also
 * break the moment the Supplier and Agent forms reuse these endpoints.
 *
 * The initial options are rendered server-side by BuyerController, so an edit
 * form shows the right state and city before any JavaScript runs. These
 * endpoints are only hit when the user actually changes a parent.
 *
 * Many ISO countries ship without seeded divisions. Store endpoints let the
 * user type a missing state/city so export buyers outside the seed set are
 * not blocked; firstOrCreate keeps duplicates from piling up.
 */
class GeoController extends Controller
{
    /**
     * States belonging to one country.
     */
    public function states(Request $request): JsonResponse
    {
        // No country means no states — not every state in the database.
        if (! $request->filled('country_id')) {
            return response()->json([]);
        }

        return response()->json(
            State::query()
                ->active()
                ->where('country_id', $request->integer('country_id'))
                ->orderBy('name')
                ->get(['id', 'name'])
        );
    }

    /**
     * Cities belonging to one state.
     */
    public function cities(Request $request): JsonResponse
    {
        if (! $request->filled('state_id')) {
            return response()->json([]);
        }

        $stateId = $request->integer('state_id');

        $cities = City::query()
            ->active()
            ->where('state_id', $stateId)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Some CSC "states" are settlements with no child cities. Offer the
        // place name itself so the City dropdown is never an empty dead-end.
        if ($cities->isEmpty()) {
            $state = State::query()->find($stateId);
            if ($state) {
                $city = City::query()->firstOrCreate(
                    ['state_id' => $state->id, 'name' => $state->name],
                    ['status' => 'active']
                );
                $cities = collect([['id' => $city->id, 'name' => $city->name]]);
            }
        }

        return response()->json($cities);
    }

    /**
     * Create (or reuse) a state under the selected country.
     */
    public function storeState(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
            'name'       => ['required', 'string', 'max:120'],
        ]);

        $name = trim($data['name']);

        $state = State::query()->firstOrCreate(
            [
                'country_id' => (int) $data['country_id'],
                'name'       => $name,
            ],
            ['status' => 'active']
        );

        return response()->json([
            'id'   => $state->id,
            'name' => $state->name,
        ]);
    }

    /**
     * Create (or reuse) a city under the selected state.
     */
    public function storeCity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'state_id' => ['required', 'integer', Rule::exists('states', 'id')],
            'name'     => ['required', 'string', 'max:120'],
        ]);

        $name = trim($data['name']);

        $city = City::query()->firstOrCreate(
            [
                'state_id' => (int) $data['state_id'],
                'name'     => $name,
            ],
            ['status' => 'active']
        );

        return response()->json([
            'id'   => $city->id,
            'name' => $city->name,
        ]);
    }
}
