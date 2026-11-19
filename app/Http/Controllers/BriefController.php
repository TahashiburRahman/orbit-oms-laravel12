<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Brief;
use App\Models\Campaign;
use App\Models\Deliverable;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BriefController extends Controller
{
    private function graphics()
    {
        return User::where('active', true)->inTeams('graphics')->orderBy('name')->get();
    }

    private function formData(Request $r, Brief $brief): array
    {
        return ['brief' => $brief, 'graphics' => $this->graphics(), 'campaigns' => Campaign::visibleTo($r->user())->orderBy('name')->get()];
    }

    public function index(Request $r)
    {
        $briefs = Brief::visibleTo($r->user())->with('requester', 'assignee')->when($r->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$r->string('q').'%'))->when($r->filled('status'), fn ($q) => $q->where('status', $r->string('status')))->orderByRaw('due_date IS NULL')->orderBy('due_date')->paginate(20)->withQueryString();

        return view('marketing.briefs.index', compact('briefs'));
    }

    public function create(Request $r)
    {
        Gate::authorize('create', Brief::class);

        return view('marketing.briefs.form', $this->formData($r, new Brief));
    }

    public function store(Request $r)
    {
        Gate::authorize('create', Brief::class);
        $brief = Brief::create($this->validated($r) + ['requested_by' => $r->user()->id, 'status' => 'open']);
        if ($brief->assignee) {
            Notify::send([$brief->assignee], 'Creative brief assigned: '.$brief->title, route('briefs.show', $brief, false));
        } else {
            Notify::send($this->graphics(), 'New creative brief available: '.$brief->title, route('briefs.show', $brief, false));
        }

        return redirect()->route('briefs.show', $brief)->with('success', 'Brief created.');
    }

    public function show(Brief $brief)
    {
        Gate::authorize('view', $brief);
        $brief->load(['deliverables' => fn ($q) => $q->orderByDesc('version'), 'deliverables.uploader', 'deliverables.reviewer', 'requester', 'assignee']);
        $activities = AuditLog::where('subject_type', Brief::class)->where('subject_id', $brief->id)->with('user')->orderBy('id')->get();
        $staffNames = User::pluck('name', 'id');

        return view('marketing.briefs.show', compact('brief', 'activities', 'staffNames'));
    }

    public function edit(Request $r, Brief $brief)
    {
        Gate::authorize('update', $brief);
        abort_if($brief->status === 'approved', 422, 'Approved brief is preserved. Create a new brief for additional work.');

        return view('marketing.briefs.form', $this->formData($r, $brief));
    }

    public function update(Request $r, Brief $brief)
    {
        Gate::authorize('update', $brief);
        abort_if(in_array($brief->status, ['submitted', 'approved']), 422, 'Review submitted work before changing the brief.');
        $old = $brief->assigned_to;
        $brief->update($this->validated($r));
        if ($old != $brief->assigned_to && $brief->assignee()->exists()) {
            Notify::send([$brief->assignee()->first()], 'Creative brief assigned: '.$brief->title, route('briefs.show', $brief, false));
        }

        return redirect()->route('briefs.show', $brief)->with('success', 'Brief updated.');
    }

    public function claim(Request $r, Brief $brief)
    {
        abort_unless($r->user()->inTeam('graphics'), 403);
        Gate::authorize('view', $brief);
        DB::transaction(function () use ($brief, $r) {
            $b = Brief::lockForUpdate()->findOrFail($brief->id);
            abort_unless(! $b->assigned_to && $b->status === 'open', 422, 'This brief has already been claimed.');
            $b->update(['assigned_to' => $r->user()->id, 'status' => 'in_progress']);
        });
        Notify::send([$brief->requester], 'Your brief was claimed: '.$brief->title, route('briefs.show', $brief, false));

        return back()->with('success', 'Brief assigned to you.');
    }

    public function upload(Request $r, Brief $brief)
    {
        Gate::authorize('view', $brief);
        abort_unless($brief->canProduce($r->user()), 403);
        $data = $r->validate(['file' => 'required|file|max:51200|mimes:pdf,png,jpg,jpeg,webp,gif,mp4,webm,mov,zip', 'notes' => 'nullable|string|max:10000']);
        $file = $r->file('file');
        $path = null;
        try {
            DB::transaction(function () use ($brief, $r, $file, $data, &$path) {
                $b = Brief::lockForUpdate()->findOrFail($brief->id);
                abort_unless($b->canProduce($r->user()), 403);
                abort_unless(in_array($b->status, ['open', 'in_progress', 'revision_requested']), 422, 'This brief cannot accept a new draft now.');
                $path = $file->store('briefs', 'local');
                $b->deliverables()->create(['version' => ($b->deliverables()->max('version') ?? 0) + 1, 'uploaded_by' => $r->user()->id, 'path' => $path, 'original_name' => basename($file->getClientOriginalName()), 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'notes' => $data['notes'] ?? null, 'status' => 'draft']);
                $b->update(['status' => 'in_progress']);
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            } throw $e;
        }

        return back()->with('success', 'Private draft uploaded. Submit it when ready for review.');
    }

    public function submit(Request $r, Brief $brief)
    {
        Gate::authorize('view', $brief);
        abort_unless($brief->canProduce($r->user()), 403);
        DB::transaction(function () use ($brief, $r) {
            $b = Brief::lockForUpdate()->findOrFail($brief->id);
            abort_unless($b->canProduce($r->user()), 403);
            abort_unless(in_array($b->status, ['in_progress', 'revision_requested']), 422, 'This brief is not ready for submission.');
            $d = $b->deliverables()->orderByDesc('version')->first();
            abort_unless($d && $d->status === 'draft', 422, 'Upload a new draft before submitting.');
            $d->update(['status' => 'submitted']);
            $b->update(['status' => 'submitted']);
        });
        Notify::send([$brief->requester], 'Review requested: '.$brief->title, route('briefs.show', $brief, false));

        return back()->with('success', 'Draft submitted for review.');
    }

    public function review(Request $r, Brief $brief)
    {
        Gate::authorize('update', $brief);
        $data = $r->validate(['decision' => ['required', Rule::in(['approved', 'revision_requested'])], 'review_notes' => 'required|string|max:10000']);
        DB::transaction(function () use ($brief, $r, $data) {
            $b = Brief::lockForUpdate()->findOrFail($brief->id);
            abort_unless($b->status === 'submitted', 422, 'Only submitted work can be reviewed.');
            $d = $b->deliverables()->orderByDesc('version')->firstOrFail();
            $d->update(['status' => $data['decision'], 'review_notes' => $data['review_notes'], 'reviewed_by' => $r->user()->id, 'reviewed_at' => now()]);
            $b->update(['status' => $data['decision']]);
        });
        if ($brief->assignee) {
            Notify::send([$brief->assignee], 'Brief '.$data['decision'].': '.$brief->title, route('briefs.show', $brief, false));
        }

        return back()->with('success', 'Review recorded.');
    }

    public function download(Deliverable $deliverable)
    {
        Gate::authorize('view', $deliverable);
        abort_unless(Storage::disk('local')->exists($deliverable->path), 404);

        return Storage::disk('local')->download($deliverable->path, $deliverable->original_name, ['X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox"]);
    }

    private function validated(Request $r): array
    {
        return $r->validate(['title' => 'required|string|max:200', 'type' => ['required', Rule::in(['graphic', 'software_video'])], 'description' => 'required|string|max:20000', 'due_date' => 'nullable|date', 'assigned_to' => ['nullable', Rule::in($this->graphics()->modelKeys())], 'campaign_id' => ['nullable', Rule::in(Campaign::visibleTo($r->user())->pluck('id')->all())]]);
    }
}
