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
                @can('inquiry.view')
                    <li class="mt-1"><a href="{{ route('sales.inquiries.follow-ups') }}">Buyer follow-ups</a></li>
                @endcan
            </ul>
        </x-ui.card>
    </div>
</x-app-layout>

