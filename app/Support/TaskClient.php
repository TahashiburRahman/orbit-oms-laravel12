<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ClientWebsite;
use App\Models\Task;
use App\Models\User;
use App\Rules\DomainOrUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TaskClient
{
    public static function rules(): array
    {
        return ['client_id' => 'nullable|integer|exists:clients,id', 'client_name' => 'nullable|string|max:160', 'client_website_id' => 'nullable|integer|exists:client_websites,id', 'domain_name' => ['nullable', 'string', 'max:255', new DomainOrUrl], 'website_name' => 'nullable|string|max:255'];
    }

    // Called inside the task transaction, so failed task validation cannot leave a client behind.
    public static function resolve(Request $r, array $data, ?Task $task = null): array
    {
        if ($task?->client_id && ! $r->hasAny(['client_id', 'client_name'])) {
            $data += ['client_id' => $task->client_id, 'client_name' => $task->client?->name];
        }
        $client = null;
        $name = preg_replace('/\s+/u', ' ', trim($data['client_name'] ?? ''));
        if (! empty($data['client_id'])) {
            $client = Client::lockForUpdate()->findOrFail($data['client_id']);
            Gate::authorize('view', $client);
            if ($name !== '' && mb_strtolower($name) !== mb_strtolower(preg_replace('/\s+/u', ' ', trim($client->name)))) {
                throw ValidationException::withMessages(['client_name' => 'Select the matching client suggestion or clear the selection to add a new client.']);
            }
        } elseif ($name !== '') {
            // Lock the creator to serialize repeat submissions from the same account.
            User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            $matches = Client::visibleTo($r->user())->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])->limit(2)->get();
            if ($matches->count() > 1) {
                throw ValidationException::withMessages(['client_name' => 'More than one client has this name. Select a suggestion to identify the client.']);
            }
            $client = $matches->first() ?? Client::create(['name' => $name, 'status' => 'active', 'created_by' => $r->user()->id, 'assigned_to' => $r->user()->id]);
        }

        $website = null;
        if (! empty($data['client_website_id'])) {
            $website = $client?->websites()->whereKey($data['client_website_id'])->first();
            if (! $website) {
                throw ValidationException::withMessages(['client_website_id' => 'Choose a domain belonging to the selected client.']);
            }
            if (! empty($data['domain_name']) && ClientWebsite::domainKey($data['domain_name']) !== $website->normalized_domain) {
                throw ValidationException::withMessages(['domain_name' => 'The domain does not match the selected website.']);
            }
            $keepSnapshot = $task?->client_website_id === $website->id && $task?->client_id === $client->id;
            $data['domain_name'] = $keepSnapshot ? ($task->domain_name ?? $website->domain) : $website->domain;
            $data['website_name'] = $keepSnapshot ? ($task->website_name ?? $website->website_name) : $website->website_name;
        } elseif ($client && ! empty($data['domain_name'])) {
            $website = self::addWebsite($client, $data['domain_name'], $data['website_name'] ?? null);
        }

        return [...$data, 'client_id' => $client?->id, 'client_name' => $client?->name, 'client_website_id' => $website?->id];
    }

    public static function addWebsite(Client $client, string $domain, ?string $name): ClientWebsite
    {
        return $client->websites()->firstOrCreate(['normalized_domain' => ClientWebsite::domainKey($domain)], ['domain' => $domain, 'website_name' => $name]);
    }
}
