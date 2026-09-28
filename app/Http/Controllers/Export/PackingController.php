<?php

namespace App\Http\Controllers\Export;

use App\Exports\SupplierPackingListTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\ExportDocument;
use App\Models\Supplier;
use App\Services\Export\GeminiDocumentExtractor;
use App\Services\Export\SupplierPackingListImporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class PackingController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly SupplierPackingListImporter $importer,
        private readonly GeminiDocumentExtractor $ocr,
    ) {
    }

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:packing.view', only: [
                'index', 'show', 'templateExcel', 'templatePdf',
                'templateExcelForDocument', 'templatePdfForDocument',
            ]),
            new Middleware('permission:export-document.edit', only: ['importPreview', 'importApply']),
        ];
    }

    public function index(Request $request): View
    {
        $documents = ExportDocument::query()
            ->with([
                'buyer:id,company_name,display_code',
                'cartons.lines',
                'checklist' => fn ($q) => $q->with('type:id,code,variant_labels'),
            ])
            ->when(
                $request->filled('search'),
                fn ($q) => $q->search($request->string('search')->toString())
            )
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('export.packing.index', compact('documents'));
    }

    public function show(ExportDocument $document): View
    {
        $document->load([
            'buyer:id,company_name,display_code',
            'orderConfirmation.items.supplier:id,company_name,display_code',
            'cartons.lines',
            'checklist' => fn ($q) => $q->with('type:id,code,name,variant_labels'),
        ]);

        $packingRows = $document->checklist
            ->filter(fn ($row) => $row->type?->code === 'packing_list')
            ->values();

        $suppliers = $document->orderConfirmation?->items
            ->pluck('supplier')
            ->filter()
            ->unique('id')
            ->sortBy('company_name')
            ->values()
            ?? collect();

        if ($suppliers->isEmpty()) {
            $suppliers = Supplier::active()->ofParty('supplier')->orderBy('company_name')->limit(200)->get();
        }

        return view('export.packing.show', [
            'document'      => $document,
            'packingRows'   => $packingRows,
            'suppliers'     => $suppliers,
            'ocrConfigured' => $this->ocr->isConfigured(),
        ]);
    }

    public function templateExcel(): BinaryFileResponse
    {
        return Excel::download(
            new SupplierPackingListTemplateExport,
            'supplier-packing-list-template.xlsx'
        );
    }

    public function templatePdf(): Response
    {
        $pdf = Pdf::loadView('export.packing.supplier-template-pdf', ['document' => null]);

        return $pdf->download('supplier-packing-list-form.pdf');
    }

    public function templateExcelForDocument(ExportDocument $document): BinaryFileResponse
    {
        $document->loadMissing([
            'items.product',
            'items.sourceItem.supplier:id,company_name,display_code',
        ]);

        $seed = [];
        $ci = 1;
        foreach ($document->items as $item) {
            $desc = $item->description
                ?: ($item->product?->name ?: ($item->design_no ?: 'Item '.$ci));
            $seed[] = [
                (string) $ci,
                '',
                '',
                '',
                $desc,
                $item->unit ?: 'PCS',
                $item->qty ?: '',
                '',
                $item->sourceItem?->supplier?->display_code ?: '',
            ];
            $ci++;
        }

        $name = 'packing-list-'.str_replace(['/', '\\'], '-', $document->doc_num).'.xlsx';

        return Excel::download(new SupplierPackingListTemplateExport($seed), $name);
    }

    public function templatePdfForDocument(ExportDocument $document): Response
    {
        $pdf = Pdf::loadView('export.packing.supplier-template-pdf', [
            'document' => $document->loadMissing('buyer'),
        ]);

        $name = 'packing-list-form-'.str_replace(['/', '\\'], '-', $document->doc_num).'.pdf';

        return $pdf->download($name);
    }

    public function importPreview(Request $request, ExportDocument $document): JsonResponse
    {
        $data = $request->validate([
            'file'        => ['required', 'file', 'max:12288'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        $mime = $file->getMimeType() ?: '';

        try {
            if (in_array($ext, ['xlsx', 'xls', 'csv'], true)
                || str_contains($mime, 'spreadsheet')
                || str_contains($mime, 'excel')
                || $mime === 'text/csv') {
                $parsed = $this->importer->fromExcel($file);
            } elseif (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif'], true)
                || str_starts_with($mime, 'image/')
                || $mime === 'application/pdf') {
                if (! $this->ocr->isConfigured()) {
                    return response()->json([
                        'message' => 'PDF/image OCR needs GEMINI_API_KEY on the server. Use the Excel template for now (works without OCR).',
                    ], 503);
                }
                $parsed = $this->importer->fromPdfOrImage($file);
            } else {
                return response()->json([
                    'message' => 'Upload Excel (.xlsx) or a PDF/image of the filled packing list form.',
                ], 422);
            }
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Could not read that file. Try the Excel template.'], 500);
        }

        $supplier = ! empty($data['supplier_id'])
            ? Supplier::query()->find($data['supplier_id'])
            : null;

        return response()->json([
            'document_id'   => $document->id,
            'supplier_id'   => $supplier?->id,
            'supplier_name' => $supplier?->label ?? $parsed['supplier_name'],
            'reference_no'  => $parsed['reference_no'],
            'remarks'       => $parsed['remarks'],
            'cartons'       => $parsed['cartons'],
            'carton_count'  => count($parsed['cartons']),
        ]);
    }

    public function importApply(Request $request, ExportDocument $document): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id'  => ['nullable', 'integer', 'exists:suppliers,id'],
            'mode'         => ['required', 'in:replace,append'],
            'cartons_json' => ['required', 'string'],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'remarks'      => ['nullable', 'string', 'max:1000'],
            'file'         => ['nullable', 'file', 'max:12288'],
        ]);

        $cartons = json_decode($data['cartons_json'], true);
        if (! is_array($cartons)) {
            return back()->with('error', 'Invalid carton payload. Run Preview again.');
        }

        $supplier = ! empty($data['supplier_id'])
            ? Supplier::query()->find($data['supplier_id'])
            : null;

        try {
            $this->importer->apply(
                $document,
                $cartons,
                $data['mode'],
                $supplier,
                $request->file('file'),
                $data['reference_no'] ?? null,
                $data['remarks'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $note = $supplier
            ? "Packing list imported for {$supplier->label}."
            : 'Packing list imported from supplier form.';

        return redirect()
            ->route('export.packing.show', $document)
            ->with('success', $note.' You can now generate packing-list PDFs for export docs.');
    }
}
