<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Services\AttachmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 11C: one valid government ID of a client (competent evidence of identity).
 * ID numbers are sensitive personal information (Data Privacy Act, RA 10173):
 * lists show only the masked number; the full number is shown on the client's profile.
 */
class ClientId extends Model
{
    use LogsActivity;

    /** Suggestions for the "ID type" field (free text is allowed for other IDs). */
    public const COMMON_TYPES = [
        'PhilSys National ID', 'Passport', "Driver's License", 'UMID', 'SSS ID', 'GSIS eCard', 'PRC ID',
        'Postal ID', "Voter's ID", 'PhilHealth ID', 'TIN ID', 'Senior Citizen ID', 'PWD ID', 'OFW ID',
    ];

    /** Folder (on the private disk) where ID scans are stored. */
    public const DIR = 'client-ids';

    protected $fillable = [
        'client_id', 'id_type', 'id_number',
        'scan_path', 'scan_name', 'scan_mime', 'scan_size',
        'created_by',
    ];

    protected $hidden = ['scan_path'];

    /** $id->masked → "•••• 3540" */
    protected function masked(): Attribute
    {
        return Attribute::get(fn () => '•••• '.mb_substr(preg_replace('/\s+/', '', (string) $this->id_number), -4));
    }

    /** $id->label → "Driver's License No. D12-88-013540" (full — for the profile and the register) */
    protected function label(): Attribute
    {
        return Attribute::get(fn () => "{$this->id_type} No. {$this->id_number}");
    }

    /** Record a valid ID (with an optional scan) for a client. Used by the profile card and the new-client form. */
    public static function recordFor(Client $client, string $type, string $number, ?UploadedFile $scan, ?int $userId): self
    {
        return $client->validIds()->create([
            'id_type' => trim($type),
            'id_number' => trim($number),
            'scan_path' => $scan?->store(self::DIR."/{$client->id}", AttachmentService::DISK),
            'scan_name' => $scan?->getClientOriginalName(),
            'scan_mime' => $scan?->getClientMimeType(),
            'scan_size' => $scan?->getSize(),
            'created_by' => $userId,
        ]);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activityLabel(): string
    {
        return "valid ID ({$this->id_type} {$this->masked}) of client {$this->client?->client_code}";
    }

    /** Changes to the scan file path aren't useful in the log. */
    protected function activityIgnoredAttributes(): array
    {
        return ['scan_path', 'scan_mime', 'scan_size'];
    }
}
