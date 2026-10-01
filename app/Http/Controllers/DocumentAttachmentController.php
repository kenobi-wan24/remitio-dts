<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Services\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentAttachmentController extends Controller
{
    public function __construct(private AttachmentService $attachments)
    {
    }

    public function store(Request $request, Document $document): RedirectResponse
    {
        $request->validate([
            ...AttachmentService::rules(required: true),
            'version_of' => ['nullable', Rule::exists('document_attachments', 'id')->where('document_id', $document->id)],
            'version_notes' => ['nullable', 'string', 'max:255'],
        ], AttachmentService::messages());

        $versionOf = $request->filled('version_of') ? DocumentAttachment::find($request->input('version_of')) : null;

        if ($versionOf && count($request->file('attachments')) > 1) {
            return back()->withInput()->withErrors(['attachments' => 'Upload only one file when adding a new version.']);
        }

        $created = $this->attachments->storeFiles(
            $document,
            $request->file('attachments'),
            $request->user(),
            $versionOf,
            $request->input('version_notes'),
        );

        $message = $versionOf
            ? "Uploaded as version {$created[0]->version} of \"{$versionOf->original_name}\"."
            : (count($created) === 1 ? '1 file uploaded.' : count($created).' files uploaded.');

        return back()->with('success', $message);
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
