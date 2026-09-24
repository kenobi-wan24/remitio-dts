<?php

namespace Database\Factories\Support;

/**
 * Fictional, Davao-flavored demo data. None of this is real client info.
 */
class DemoData
{
    public const FIRST_NAMES = [
        'Juan', 'Maria', 'Jose', 'Ana', 'Mark Anthony', 'Kristine', 'John Paul', 'Mary Joy',
        'Rogelio', 'Lorna', 'Ramon', 'Cecilia', 'Arnel', 'Jocelyn', 'Rey', 'Liza',
        'Christian', 'Rhea', 'Jericho', 'Angelica', 'Noel', 'Marites', 'Dennis', 'Grace',
    ];

    public const LAST_NAMES = [
        'Dela Cruz', 'Santos', 'Reyes', 'Garcia', 'Mendoza', 'Bautista', 'Villanueva',
        'Fernandez', 'Castillo', 'Aquino', 'Ramos', 'Navarro', 'Torres', 'Flores',
        'Gonzales', 'Lim', 'Tan', 'Uy', 'Cabahug', 'Pacquing', 'Alburo', 'Sumalinog',
    ];

    public const COMPANY_WORDS = [
        'Mindanao', 'Davao Gulf', 'Southern', 'Mt. Apo', 'Samal Coast', 'Durian City',
        'Bangkerohan', 'Agdao', 'Talomo', 'Lanang', 'Sta. Ana', 'Matina',
    ];

    public const COMPANY_KINDS = [
        'Agri Ventures', 'Trading', 'Realty', 'Construction', 'Logistics',
        'Food Products', 'Hardware', 'Transport Cooperative', 'Development',
    ];

    public const COMPANY_SUFFIXES = ['Inc.', 'Corp.', 'Co.', 'Enterprises'];

    public const BARANGAYS = [
        'Matina Crossing', 'Buhangin', 'Toril', 'Talomo', 'Bajada', 'Lanang', 'Agdao',
        'Bangkal', 'Catalunan Grande', 'Mintal', 'Sasa', 'Ma-a', 'Ecoland', 'Obrero',
    ];

    public const LOCATIONS = [
        'Front Desk Tray', 'Cabinet A - Drawer 1', 'Cabinet A - Drawer 2', 'Cabinet B - Drawer 1',
        "Attorney's Office - Table", 'Cabinet C - Drawer 3', 'For Filing Folder',
    ];

    /** [title, document type name] — types must match DocumentTypeSeeder. */
    public const DOCUMENTS = [
        ['Complaint for Sum of Money', 'Pleading'],
        ['Answer with Counterclaim', 'Pleading'],
        ['Position Paper', 'Pleading'],
        ['Reply', 'Pleading'],
        ['Motion to Dismiss', 'Motion'],
        ['Motion for Extension of Time', 'Motion'],
        ['Motion for Reconsideration', 'Motion'],
        ['Affidavit of Loss', 'Affidavit'],
        ['Judicial Affidavit of Witness', 'Affidavit'],
        ['Affidavit of Desistance', 'Affidavit'],
        ['Contract of Lease', 'Contract / Agreement'],
        ['Memorandum of Agreement', 'Contract / Agreement'],
        ['Deed of Absolute Sale', 'Deed'],
        ['Deed of Donation', 'Deed'],
        ['Order Setting Pre-Trial', 'Court Order / Resolution'],
        ['Resolution of the Prosecutor', 'Court Order / Resolution'],
        ['Notice of Hearing', 'Notice'],
        ['Summons', 'Notice'],
        ['Final Demand Letter', 'Demand Letter'],
        ['Photocopies of Receipts (Exhibits)', 'Evidence / Exhibit'],
        ['Letter from Opposing Counsel', 'Correspondence'],
        ['Special Power of Attorney', 'Power of Attorney'],
        ["Secretary's Certificate", 'Certificate'],
    ];

    public const COURTS = [
        'civil' => ['RTC Branch 12, Davao City', 'RTC Branch 15, Davao City', 'MTCC Branch 3, Davao City'],
        'criminal' => ['Office of the City Prosecutor, Davao City', 'RTC Branch 9, Davao City', 'MTCC Branch 5, Davao City'],
        'labor' => ['NLRC Regional Arbitration Branch XI, Davao City'],
        'administrative' => ['Civil Service Commission RO XI', 'Office of the Ombudsman - Mindanao'],
        'special_proceeding' => ['RTC Branch 14, Davao City'],
    ];

    public static function personName(): string
    {
        return fake()->randomElement(self::FIRST_NAMES).' '.fake()->randomElement(self::LAST_NAMES);
    }

    public static function companyName(): string
    {
        return fake()->randomElement(self::COMPANY_WORDS).' '
            .fake()->randomElement(self::COMPANY_KINDS).' '
            .fake()->randomElement(self::COMPANY_SUFFIXES);
    }
}
