<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\StoreBomRequest;
use App\Http\Requests\Masters\UpdateBomRequest;
use App\Models\Bom;
use App\Models\TrimAccessory;
use App\Services\Masters\BomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class BomController extends Controller implements HasMiddleware
{
    public function __construct(private readonly BomService $service)
    {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:bom.view', only: ['index', 'show']),
            new Middleware('permission:bom.create', only: ['create', 'store']),
            new Middleware('permission:bom.edit', only: ['edit', 'update', 'toggleStatus']),
            new Middleware('permission:bom.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $boms = Bom::query()
            ->with('lines')
            ->withCount('lines')
            ->search($request->string('search')->toString())
            ->status($request->string('status')->toString())
            ->sort($request->string('sort')->toString(), $request->string('direction')->toString())
            ->paginate(15)
            ->withQueryString();

        return view('masters.boms.index', [
            'boms'    => $boms,
            'filters' => $request->only('search', 'status', 'sort', 'direction'),
        ]);
    }

    public function create(): View
    {
        return view('masters.boms.create', $this->formExtras());
    }

    public function store(StoreBomRequest $request): RedirectResponse
    {
        $bom = $this->service->create($request->validated());

        return redirect()
            ->route('masters.boms.index')
            ->with('success', "BOM \"{$bom->name}\" created successfully.");
    }

    public function show(Bom $bom): View
    {
        return view('masters.boms.show', [
            'bom' => $bom->load(['lines', 'creator', 'updater']),
        ]);
    }

    public function edit(Bom $bom): View
    {
        return view('masters.boms.edit', $this->formExtras() + [
            'bom' => $bom->load('lines'),
        ]);
    }

    public function update(UpdateBomRequest $request, Bom $bom): RedirectResponse
    {
        $this->service->update($bom, $request->validated());

        return redirect()
            ->route('masters.boms.index')
            ->with('success', "BOM \"{$bom->name}\" updated successfully.");
    }

    public function destroy(Bom $bom): RedirectResponse
    {
        $name = $bom->name;
        $this->service->delete($bom);

        return redirect()
            ->route('masters.boms.index')
            ->with('success', "BOM \"{$name}\" deleted successfully.");
    }

    public function toggleStatus(Bom $bom): RedirectResponse
    {
        $bom->update([
            'status' => $bom->status === 'active' ? 'inactive' : 'active',
        ]);

        return back()->with('success', "BOM \"{$bom->name}\" marked {$bom->status}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formExtras(): array
    {
        return [
            'trimThumbs' => TrimAccessory::query()
                ->whereNotNull('image_path')
                ->get(['name', 'image_path'])
                ->mapWithKeys(fn (TrimAccessory $row) => [strtolower($row->name) => $row->image_url])
                ->filter(),
        ];
    }
}
