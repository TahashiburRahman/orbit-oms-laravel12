<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\ReleaseNote;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function redirect(Request $request)
    {
        $user = $request->user();
        $team = $user->team ?? ($user->isSuperAdmin() ? Team::orderBy('id')->first() : $user->teams()->orderBy('teams.id')->first());
        abort_unless($team, 403);

        return redirect()->route('dashboard.team', $team->slug);
    }

    public function show(Request $request, string $team)
    {
        $user = $request->user();
        $dashboardTeam = Team::where('slug', $team)->firstOrFail();
        abort_unless($user->isSuperAdmin() || $user->inTeam($team), 403);
        $request->session()->put('active_dashboard_team', $team);
        $taskQuery = Task::visibleTo($user);
        $openStatuses = ['open', 'in_progress', 'blocked', 'submitted'];
        $statusCounts = (clone $taskQuery)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $taskTotal = (int) $statusCounts->sum();
        $finishedTasks = (int) $statusCounts->get('completed', 0) + (int) $statusCounts->get('verified', 0);
        $completionRate = $taskTotal ? (int) round($finishedTasks / $taskTotal * 100) : 0;
        $taskStatuses = collect([
            'open' => 'Open',
            'in_progress' => 'In progress',
            'blocked' => 'Blocked',
            'submitted' => 'In review',
            'verified' => 'Verified',
            'completed' => 'Completed',
        ])->map(fn ($label, $status) => [
            'status' => $status,
            'label' => $label,
            'count' => (int) $statusCounts->get($status, 0),
            'percentage' => $taskTotal ? round((int) $statusCounts->get($status, 0) / $taskTotal * 100, 1) : 0,
        ])->values();
        $dashboardTeamName = $dashboardTeam->name;
        $stats = [
            ['label' => 'Open tasks', 'value' => (clone $taskQuery)->whereIn('status', $openStatuses)->count(), 'note' => 'Across your accessible work', 'tone' => 'neutral', 'route' => 'tasks.index', 'query' => []],
            ['label' => 'Overdue', 'value' => (clone $taskQuery)->whereIn('status', $openStatuses)->whereDate('due_date', '<', today())->count(), 'note' => 'Past the scheduled due date', 'tone' => 'danger', 'route' => 'tasks.index', 'query' => ['overdue' => 1]],
            ['label' => 'Blocked', 'value' => (clone $taskQuery)->where('status', 'blocked')->count(), 'note' => 'Waiting on a resolution', 'tone' => 'warning', 'route' => 'tasks.index', 'query' => ['status' => 'blocked']],
            ['label' => 'Projects', 'value' => Project::visibleTo($user)->count(), 'note' => 'Shared with your workspace', 'tone' => 'neutral', 'route' => 'projects.index', 'query' => []],
        ];
        $tasks = (clone $taskQuery)->with('assignee')->whereIn('status', $openStatuses)->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('id')->limit(7)->get();
        $projects = Project::visibleTo($user)->latest('id')->limit(4)->get();
        $releases = ReleaseNote::visibleTo($user)->published()->latest('publish_at')->limit(3)->get();
        $special = match ($team) {
            'admin' => ['label' => 'Company clients', 'value' => Client::visibleTo($user)->count(), 'description' => 'Manage people, projects, approvals, and company activity.', 'route' => 'clients.index', 'action' => 'View clients'],
            'operations' => ['label' => 'Development requests', 'value' => (clone $taskQuery)->where('work_type', '!=', 'general')->count(), 'description' => 'Onboard clients, coordinate technical setup, and verify handovers.', 'route' => 'development.index', 'action' => 'Review development work'],
            'marketing' => ['label' => 'Your leads', 'value' => $this->countFor('App\\Models\\Lead', $user), 'description' => 'Follow up with leads, plan campaigns, and keep client conversations moving.', 'route' => 'leads.index', 'action' => 'Open lead pipeline'],
            'graphics' => ['label' => 'Creative briefs', 'value' => $this->countFor('App\\Models\\Brief', $user), 'description' => 'Turn briefs into useful graphics and videos, then share your work for review.', 'route' => 'briefs.index', 'action' => 'Open creative briefs'],
            'developer' => ['label' => 'Technical assignments', 'value' => (clone $taskQuery)->where('work_type', '!=', 'general')->count(), 'description' => 'Complete technical setup, document blockers, and hand work over for verification.', 'route' => 'development.index', 'action' => 'Open development work'],
            default => ['label' => 'Team work', 'value' => (clone $taskQuery)->count(), 'description' => 'Track accessible tasks, shared projects and company updates.', 'route' => 'tasks.index', 'action' => 'Open tasks'],
        };

        return view('dashboards.show', compact('stats', 'tasks', 'projects', 'releases', 'special', 'taskStatuses', 'taskTotal', 'finishedTasks', 'completionRate', 'dashboardTeamName'));
    }

    private function countFor(string $model, $user): int
    {
        return class_exists($model) ? $model::visibleTo($user)->count() : 0;
    }
}
