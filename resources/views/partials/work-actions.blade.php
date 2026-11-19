@php($context=request()->route('team'))
@php($availableWorkTeams=App\Models\Team::whereIn('slug',['developer','operations','marketing'])->pluck('slug')->all())
<section class="card"><div class="actions">
@if(in_array($context,['developer','operations'],true)&&in_array($context,$availableWorkTeams,true)&&auth()->user()->inTeam($context))<a class="btn" href="{{ route('work.create',$context) }}">Add {{ ucfirst($context) }} update</a>
@elseif($context==='marketing'&&in_array('marketing',$availableWorkTeams,true)&&auth()->user()->inTeam('marketing'))<a class="btn" href="{{ route('marketing_update.create') }}">Add Marketing update</a><a class="btn-secondary" href="{{ route('marketing_update.index') }}">View Marketing updates</a>@endif
<a class="btn-secondary" href="{{ route('referral.create') }}">Bring a Client</a><a class="text-link" href="{{ route('referral.index',['mine'=>1]) }}">My referrals</a>
</div>
@if($context==='marketing')<p class="muted">{{ App\Models\Lead::visibleTo(auth()->user())->where('record_type','marketing_update')->count() }} Marketing updates in your scope. Task metrics below count operational tasks separately.</p>@endif
</section>
