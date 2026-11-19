<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignPost;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    private function marketers()
    {
        return User::where('active', true)->inTeams('admin', 'marketing')->orderBy('name')->get();
    }

    public function index(Request $r)
    {
        $campaigns = Campaign::visibleTo($r->user())->withCount('posts', 'briefs')->when($r->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$r->string('q').'%'))->latest()->paginate(20)->withQueryString();

        return view('marketing.campaigns.index', compact('campaigns'));
    }

    public function create()
    {
        Gate::authorize('create', Campaign::class);

        return view('marketing.campaigns.form', ['campaign' => new Campaign, 'marketers' => $this->marketers()]);
    }

    public function store(Request $r)
    {
        Gate::authorize('create', Campaign::class);
        $campaign = Campaign::create($this->validated($r) + ['created_by' => $r->user()->id]);
        Notify::send([$campaign->assignee], 'Campaign assigned: '.$campaign->name, route('campaigns.show', $campaign, false));

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaign created.');
    }

    public function show(Request $r, Campaign $campaign)
    {
        Gate::authorize('view', $campaign);
        $campaign->load(['posts', 'briefs' => fn ($q) => $q->visibleTo($r->user())]);

        return view('marketing.campaigns.show', compact('campaign'));
    }

    public function edit(Campaign $campaign)
    {
        Gate::authorize('update', $campaign);

        return view('marketing.campaigns.form', ['campaign' => $campaign, 'marketers' => $this->marketers()]);
    }

    public function update(Request $r, Campaign $campaign)
    {
        Gate::authorize('update', $campaign);
        $old = $campaign->assigned_to;
        $campaign->update($this->validated($r));
        if ($old != $campaign->assigned_to) {
            Notify::send([$campaign->assignee()->first()], 'Campaign assigned: '.$campaign->name, route('campaigns.show', $campaign, false));
        }

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaign updated.');
    }

    public function storePost(Request $r, Campaign $campaign)
    {
        Gate::authorize('update', $campaign);
        $campaign->posts()->create($this->postData($r));

        return back()->with('success', 'Post added to schedule.');
    }

    public function editPost(CampaignPost $post)
    {
        Gate::authorize('update', $post);

        return view('marketing.campaigns.post', compact('post'));
    }

    public function updatePost(Request $r, CampaignPost $post)
    {
        Gate::authorize('update', $post);
        $post->update($this->postData($r));

        return redirect()->route('campaigns.show', $post->campaign)->with('success', 'Post schedule updated.');
    }

    private function validated(Request $r): array
    {
        return $r->validate(['name' => 'required|string|max:200', 'description' => 'nullable|string|max:20000', 'status' => ['required', Rule::in(['planning', 'active', 'paused', 'completed'])], 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at', 'assigned_to' => ['required', Rule::in($this->marketers()->modelKeys())]]);
    }

    private function postData(Request $r): array
    {
        return $r->validate(['title' => 'required|string|max:200', 'channel' => 'required|string|max:80', 'content' => 'required|string|max:20000', 'scheduled_at' => 'nullable|date', 'status' => ['required', Rule::in(['draft', 'scheduled', 'published', 'cancelled'])]]);
    }
}
