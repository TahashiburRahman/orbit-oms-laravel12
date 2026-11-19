<?php

namespace App\Support;

use App\Models\Brief;
use App\Models\Campaign;
use App\Models\CampaignPost;
use App\Models\Client;
use App\Models\ClientQuery;
use App\Models\ClientWebsite;
use App\Models\Deliverable;
use App\Models\Lead;
use App\Models\PayrollCorrection;
use App\Models\PayrollRun;
use App\Models\Project;
use App\Models\ReleaseNote;
use App\Models\Task;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RecordDeletion
{
    public const RESOURCES = [
        'clients' => [Client::class, 'clients.index'],
        'websites' => [ClientWebsite::class, 'clients.index'],
        'projects' => [Project::class, 'projects.index'],
        'tasks' => [Task::class, 'tasks.index'],
        'leads' => [Lead::class, 'leads.index'],
        'campaigns' => [Campaign::class, 'campaigns.index'],
        'posts' => [CampaignPost::class, 'campaigns.index'],
        'briefs' => [Brief::class, 'briefs.index'],
        'deliverables' => [Deliverable::class, 'briefs.index'],
        'queries' => [ClientQuery::class, 'queries.index'],
        'releases' => [ReleaseNote::class, 'releases.index'],
        'payroll' => [PayrollRun::class, 'payroll.index'],
    ];

    public static function delete(string $resource, int $id): string
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
        abort_unless(isset(self::RESOURCES[$resource]), 404);
        [$class, $destination] = self::RESOURCES[$resource];
        try {
            DB::transaction(function () use ($resource, $class, $id) {
                $record = $class::lockForUpdate()->findOrFail($id);
                if ($record instanceof ClientWebsite) {
                    Gate::authorize('view', $record->client);
                } else {
                    Gate::authorize('view', $record);
                }
                switch ($resource) {
                    case 'clients':
                        self::requireNoReferences($record->projects()->exists() || $record->tasks()->exists() || Lead::where('converted_client_id', $id)->exists(), 'Remove linked projects and tasks first. Converted lead history also keeps this client in use.');
                        foreach ($record->websites()->lockForUpdate()->get() as $website) {
                            self::requireNoReferences(Task::where('client_website_id', $website->id)->exists(), 'A linked website is still used by a task.');
                            Audit::record($website, 'deleted');
                            $website->delete();
                        }
                        break;
                    case 'websites':
                        self::requireNoReferences(Task::where('client_website_id', $id)->exists(), 'This domain is used by a task. Remove or reassign that task first.');
                        break;
                    case 'projects':
                        self::requireNoReferences($record->tasks()->exists() || ClientQuery::where('project_id', $id)->exists(), 'Remove or reassign linked tasks and client queries first.');
                        break;
                    case 'tasks':
                        $record->comments()->delete();
                        break;
                    case 'leads':
                        self::requireNoReferences((bool) $record->converted_client_id || $record->stage === 'converted', 'Converted lead history is preserved.');
                        break;
                    case 'campaigns':
                        self::requireNoReferences(CampaignPost::where('campaign_id', $id)->exists() || Brief::where('campaign_id', $id)->exists(), 'Remove or reassign campaign posts and creative briefs first.');
                        break;
                    case 'briefs':
                        self::requireNoReferences($record->status === 'approved' || $record->deliverables()->exists(), 'Approved creative history is preserved. Remove unapproved draft files before deleting their brief.');
                        break;
                    case 'deliverables':
                        $brief = $record->brief()->lockForUpdate()->firstOrFail();
                        self::requireNoReferences(in_array($record->status, ['submitted', 'approved'], true) || in_array($brief->status, ['submitted', 'approved'], true), 'Submitted and approved creative files are preserved. Complete the review before deleting an unapproved draft.');
                        $path = $record->path;
                        self::requireNoReferences(! str_starts_with($path, 'briefs/') || str_contains($path, '..') || str_contains($path, '\\'), 'This file has an unexpected storage path and needs manual review.');
                        DB::afterCommit(function () use ($record, $path) {
                            try {
                                if (Storage::disk('local')->exists($path) && ! Storage::disk('local')->delete($path)) {
                                    Audit::record($record, 'private_file_cleanup_pending');
                                }
                            } catch (\Throwable) {
                                Audit::record($record, 'private_file_cleanup_pending');
                            }
                        });
                        break;
                    case 'queries':
                        $record->messages()->delete();
                        break;
                    case 'payroll':
                        self::requireNoReferences($record->status !== 'draft', 'Only draft payroll can be deleted. Submitted, approved and paid history is preserved.');
                        $entries = $record->entries()->lockForUpdate()->get();
                        self::requireNoReferences(PayrollCorrection::whereIn('payroll_entry_id', $entries->modelKeys())->exists(), 'Payroll with correction history cannot be deleted.');
                        foreach ($entries as $entry) {
                            Audit::record($entry, 'deleted');
                            $entry->delete();
                        }
                        break;
                }
                Audit::record($record, 'deleted', ['resource' => $resource]);
                $record->delete();
            }, 3);
        } catch (QueryException $exception) {
            // A concurrent insert can create a new reference after the explicit checks.
            if (in_array($exception->errorInfo[0] ?? $exception->getCode(), ['23000', '23503'], true)) {
                throw ValidationException::withMessages(['delete' => 'This record is still referenced by another record. Remove or reassign its dependencies first.']);
            }
            throw $exception;
        }

        return $destination;
    }

    private static function requireNoReferences(bool $inUse, string $message): void
    {
        if ($inUse) {
            throw ValidationException::withMessages(['delete' => $message]);
        }
    }
}
