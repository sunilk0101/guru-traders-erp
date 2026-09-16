<?php

// Standard garment BOM: matches the full worked example on the "BOM Format"
// sheet of "BOM and Inquiry Format final.xlsx" (Fabric + Labour + every
// trim/accessory line + 10% wastage on trims+accessories).
//
// Note: the sheet's own "Accessories:" total cell (G46 = 19.55) does not
// reconcile against the sum of its own named accessory lines (17.55) - row
// 34, directly under the "Accessories:" header, has stray values (1 x 5 = 5)
// that don't map to any named component. We total the named lines only
// (17.55), so this preset's grand total is 414.05 rather than the sheet's
// stated 416.245 (a 2.195 difference traceable to that one stray cell).
$standardLines = [
    ['component_name' => 'Fabric', 'size' => null, 'qty' => 1.5, 'rate' => 150, 'remarks' => null],
    ['component_name' => 'Labour', 'size' => null, 'qty' => 1, 'rate' => 100, 'remarks' => null],
    ['component_name' => 'Embroidery', 'size' => null, 'qty' => 1, 'rate' => 5, 'remarks' => null],
    ['component_name' => 'Print', 'size' => null, 'qty' => 1, 'rate' => 0, 'remarks' => null],
    ['component_name' => 'Transport', 'size' => null, 'qty' => 1, 'rate' => 3, 'remarks' => null],
    ['component_name' => 'Pattern', 'size' => null, 'qty' => 1, 'rate' => 3, 'remarks' => null],
    ['component_name' => 'Main Label', 'size' => null, 'qty' => 1, 'rate' => 1.5, 'remarks' => null],
    ['component_name' => 'Size Label', 'size' => null, 'qty' => 1, 'rate' => 0.8, 'remarks' => null],
    ['component_name' => 'Pocket Label', 'size' => null, 'qty' => 1, 'rate' => 1.25, 'remarks' => null],
    ['component_name' => 'Washcare1', 'size' => null, 'qty' => 1, 'rate' => 5, 'remarks' => null],
    ['component_name' => 'Washcare2', 'size' => null, 'qty' => 1, 'rate' => 0, 'remarks' => null],
    ['component_name' => 'Style No labels', 'size' => null, 'qty' => 1, 'rate' => 3, 'remarks' => null],
    ['component_name' => 'Belt Lining', 'size' => null, 'qty' => 1, 'rate' => 3, 'remarks' => null],
    ['component_name' => 'Zip', 'size' => null, 'qty' => 1, 'rate' => 1.5, 'remarks' => 'Dye to match'],
    ['component_name' => 'Zip', 'size' => null, 'qty' => 1, 'rate' => 0.8, 'remarks' => 'Single Colour'],
    ['component_name' => 'Buttons', 'size' => '28L', 'qty' => 1, 'rate' => 1.25, 'remarks' => 'Dye to match'],
    ['component_name' => 'Buttons', 'size' => '24L', 'qty' => 1, 'rate' => 5, 'remarks' => 'Dye to match'],
    ['component_name' => 'Buttons', 'size' => '28L', 'qty' => 1, 'rate' => 0, 'remarks' => 'Single Colour'],
    ['component_name' => 'Buttons', 'size' => '24L', 'qty' => 1, 'rate' => 3, 'remarks' => 'Single Colour'],
    ['component_name' => 'Pocketing Fabric', 'size' => '4pocket-2Pocket', 'qty' => 0.35, 'rate' => 65, 'remarks' => null],
    ['component_name' => 'Pipping Fabric', 'size' => null, 'qty' => 1, 'rate' => 1.5, 'remarks' => null],
    ['component_name' => 'Elastic', 'size' => null, 'qty' => 1, 'rate' => 0.8, 'remarks' => null],
    ['component_name' => 'Ribit', 'size' => null, 'qty' => 1, 'rate' => 1.25, 'remarks' => null],
    ['component_name' => 'Velcrow', 'size' => null, 'qty' => 1, 'rate' => 0, 'remarks' => null],
    ['component_name' => 'Fold Tag', 'size' => null, 'qty' => 1, 'rate' => 0, 'remarks' => null],
    ['component_name' => 'Main Tag', 'size' => null, 'qty' => 1, 'rate' => 3, 'remarks' => null],
    ['component_name' => 'Tag Pin', 'size' => null, 'qty' => 1, 'rate' => 3, 'remarks' => null],
    ['component_name' => 'Drawchord', 'size' => null, 'qty' => 1, 'rate' => 1.5, 'remarks' => null],
    ['component_name' => 'Barcode', 'size' => null, 'qty' => 1, 'rate' => 0.8, 'remarks' => null],
    ['component_name' => 'Polybag', 'size' => null, 'qty' => 1, 'rate' => 1.25, 'remarks' => null],
    ['component_name' => 'Carton', 'size' => null, 'qty' => 1, 'rate' => 5, 'remarks' => null],
    ['component_name' => 'Carton Plate', 'size' => null, 'qty' => 1, 'rate' => 0, 'remarks' => null],
    ['component_name' => 'Shrink wrap', 'size' => null, 'qty' => 1, 'rate' => 3, 'remarks' => null],
    ['component_name' => 'Wastage (10% of trims & accessories)', 'size' => null, 'qty' => 1, 'rate' => 8.095, 'remarks' => null],
];

$labelsOnly = [
    ['component_name' => 'Main Label', 'size' => null, 'qty' => 1, 'rate' => 1.5, 'remarks' => null],
    ['component_name' => 'Size Label', 'size' => null, 'qty' => 1, 'rate' => 0.8, 'remarks' => null],
    ['component_name' => 'Pocket Label', 'size' => null, 'qty' => 1, 'rate' => 1.25, 'remarks' => null],
    ['component_name' => 'Washcare1', 'size' => null, 'qty' => 1, 'rate' => 5, 'remarks' => null],
    ['component_name' => 'Style No labels', 'size' => null, 'qty' => 1, 'rate' => 3, 'remarks' => null],
];

return [
    /*
     | Named BOM cost packs for Inquiry (Excel "BOM Format").
     | Staff pick one from a dropdown — the long trim list stays off the main grid.
     */
    'templates' => [
        'standard' => [
            'name'  => 'Standard garment BOM',
            'lines' => $standardLines,
        ],
        'labels' => [
            'name'  => 'Labels & washcare only',
            'lines' => $labelsOnly,
        ],
    ],

    'default_lines' => $standardLines,
];
