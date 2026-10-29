@extends('layouts.srrv')

@section('title', 'Edit '.$file->reference.' - AMEGA Travel and Tours')

@section('content')
    @if ($preview)
        @include('srrv.partials._preview-banner', ['file' => $file, 'kind' => 'request', 'label' => $file->typeLabel()])
    @endif

    <form method="POST" action="{{ route('srrv.requests.update', $file) }}" enctype="multipart/form-data" novalidate
          @if ($preview) data-preview x-data x-init="$nextTick(() => $el.querySelectorAll('input, select, textarea').forEach(field => { if (field.type !== 'hidden' && field.name !== '_token') field.disabled = true }))" @endif>
        @csrf
        @method('PUT')
        @include('srrv.requests._form', ['type' => $file->type, 'file' => $file, 'preview' => $preview])
    </form>
@endsection
