<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientId;
use App\Services\ActivityLogger;
use App\Services\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 11C: a client's valid IDs (competent evidence of identity).
 * Scans are stored privately (storage/app/private/client-ids) and every view of a scan is logged.
 */
class ClientIdController extends Controller
{
    public const DIR = 'client-ids';

    public function store(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'id_type' => ['required', 'string', 'max:60'],
            'id_number' => ['required', 'string', 'max:60'],
            'scan' => ['nullable', 'file', 'extensions:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:'.AttachmentService::MAX_KB],
        ], [
            'scan.extensions' => 'The scan must be a PDF, JPG or PNG file.',
            'scan.mimetypes' => 'The scan must be a PDF, JPG or PNG file.',
            'scan.max' => 'The scan must be 10 MB or smaller.',
        ]);

        $scan = $request->file('scan');

        $client->validIds()->create([
            'id_type' => trim($data['id_type']),
            'id_number' => trim($data['id_number']),
            'scan_path' => $scan?->store(self::DIR."/{$client->id}", AttachmentService::DISK),
            'scan_name' => $scan?->getClientOriginalName(),
            'scan_mime' => $scan?->getClientMimeType(),
            'scan_size' => $scan?->getSize(),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', "Valid ID added for {$client->display_name}.");
    }

    public function scan(ClientId $clientId): StreamedResponse
    {
        $disk = Storage::disk(AttachmentService::DISK);
        abort_unless($clientId->scan_path && $disk->exists($clientId->scan_path), 404, 'No scanned copy for this ID.');

        ActivityLogger::log('id_viewed', $clientId->client, "Viewed the scanned {$clientId->id_type} of client {$clientId->client?->client_code}");

        return AttachmentService::isPreviewable($clientId->scan_mime)
            ? $disk->response($clientId->scan_path, $clientId->scan_name)
            : $disk->download($clientId->scan_path, $clientId->scan_name);
    }

    public function destroy(ClientId $clientId): RedirectResponse
    {
        Gate::authorize('delete', $clientId);

        if ($clientId->scan_path) {
            Storage::disk(AttachmentService::DISK)->delete($clientId->scan_path);
        }
        $clientId->delete();

        return back()->with('success', 'Valid ID removed.');
    }
}
