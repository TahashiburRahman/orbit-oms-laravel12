<div class="card form-card"><h2>Assignment and status history</h2>
@foreach($activities as $activity)
    @php $changes = $activity->metadata['changes'] ?? []; $previous = $activity->metadata['previous'] ?? []; @endphp
    @if($activity->event === 'created' || array_key_exists('assigned_to',$changes) || array_key_exists('status',$changes))
    <div class="activity-item"><strong>{{ $activity->user?->name ?? 'System' }}</strong> <small>· {{ $activity->created_at->format('d M Y, H:i') }}</small>
        @if($activity->event==='created')<p>Record created.</p>@endif
        @if(array_key_exists('assigned_to',$changes))<p>Assignment: {{ $staffNames[$previous['assigned_to'] ?? ''] ?? 'Unassigned' }} to {{ $staffNames[$changes['assigned_to'] ?? ''] ?? 'Unassigned' }}</p>@endif
        @if(array_key_exists('status',$changes))<p>Status: {{ str_replace('_',' ',$previous['status'] ?? 'open') }} to {{ str_replace('_',' ',$changes['status']) }}</p>@endif
    </div>
    @endif
@endforeach
</div>
