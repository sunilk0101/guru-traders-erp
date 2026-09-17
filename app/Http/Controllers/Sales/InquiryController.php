<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreInquiryRequest;
use App\Http\Requests\Sales\UpdateInquiryRequest;
use App\Models\Agent;
use App\Models\Buyer;
use App\Models\Category;
use App\Models\Currency;
use App\Models\DocumentFormat;
use App\Models\FobValue;
use App\Models\Inquiry;
use App\Models\InquiryFollowUp;
use App\Models\InquirySource;
use App\Models\Markup;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\TrimAccessory;
use App\Exports\InquiryExport;
use App\Services\NumberSeriesService;
use App\Services\Sales\InquiryService;
use App\Services\Export\OcrOrderContextBuilder;
use App\Support\FinancialYear;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class InquiryController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly InquiryService $inquiries,
        private readonly NumberSeriesService $numbers,
        private readonly OcrOrderContextBuilder $orderContext,
    ) {
    }

    /**
     * Per-action permissions live here rather than on the route, so a new
     * action cannot be added without also deciding what guards it.
     *
     * products() / suppliers() are deliberately absent — cascade endpoints
     * for a dropdown, guarded by `auth` alone at the route-group level, same
     * call the codebase already makes for GeoController and
     * SupplierController::agents().
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inquiry.view', only: ['index', 'show', 'pdf', 'xlsx', 'followUps']),
            new Middleware('permission:inquiry.create', only: ['create', 'store']),
            new Middleware('permission:inquiry.edit', only: ['edit', 'update']),
            new Middleware('permission:inquiry.delete', only: ['destroy']),
            // Reachable from either the create or the edit form.
            new Middleware('permission:inquiry.create|inquiry.edit', only: ['storeSource']),
        ];
    }

    public function index(Request $request): View
    {
        $inquiries = Inquiry::query()
            ->with(['buyer:id,company_name,display_code', 'category:id,name', 'format:id,name', 'items'])
            ->when(
                $request->filled('buyer_id'),
                fn ($q) => $q->where('buyer_id', $request->integer('buyer_id'))
            )
            ->search($request->string('search')->toString())
            ->status($request->string('status')->toString())
            ->sort($request->string('sort')->toString(), $request->string('direction')->toString())
            ->paginate(15)
            ->withQueryString();

        // Counted across every inquiry, not just the current filtered page —
        // the strip answers "how is the pipeline doing overall", the table
        // below it answers "show me these ones".
        $byStatus = Inquiry::query()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');

        $stats = [
            'total'         => (int) $byStatus->sum(),
            'draft'         => (int) ($byStatus['draft'] ?? 0),
            'price_working' => (int) ($byStatus['price_working'] ?? 0),
            'quote_sent'    => (int) ($byStatus['quote_sent'] ?? 0),
            // A confirmed inquiry that has since converted to an OC is still
            // "confirmed" from the pipeline's point of view — converting is
            // what confirmed inquiries are for, not a different outcome.
            'confirmed'     => (int) (($byStatus['confirmed'] ?? 0) + ($byStatus['converted_to_oc'] ?? 0)),
        ];

        return view('sales.inquiries.index', [
            'inquiries' => $inquiries,
            'buyers'    => Buyer::active()->orderBy('company_name')->get()->pluck('label', 'id'),
            'statuses'  => Inquiry::STATUSES,
            'filters'   => $request->only('search', 'status', 'buyer_id', 'sort', 'direction'),
            'stats'     => $stats,
        ]);
    }

    /**
     * Every dated follow-up comment across every inquiry, newest first, with
     * an optional Category and Buyer filter and a date range. The "date
     * wise" grouping is done in PHP on the already-paginated page (not with
     * a DB GROUP BY), so a page still shows exactly $perPage individual
     * entries and pagination stays simple - the grouping only changes how
     * those entries are broken into date headings in the view.
     */
    public function followUps(Request $request): View
    {
        $perPage = 30;

        $followUps = InquiryFollowUp::query()
            ->with([
                'inquiry:id,inquiry_no,buyer_id,category_id',
                'inquiry.buyer:id,company_name,display_code',
                'inquiry.category:id,name',
                'creator:id,name',
            ])
            ->when(
                $request->filled('category_id'),
                fn ($q) => $q->whereHas(
                    'inquiry',
                    fn ($iq) => $iq->where('category_id', $request->integer('category_id'))
                )
            )
            ->when(
                $request->filled('buyer_id'),
                fn ($q) => $q->whereHas(
                    'inquiry',
                    fn ($iq) => $iq->where('buyer_id', $request->integer('buyer_id'))
                )
            )
            ->when(
                $request->filled('from'),
                fn ($q) => $q->whereDate('follow_up_date', '>=', $request->date('from'))
            )
            ->when(
                $request->filled('to'),
                fn ($q) => $q->whereDate('follow_up_date', '<=', $request->date('to'))
            )
            ->orderByDesc('follow_up_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $grouped = $followUps->getCollection()->groupBy(
            fn (InquiryFollowUp $followUp) => $followUp->follow_up_date->format('Y-m-d')
        );

        return view('sales.inquiries.follow-ups', [
            'followUps'  => $followUps,
            'grouped'    => $grouped,
            'categories' => Category::active()->orderBy('name')->get()->pluck('name', 'id'),
            'buyers'     => Buyer::active()->orderBy('company_name')->get()->pluck('label', 'id'),
            'filters'    => $request->only('category_id', 'buyer_id', 'from', 'to'),
        ]);
    }

    public function create(): View
    {
        return view('sales.inquiries.create', $this->formData());
    }

    public function store(StoreInquiryRequest $request): RedirectResponse
    {
        $inquiry = $this->inquiries->create($request->validated());

        return redirect()
            ->route('sales.inquiries.index')
            ->with('success', "Inquiry \"{$inquiry->inquiry_no}\" created successfully.");
    }

    public function show(Inquiry $inquiry): View
    {
        $inquiry->load([
            'buyer', 'category', 'format', 'agent', 'currency', 'source',
            'items' => fn ($q) => $q->with(['product', 'supplier', 'fobValue', 'category', 'format', 'colours.sizes', 'bomLines']),
            'followUps.creator',
            'creator', 'updater',
        ]);

        return view('sales.inquiries.show', [
            'inquiry' => $inquiry,
            'orderContext' => $this->orderContext->buildFromInquiry($inquiry),
        ]);
    }

    public function edit(Inquiry $inquiry): View
    {
        $inquiry->load([
            'items' => fn ($q) => $q->with(['colours.sizes', 'bomLines']),
            'followUps',
        ]);

        return view('sales.inquiries.edit', $this->formData() + ['inquiry' => $inquiry]);
    }

    public function update(UpdateInquiryRequest $request, Inquiry $inquiry): RedirectResponse
    {
        $this->inquiries->update($inquiry, $request->validated());

        return redirect()
            ->route('sales.inquiries.index')
            ->with('success', "Inquiry \"{$inquiry->inquiry_no}\" updated successfully.");
    }

    public function pdf(Inquiry $inquiry): Response
    {
        $pdf = Pdf::loadView('sales.inquiries.pdf', ['inquiry' => $this->loadForDocument($inquiry)]);

        return $pdf->download($this->documentFilename($inquiry, 'pdf'));
    }

    public function xlsx(Inquiry $inquiry)
    {
        return Excel::download(
            new InquiryExport($this->loadForDocument($inquiry)),
            $this->documentFilename($inquiry, 'xlsx')
        );
    }

    public function destroy(Inquiry $inquiry): RedirectResponse
    {
        $inquiry->delete();

        return redirect()
            ->route('sales.inquiries.index')
            ->with('success', "Inquiry \"{$inquiry->inquiry_no}\" deleted successfully.");
    }

    /**
     * Quick-add for the Source field. Same shape as
     * BuyerController::storeDesignation() — typing a name not already in the
     * list adds it, rather than sending the user off to a separate screen.
     */
    public function storeSource(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $source = InquirySource::firstOrCreate(
            ['name' => trim($data['name'])],
            ['status' => 'active']
        );

        return response()->json(['id' => $source->id, 'name' => $source->name]);
    }

    /**
     * Product options for an item row, narrowed to the inquiry's category —
     * same cascade shape as GeoController::cities().
     */
    public function products(Request $request): JsonResponse
    {
        // unit_export / unit_po travel with each option so the Inquiry (and OC)
        // item row can default Unit from Product Master — Unit Master was
        // cancelled, so Product is the only authoritative source beyond the
        // Order Format's own unit chips.
        //
        // Ordered and labelled by Item Group Code first, not Product Name —
        // "the reason for the numbers is so it can be easily found. need the
        // number series to be first for keyword search" (09-Sep call). Some
        // products were imported with the code already typed into the name
        // (e.g. name "COTTON LADIES KURTI FREE (461AA)", code "461AA"), so the
        // trailing "(CODE)" is stripped from the name before the code is
        // prepended — otherwise it would show up twice.
        $products = Product::active()
            ->with(['bomItems', 'incentives'])
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->orderBy('item_group_code')
            ->get(['id', 'name', 'item_group_code', 'image_path', 'unit_po', 'unit_export'])
            ->map(function (Product $product) {
                $name = trim((string) preg_replace(
                    '/\s*\('.preg_quote((string) $product->item_group_code, '/').'\)\s*$/i',
                    '',
                    $product->name
                ));

                return [
                    'id'          => $product->id,
                    'text'        => $product->item_group_code ? "{$product->item_group_code} — {$name}" : $name,
                    'image_url'   => $product->image_url,
                    'unit_po'     => $product->unit_po,
                    'unit_export' => $product->unit_export,
                    'bom'         => $product->bomItems->map(fn ($line) => [
                        'component_name' => $line->component_name,
                        'qty'            => (float) $line->qty,
                        'unit'           => $line->unit,
                        'is_custom'      => (bool) $line->is_custom,
                        'remarks'        => $line->remarks,
                    ])->values(),
                    // Rate % × FOB vs Cap × PCS → lower (same as ProductIncentive::claimAmount).
                    'incentives'  => $product->incentives->map(fn ($row) => [
                        'scheme'      => $row->scheme,
                        'label'       => $row->schemeLabel(),
                        'percent_1'   => (float) ($row->percent_1 ?? 0),
                        'percent_2'   => (float) ($row->percent_2 ?? 0),
                        'cap_value'   => $row->cap_value !== null ? (float) $row->cap_value : null,
                        'cap_value_2' => $row->cap_value_2 !== null ? (float) $row->cap_value_2 : null,
                    ])->values(),
                ];
            });

        return response()->json($products);
    }

    /**
     * Supplier options for an item row, narrowed the same way as products —
     * Category::suppliers() is the same pivot the Supplier master itself uses.
     */
    public function suppliers(Request $request): JsonResponse
    {
        $suppliers = Supplier::active()
            ->ofParty('supplier')
            ->when(
                $request->filled('category_id'),
                fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereKey($request->integer('category_id')))
            )
            ->orderBy('company_name')
            ->get()
            // display_code travels with each option so the item row can seed
            // Design no / name with it the moment a supplier is picked —
            // "the code should come immediately in the design number once I
            // choose the supplier ... AJC- (here I'll type the design number)".
            ->map(fn (Supplier $supplier) => [
                'id'   => $supplier->id,
                'text' => $supplier->label,
                'code' => $supplier->display_code,
            ]);

        return response()->json($suppliers);
    }

    /**
     * Returns only the FOB unit figure for a cost — Markup % never leaves the
     * server (staff must not see margins on the Inquiry screen).
     */
    public function quoteFob(Request $request): JsonResponse
    {
        $finalCost = (float) $request->input('final_cost', 0);
        $buyerId = $request->integer('buyer_id');
        $supplierId = $request->integer('supplier_id');
        $exchangeRate = (float) $request->input('exchange_rate', 0);
        $currencyId = $request->integer('currency_id');

        if ($finalCost <= 0) {
            return response()->json(['fob_unit' => 0]);
        }

        $fobInr = $finalCost;
        if ($buyerId && $supplierId) {
            $markup = Markup::query()
                ->where('buyer_id', $buyerId)
                ->where('supplier_id', $supplierId)
                ->where('status', 'active')
                ->first();
            if ($markup) {
                $fobInr = $markup->clientPrice($finalCost);
            }
        }

        $iso = $currencyId
            ? Currency::query()->whereKey($currencyId)->value('iso_code')
            : 'INR';

        if ($exchangeRate > 0 && $iso && strtoupper((string) $iso) !== 'INR') {
            return response()->json(['fob_unit' => round($fobInr / $exchangeRate, 4)]);
        }

        return response()->json(['fob_unit' => round($fobInr, 4)]);
    }

    /**
     * Everything the PDF and Excel documents read: the format's own column
     * list (for which columns to draw and the Size sub-column tags), and each
     * item's colour/size breakdown.
     */
    private function loadForDocument(Inquiry $inquiry): Inquiry
    {
        return $inquiry->load([
            'buyer', 'category', 'format.columns', 'format.images', 'currency', 'source',
            'items' => fn ($q) => $q->with(['product', 'supplier', 'colours.sizes']),
        ]);
    }

    /**
     * inquiry_no reads like "INQ/2026-27/001" — readable in the UI, unsafe as
     * a filename since a slash there means "directory", not "dash".
     */
    private function documentFilename(Inquiry $inquiry, string $extension): string
    {
        return str_replace('/', '-', $inquiry->inquiry_no).'.'.$extension;
    }

    /**
     * Dropdown sources shared by the create and edit forms, plus everything
     * the JS cascades need pre-embedded as JSON: which categories belong to
     * which buyer, which formats belong to which category, and each buyer's
     * own agent/commission/currency to snapshot in when picked.
     *
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        $financialYear = FinancialYear::current();

        return [
            'buyers' => Buyer::active()->orderBy('company_name')->get([
                'id', 'company_name', 'display_code', 'agent_id',
                'agent_commission_type', 'agent_commission_value', 'currency_id',
            ])->load('categories:id'),

            'categories' => Category::active()->orderBy('name')->pluck('name', 'id'),

            // Buyer-side agents, same filter BuyerController itself applies —
            // the select narrows the list, StoreInquiryRequest does not
            // re-enforce the side, same call already made there.
            'agents' => Agent::active()->ofType('buyer')->orderBy('name')->get()->pluck('label', 'id'),

            // Commission on Inquiry is read-only from Agent Master (first
            // commission row). Map Agent's fixed → Inquiry's flat.
            'agentCommissions' => Agent::active()->ofType('buyer')
                ->with('commissions')
                ->get()
                ->mapWithKeys(function (Agent $agent) {
                    $first = $agent->commissions->first();

                    return [$agent->id => $first ? [
                        'type'  => $first->commission_type === 'percent' ? 'percent' : 'flat',
                        'value' => (float) $first->amount,
                    ] : null];
                })
                ->all(),

            'formats' => DocumentFormat::active()->with(['units', 'columns', 'categories:id', 'images'])
                ->orderBy('name')->get(),

            'fobValues'  => FobValue::active()->orderBy('name')->pluck('name', 'id'),
            'currencies' => Currency::active()->orderBy('iso_code')->get()->pluck('label', 'id'),
            'defaultBomLines' => config('inquiry_bom.default_lines', []),
            'bomTemplates' => collect(config('inquiry_bom.templates', []))->map(function (array $template, string $key) {
                $lines = $template['lines'] ?? [];
                $total = round(collect($lines)->sum(
                    fn ($line) => (float) ($line['qty'] ?? 0) * (float) ($line['rate'] ?? 0)
                ), 2);

                return [
                    'key'   => $key,
                    'name'  => $template['name'] ?? $key,
                    'total' => $total,
                    'lines' => $lines,
                ];
            })->values(),

            // "I need a small image against every line which will be added
            // while making the bom cost ... a separate photo per trim type"
            // (16-Sep call). name => photo URL, keyed lowercase so the BOM
            // trims panel can match a typed line name case-insensitively.
            'trimAccessoriesJs' => TrimAccessory::active()->get(['name', 'image_path'])
                ->mapWithKeys(fn (TrimAccessory $row) => [strtolower($row->name) => $row->image_url])
                ->filter(),

            'statuses' => Inquiry::STATUSES,
            // Change request #8 — quick-add lookup, replacing the fixed list.
            'sources'  => InquirySource::active()->orderBy('name')->pluck('name', 'id'),

            'financialYear'  => $financialYear,
            'numberPreview'  => $this->numbers->preview('inquiry', $financialYear) ?? "INQ/{$financialYear}/001",
        ];
    }
}
