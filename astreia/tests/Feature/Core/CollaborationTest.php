<?php

use App\Models\Permission;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_system_admin' => true]);
    legacyProjectFixture($this->admin);
    $this->project = Project::firstOrFail();
    $this->path = '/projects/'.$this->project->id;
    $this->post($this->path.'/tickets', ['title' => 'Client inquiry', 'description' => 'Investigate access.', 'category' => 'support', 'priority' => 'normal']);
    $this->ticket = Ticket::firstOrFail();
    $this->ticketPath = $this->path.'/tickets/'.$this->ticket->id;
});

test('private evidence downloads require authorized project and ticket scope', function () {
    Storage::fake('local');
    Storage::disk('local')->put('attachments/evidence.txt', 'Private evidence');
    $attachment = $this->ticket->attachments()->create(['uploaded_by' => $this->admin->id, 'storage_disk' => 'local', 'storage_path' => 'attachments/evidence.txt', 'original_filename' => 'evidence.txt', 'mime_type' => 'text/plain', 'size_bytes' => 16]);
    $this->get($this->ticketPath.'/attachments/'.$attachment->id)->assertDownload('evidence.txt');
    $this->actingAs(User::factory()->create())->get($this->ticketPath.'/attachments/'.$attachment->id)->assertForbidden();
});

test('uploads remain disabled until policy approval and enabled uploads stay private', function () {
    Storage::fake('local');
    $this->post($this->ticketPath.'/attachments', ['file' => UploadedFile::fake()->create('evidence.txt', 1, 'text/plain')])->assertForbidden();
    config(['astreia.attachments.enabled' => true, 'astreia.attachments.extensions' => ['txt'], 'astreia.attachments.max_kilobytes' => 100]);
    $this->post($this->ticketPath.'/attachments', ['file' => UploadedFile::fake()->createWithContent('evidence.txt', 'Private evidence')])->assertRedirect();
    $attachment = $this->ticket->attachments()->firstOrFail();
    Storage::disk('local')->assertExists($attachment->storage_path);
    $this->post($this->ticketPath.'/attachments', ['file' => UploadedFile::fake()->create('unsafe.exe', 1)])->assertSessionHasErrors('file');
    $this->assertDatabaseCount('ticket_attachments', 1);
});

test('comments and project labels are audited and scoped', function () {
    $this->post($this->ticketPath.'/comments', ['body' => 'Client confirmed the expected behavior.'])->assertRedirect();
    $this->assertDatabaseHas('ticket_comments', ['ticket_id' => $this->ticket->id, 'author_id' => $this->admin->id]);
    $this->post($this->path.'/labels', ['name' => 'Client follow-up'])->assertRedirect();
    $this->assertDatabaseHas('project_labels', ['project_id' => $this->project->id, 'name' => 'Client follow-up']);
    $this->assertDatabaseHas('audit_logs', ['project_id' => $this->project->id, 'action' => 'label.created']);
});

test('project filters search ticket keys and summaries respect ticket read permission', function () {
    $this->get($this->path.'?q=HRIS-00001')->assertInertia(fn ($page) => $page->has('tickets.data', 1));
    $this->get($this->path.'?priority=urgent')->assertInertia(fn ($page) => $page->has('tickets.data', 0));
    $viewer = User::factory()->create();
    $role = $this->project->roles()->create(['name' => 'Project only']);
    $role->permissions()->attach(Permission::where('key', 'project.view')->firstOrFail()->id);
    $member = $this->project->members()->create(['user_id' => $viewer->id]);
    $member->roles()->attach($role->id, ['project_id' => $this->project->id]);
    $this->actingAs($viewer)->get('/projects')->assertInertia(fn ($page) => $page->where('projects.data.0.open_tickets_count', 0));
    $this->get($this->path)->assertInertia(fn ($page) => $page->has('tickets.data', 0)->where('counts.open', 0));
});

test('the project board includes all matching tickets across list pages and reports workload', function () {
    for ($i = 0; $i < 30; $i++) {
        $this->post($this->path.'/tickets', ['title' => 'Request '.$i, 'description' => 'Client request.', 'category' => 'support', 'priority' => 'normal'])->assertRedirect();
    }
    $this->get($this->path.'?view=board')->assertInertia(fn ($page) => $page
        ->has('boardColumns.0.tickets.data', 20)
        ->where('boardColumns.0.tickets.total', 31)
        ->where('reports.by_category.4.total', 31)
        ->where('reports.workload.0.name', 'Unassigned')
        ->where('reports.workload.0.total', 31));
    $this->get($this->path.'?view=board&board_page_'.$this->ticket->stage_id.'=2')->assertInertia(fn ($page) => $page->has('boardColumns.0.tickets.data', 11));
});
