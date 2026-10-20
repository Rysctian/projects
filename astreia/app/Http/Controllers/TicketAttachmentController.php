<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectAccess;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    public function store(Request $request, Project $project, int $ticket, TicketService $service): RedirectResponse
    {
        ProjectAccess::authorize($request->user(), $project, 'tickets.attach');
        ProjectAccess::authorize($request->user(), $project, 'tickets.view', false);
        abort_unless(config('astreia.attachments.enabled') && count(config('astreia.attachments.extensions')) > 0, 403, 'Attachment uploads await approval of the file policy.');
        $request->validate(['file' => ['required', File::types(config('astreia.attachments.extensions'))->max(config('astreia.attachments.max_kilobytes'))]]);
        $file = $request->file('file');
        $path = $file->store('attachments/'.$project->id, 'local');
        abort_unless(is_string($path), 500, 'Unable to store this file.');
        try {
            DB::transaction(function () use ($request, $project, $ticket, $service, $file, $path) {
                $project = Project::lockForUpdate()->findOrFail($project->id);
                ProjectAccess::authorize($request->user(), $project, 'tickets.attach');
                $ticket = $project->tickets()->lockForUpdate()->findOrFail($ticket);
                $attachment = $ticket->attachments()->create([
                    'uploaded_by' => $request->user()->id, 'storage_disk' => 'local', 'storage_path' => $path,
                    'original_filename' => basename(str_replace('\\', '/', $file->getClientOriginalName())),
                    'mime_type' => $file->getMimeType() ?? 'application/octet-stream', 'size_bytes' => $file->getSize(),
                ]);
                $service->activity($ticket, $request->user(), 'attached', ['attachment_id' => $attachment->id, 'filename' => $attachment->original_filename]);
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }

        return back();
    }

    public function download(Request $request, Project $project, int $ticket, int $attachment): StreamedResponse
    {
        ProjectAccess::authorize($request->user(), $project, 'tickets.view', false);
        $ticket = $project->tickets()->findOrFail($ticket);
        $attachment = $ticket->attachments()->findOrFail($attachment);
        abort_unless($attachment->storage_disk === 'local' && Storage::disk('local')->exists($attachment->storage_path), 404);

        return Storage::disk('local')->download($attachment->storage_path, $attachment->original_filename, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff']);
    }
}
