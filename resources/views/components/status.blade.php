@props(['value', 'label' => null])
<span {{ $attributes->class(['badge', 'status-'.preg_replace('/[^a-z0-9_]/', '', strtolower((string) $value))]) }}>{{ $label ?? ucfirst(str_replace('_', ' ', $value)) }}</span>
