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

{{-- A custom package's day-by-day itinerary; each day is dated from the departure date. --}}
@php $itinerary = array_values(array_filter($packageSpecs['itinerary'] ?? [], 'is_array')); @endphp
@if ($itinerary !== [])
    <table class="details">
        <tr>
            <td colspan="2" class="details-title">Day-by-day itinerary</td>
        </tr>
        @foreach ($itinerary as $itineraryDay)
            @php $dayNumber = (int) ($itineraryDay['day'] ?? $loop->iteration); @endphp
            <tr>
                <th>
                    Day {{ $dayNumber }}
                    @if ($ticket->departure_date)
                        <div style="font-weight: normal;">{{ $ticket->departure_date->copy()->addDays($dayNumber - 1)->format('D, M j, Y') }}</div>
                    @endif
                </th>
                <td>
                    @if (filled($itineraryDay['title'] ?? null))
                        <div style="font-weight: bold;">{{ $itineraryDay['title'] }}</div>
                    @endif
                    @if (filled($itineraryDay['details'] ?? null))
                        <div>{!! nl2br(e($itineraryDay['details'])) !!}</div>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
@endif
