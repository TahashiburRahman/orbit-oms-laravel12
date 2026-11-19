@props(['task', 'domainRequired' => false])
@php
    $selectedId = old('client_id', $task->client_id ?? request('client_id'));
    $selectedClient = $selectedId ? App\Models\Client::visibleTo(auth()->user())->with('websites')->find($selectedId) : null;
    $domain = old('domain_name', $task->domain_name);
    $websiteId = old('client_website_id', $task->client_website_id);
@endphp
<fieldset class="full-width client-entry grid-form" data-client-entry data-domain-required="{{ $domainRequired ? 'true' : 'false' }}" data-url="{{ route('clients.suggestions') }}" data-initial="{{ json_encode($selectedClient ? $selectedClient->only(['id','name','company']) + ['websites' => $selectedClient->websites->map->only(['id','domain','website_name'])->all()] : null) }}">
    <legend class="sr-only">Client and website</legend>
    <div class="field client-name-field">
        <label for="client-name">Client name (optional)</label>
        <input id="client-name" name="client_name" value="{{ old('client_name', $selectedClient?->name ?? $task->client_name) }}" maxlength="160" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="client-suggestions" aria-describedby="client-help" data-client-name>
        <input type="hidden" name="client_id" value="{{ $selectedClient?->id }}" data-client-id>
        <div class="client-suggestions" id="client-suggestions" role="listbox" aria-label="Matching clients" data-client-suggestions hidden></div>
        <small class="field-hint" id="client-help">Type a name to find an accessible client, or enter a new name to create one when saving.</small>
        <small class="field-hint" data-client-message role="status" aria-live="polite"></small>
    </div>
    <div class="field">
        <label for="{{ $selectedClient ? 'saved-client-domain' : 'client-domain' }}" data-domain-label>Domain or website @if($domainRequired)<span aria-hidden="true">*</span>@endif</label>
        <select id="saved-client-domain" name="client_website_id" data-client-website aria-label="Saved client domains" @disabled(!$selectedClient) @if(!$selectedClient)hidden @endif>
            <option value="">Add a new domain or website</option>
            @foreach($selectedClient?->websites ?? [] as $website)<option value="{{ $website->id }}" @selected((string)$websiteId === (string)$website->id)>{{ $website->website_name ? $website->website_name.' (' . $website->domain . ')' : $website->domain }}</option>@endforeach
        </select>
        <input id="client-domain" name="domain_name" aria-label="Domain or website" value="{{ $domain }}" maxlength="255" placeholder="example.com or https://example.com" data-client-domain @required($domainRequired && !$websiteId) @disabled((bool)$websiteId) @if($websiteId)hidden @endif>
        <small class="field-hint">An existing client can have more than one domain. Choose one or add another.</small>
    </div>
    <div class="field"><label for="client-website-name">Website name (optional)</label><input id="client-website-name" name="website_name" value="{{ old('website_name', $task->website_name) }}" maxlength="255" data-client-website-name @disabled((bool)$websiteId)></div>
</fieldset>
