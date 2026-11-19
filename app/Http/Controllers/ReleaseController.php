<?php

namespace App\Http\Controllers;

use App\Models\ReleaseNote;
use App\Models\Team;
use App\Support\Audit;
use App\Support\ReleasePublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReleaseController extends Controller
{
    public function index(Request $request)
    {
        $releases = ReleaseNote::visibleTo($request->user())->with('targetTeams')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('version', 'like', '%'.$request->string('q').'%')->orWhere('summary', 'like', '%'.$request->string('q').'%')))
            ->latest('publish_at')->latest('id')->paginate(15)->withQueryString();

        return view('releases.index', compact('releases'));
    }

    public function create()
    {
        Gate::authorize('create', ReleaseNote::class);

        return view('releases.form', ['release' => new ReleaseNote(['status' => 'draft', 'publish_at' => now()]), 'teams' => Team::all()]);
    }

    public function store(Request $request, ReleasePublisher $publisher)
    {
        Gate::authorize('create', ReleaseNote::class);
        $data = $this->validated($request);
        $release = DB::transaction(function () use ($data, $request) {
            $release = ReleaseNote::create(collect($data)->except('teams')->all() + ['created_by' => $request->user()->id]);
            $release->targetTeams()->sync($data['teams']);
            Audit::record($release, 'target_teams_changed', ['team_ids' => $data['teams']]);

            return $release;
        });
        $publisher->notify($release);

        return redirect()->route('releases.show', $release)->with('success', 'Software update saved.');
    }

    public function show(ReleaseNote $release)
    {
        Gate::authorize('view', $release);
        $release->load('targetTeams');

        return view('releases.show', compact('release'));
    }

    public function edit(ReleaseNote $release)
    {
        Gate::authorize('update', $release);

        return view('releases.form', ['release' => $release->load('targetTeams'), 'teams' => Team::all()]);
    }

    public function update(Request $request, ReleaseNote $release, ReleasePublisher $publisher)
    {
        Gate::authorize('update', $release);
        $data = $this->validated($request, $release);
        DB::transaction(function () use ($data, $release) {
            $release->update(collect($data)->except('teams')->all());
            $old = $release->targetTeams()->pluck('teams.id')->sort()->values()->all();
            $release->targetTeams()->sync($data['teams']);
            if ($old !== collect($data['teams'])->map(fn ($id) => (int) $id)->sort()->values()->all()) {
                Audit::record($release, 'target_teams_changed', ['team_ids' => $data['teams']]);
            }
        });
        $publisher->notify($release);

        return redirect()->route('releases.show', $release)->with('success', 'Software update saved.');
    }

    private function validated(Request $request, ?ReleaseNote $release = null): array
    {
        return $request->validate([
            'version' => ['required', 'string', 'max:60', Rule::unique('release_notes')->ignore($release?->id)],
            'summary' => ['required', 'string', 'max:255'],
            'details' => ['required', 'string', 'max:30000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'publish_at' => ['nullable', 'required_if:status,published', 'date'],
            'teams' => ['required', 'array', 'min:1'],
            'teams.*' => ['required', 'integer', 'distinct', 'exists:teams,id'],
        ]);
    }
}
