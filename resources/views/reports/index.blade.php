<x-app-layout>
    <x-slot name="header">Reports</x-slot>

    <div class="row g-3">
        <div class="col-md-3">
            <x-ui.card title="Purchase Orders">
                <div class="fs-4 fw-semibold">{{ number_format($stats['purchase_orders']) }}</div>
            </x-ui.card>
        </div>
        <div class="col-md-3">
            <x-ui.card title="Export Documents">
                <div class="fs-4 fw-semibold">{{ number_format($stats['export_documents']) }}</div>
            </x-ui.card>
        </div>
        <div class="col-md-3">
            <x-ui.card title="Open Shipments">
                <div class="fs-4 fw-semibold">{{ number_format($stats['open_shipments']) }}</div>
            </x-ui.card>
        </div>
        <div class="col-md-3">
            <x-ui.card title="Closed Shipments">
                <div class="fs-4 fw-semibold">{{ number_format($stats['closed_shipments']) }}</div>
            </x-ui.card>
        </div>
    </div>

    <div class="mt-3">
        <x-ui.card title="Report Links">
            <ul class="mb-0">
                <li><a href="{{ route('reports.outstanding.index') }}">Outstanding</a></li>
                {{-- M-11: was a literal developer note shipped straight to end
                     users ("More report exports can be added on this page
                     based on demo feedback."). Dropped — it told buyers/staff
                     nothing about their data. --}}
            </ul>
        </x-ui.card>
    </div>
</x-app-layout>

