<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Services\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentAttachmentController extends Controller
{
    public function __construct(private AttachmentService $attachments)
    {
    }

    /**
     * Files card upload = OTHER files (scans, supporting papers).
     * New versions of the document's draft are uploaded only through Submit / Resubmit
     * for review in Update Tracking, so every version is tied to a recorded step.
     */
    public function store(Request $request, Document $document): RedirectResponse
    {
        $request->validate(AttachmentService::rules(required: true), AttachmentService::messages());

        $created = $this->attachments->storeFiles($document, $request->file('attachments'), $request->user());

        return back()->with('success', count($created) === 1 ? '1 file uploaded.' : count($created).' files uploaded.');
    }

    /**
     * Only reachable when logged in (route is inside the auth group).
     * ?inline=1 opens PDFs/images in the browser instead of downloading.
     */
    public function download(Request $request, DocumentAttachment $attachment): StreamedResponse
    {
        $disk = Storage::disk(AttachmentService::DISK);

        abort_unless($disk->exists($attachment->path), 404, 'This file could not be found on the server.');

        if ($request->boolean('inline') && AttachmentService::isPreviewable($attachment->mime_type)) {
            return $disk->response($attachment->path, $attachment->original_name);
        }

        return $disk->download($attachment->path, $attachment->original_name);
    }

    public function destroy(DocumentAttachment $attachment): RedirectResponse
    {
        Gate::authorize('delete', $attachment);

        $name = $attachment->original_name;
        $this->attachments->delete($attachment);

        return back()->with('success', "\"{$name}\" was deleted.");
    }
}
