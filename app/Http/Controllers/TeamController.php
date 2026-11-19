<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Support\Audit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('users.teams', ['teams' => Team::withCount(['members', 'users'])->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_-]*$/D', Rule::unique('teams', 'slug')],
        ]);
        DB::transaction(function () use ($data) {
            $team = Team::create($data);
            Audit::record($team, 'team_created', $data);
        });

        return redirect()->route('teams.index')->with('status', 'Team created. Assign employees through People & access.');
    }

    public function update(Request $request, Team $team)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['name' => 'required|string|max:80', 'slug' => 'prohibited']);
        DB::transaction(function () use ($team, $data) {
            $locked = Team::lockForUpdate()->findOrFail($team->id);
            $previous = $locked->name;
            $locked->update($data);
            Audit::record($locked, 'team_renamed', ['name' => $locked->name, 'previous' => $previous]);
        });

        return redirect()->route('teams.index')->with('status', 'Team name updated.');
    }

    public function destroy(Request $request, Team $team)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $slug = $team->slug;
        try {
            DB::transaction(function () use ($team) {
                $locked = Team::lockForUpdate()->findOrFail($team->id);
                foreach (['users', 'team_user', 'projects', 'tasks', 'release_note_team'] as $table) {
                    if (DB::table($table)->where('team_id', $locked->id)->exists()) {
                        throw ValidationException::withMessages(['team' => 'This team is still linked to employees, work or software updates. Reassign those records before deleting it.']);
                    }
                }
                Audit::record($locked, 'team_deleted', ['name' => $locked->name, 'slug' => $locked->slug]);
                $locked->delete();
            });
        } catch (QueryException) {
            // Foreign keys also protect against new references created during this request.
            throw ValidationException::withMessages(['team' => 'This team is linked to other records and cannot be deleted yet.']);
        }
        if ($request->session()->get('active_dashboard_team') === $slug) {
            $request->session()->forget('active_dashboard_team');
        }

        return redirect()->route('teams.index')->with('status', 'Team deleted.');
    }
}
