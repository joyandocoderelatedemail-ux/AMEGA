{{-- The toolbar and the letterhead, opening the agreement's page (closed by _service-agreement-bottom). --}}
<div class="toolbar">
    <button type="button" onclick="window.print()">Print agreement</button>
    @foreach ($links as [$linkLabel, $linkUrl])
        <a class="ghost" href="{{ $linkUrl }}">{{ $linkLabel }}</a>
    @endforeach
    <span>Filled from the file. Click an underlined field to type in it, or a box to tick it. Long bond paper (8.5 &times; 13 in), no margins.</span>
</div>

<img class="letterhead" src="{{ asset('images/agreements/services-letterhead.jpg') }}" alt="">

<div class="paper">
<img class="screen-logo" src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO 2_UPDATED.png') }}" alt="AMEGA Travel and Tours Services">
<table class="layout">
    <thead><tr><td></td></tr></thead>
    <tfoot><tr><td></td></tr></tfoot>
    <tbody><tr><td>
