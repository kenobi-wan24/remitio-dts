<?php

namespace App\Http\Middleware;

use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\LegalCase;
use App\Models\NotarialEntry;
use App\Services\ActivityLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * R3 — one gate for every URL that opens a record.
 *
 * Runs after route-model binding, so it sees the actual document / case / file / notarial entry
 * named in the URL. Typing another record's ID into the address bar is refused (403) and
 * written to the activity log, whatever the page or action is.
 */
class EnsureRecordAccess
{
    private const GUARDED = [Document::class, LegalCase::class, DocumentAttachment::class, NotarialEntry::class];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        foreach ($request->route()?->parameters() ?? [] as $record) {
            if (! $user || ! $record instanceof Model || ! in_array($record::class, self::GUARDED, true)) {
                continue;
            }

            if (Gate::forUser($user)->denies('view', $record)) {
                ActivityLogger::log('access_denied', $record, "Was refused access to {$this->label($record)}", [
                    'url' => $request->path(),
                    'method' => $request->method(),
                ], $user);

                abort(403, 'This record is confidential or not assigned to you.');
            }

            // Opening a confidential record is logged (Data Privacy Act: who saw what, when)
            if ($request->isMethod('GET') && in_array($request->route()->getName(), ['documents.show', 'cases.show'], true)
                && $this->isConfidential($record)) {
                ActivityLogger::log('confidential_viewed', $record, "Opened confidential {$this->label($record)}", [], $user);
            }
        }

        return $next($request);
    }

    private function isConfidential(Model $record): bool
    {
        return match (true) {
            $record instanceof Document => $record->isConfidential(),
            $record instanceof LegalCase => (bool) $record->is_confidential,
            default => false,
        };
    }

    private function label(Model $record): string
    {
        return match (true) {
            $record instanceof Document => "document {$record->tracking_code}",
            $record instanceof LegalCase => "case {$record->case_code}",
            $record instanceof DocumentAttachment => "a file of document #{$record->document_id}",
            $record instanceof NotarialEntry => "a notarial entry of document #{$record->document_id}",
        };
    }
}
