@component('mail::message')
# Thanks, {{ $booking->guest_name }}

We have passed your request to **{{ $booking->restaurant->name }}**. You will get a
second email as soon as they confirm the table.

@component('mail::panel')
**Reference:** {{ $booking->reference }}
**Date:** {{ $booking->booking_date->format('l j F Y') }}
**Time:** {{ \Illuminate\Support\Str::substr($booking->booking_time, 0, 5) }}
**Party size:** {{ $booking->party_size }} {{ \Illuminate\Support\Str::plural('guest', $booking->party_size) }}
@if ($booking->discount_percent)
**Your discount:** {{ $booking->discount_percent }}% off
@endif
@endcomponent

@if ($booking->notes)
Your note to the restaurant: *"{{ $booking->notes }}"*
@endif

**{{ $booking->restaurant->address }}**{{ $booking->restaurant->city ? ', '.$booking->restaurant->city : '' }}
@if ($booking->restaurant->phone)
Phone: {{ $booking->restaurant->phone }}
@endif

@component('mail::button', ['url' => config('app.frontend_url').'/booking/'.$booking->reference])
View your booking
@endcomponent

No money has been charged at this stage.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
