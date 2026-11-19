<?php

namespace Database\Seeders;

use App\Models\Brief;
use App\Models\Campaign;
use App\Models\CampaignPost;
use App\Models\Client;
use App\Models\ClientQuery;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ReleaseNote;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Support\ReleasePublisher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->bound('oms.demo.password')) {
            throw new \LogicException('Use php artisan oms:demo to supply a password privately.');
        }
        DB::transaction(function () {
            $users = [];
            foreach (['admin' => 'Ayesha Rahman', 'operations' => 'Sadia Islam', 'marketing' => 'Nadia Ahmed', 'graphics' => 'Rafi Hasan', 'developer' => 'Farhan Karim'] as $slug => $name) {
                $users[$slug] = User::firstOrCreate(['email' => $slug.'@demo.example.test'], ['name' => $name, 'password' => app('oms.demo.password'), 'active' => true, 'payroll_authorized' => $slug === 'operations', 'team_id' => Team::where('slug', $slug)->value('id')]);
            }
            $ops = $users['operations'];
            $marketing = $users['marketing'];
            $dev = $users['developer'];
            $client = Client::firstOrCreate(['company' => 'Dhaka Commerce Demo'], ['name' => 'Tasnim Chowdhury', 'email' => 'tasnim@client.example.test', 'phone' => '+8801700000100', 'status' => 'active', 'notes' => 'Demo: a growing Dhaka retail business.', 'assigned_to' => $ops->id, 'created_by' => $ops->id]);
            $project = Project::firstOrCreate(['name' => 'Dhaka Commerce storefront · Demo', 'client_id' => $client->id], ['status' => 'active', 'due_date' => today()->addDays(10), 'notes' => 'Launch the storefront and verify the client handover.', 'team_id' => $ops->team_id, 'assigned_to' => $ops->id, 'created_by' => $ops->id]);
            $project->members()->syncWithoutDetaching([$marketing->id, $dev->id]);
            foreach ([['Connect commerce domain · Demo', 'domain', 'in_progress', 3], ['Install tracking pixel · Demo', 'pixel', 'open', 5], ['Courier configuration · Demo', 'courier', 'blocked', -1], ['Prepare launch landing page · Demo', 'landing_page', 'open', 7]] as [$title,$type,$status,$days]) {
                Task::firstOrCreate(['title' => $title, 'project_id' => $project->id], ['client_id' => $client->id, 'team_id' => $dev->team_id, 'assigned_to' => $dev->id, 'created_by' => $ops->id, 'work_type' => $type, 'priority' => $status === 'blocked' ? 'high' : 'normal', 'status' => $status, 'due_date' => today()->addDays($days), 'description' => 'Document setup and submit to Operations for verification.', 'blocker' => $status === 'blocked' ? 'Awaiting courier account information from client.' : null]);
            }
            Task::firstOrCreate(['title' => 'Send onboarding checklist · Demo', 'project_id' => $project->id], ['client_id' => $client->id, 'team_id' => $ops->team_id, 'assigned_to' => $ops->id, 'created_by' => $users['admin']->id, 'work_type' => 'general', 'priority' => 'high', 'status' => 'open', 'due_date' => today()->addDay()]);
            Lead::firstOrCreate(['email' => 'hello@chattogram.example.test'], ['name' => 'Mahir Hossain', 'company' => 'Chattogram Cloud Demo', 'phone' => '+8801700000200', 'source' => 'Software webinar', 'stage' => 'qualified', 'follow_up_at' => today()->addDay(), 'notes' => 'Interested in the SaaS growth plan.', 'assigned_to' => $marketing->id, 'created_by' => $marketing->id]);
            $campaign = Campaign::firstOrCreate(['name' => 'SaaS growth stories · Demo'], ['description' => 'Show how local businesses save time with our software.', 'status' => 'active', 'starts_at' => today(), 'ends_at' => today()->addMonth(), 'assigned_to' => $marketing->id, 'created_by' => $marketing->id]);
            CampaignPost::firstOrCreate(['campaign_id' => $campaign->id, 'title' => 'Customer story launch · Demo'], ['channel' => 'LinkedIn', 'content' => 'A Dhaka business, a simpler workflow. Share the customer story after approval.', 'status' => 'scheduled', 'scheduled_at' => now()->addDays(3)]);
            Brief::firstOrCreate(['campaign_id' => $campaign->id, 'title' => 'Software walkthrough video · Demo'], ['type' => 'software_video', 'description' => 'Create a 45-second Bangla software walkthrough with captions and a closing title.', 'due_date' => today()->addDays(4), 'status' => 'open', 'requested_by' => $marketing->id, 'assigned_to' => $users['graphics']->id]);
            ClientQuery::firstOrCreate(['project_id' => $project->id, 'subject' => 'When will domain verification finish? · Demo'], ['description' => 'The client would like a status update before launching their campaign.', 'status' => 'escalated', 'priority' => 'high', 'created_by' => $marketing->id, 'assigned_to' => $dev->id]);
            foreach ($users as $slug => $user) {
                Employee::firstOrCreate(['user_id' => $user->id], ['employee_code' => 'DEMO-'.strtoupper(substr($slug, 0, 3)), 'job_title' => $user->team->name.' specialist', 'joined_on' => today()->subMonths(3), 'phone' => '+8801700000000', 'address' => 'Dhaka, Bangladesh', 'base_salary' => $slug === 'developer' ? '60000.00' : '45000.00']);
            }
            $release = ReleaseNote::firstOrCreate(['version' => 'demo-1.0'], ['summary' => 'Welcome to Orbit Office', 'details' => 'Your connected workspace for client onboarding, creative reviews, development handovers, and payroll.', 'status' => 'published', 'publish_at' => now(), 'created_by' => $users['admin']->id]);
            $release->targetTeams()->syncWithoutDetaching(Team::pluck('id')->all());
        });
        app(ReleasePublisher::class)->notifyDue();
    }
}
