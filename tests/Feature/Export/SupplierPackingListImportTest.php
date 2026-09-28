<?php

namespace Tests\Feature\Export;

use App\Models\Buyer;
use App\Models\Category;
use App\Models\Currency;
use App\Models\DocumentFormat;
use App\Models\ExportDocument;
use App\Models\OrderConfirmation;
use App\Models\Supplier;
use App\Services\Export\ExportDocumentService;
use App\Services\Export\SupplierPackingListImporter;
use Database\Seeders\DocumentChecklistTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SupplierPackingListImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_excel_import_creates_cartons_tagged_with_supplier(): void
    {
        Storage::fake('public');
        $this->seed(DocumentChecklistTypeSeeder::class);

        $buyer = Buyer::forceCreate([
            'display_code' => 'BUY01',
            'company_name' => 'Test Overseas Buyer',
            'status'       => 'active',
        ]);

        $category = new Category(['name' => 'Woven Garments', 'status' => 'active']);
        $category->code = 'CAT001';
        $category->save();

        $format = DocumentFormat::create(['name' => 'Standard Order Format', 'status' => 'active']);

        $currency = new Currency(['name' => 'US Dollar', 'symbol' => '$', 'status' => 'active']);
        $currency->iso_code = 'USD';
        $currency->save();

        $oc = new OrderConfirmation([
            'buyer_id'           => $buyer->id,
            'category_id'        => $category->id,
            'document_format_id' => $format->id,
            'currency_id'        => $currency->id,
            'oc_date'            => now()->toDateString(),
            'status'             => 'confirmed',
        ]);
        $oc->oc_num = 'GT/OC/001/26-27';
        $oc->financial_year = '26-27';
        $oc->save();

        $ocItem = $oc->items()->create([
            'sort_order'  => 0,
            'design_no'   => 'D-100',
            'description' => 'Cotton T-Shirt',
            'unit'        => 'pcs',
            'price'       => 5.00,
            'qty'         => 100,
            'amount'      => 500.00,
        ]);

        $document = new ExportDocument([
            'order_confirmation_id' => $oc->id,
            'buyer_id'              => $buyer->id,
            'currency_id'           => $currency->id,
            'status'                => 'draft',
        ]);
        $document->doc_num = 'GT/ED/001/26-27';
        $document->financial_year = '26-27';
        $document->save();

        $document->items()->create([
            'order_confirmation_item_id' => $ocItem->id,
            'sort_order'                 => 0,
            'design_no'                  => 'D-100',
            'description'                => 'Cotton T-Shirt',
            'unit'                       => 'pcs',
            'price'                      => 5.00,
            'qty'                        => 100,
            'amount'                     => 500.00,
        ]);

        app(ExportDocumentService::class)->ensureChecklist($document);

        $supplier = Supplier::forceCreate([
            'display_code' => 'AJC',
            'company_name' => 'A J Creation',
            'party_type'   => 'supplier',
            'status'       => 'active',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'pl').'.xlsx';
        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray([
            ['Carton No', 'Net Weight (kg)', 'Gross Weight (kg)', 'Dimensions (LxWxH cm)', 'Description', 'Unit', 'Qty', 'Colour / Size', 'Remarks'],
            ['1', 10, 11, '40x30x25', 'GIRLS COTTON SKD', 'SET', 10, 'Assorted', ''],
            ['1', '', '', '', 'Labels', 'PCS', 10, '', ''],
            ['2', 9, 9.5, '40x30x25', 'GIRLS COTTON SKD', 'SET', 8, '', ''],
        ]);
        (new Xlsx($sheet))->save($path);

        $file = new UploadedFile($path, 'packing.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $importer = app(SupplierPackingListImporter::class);
        $parsed = $importer->fromExcel($file);

        $this->assertCount(2, $parsed['cartons']);
        $this->assertCount(2, $parsed['cartons'][0]['lines']);

        $importer->apply($document, $parsed['cartons'], 'replace', $supplier, $file, 'PL-REF-1', null);

        $document->refresh()->load('cartons.lines');

        $this->assertCount(2, $document->cartons);
        $this->assertSame(2, $document->cartons->first()->lines->count());
        $this->assertStringContainsString('[AJC]', (string) $document->cartons->first()->lines->first()->description);
        $this->assertSame(2, (int) $document->total_cartons);

        $entry = $document->checklist()
            ->whereHas('type', fn ($q) => $q->where('code', 'packing_list'))
            ->orderBy('id')
            ->first();
        $this->assertNotNull($entry?->file_path);
        $this->assertSame($supplier->id, $entry->matched_supplier_id);

        @unlink($path);
    }
}
