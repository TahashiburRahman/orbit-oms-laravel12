<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Support\Audit;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $r)
    {
        $projects = Project::visibleTo($r->user())->with(['client', 'assignee', 'team'])->when($r->search, fn ($q) => $q->where('name', 'like', '%'.$r->search.'%'))->when($r->status, fn ($q) => $q->where('status', $r->status))->latest()->paginate(20)->withQueryString();

        return view('projects.index', compact('projects'));
    }

    public function create()
    {
        Gate::authorize('create', Project::class);

        return $this->form(new Project(['status' => 'active']));
    }

    public function edit(Project $project)
    {
        Gate::authorize('update', $project);

        return $this->form($project);
    }

    private function form(Project $project)
    {
        return view('projects.form', ['project' => $project, 'clients' => Client::visibleTo(auth()->user())->orderBy('name')->get(), 'teams' => Team::all(), 'users' => User::where('active', true)->orderBy('name')->get()]);
    }

    public function store(Request $r)
    {
        Gate::authorize('create', Project::class);
        $project = $this->save($r, new Project(['created_by' => $r->user()->id]));

        return redirect()->route('projects.show', $project)->with('status', 'Project created.');
    }

    public function update(Request $r, Project $project)
    {
        Gate::authorize('update', $project);
        $this->save($r, $project);

        return redirect()->route('projects.show', $project)->with('status', 'Project updated.');
    }

    private function save(Request $r, Project $project): Project
    {
        $data = $r->validate(['name' => 'required|string|max:180', 'client_id' => 'required|exists:clients,id', 'team_id' => 'required|exists:teams,id', 'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('active', true)], 'members' => 'array', 'members.*' => [Rule::exists('users', 'id')->where('active', true)], 'status' => ['required', Rule::in(['planning', 'active', 'on_hold', 'completed', 'cancelled'])], 'due_date' => 'nullable|date', 'notes' => 'nullable|string|max:15000']);
        Gate::authorize('view', Client::findOrFail($data['client_id']));
        $members = $data['members'] ?? [];
        unset($data['members']);
        $before = $project->exists ? $project->members()->pluck('users.id')->all() : [];
        DB::transaction(function () use ($project, $data, $members, $before) {
            $project->fill($data)->save();
            $project->members()->sync($members);
            if ($members != $before) {
                Audit::record($project, 'members_assigned', ['user_ids' => $members, 'previous' => $before]);
            }
        });
        Notify::send(User::whereIn('id', array_unique([...array_diff($members, $before), $project->assigned_to]))->get(), 'Project assignment: '.$project->name, route('projects.show', $project));

        return $project;
    }

    public function show(Project $project)
    {
        Gate::authorize('view', $project);
        $tasks = $project->tasks()->visibleTo(auth()->user())->with('assignee')->latest()->get();
        $history = AuditLog::where('subject_type', Project::class)->where('subject_id', $project->id)->with('user')->latest('id')->limit(50)->get();

        return view('projects.show',compact('project','tasks','history'));
    }
}
