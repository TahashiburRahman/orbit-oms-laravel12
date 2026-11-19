@foreach($fields as $name => $field)
    @php
        $value = $record->getAttribute($name);
        $type = $field[1] ?? 'text';
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format($type === 'datetime-local' ? 'Y-m-d\TH:i' : 'Y-m-d');
        }
    @endphp
    <x-field :name="$name" :label="$field[0]" :type="$type === 'select' ? 'text' : $type" :value="$value" :options="$type === 'select' ? $field[2] : null" :required="$field[3] ?? false" :class="$type === 'textarea' ? 'full-width' : ''" />
@endforeach
