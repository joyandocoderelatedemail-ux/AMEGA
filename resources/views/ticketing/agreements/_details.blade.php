{{--
    The booking's details beyond the price lines: travellers, contact, trip, insurance, notes and airline
    restrictions. Table markup so the on-screen sheet and the dompdf PDF share it.
--}}
@if (! empty($details))
    <table class="details">
        <tr>
            <td colspan="2" class="details-title">Booking details</td>
        </tr>
        @foreach ($details as $detailLabel => $detailLines)
            <tr>
                <th>{{ $detailLabel }}</th>
                <td>
                    @foreach ($detailLines as $detailLine)
                        <div>{{ $detailLine }}</div>
                    @endforeach
                </td>
            </tr>
        @endforeach
    </table>
@endif
