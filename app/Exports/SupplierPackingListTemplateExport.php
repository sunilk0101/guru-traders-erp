<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel form for outside suppliers / packers.
 * Optional $seedRows prefill from Export Document items.
 */
class SupplierPackingListTemplateExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  list<list<string|int|float|null>>  $seedRows
     */
    public function __construct(private readonly array $seedRows = [])
    {
    }

    public function title(): string
    {
        return 'Packing List';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Carton No',
            'Net Weight (kg)',
            'Gross Weight (kg)',
            'Dimensions (LxWxH cm)',
            'Description',
            'Unit',
            'Qty',
            'Colour / Size',
            'Remarks',
        ];
    }

    /**
     * @return list<list<string|int|float|null>>
     */
    public function array(): array
    {
        if ($this->seedRows !== []) {
            return $this->seedRows;
        }

        return [
            ['1', 12.5, 13.2, '40x30x25', 'GIRLS COTTON SKD 24 TO 38', 'SET', 10, 'Assorted', ''],
            ['1', '', '', '', 'Extra labels / inserts', 'PCS', 10, '', ''],
            ['2', 11.0, 11.8, '40x30x25', 'GIRLS COTTON SKD 24 TO 38', 'SET', 8, 'Assorted', ''],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
