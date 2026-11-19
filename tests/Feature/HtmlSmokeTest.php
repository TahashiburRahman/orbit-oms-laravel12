<?php

namespace Tests\Feature;

use App\Models\Brief;
use App\Models\Campaign;
use App\Models\CampaignPost;
use App\Models\Client;
use App\Models\ClientQuery;
use App\Models\Deliverable;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\Project;
use App\Models\ReleaseNote;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HtmlSmokeTest extends TestCase
{
    use RefreshDatabase;

    private array $staff = [];

    private array $records = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        foreach (['admin', 'operations', 'marketing', 'graphics', 'developer'] as $slug) {
            $this->staff[$slug] = User::factory()->create(['name' => ucfirst($slug).' Employee', 'team_id' => Team::where('slug', $slug)->value('id'), 'active' => true, 'payroll_authorized' => $slug === 'operations']);
        }
        $admin = $this->staff['admin'];
        $ops = $this->staff['operations'];
        $marketing = $this->staff['marketing'];
        $graphics = $this->staff['graphics'];
        $developer = $this->staff['developer'];
        $this->actingAs($admin);
        $client = Client::create(['name' => 'Nusrat Rahman', 'company' => 'Dhaka Commerce', 'email' => 'nusrat@example.test', 'status' => 'active', 'assigned_to' => $marketing->id, 'created_by' => $ops->id]);
        $project = Project::create(['name' => 'Dhaka Commerce onboarding', 'client_id' => $client->id, 'team_id' => $ops->team_id, 'assigned_to' => $ops->id, 'created_by' => $ops->id, 'status' => 'active', 'due_date' => today()->addWeek()]);
        $project->members()->sync([$marketing->id, $graphics->id, $developer->id]);
        $task = Task::create(['title' => 'Set up client domain', 'description' => 'Technical setup for client onboarding.', 'client_id' => $client->id, 'project_id' => $project->id, 'team_id' => $developer->team_id, 'assigned_to' => $developer->id, 'created_by' => $ops->id, 'work_type' => 'domain', 'priority' => 'high', 'status' => 'in_progress', 'due_date' => today()->addDays(2)]);
        $task->comments()->create(['user_id' => $developer->id, 'body' => 'Configuration is in progress.']);
        $lead = Lead::create(['name' => 'Farhan Ahmed', 'company' => 'Chattogram Retail', 'email' => 'farhan@example.test', 'source' => 'Website', 'stage' => 'qualified', 'assigned_to' => $marketing->id, 'created_by' => $marketing->id, 'follow_up_at' => today()->addDay()]);
        $campaign = Campaign::create(['name' => 'Winter software launch', 'description' => 'Help local businesses get started.', 'status' => 'planning', 'assigned_to' => $marketing->id, 'created_by' => $marketing->id, 'starts_at' => today(), 'ends_at' => today()->addMonth()]);
        $post = CampaignPost::create(['campaign_id' => $campaign->id, 'title' => 'Product introduction', 'channel' => 'Facebook', 'content' => 'A useful introduction to the product.', 'scheduled_at' => now()->addDay(), 'status' => 'scheduled']);
        $brief = Brief::create(['title' => 'Software introduction video', 'campaign_id' => $campaign->id, 'type' => 'software_video', 'description' => 'Create a 30 second software walkthrough.', 'requested_by' => $marketing->id, 'assigned_to' => $graphics->id, 'status' => 'in_progress', 'due_date' => today()->addDays(3)]);
        $deliverable = Deliverable::create(['brief_id' => $brief->id, 'version' => 1, 'uploaded_by' => $graphics->id, 'path' => 'briefs/preview.mp4', 'original_name' => 'preview.mp4', 'mime' => 'video/mp4', 'size' => 2048, 'notes' => 'Initial draft for review.', 'status' => 'draft']);
        $query = ClientQuery::create(['project_id' => $project->id, 'subject' => 'Domain setup timeline', 'description' => 'When will setup be verified?', 'status' => 'open', 'priority' => 'normal', 'created_by' => $marketing->id, 'assigned_to' => $ops->id]);
        $query->messages()->create(['user_id' => $ops->id, 'body' => 'Verification follows the technical handover.']);
        $employee = Employee::create(['user_id' => $developer->id, 'employee_code' => 'EMP-005', 'job_title' => 'Software developer', 'joined_on' => '2026-01-01', 'base_salary' => '50000.00']);
        $run = PayrollRun::create(['period' => '2026-11', 'status' => 'draft', 'created_by' => $ops->id]);
        $entry = PayrollEntry::create(['payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'employee_name' => $developer->name, 'employee_code' => $employee->employee_code, 'base_salary' => '50000.00', 'allowances' => '1000.00', 'deductions' => '100.00', 'net_pay' => '50900.00']);
        $release = ReleaseNote::create(['version' => 'v1.2.3', 'summary' => 'Onboarding improvements', 'details' => 'Client onboarding is easier to track.', 'status' => 'published', 'publish_at' => now()->subHour(), 'created_by' => $admin->id]);
        $release->targetTeams()->sync(Team::pluck('id')->all());
        $this->records = compact('client', 'project', 'task', 'lead', 'campaign', 'post', 'brief', 'deliverable', 'query', 'employee', 'run', 'entry', 'release');
    }

    private function assertHtml(string $routeName, mixed $parameters = []): void
    {
        $this->get(route($routeName, $parameters))->assertOk()->assertSee('<html', false)->assertDontSee('<x-field', false)->assertDontSee('@multiple', false)->assertDontSee('@selected', false)->assertDontSee('@csrf', false);
    }

    public function test_admin_can_render_every_module_list_create_show_and_edit_screen(): void
    {
        $this->actingAs($this->staff['admin']);
        foreach (['users', 'clients', 'projects', 'tasks', 'leads', 'campaigns', 'briefs', 'queries', 'employees', 'payroll', 'releases'] as $module) {
            $this->assertHtml($module.'.index');
            $this->assertHtml($module.'.create');
        }
        $resources = ['users' => $this->staff['developer'], 'clients' => $this->records['client'], 'projects' => $this->records['project'], 'tasks' => $this->records['task'], 'leads' => $this->records['lead'], 'campaigns' => $this->records['campaign'], 'briefs' => $this->records['brief'], 'queries' => $this->records['query'], 'employees' => $this->records['employee'], 'payroll' => $this->records['run'], 'releases' => $this->records['release']];
        foreach ($resources as $module => $record) {
            $this->assertHtml($module.'.show', $record);
            $this->assertHtml($module.'.edit', $record);
        }
        foreach (['teams.index', 'development.index', 'audit.index', 'notifications.index'] as $routeName) {
            $this->assertHtml($routeName);
        }
        $this->assertHtml('posts.edit', $this->records['post']);
        $this->assertHtml('payroll.payslip', [$this->records['run'], $this->records['entry']]);
    }

    public function test_each_team_can_reach_its_practical_work_screens_and_expected_menu(): void
    {
        foreach ($this->staff as $slug => $user) {
            $this->actingAs($user)->get(route('dashboard.team', $slug))->assertOk()->assertSee('href="'.route('tasks.index').'"', false)->assertSee('href="'.route('releases.index').'"', false);
            $this->assertHtml('clients.index');
            $this->assertHtml('projects.show', $this->records['project']);
        }
        $this->actingAs($this->staff['operations']);
        foreach (['clients.create', 'projects.create', 'tasks.create', 'development.index', 'employees.index', 'employees.create', 'payroll.index', 'payroll.create'] as $routeName) {
            $this->assertHtml($routeName);
        }
        $this->assertHtml('queries.edit', $this->records['query']);
        $this->actingAs($this->staff['marketing']);
        foreach (['leads', 'campaigns', 'briefs', 'queries'] as $module) {
            $key = ['leads' => 'lead', 'campaigns' => 'campaign', 'briefs' => 'brief', 'queries' => 'query'][$module];
            $this->assertHtml($module.'.create');
            $this->assertHtml($module.'.show', $this->records[$key]);
            $this->assertHtml($module.'.edit', $this->records[$key]);
        }
        $this->actingAs($this->staff['graphics']);
        $this->assertHtml('briefs.index');
        $this->assertHtml('briefs.show', $this->records['brief']);
        $this->get(route('dashboard.team', 'graphics'))->assertSee('href="'.route('briefs.index').'"', false)->assertDontSee('href="'.route('payroll.index').'"', false);
        $this->actingAs($this->staff['developer']);
        $this->assertHtml('development.index');
        $this->assertHtml('tasks.show', $this->records['task']);
        $this->assertHtml('tasks.edit', $this->records['task']);
        $this->get(route('dashboard.team', 'developer'))->assertSee('href="'.route('development.index').'"', false)->assertDontSee('href="'.route('leads.index').'"', false)->assertDontSee('href="'.route('payroll.index').'"', false);
    }
}
