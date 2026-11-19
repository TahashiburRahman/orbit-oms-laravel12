<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Lead;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    private function marketers()
    {
        return User::where('active', true)->inTeams('admin', 'marketing')->orderBy('name')->get();
    }

    public function index(Request $r)
    {
        $leads = Lead::visibleTo($r->user())->with('assignee')->when($r->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$r->string('q').'%')->orWhere('company', 'like', '%'.$r->string('q').'%')->orWhere('email', 'like', '%'.$r->string('q').'%')))->when($r->filled('stage'), fn ($q) => $q->where('stage', $r->string('stage')))->orderByRaw('follow_up_at IS NULL')->orderBy('follow_up_at')->paginate(20)->withQueryString();

        return view('marketing.leads.index', compact('leads'));
    }

    public function create()
    {
        Gate::authorize('create', Lead::class);

        return view('marketing.leads.form', ['lead' => new Lead, 'marketers' => $this->marketers()]);
    }

    public function store(Request $r)
    {
        Gate::authorize('create', Lead::class);
        $data = $this->validated($r);
        $lead = Lead::create($data + ['created_by' => $r->user()->id]);
        Notify::send([$lead->assignee], 'Lead assigned: '.$lead->name, route('leads.show', $lead, false));

        return redirect()->route('leads.show', $lead)->with('success', 'Lead created.');
    }

    public function show(Lead $lead)
    {
        Gate::authorize('view', $lead);
        $history = AuditLog::where('subject_type', Lead::class)->where('subject_id', $lead->id)->with('user')->latest('id')->limit(60)->get();

        return view('marketing.leads.show', compact('lead', 'history'));
    }

    public function edit(Lead $lead)
    {
        Gate::authorize('update', $lead);

        return view('marketing.leads.form', ['lead' => $lead, 'marketers' => $this->marketers()]);
    }

    public function update(Request $r, Lead $lead)
    {
        Gate::authorize('update', $lead);
        $data = $this->validated($r);
        abort_if($lead->converted_client_id, 422, 'Converted lead history is preserved. Edit the linked client instead.');
        $changed = $lead->assigned_to != $data['assigned_to'];
        $lead->update($data);
        if ($changed) {
            Notify::send([$lead->assignee()->first()], 'Lead assigned: '.$lead->name, route('leads.show', $lead, false));
        }

        return redirect()->route('leads.show', $lead)->with('success', 'Lead updated.');
    }

    public function convert(Request $r, Lead $lead)
    {
        Gate::authorize('update', $lead);
        $client = DB::transaction(function () use ($lead, $r) {
            $locked = Lead::lockForUpdate()->findOrFail($lead->id);
            Gate::authorize('update', $locked);
            if ($locked->converted_client_id) {
                return $locked->client;
            }
            abort_unless($locked->stage === 'qualified', 422, 'Qualify the lead before conversion.');
            $client = Client::create(['name' => $locked->name, 'company' => $locked->company, 'email' => $locked->email, 'phone' => $locked->phone, 'status' => 'active', 'notes' => $locked->notes, 'assigned_to' => $locked->assigned_to, 'created_by' => $r->user()->id]);
            $locked->update(['stage' => 'converted', 'converted_client_id' => $client->id, 'converted_at' => now()]);

            return $client;
        });

        return redirect()->route('clients.show', $client)->with('success', 'Lead linked to client. Its history is preserved.');
    }

    private function validated(Request $r): array
    {
        return $r->validate(['name' => 'required|string|max:200', 'company' => 'nullable|string|max:200', 'email' => 'nullable|email|max:200', 'phone' => 'nullable|string|max:40', 'source' => 'required|string|max:120', 'stage' => ['required', Rule::in(['new', 'contacted', 'qualified', 'lost'])], 'follow_up_at' => 'nullable|date', 'notes' => 'nullable|string|max:20000', 'assigned_to' => ['required', Rule::in($this->marketers()->modelKeys())]]);
    }
}
