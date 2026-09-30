<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Stores uploaded files on the PRIVATE "local" disk (storage/app/private),
 * so they can only be reached through the auth-protected download route.
 *
 * Phase 8: version control — a file can be uploaded as a new version of an
 * existing one; exactly one version per file can be marked FINAL.
 */
class AttachmentService
{
    public const DISK = 'local';

    public const ALLOWED_EXTENSIONS = 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png';

    /**
     * Content types we accept. Windows/XAMPP's file detection often reports
     * .docx/.xlsx as a ZIP (they ARE zip packages inside) and .doc/.xls as
     * "CDFV2"/"vnd.ms-office", so those are listed too. The extension rule
     * still limits uploads to the 8 allowed extensions.
     */
    public const ALLOWED_MIMETYPES = [
        'application/pdf',
        'image/jpeg', 'image/png',
        'application/msword', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip', 'application/x-zip-compressed', 'application/octet-stream',
    ];

    public const MAX_KB = 10240; // 10 MB per file

    public const MAX_FILES = 10;

    /** Validation rules reused by every upload form. */
    public static function rules(bool $required = false): array
    {
        return [
            'attachments' => [$required ? 'required' : 'nullable', 'array', 'max:'.self::MAX_FILES],
            'attachments.*' => [
                'file',
                'extensions:'.self::ALLOWED_EXTENSIONS,
                'mimetypes:'.implode(',', self::ALLOWED_MIMETYPES),
                'max:'.self::MAX_KB,
            ],
        ];
    }

    public static function messages(): array
    {
        return [
            'attachments.required' => 'Choose at least one file to upload.',
            'attachments.max' => 'You can upload up to '.self::MAX_FILES.' files at a time.',
            'attachments.*.extensions' => 'Allowed file types: PDF, Word, Excel, JPG, PNG.',
            'attachments.*.mimetypes' => 'This file does not look like a real PDF, Word, Excel, JPG or PNG file.',
            'attachments.*.max' => 'Each file must be 10 MB or smaller.',
            'attachments.*.uploaded' => 'A file failed to upload. It may be larger than the server allows.',
        ];
    }

    /**
     * @param  array<UploadedFile>  $files
     * @param  DocumentAttachment|null  $versionOf  upload as the next version of this file
     * @return array<DocumentAttachment>
     */
    public function storeFiles(Document $document, array $files, User $user, ?DocumentAttachment $versionOf = null, ?string $notes = null): array
    {
        $created = [];

        foreach ($files as $file) {
            $attributes = [
                'original_name' => $file->getClientOriginalName(),
                'path' => $file->store("documents/{$document->id}", self::DISK),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $user->id,
                'version_notes' => $notes,
            ];

            if ($versionOf) {
                $group = $versionOf->version_group_id ?? $versionOf->id;
                $attributes['version_group_id'] = $group;
                $attributes['version'] = (int) DocumentAttachment::where('version_group_id', $group)->max('version') + 1;
            }

            $attachment = $document->attachments()->create($attributes);

            // A brand-new file starts its own version group
            if (! $attachment->version_group_id) {
                $attachment->update(['version_group_id' => $attachment->id]);
            }

            $created[] = $attachment;

            // Phase 9: activity log
            ActivityLogger::log('uploaded', $document, $versionOf
                ? "Uploaded v{$attachment->version} of \"{$attachment->original_name}\" to document {$document->tracking_code}"
                : "Uploaded \"{$attachment->original_name}\" to document {$document->tracking_code}", [], $user);
        }

        return $created;
    }

    /** Only one version of a file can be final. */
    public function markFinal(DocumentAttachment $attachment): void
    {
        DB::transaction(function () use ($attachment) {
            DocumentAttachment::where('version_group_id', $attachment->version_group_id)
                ->update(['is_final' => false]);

            $attachment->update(['is_final' => true]);
        });

        ActivityLogger::log('finalized', $attachment->document,
            "Marked \"{$attachment->original_name}\" (v{$attachment->version}) as FINAL on document {$attachment->document?->tracking_code}");
    }

    public function delete(DocumentAttachment $attachment): void
    {
        Storage::disk(self::DISK)->delete($attachment->path);
        $attachment->delete();

        ActivityLogger::log('file_deleted', $attachment->document,
            "Deleted file \"{$attachment->original_name}\" (v{$attachment->version}) from document {$attachment->document?->tracking_code}");
    }

    /** PDFs and images can be opened in the browser instead of downloaded. */
    public static function isPreviewable(?string $mime): bool
    {
        return in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true);
    }
}
