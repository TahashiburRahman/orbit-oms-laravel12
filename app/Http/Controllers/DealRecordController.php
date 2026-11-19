<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Rules\DomainOrUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DealRecordController extends Controller
{
    public function index(Request $r, string $kind = 'referral')
    {
        $this->kind($r, $kind, false);
        $r->validate(['deal_status' => ['nullable', Rule::in(array_keys(Lead::DEAL_STATUSES))]]);
        $records = Lead::visibleTo($r->user())->where('record_type', $kind)->when($r->boolean('mine'), fn ($q) => $q->where('created_by', $r->user()->id))->when($r->filled('deal_status'), fn ($q) => $q->where('deal_status', $r->deal_status))->when($r->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$r->string('q').'%'))->with('submitter', 'assignee')->latest()->paginate(20)->withQueryString();

        return view('work.deals-index', compact('records', 'kind'));
    }

    public function create(Request $r, string $kind = 'referral')
    {
        $this->kind($r, $kind, true);

        return view('work.deal-form', ['lead' => new Lead(['record_type' => $kind, 'deal_status' => 'in_progress']), 'kind' => $kind]);
    }

    public function store(Request $r, string $kind = 'referral')
    {
        $this->kind($r, $kind, true);
        if ($kind === 'referral' && ! $r->has('deal_status')) {
            $r->merge(['deal_status' => 'in_progress']);
        }$data = $this->data($r);
        if ($kind === 'referral' && ! $r->user()->inTeam('admin', 'marketing')) {
            abort_unless($data['deal_status'] === 'in_progress', 403);
        }
        $lead = DB::transaction(fn () => Lead::create($data + ['record_type' => $kind, 'source' => $kind === 'referral' ? 'Employee referral' : 'Marketing update', 'stage' => 'new', 'created_by' => $r->user()->id, 'submitted_by' => $r->user()->id, 'assigned_to' => $r->user()->id]));

        return redirect()->route('leads.show', $lead)->with('status', 'Client record saved. No client conversion has been performed.');
    }

    public function edit(Request $r, Lead $lead)
    {
        $this->authorizeEdit($r, $lead);

        return view('work.deal-form', ['lead' => $lead, 'kind' => $lead->record_type]);
    }

    public function update(Request $r, Lead $lead)
    {
        DB::transaction(function () use ($r, $lead) {
            $lead = Lead::lockForUpdate()->findOrFail($lead->id);
            $this->authorizeEdit($r, $lead);
            $data = $this->data($r);
            if (! $lead->canEdit($r->user())) {
                abort_unless($data['deal_status'] === $lead->deal_status, 403);
            }
            $lead->update($data);
        });

        return redirect()->route('leads.show', $lead)->with('status', 'Client record updated.');
    }

    private function authorizeEdit(Request $r, Lead $lead): void
    {
        Gate::authorize('view', $lead);
        abort_unless(in_array($lead->record_type, ['referral', 'marketing_update'], true), 422);
        abort_if($lead->converted_client_id, 422, 'Converted history is preserved.');
        abort_unless($lead->canEdit($r->user()) || $lead->canCorrectReferral($r->user()), 403);
    }

    private function kind(Request $r, string $kind, bool $create): void
    {
        abort_unless(in_array($kind, ['referral', 'marketing_update'], true), 404);
        if ($kind === 'marketing_update') {
            abort_unless($r->user()->inTeam('marketing') || (! $create && $r->user()->isAdmin()), 403);
        }
    }

    private function data(Request $r): array
    {
        return $r->validate(['name' => 'required|string|max:200', 'domain_name' => ['nullable', 'string', 'max:255', new DomainOrUrl], 'deal_status' => ['required', Rule::in(array_keys(Lead::DEAL_STATUSES))], 'record_type' => 'prohibited', 'submitted_by' => 'prohibited', 'created_by' => 'prohibited', 'assigned_to' => 'prohibited', 'stage' => 'prohibited', 'status' => 'prohibited', 'actions' => 'prohibited', 'classification' => 'prohibited', 'description' => 'prohibited', 'work_status' => 'prohibited', 'work_date' => 'prohibited', 'work_time' => 'prohibited', 'website_name' => 'prohibited']);
    }
}
