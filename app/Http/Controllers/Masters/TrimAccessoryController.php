<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\StoreTrimAccessoryRequest;
use App\Http\Requests\Masters\UpdateTrimAccessoryRequest;
use App\Models\TrimAccessory;
use App\Services\Masters\TrimAccessoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class TrimAccessoryController extends Controller implements HasMiddleware
{
    public function __construct(private readonly TrimAccessoryService $service)
    {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:trim-accessory.view', only: ['index', 'show']),
            new Middleware('permission:trim-accessory.create', only: ['create', 'store']),
            new Middleware('permission:trim-accessory.edit', only: ['edit', 'update', 'toggleStatus']),
            new Middleware('permission:trim-accessory.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $trimAccessories = TrimAccessory::query()
            ->search($request->string('search')->toString())
            ->status($request->string('status')->toString())
            ->sort($request->string('sort')->toString(), $request->string('direction')->toString())
            ->paginate(15)
            ->withQueryString();

        return view('masters.trim-accessories.index', [
            'trimAccessories' => $trimAccessories,
            'filters'         => $request->only('search', 'status', 'sort', 'direction'),
        ]);
    }

    public function create(): View
    {
        return view('masters.trim-accessories.create');
    }

    public function store(StoreTrimAccessoryRequest $request): RedirectResponse
    {
        $trimAccessory = $this->service->create($request->validated());

        return redirect()
            ->route('masters.trim-accessories.index')
            ->with('success', "Trim / Accessory \"{$trimAccessory->name}\" created successfully.");
    }

    public function show(TrimAccessory $trimAccessory): View
    {
        return view('masters.trim-accessories.show', [
            'trimAccessory' => $trimAccessory->load('creator', 'updater'),
        ]);
    }

    public function edit(TrimAccessory $trimAccessory): View
    {
        return view('masters.trim-accessories.edit', [
            'trimAccessory' => $trimAccessory,
        ]);
    }

    public function update(UpdateTrimAccessoryRequest $request, TrimAccessory $trimAccessory): RedirectResponse
    {
        $this->service->update($trimAccessory, $request->validated());

        return redirect()
            ->route('masters.trim-accessories.index')
            ->with('success', "Trim / Accessory \"{$trimAccessory->name}\" updated successfully.");
    }

    public function destroy(TrimAccessory $trimAccessory): RedirectResponse
    {
        $trimAccessory->delete();

        return redirect()
            ->route('masters.trim-accessories.index')
            ->with('success', "Trim / Accessory \"{$trimAccessory->name}\" deleted successfully.");
    }

    public function toggleStatus(TrimAccessory $trimAccessory): RedirectResponse
    {
        $trimAccessory->update([
            'status' => $trimAccessory->status === 'active' ? 'inactive' : 'active',
        ]);

        return back()->with('success', "Trim / Accessory \"{$trimAccessory->name}\" marked {$trimAccessory->status}.");
    }
}
