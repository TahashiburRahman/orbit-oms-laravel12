@props(['resource', 'record', 'label' => 'Delete record', 'compact' => false])
@if(auth()->user()?->isSuperAdmin())
@unless($compact)<section class="card"><h2>Superadmin actions</h2><p class="muted">Deletion is permanent. Referenced records must be removed or reassigned first; protected history remains intact.</p>@endunless
<form method="post" action="{{ route('records.destroy', ['resource' => $resource, 'record' => $record->id]) }}" data-confirm="Delete this record permanently? Related records and protected history must be handled first.">@csrf @method('delete')<button class="btn-secondary {{ $compact ? 'btn-sm' : '' }}" type="submit">{{ $label }}</button></form>
@unless($compact)</section>@endunless
@endif
