@props(['name','label','value'=>null,'type'=>'text','options'=>null,'required'=>false,'multiple'=>false,'hint'=>null])
@php
    $current = old($name, $value);
    $fieldId = $attributes->get('id', 'field-'.preg_replace('/[^a-zA-Z0-9_-]/', '-', $name));
    $hasError = $errors->has($name) || $errors->has($name.'.*');
    $describedBy = trim(($attributes->get('aria-describedby') ?? '').' '.($hint ? $fieldId.'-hint' : '').' '.($hasError ? $fieldId.'-error' : ''));
    $controlAttributes = $attributes->except(['class', 'id', 'aria-describedby', 'aria-invalid']);
@endphp
<div class="field {{ $attributes->get('class') }}">
    <label for="{{ $fieldId }}">{{ $label }} @if($required)<span aria-hidden="true">*</span>@endif</label>
    @if($type==='textarea')
        <textarea id="{{ $fieldId }}" name="{{ $name }}" rows="4" @required($required) @if($describedBy) aria-describedby="{{ $describedBy }}" @endif @if($hasError) aria-invalid="true" @endif {{ $controlAttributes }}>{{ $current }}</textarea>
    @elseif($options!==null)
        <select id="{{ $fieldId }}" name="{{ $name }}{{ $multiple?'[]':'' }}" {{ $multiple?'multiple':'' }} @required($required) @if($describedBy) aria-describedby="{{ $describedBy }}" @endif @if($hasError) aria-invalid="true" @endif {{ $controlAttributes }}>
            @unless($required || $multiple || collect($options)->keys()->containsStrict(''))<option value="">None selected</option>@endunless
            @foreach($options as $key=>$text)<option value="{{ $key }}" @selected($multiple ? in_array((string)$key,array_map('strval',(array)$current)) : (string)$current===(string)$key)>{{ $text }}</option>@endforeach
        </select>
    @else
        <input id="{{ $fieldId }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type==='password'?'':$current }}" @required($required) @if($describedBy) aria-describedby="{{ $describedBy }}" @endif @if($hasError) aria-invalid="true" @endif {{ $controlAttributes }}>
    @endif
    @if($hint)<small id="{{ $fieldId }}-hint" class="field-hint">{{ $hint }}</small>@endif
    @if($hasError)<small id="{{ $fieldId }}-error" class="field-error" role="alert">{{ $errors->first($name) ?: $errors->first($name.'.*') }}</small>@endif
</div>
