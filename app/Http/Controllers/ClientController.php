<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use App\Support\Notify;
use App\Support\TaskClient;
use App\Rules\DomainOrUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function suggestions(Request $r)
    {
        $data = $r->validate(['q' => 'nullable|string|max:160', 'id' => 'nullable|integer']);
        $q = trim($data['q'] ?? '');
        if ($q === '' && empty($data['id'])) {
            return response()->json([])->header('Cache-Control', 'private, no-store');
        }
        $clients = Client::visibleTo($r->user())->with('websites:id,client_id,domain,website_name')
            ->when(! empty($data['id']), fn ($query) => $query->whereKey($data['id']))
            ->when(empty($data['id']), fn ($query) => $query->whereRaw("name LIKE ? ESCAPE '!'", [str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%']))
            ->orderBy('name')->limit(12)->get(['id', 'name', 'company']);

        return response()->json($clients)->header('Cache-Control', 'private, no-store');
    }

    public function index(Request $r)
    {
        $clients = Client::visibleTo($r->user())->with('assignee')->when($r->search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$r->search.'%')->orWhere('company', 'like', '%'.$r->search.'%')->orWhere('email', 'like', '%'.$r->search.'%')))->when($r->status, fn ($q) => $q->where('status', $r->status))->latest()->paginate(20)->withQueryString();

        return view('clients.index', compact('clients'));
    }

    public function create()
    {
        Gate::authorize('create', Client::class);

        return $this->form(new Client(['status' => 'active']));
    }

    public function edit(Client $client)
    {
        Gate::authorize('update', $client);

        return $this->form($client);
    }

    private function form(Client $client)
    {
        return view('clients.form', ['client' => $client, 'users' => User::where('active', true)->orderBy('name')->get()]);
    }

    public function store(Request $r)
    {
        Gate::authorize('create', Client::class);
        $client = DB::transaction(function () use ($r) {
            $data = $this->data($r);
            $client = Client::create([...collect($data)->except(['domain_name', 'website_name'])->all(), 'created_by' => $r->user()->id]);
            if (! empty($data['domain_name'])) {
                TaskClient::addWebsite($client, $data['domain_name'], $data['website_name'] ?? null);
            }

            return $client;
        });
        $this->notify($client);

        return redirect()->route('clients.show', $client)->with('status', 'Client created.');
    }

    public function show(Client $client)
    {
        Gate::authorize('view', $client);
        $projects = $client->projects()->visibleTo(auth()->user())->with('assignee')->latest()->get();
        $history = AuditLog::where('subject_type', Client::class)->where('subject_id', $client->id)->with('user')->latest('id')->limit(50)->get();

        return view('clients.show', compact('client', 'projects', 'history'));
    }

    public function update(Request $r, Client $client)
    {
        Gate::authorize('update', $client);
        DB::transaction(function () use ($r, $client) {
            $data = $this->data($r);
            $client->update(collect($data)->except(['domain_name', 'website_name'])->all());
            if (! empty($data['domain_name'])) {
                TaskClient::addWebsite($client, $data['domain_name'], $data['website_name'] ?? null);
            }
        });
        if ($client->wasChanged('assigned_to')) {
            $this->notify($client);
        }

        return redirect()->route('clients.show', $client)->with('status', 'Client updated.');
    }

    private function notify(Client $client): void
    {
        Notify::send([$client->assignee], 'Client assigned: '.$client->name, route('clients.show', $client));
    }

    private function data(Request $r): array
    {
        $data = $r->validate(['name' => 'required|string|max:160', 'company' => 'nullable|string|max:160', 'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:40', 'status' => ['required', Rule::in(['active', 'inactive'])], 'notes' => 'nullable|string|max:10000', 'assigned_to' => ['nullable', Rule::exists('users','id')->where('active',true)], 'domain_name' => ['nullable', 'max:255', new DomainOrUrl], 'website_name' => 'nullable|string|max:255']);
        if (! $r->user()->inTeam('admin', 'operations')) {
            $data['assigned_to'] = $r->user()->id;
            $data['status'] = 'active';
        }

        return $data;
    }
}
