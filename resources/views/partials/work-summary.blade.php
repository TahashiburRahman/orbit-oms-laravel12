@if(in_array($task->record_type,['developer','operations'],true))
<span class="record-subtitle">{{ ucfirst($task->record_type) }} @if($task->record_type==='developer') | {{ $task->classification??'Unclassified legacy work' }} @endif | {{ App\Models\Task::WORK_STATUSES[$task->work_status]??$task->status }}{{ $task->status==='submitted'?' (awaiting verification)':'' }}</span>
@if($task->domain_name)<span class="record-subtitle">{{ $task->domain_name }}</span>@endif
@if($task->website_name||$task->client_name)<span class="record-subtitle">{{ $task->website_name??$task->client_name }}</span>@endif
@if($task->reported_at)<span class="record-subtitle">{{ $task->reported_at->format('d M Y H:i') }} | {{ $task->reporter?->name }}</span>@endif
@if($task->actions)<span class="record-subtitle">{{ Illuminate\Support\Str::limit($task->actions,100) }}</span>@endif
@endif
