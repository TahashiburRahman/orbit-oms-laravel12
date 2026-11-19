<section class="card"><h2>Activity history</h2>
@forelse($history as $entry)
<div class="activity-item"><strong>{{ $entry->user?->name ?? 'Local system' }}</strong> · {{ str_replace('_',' ',$entry->event) }} <small class="muted">{{ $entry->created_at->format('d M Y, h:i A') }}</small>
@if(isset($entry->metadata['changes']))<p class="muted">Changed: {{ implode(', ',array_keys($entry->metadata['changes'])) }}</p>@endif
</div>
@empty<p class="empty-state">No activity recorded.</p>@endforelse
</section>
