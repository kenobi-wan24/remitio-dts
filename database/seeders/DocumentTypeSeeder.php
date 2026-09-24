<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Pleading' => 'Complaints, answers, replies, position papers',
            'Motion' => 'Motions filed in court or agency',
            'Affidavit' => 'Sworn statements, judicial affidavits',
            'Contract / Agreement' => 'Leases, MOAs, service agreements',
            'Deed' => 'Deeds of sale, donation, assignment',
            'Court Order / Resolution' => 'Orders, decisions, resolutions received',
            'Notice' => 'Notices of hearing, summons, subpoenas',
            'Demand Letter' => 'Demand and collection letters',
            'Evidence / Exhibit' => 'Supporting documents, receipts, photos',
            'Correspondence' => 'Letters and other communications',
            'Power of Attorney' => 'General and special powers of attorney',
            'Certificate' => "Secretary's certificates and other certifications",
            'Other' => 'Anything not covered above',
        ];

        foreach ($types as $name => $description) {
            DocumentType::updateOrCreate(['name' => $name], ['description' => $description, 'is_active' => true]);
        }
    }
}
