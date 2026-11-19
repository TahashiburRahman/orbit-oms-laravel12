<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Support\Notify;
use App\Support\TaskClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function index(Request $r)
    {
        return $this->listing($r, false);
    }

    public function development(Request $r)
    {
        abort_unless($r->user()->inTeam('admin', 'operations', 'developer'), 403);

        return $this->listing($r, true);
    }

    private function listing(Request $r, bool $development)
    {
        $r->validate(['classification' => ['nullable', Rule::in(Task::CLASSIFICATIONS)], 'record_type' => ['nullable', Rule::in(['developer', 'operations', 'legacy'])]]);
        $tasks = Task::visibleTo($r->user())->with(['project', 'team', 'assignee'])
            ->when($development, fn ($q) => $q->where('work_type', '!=', 'general'))
            ->when($r->filled('classification'), fn ($q) => $q->where('classification', $r->classification))
            ->when($r->filled('record_type'), fn ($q) => $q->where('record_type', $r->record_type))
            ->when($r->search, fn ($q) => $q->where('title', 'like', '%'.$r->search.'%'))
            ->when($r->status, fn ($q) => $q->withWorkStatus($r->status))
            ->when($r->boolean('mine'), fn ($q) => $q->where('assigned_to', $r->user()->id))
            ->when($r->boolean('overdue'), fn ($q) => $q->whereDate('due_date', '<', today())->whereNotIn('status', ['verified', 'completed']))->latest()->paginate(20)->withQueryString();

        return view('tasks.index', compact('tasks', 'development'));
    }

    public function create(Request $r)
    {
        Gate::authorize('create', Task::class);
        abort_if(! $r->user()->inTeam('admin', 'operations') && $r->query('type', 'general') !== 'general', 403);

        return $this->form(new Task(['work_type' => $r->query('type', 'general'), 'status' => 'open', 'priority' => 'normal']));
    }

    public function edit(Task $task)
    {
        Gate::authorize('update', $task);
        if ($task->structured_fields_required) {
            return redirect()->route('work.edit', $task);
        }

        return $this->form($task);
    }

    private function form(Task $task)
    {
        return view('tasks.form', ['task' => $task, 'projects' => Project::visibleTo(auth()->user())->orderBy('name')->get(), 'teams' => Team::all(), 'users' => User::where('active', true)->with('team')->orderBy('name')->get()]);
    }

    public function store(Request $r)
    {
        Gate::authorize('create', Task::class);
        $task = DB::transaction(fn () => $this->save($r, new Task(['created_by' => $r->user()->id, 'status' => 'open'])));

        return redirect()->route('tasks.show', $task)->with('status', 'Task assigned.');
    }

    public function update(Request $r, Task $task)
    {
        Gate::authorize('update', $task);
        if ($task->structured_fields_required || $r->hasAny(['work_status', 'classification', 'reported_by', 'reported_at', 'record_type'])) {
            return app(TeamWorkController::class)->update($r, $task);
        }
        DB::transaction(fn () => $this->save($r, $task));

        return redirect()->route('tasks.show', $task)->with('status', 'Task updated.');
    }

    private function save(Request $r, Task $task): Task
    {
        if ($task->exists) {
            $task = Task::lockForUpdate()->findOrFail($task->id);
            Gate::authorize('update', $task);
        }
        $manager = $r->user()->inTeam('admin', 'operations');
        $rules = ['status' => ['required', Rule::in(['open', 'in_progress', 'blocked', 'submitted', 'verified', 'completed'])], 'blocker' => 'nullable|string|max:6000', 'handover_notes' => 'nullable|string|max:10000', 'configuration_summary' => 'nullable|string|max:10000', 'verification_results' => 'nullable|string|max:10000'];
        if ($manager) {
            $presence = $task->exists ? 'sometimes|required' : 'required';
            $choicePresence = $task->exists ? ['sometimes', 'required'] : ['required'];
            $rules += ['title' => $presence.'|string|max:180', 'description' => 'nullable|string|max:15000', 'client_id' => 'nullable|exists:clients,id', 'project_id' => 'nullable|exists:projects,id', 'team_id' => $presence.'|exists:teams,id', 'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('active', true)], 'work_type' => [...$choicePresence, Rule::in(['general', 'domain', 'pixel', 'courier', 'landing_page'])], 'priority' => [...$choicePresence, Rule::in(['low', 'normal', 'high', 'urgent'])], 'due_date' => 'nullable|date'];
            $rules = array_replace($rules, TaskClient::rules());
        } elseif (! $task->exists) {
            $rules += TaskClient::rules() + ['title' => 'required|string|max:180', 'description' => 'nullable|string|max:15000', 'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])], 'due_date' => 'nullable|date', 'project_id' => 'nullable|exists:projects,id', 'team_id' => 'prohibited', 'assigned_to' => 'prohibited', 'work_type' => ['sometimes', Rule::in(['general'])]];
        }
        $data = $r->validate($rules);
        if (! $manager && ! $task->exists) {
            $data['team_id'] = $r->user()->team_id;
            $data['assigned_to'] = $r->user()->id;
            $data['work_type'] = 'general';
        }
        $type = $data['work_type'] ?? $task->work_type;
        if ($task->exists && ($data['work_type'] ?? $type) !== $task->work_type) {
            throw ValidationException::withMessages(['work_type' => 'The work type is fixed after creation.']);
        }
        if ($task->exists && in_array($task->status, ['verified', 'completed']) && ! $r->user()->isAdmin()) {
            throw ValidationException::withMessages(['status' => 'Closed tasks can only be reopened by an administrator.']);
        }
        $projectId = array_key_exists('project_id', $data) ? $data['project_id'] : $task->project_id;
        $project = null;
        if ($projectId) {
            $project = Project::findOrFail($projectId);
            Gate::authorize('view', $project);
            if (empty($data['client_id']) && empty($data['client_name'])) {
                $data['client_id'] = $project->client_id;
                $data['client_name'] = $project->client?->name;
            }
        }
        if (($manager || ! $task->exists) && ($r->hasAny(['client_id', 'client_name', 'client_website_id', 'domain_name', 'website_name']) || $project)) {
            $data = TaskClient::resolve($r, $data, $task);
        }
        if ($project) {
            $clientId = array_key_exists('client_id', $data) ? $data['client_id'] : $task->client_id;
            if ($clientId && (int) $clientId !== $project->client_id) {
                throw ValidationException::withMessages(['project_id' => 'Choose a project belonging to this client.']);
            }
            $data['client_id'] = $project->client_id;
            $data['client_name'] = $project->client?->name;
        }
        if ($type !== 'general') {
            $clientId = array_key_exists('client_id', $data) ? $data['client_id'] : $task->client_id;
            $projectId = array_key_exists('project_id', $data) ? $data['project_id'] : $task->project_id;
            if (! $clientId && ! $projectId) {
                throw ValidationException::withMessages(['client_id' => 'Development requests must link to a client or project.']);
            }
            if (! $task->exists && $data['status'] !== 'open') {
                throw ValidationException::withMessages(['status' => 'New development requests start open.']);
            }
            if ($manager && Team::findOrFail($data['team_id'] ?? $task->team_id)->slug !== 'developer') {
                throw ValidationException::withMessages(['team_id' => 'Development requests belong to Developer.']);
            }
            if (! empty($data['assigned_to']) && ! User::findOrFail($data['assigned_to'])->inTeam('developer')) {
                throw ValidationException::withMessages(['assigned_to' => 'Assign technical work to a developer.']);
            }
            if ($data['status'] === 'completed') {
                throw ValidationException::withMessages(['status' => 'Submit technical work, then Operations verifies and closes it.']);
            }
            $statusChanged = ! $task->exists || $data['status'] !== $task->status;
            if ($statusChanged && $data['status'] === 'verified') {
                abort_unless($manager, 403);
                if ($task->status !== 'submitted' || empty($data['verification_results'])) {
                    throw ValidationException::withMessages(['verification_results' => 'Verify submitted work and record the verification result.']);
                }
                $data['verified_by'] = $r->user()->id;
                $data['verified_at'] = now();
            } elseif ($statusChanged && $task->exists && ! $r->user()->isAdmin()) {
                abort_unless($r->user()->inTeam('developer') && ($task->assigned_to === $r->user()->id || (! $task->assigned_to && in_array($task->team_id, $r->user()->teamIds(), true))), 403);
                if (! in_array($data['status'], ['open', 'in_progress', 'blocked', 'submitted'])) {
                    abort(422);
                }
                if ($data['status'] === 'submitted' && (empty($data['configuration_summary']) || empty($data['handover_notes']))) {
                    throw ValidationException::withMessages(['handover_notes' => 'Submission needs a configuration summary and handover notes.']);
                }
            }
            if (! $manager) {
                unset($data['verification_results']);
            }
            if ($task->status === 'verified' && $data['status'] !== 'verified') {
                $data['verified_at'] = null;
                $data['verified_by'] = null;
                $data['verification_results'] = null;
            }
        } elseif (in_array($data['status'], ['submitted', 'verified'])) {
            throw ValidationException::withMessages(['status' => 'Use completed to finish a general task.']);
        }
        if ($data['status'] === 'blocked' && empty($data['blocker'])) {
            throw ValidationException::withMessages(['blocker' => 'Describe the blocker.']);
        }
        if (! $task->exists || array_key_exists('assigned_to', $data) || array_key_exists('team_id', $data)) {
            $assigneeId = array_key_exists('assigned_to', $data) ? $data['assigned_to'] : $task->assigned_to;
            $teamId = (int) ($data['team_id'] ?? $task->team_id);
            if ($assigneeId && ! in_array($teamId, User::findOrFail($assigneeId)->teamIds(), true)) {
                throw ValidationException::withMessages(['assigned_to' => 'Choose an employee who belongs to the assigned team. Use project members to share work across teams.']);
            }
        }
        $oldStatus = $task->status;
        $oldAssignee = $task->assigned_to;
        DB::transaction(fn () => $task->fill($data)->save());
        if ($task->assigned_to && $oldAssignee !== $task->assigned_to) {
            Notify::send([$task->assignee], 'Task assigned: '.$task->title, route('tasks.show', $task));
        }
        if ($oldStatus !== $task->status) {
            Notify::send([$task->creator, $task->assignee], 'Task '.$task->status.': '.$task->title, route('tasks.show', $task));
        }

        return $task;
    }

    public function show(Task $task)
    {
        Gate::authorize('view', $task);
        $comments = $task->comments()->with('user')->oldest()->get();
        $history = AuditLog::where('subject_type', Task::class)->where('subject_id', $task->id)->with('user')->latest('id')->limit(60)->get();

        $assignmentUsers = auth()->user()->inTeam('admin', 'operations') ? User::where('active', true)->inTeams($task->team->slug)->orderBy('name')->get() : collect();

        return view('tasks.show', compact('task', 'comments', 'history', 'assignmentUsers'));
    }

    public function assign(Request $r, Task $task)
    {
        abort_unless($r->user()->inTeam('admin', 'operations'), 403);
        Gate::authorize('update', $task);
        $data = $r->validate(['assigned_to' => ['nullable', Rule::exists('users', 'id')->where('active', true)]]);
        DB::transaction(function () use ($task, $data) {
            $locked = Task::lockForUpdate()->findOrFail($task->id);
            Gate::authorize('update', $locked);
            $id = $data['assigned_to'] ?? null;
            if ($id && ! in_array($locked->team_id, User::findOrFail($id)->teamIds(), true)) {
                throw ValidationException::withMessages(['assigned_to' => 'Choose a member of the assigned team.']);
            }$locked->update(['assigned_to' => $id]);
            Notify::send([$locked->assignee], 'Task assigned: '.$locked->title, route('tasks.show', $locked));
        });

        return back()->with('status', 'Assignment updated.');
    }

    public function comment(Request $r, Task $task)
    {
        Gate::authorize('view', $task);
        $data = $r->validate(['body' => 'required|string|max:10000']);
        $task->comments()->create([...$data, 'user_id' => $r->user()->id]);
        Notify::send([$task->creator, $task->assignee], 'New task comment: '.$task->title, route('tasks.show', $task));

        return back()->with('status', 'Comment added.');
    }
}
