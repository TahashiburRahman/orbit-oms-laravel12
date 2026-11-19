<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ClientQuery;
use App\Models\Project;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ClientQueryController extends Controller
{
    private function assignees()
    {
        return User::where('active', true)->inTeams('admin', 'marketing', 'operations', 'developer')->orderBy('name')->get();
    }

    private function projects(Request $r)
    {
        return Project::visibleTo($r->user())->where('status', 'active')->orderBy('name')->get();
    }

    public function index(Request $r)
    {
        $queries = ClientQuery::visibleTo($r->user())->with('project', 'assignee')->when($r->filled('q'), fn ($q) => $q->where('subject', 'like', '%'.$r->string('q').'%'))->when($r->filled('status'), fn ($q) => $q->where('status', $r->string('status')))->latest()->paginate(20)->withQueryString();

        return view('marketing.queries.index', compact('queries'));
    }

    public function create(Request $r)
    {
        Gate::authorize('create', ClientQuery::class);

        return view('marketing.queries.form', ['query' => new ClientQuery, 'projects' => $this->projects($r), 'assignees' => $this->assignees()]);
    }

    public function store(Request $r)
    {
        Gate::authorize('create', ClientQuery::class);
        $data = $this->validated($r, true);
        $query = ClientQuery::create($data + ['created_by' => $r->user()->id]);
        Notify::send([$query->assignee], 'Client query assigned: '.$query->subject, route('queries.show', $query, false));

        return redirect()->route('queries.show', $query)->with('success', 'Client query logged.');
    }

    public function show(ClientQuery $query)
    {
        Gate::authorize('view', $query);
        $query->load('project.client', 'assignee', 'requester', 'messages.author');
        $activities = AuditLog::where('subject_type', ClientQuery::class)->where('subject_id', $query->id)->with('user')->orderBy('id')->get();
        $staffNames = User::pluck('name', 'id');

        return view('marketing.queries.show', compact('query', 'activities', 'staffNames'));
    }

    public function edit(Request $r, ClientQuery $query)
    {
        Gate::authorize('update', $query);

        return view('marketing.queries.form', ['query' => $query, 'projects' => $this->projects($r), 'assignees' => $this->assignees()]);
    }

    public function update(Request $r, ClientQuery $query)
    {
        Gate::authorize('update', $query);
        $data = $this->validated($r, false);
        // Staff responding to an escalation may change its status and assignment, while the original question remains intact.
        if (! $r->user()->isAdmin() && $query->created_by !== $r->user()->id) {
            unset($data['subject'],$data['description'],$data['priority']);
        }
        $old = $query->assigned_to;
        $query->update($data);
        if ($old != $query->assigned_to || $query->status === 'escalated') {
            Notify::send([$query->assignee()->first()], 'Client query escalated / assigned: '.$query->subject, route('queries.show', $query, false));
        }

        return redirect()->route('queries.show', $query)->with('success', 'Query updated. Assignment and status changes are recorded.');
    }

    public function message(Request $r, ClientQuery $query)
    {
        Gate::authorize('update', $query);
        $data = $r->validate(['body' => 'required|string|max:20000']);
        $query->messages()->create($data + ['user_id' => $r->user()->id]);
        $recipients = collect([$query->requester, $query->assignee])->unique('id')->reject(fn ($u) => $u->id === $r->user()->id);
        Notify::send($recipients, 'New response: '.$query->subject, route('queries.show', $query, false));

        return back()->with('success', 'Response added to the conversation.');
    }

    private function validated(Request $r, bool $creating): array
    {
        $rules = ['subject' => 'required|string|max:200', 'description' => 'required|string|max:20000', 'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])], 'status' => ['required', Rule::in(['open', 'awaiting_response', 'escalated', 'resolved'])], 'assigned_to' => ['required', Rule::in($this->assignees()->modelKeys())]];
        if ($creating) {
            $rules['project_id'] = ['required', Rule::in($this->projects($r)->modelKeys())];
        }

        return $r->validate($rules);
    }
}
