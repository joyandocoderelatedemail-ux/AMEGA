{{--
    The Official Ticket Booking Confirmation: the whole booking on one sheet.
    Shown on the ticket page (with its working controls) and, read-only, on the
    admin approval and cashier pages.

    $readonly       hide the restriction editor and the document uploads
    $documentLinks  link each uploaded document for download
    $documentGaps   missing documents by passenger id (uploads; ticket page only)
    $layout         'approval': the passenger manifest first, then every booking detail
                    in one box, then the fare and quotation (the admin's approval page);
                    otherwise the whole booking on one sheet
--}}
@php
    $readonly = $readonly ?? false;
    $documentLinks = $documentLinks ?? ! $readonly;
    $documentGaps = $readonly ? collect() : ($documentGaps ?? collect());
    $layout = $layout ?? 'sheet';
@endphp
@if ($layout === 'approval')
    <div class="space-y-6">
        <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-200 shadow-sm space-y-8 print:p-0 print:border-none print:shadow-none">
            @include('ticketing.tickets._confirmation-manifest')
        </div>

        <!-- Booking details, all in one box -->
        <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-200 shadow-sm space-y-8 print:p-0 print:border-none print:shadow-none">
            @include('ticketing.tickets._confirmation-top')
            @include('ticketing.tickets._confirmation-bottom')
        </div>

        @if($ticket->total_amount > 0 || $ticket->estimated_fare > 0)
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-200 shadow-sm space-y-8 print:p-0 print:border-none print:shadow-none">
                @include('ticketing.tickets._confirmation-pricing')
            </div>
        @endif
    </div>
@else
    <!-- Ticket Voucher Sheet (Print Friendly) -->
    <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-200 shadow-sm space-y-8 print:p-0 print:border-none print:shadow-none">
        @include('ticketing.tickets._confirmation-top')
        @include('ticketing.tickets._confirmation-manifest')
        @include('ticketing.tickets._confirmation-pricing')
        @include('ticketing.tickets._confirmation-bottom')
    </div>
@endif
