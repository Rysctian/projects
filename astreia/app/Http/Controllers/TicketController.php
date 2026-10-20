<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Ticket;
use App\Services\ProjectAccess;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function store(Request $request, Project $project, TicketService $service): RedirectResponse
    {
        $ticket = $service->create($project, $request->user(), $request->all());

        return redirect('/projects/'.$project->id.'/tickets/'.$ticket->id);
    }

    public function show(Request $request, Project $project, int $ticket): Response
    {
        ProjectAccess::authorize($request->user(), $project, 'tickets.view', false);
        $ticket = $project->tickets()->with(['stage', 'workflow', 'assignee:id,name', 'reporter:id,name', 'module', 'labels', 'project'])->findOrFail($ticket);
        $transitions = $ticket->workflow->transitions()->where('from_stage_id', $ticket->stage_id)->with(['toStage', 'requiredPermission'])->get()
            ->filter(fn ($edge) => (! $edge->requiredPermission || $request->user()->hasProjectPermission($project, $edge->requiredPermission->key))
                && ($edge->toStage->type !== 'closed' || $request->user()->hasProjectPermission($project, 'tickets.close')))->values();

        return Inertia::render('tickets/Show', [
            'project' => $project, 'ticket' => $ticket,
            'transitions' => $transitions,
            'activities' => $ticket->activities()->with('actor:id,name')->orderByDesc('id')->get(),
            'comments' => $ticket->comments()->with('author:id,name')->orderBy('id')->get(),
            'signoffs' => $ticket->signoffs()->with('actor:id,name')->orderByDesc('id')->get(),
            'attachments' => $ticket->attachments()->get(['id', 'original_filename', 'size_bytes']),
            'eligibleAssignees' => self::assignees($project), 'modules' => $project->modules()->where('status', 'active')->get(),
            'categories' => Ticket::CATEGORIES, 'priorities' => Ticket::PRIORITIES,
            'capabilities' => ProjectAccess::capabilities($request->user(), $project), 'labels' => $project->labels()->orderBy('name')->get(),
            'uploadsEnabled' => (bool) config('astreia.attachments.enabled'),
        ]);
    }

    public function update(Request $request, Project $project, int $ticket, TicketService $service): RedirectResponse
    {
        $service->update($project, $request->user(), $project->tickets()->findOrFail($ticket), $request->all());

        return back();
    }

    public function transition(Request $request, Project $project, int $ticket, TicketService $service): RedirectResponse
    {
        $service->transition($project, $request->user(), $project->tickets()->findOrFail($ticket), $request->all());

        return back();
    }

    public function signoff(Request $request, Project $project, int $ticket, TicketService $service): RedirectResponse
    {
        $service->signoff($project, $request->user(), $project->tickets()->findOrFail($ticket), $request->all());

        return back();
    }

    public function action(Request $request, Project $project, int $ticket, TicketService $service): RedirectResponse
    {
        $service->action($project, $request->user(), $project->tickets()->findOrFail($ticket), $request->all());

        return back();
    }

    public function comment(Request $request, Project $project, int $ticket, TicketService $service): RedirectResponse
    {
        DB::transaction(function () use ($request, $project, $ticket, $service) {
            $project = Project::lockForUpdate()->findOrFail($project->id);
            ProjectAccess::authorize($request->user(), $project, 'tickets.comment');
            ProjectAccess::authorize($request->user(), $project, 'tickets.view', false);
            $ticket = $project->tickets()->lockForUpdate()->findOrFail($ticket);
            $data = $request->validate(['body' => 'required|string|max:20000']);
            $comment = $ticket->comments()->create([...$data, 'author_id' => $request->user()->id]);
            $service->activity($ticket, $request->user(), 'commented', ['comment_id' => $comment->id]);
        });

        return back();
    }

    /** @return array<int, array{id: positive-int, name: string}> */
    public static function assignees(Project $project): array
    {
        return $project->members()->where('is_active', true)->with('user')->get()
            ->filter(fn ($member) => $member->user->is_active && $member->user->hasProjectPermission($project, 'tickets.work'))
            ->map(fn ($member) => ['id' => $member->user->id, 'name' => $member->user->name])->values()->all();
    }
}
