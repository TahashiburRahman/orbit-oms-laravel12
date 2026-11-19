<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Team;
use App\Support\Notify;
use App\Support\TaskClient;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeamWorkController extends Controller
{
    public function create(Request $r, string $team)
    {
        $this->membership($r, $team);

        return view('work.form', ['task' => new Task(['record_type' => $team, 'structured_fields_required' => true]), 'team' => $team]);
    }

    public function edit(Request $r, Task $task)
    {
        Gate::authorize('update', $task);
        $team = $this->type($task);
        abort_unless($r->user()->inTeam($team) || $r->user()->isAdmin(), 403);

        return view('work.form', compact('task', 'team'));
    }

    public function store(Request $r, string $team)
    {
        $this->membership($r, $team);
        $task = new Task(['record_type' => $team, 'structured_fields_required' => true, 'team_id' => Team::where('slug', $team)->value('id'), 'created_by' => $r->user()->id, 'assigned_to' => $r->user()->id, 'work_type' => 'general', 'priority' => 'normal', 'status' => 'open']);
        $task = $this->persist($r, $task, $team);

        return redirect()->route('tasks.show', $task)->with('status', 'Work record saved.');
    }

    public function update(Request $r, Task $task)
    {
        Gate::authorize('update', $task);
        $team = $this->type($task);
        abort_unless($r->user()->inTeam($team) || $r->user()->isAdmin(), 403);
        $this->persist($r, $task, $team);

        return redirect()->route('tasks.show', $task)->with('status', 'Work update saved.');
    }

    private function membership(Request $r, string $team): void
    {
        abort_unless(in_array($team, ['developer', 'operations'], true), 404);
        abort_unless(Team::where('slug', $team)->exists(), 404);
        abort_unless($r->user()->inTeam($team), 403);
    }

    private function type(Task $task): string
    {
        $type = $task->record_type === 'legacy' ? $task->team->slug : $task->record_type;
        abort_unless(in_array($type, ['developer', 'operations'], true), 422);

        return $type;
    }

    private function persist(Request $r, Task $task, string $team): Task
    {
        return DB::transaction(function () use ($r, $task, $team) {
            if ($task->exists) {
                $task = Task::lockForUpdate()->findOrFail($task->id);
                Gate::authorize('update', $task);
            }
            $required = $task->structured_fields_required ? 'required' : 'nullable';
            $rules = TaskClient::rules() + ['work_status' => ['required', Rule::in(array_keys($task->work_status_options))], 'actions' => 'nullable|string|max:10000', 'record_type' => 'prohibited', 'created_by' => 'prohibited', 'assigned_to' => 'prohibited', 'team_id' => 'prohibited', 'reported_by' => 'prohibited', 'name' => 'prohibited', 'deal_status' => 'prohibited', 'status' => 'prohibited', 'work_type' => 'prohibited', 'reported_at' => 'prohibited'];
            $rules += ['description' => $required.'|string|max:15000'];
            if ($team === 'developer') {
                $rules += ['work_date' => $required.'|date_format:Y-m-d', 'work_time' => $required.'|date_format:H:i', 'classification' => [$required, Rule::in(Task::CLASSIFICATIONS)]];
            } else {
                $rules += ['work_date' => 'prohibited', 'work_time' => 'prohibited', 'classification' => 'prohibited'];
            }
            $data = $r->validate($rules);
            if ($task->structured_fields_required && $data['work_status'] === 'completed' && empty($data['actions'])) {
                throw ValidationException::withMessages(['actions' => 'Record what was done before marking work completed.']);
            }
            if ($task->exists && in_array($task->status, ['verified', 'completed']) && ! $r->user()->isAdmin()) {
                throw ValidationException::withMessages(['work_status' => 'Only Admin can edit closed work.']);
            }
            $target = match ($data['work_status']) {
                'pending' => 'open','completed' => 'completed','in_progress' => 'in_progress',default => $task->status
            };
            if ($task->isDevelopment()) {
                if (in_array($task->status, ['submitted', 'verified'], true)) {
                    if ($target === 'completed') {
                        $target = $task->status;
                    } elseif (! $r->user()->isAdmin()) {
                        throw ValidationException::withMessages(['work_status' => 'Submitted work remains in review until Operations verifies it.']);
                    }
                } elseif ($target === 'completed') {
                    abort_unless($r->user()->isSuperAdmin() || ($r->user()->inTeam('developer') && ($task->assigned_to === $r->user()->id || ! $task->assigned_to)), 403);
                    if (empty($data['actions'])) {
                        throw ValidationException::withMessages(['actions' => 'Describe the completed work before submitting it for verification.']);
                    }
                    $target = 'submitted';
                    $task->configuration_summary = $task->configuration_summary ?: $data['actions'];
                    $task->handover_notes = $task->handover_notes ?: $data['actions'];
                } else {
                    abort_unless($r->user()->isAdmin() || ($r->user()->inTeam('developer') && ($task->assigned_to === $r->user()->id || ! $task->assigned_to)), 403);
                }
            }
            $old = $task->status;
            if ($old === 'verified' && $target !== 'verified') {
                $task->verified_at = null;
                $task->verified_by = null;
                $task->verification_results = null;
            }
            if ($r->hasAny(['client_id', 'client_name', 'client_website_id']) || $task->client_id) {
                $data = TaskClient::resolve($r, $data, $task);
                if ($task->project_id && (int) $data['client_id'] !== $task->project->client_id) {
                    throw ValidationException::withMessages(['client_name' => 'Keep the client belonging to this task\'s project.']);
                }
            }
            if ($task->structured_fields_required && empty($data['domain_name'])) {
                throw ValidationException::withMessages(['domain_name' => 'Choose or enter a domain for this work.']);
            }
            $task->fill(collect($data)->except(['work_status', 'work_date', 'work_time'])->all());
            $task->status = $target;
            $task->record_type = $team;
            $task->reported_by = $r->user()->id;
            if ($team === 'developer' && ! empty($data['work_date']) && ! empty($data['work_time'])) {
                $task->reported_at = Carbon::createFromFormat('Y-m-d H:i', $data['work_date'].' '.$data['work_time'], 'Asia/Dhaka')->startOfMinute();
            }
            if (! $task->exists || $task->structured_fields_required) {
                $task->title = Str::limit($task->description, 170, '');
            }
            $task->save();
            if ($old !== $target) {
                Notify::send([$task->creator, $task->assignee], 'Work status updated: '.$task->title, route('tasks.show', $task));
            }

            return $task;
        });
    }
}
