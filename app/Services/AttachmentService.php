<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores uploaded files on the PRIVATE "local" disk (storage/app/private),
 * so they can only be reached through the auth-protected download route.
 */
class AttachmentService
{
    public const DISK = 'local';

    public const ALLOWED_MIMES = 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png';

    public const MAX_KB = 10240; // 10 MB per file

    public const MAX_FILES = 10;

    /** Validation rules reused by every upload form. */
    public static function rules(bool $required = false): array
    {
        return [
            'attachments' => [$required ? 'required' : 'nullable', 'array', 'max:'.self::MAX_FILES],
            'attachments.*' => ['file', 'mimes:'.self::ALLOWED_MIMES, 'max:'.self::MAX_KB],
        ];
    }

    public static function messages(): array
    {
        return [
            'attachments.required' => 'Choose at least one file to upload.',
            'attachments.max' => 'You can upload up to '.self::MAX_FILES.' files at a time.',
            'attachments.*.mimes' => 'Allowed file types: PDF, Word, Excel, JPG, PNG.',
            'attachments.*.max' => 'Each file must be 10 MB or smaller.',
            'attachments.*.uploaded' => 'A file failed to upload. It may be larger than the server allows.',
        ];
    }

    /**
     * @param  array<UploadedFile>  $files
     */
    public function storeFiles(Document $document, array $files, User $user): int
    {
        foreach ($files as $file) {
            $path = $file->store("documents/{$document->id}", self::DISK);

            $document->attachments()->create([
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $user->id,
            ]);
        }

        return count($files);
    }

    public function delete(DocumentAttachment $attachment): void
    {
        Storage::disk(self::DISK)->delete($attachment->path);
        $attachment->delete();
    }

    /** PDFs and images can be opened in the browser instead of downloaded. */
    public static function isPreviewable(?string $mime): bool
    {
        return in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true);
    }
}
