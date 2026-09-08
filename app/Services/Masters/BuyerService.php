<?php

namespace App\Services\Masters;

use App\Models\Buyer;
use App\Services\NumberSeriesService;
use Illuminate\Support\Facades\DB;

class BuyerService
{
    public function __construct(private readonly NumberSeriesService $numbers)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Buyer
    {
        // One transaction: a buyer whose categories or carton marks failed to
        // write is a half-saved master the user would have to spot themselves.
        return DB::transaction(function () use ($data) {
            // display_code is not fillable (see Buyer's docblock), so it is
            // never in $this->columns($data) — assigned as a property instead.
            // Inside the transaction on purpose: next() holds a row lock on the
            // counter until this insert commits, so two people saving at the
            // same moment cannot both be handed BUY05.
            $buyer = new Buyer($this->columns($data));
            $buyer->display_code = $this->numbers->next('buyer');
            $buyer->save();

            $buyer->categories()->sync($data['category_ids'] ?? []);
            $this->syncAcceptedSets($buyer, $data);
            $this->syncCartonMarkings($buyer, $data['carton_markings'] ?? []);
            $this->syncContacts($buyer, $data['contacts'] ?? []);

            return $buyer;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Buyer $buyer, array $data): Buyer
    {
        return DB::transaction(function () use ($buyer, $data) {
            $buyer->update($this->columns($data));

            $buyer->categories()->sync($data['category_ids'] ?? []);
            $this->syncAcceptedSets($buyer, $data);
            $this->syncCartonMarkings($buyer, $data['carton_markings'] ?? []);
            $this->syncContacts($buyer, $data['contacts'] ?? []);

            return $buyer->refresh();
        });
    }

    /**
     * Nothing points at a buyer yet — orders, quotations and shipments come in
     * later phases. The check exists now so the guard is in one place when they
     * do, and so the controller's shape matches Category and Product.
     *
     * @return array{allowed: bool, reason: ?string}
     */
    public function canDelete(Buyer $buyer): array
    {
        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Strip the two keys that are relations rather than columns, so the rest
     * can go through mass assignment and $fillable still governs it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function columns(array $data): array
    {
        return array_diff_key($data, array_flip([
            'category_ids',
            'carton_markings',
            'currency_ids',
            'incoterm_ids',
            'shipment_method_ids',
            'contacts',
        ]));
    }

    /**
     * The currencies, incoterms and shipment methods this buyer accepts.
     *
     * The single `currency_id` / `incoterm_id` / `shipment_method_id` columns
     * survive as the default within each set — see the 000200 expand migration.
     * The request has already made sure each default is inside its own set, so
     * a plain sync here cannot orphan one.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncAcceptedSets(Buyer $buyer, array $data): void
    {
        $buyer->currencies()->sync($data['currency_ids'] ?? []);
        $buyer->incoterms()->sync($data['incoterm_ids'] ?? []);
        $buyer->shipmentMethods()->sync($data['shipment_method_ids'] ?? []);
    }

    /**
     * Rewrite a buyer's carton mark lines from the submitted rows.
     *
     * Deleted and re-inserted rather than diffed: `line_no` is the print order,
     * so reordering or removing a middle line renumbers everything after it,
     * and matching rows up by id to achieve the same result is more code for no
     * gain. Nothing references a marking row by id.
     *
     * Rows with no label AND no value are dropped — that is an empty line the
     * user left alone, not a line they want printed blank.
     *
     * @param  array<int, array{label?: ?string, value?: ?string}>  $rows
     */
    private function syncCartonMarkings(Buyer $buyer, array $rows): void
    {
        $buyer->cartonMarkings()->delete();

        $lineNo = 0;

        foreach ($rows as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));

            if ($label === '' && $value === '') {
                continue;
            }

            $buyer->cartonMarkings()->create([
                'line_no'     => ++$lineNo,
                'label'       => $label !== '' ? $label : 'LINE '.$lineNo,
                'value'       => $value !== '' ? $value : null,
                // The sheet stars the first three lines. Recorded so the packing
                // screen can later refuse to print a carton with a blank one.
                'is_required' => $lineNo <= 3,
            ]);
        }
    }

    /**
     * Rewrite a buyer's additional contacts (change request #3) from the
     * submitted rows. Deleted and re-inserted rather than diffed — nothing
     * references a contact row by id, same call as Supplier's contacts and
     * this buyer's own carton markings.
     *
     * A row with no name is dropped — a line the user left alone, not a
     * person they meant to record. BuyerRequest already caps this at 3.
     *
     * @param  array<int, array{name?: ?string, designation_id?: mixed, mobile?: ?string, email?: ?string}>  $rows
     */
    private function syncContacts(Buyer $buyer, array $rows): void
    {
        $buyer->contacts()->delete();

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $buyer->contacts()->create([
                'name'           => $name,
                'designation_id' => $row['designation_id'] ?? null,
                'mobile'         => $row['mobile'] ?? null,
                'email'          => $row['email'] ?? null,
            ]);
        }
    }
}
