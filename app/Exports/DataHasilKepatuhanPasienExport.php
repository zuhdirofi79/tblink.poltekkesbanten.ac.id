<?php

namespace App\Exports;

use App\Models\Pasien;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class DataHasilKepatuhanPasienExport
{
    public const SHEET_NAME = 'Worksheet';

    public const HEADER_ROW = 3;

    public const DATA_START_ROW = 4;

    public const HEADERS = [
        'No',
        'Puskesmas',
        'Nama',
        'Umur',
        'Jenis Kelamin',
        'No Telp',
        'Nama PMO',
        'No Telp PMO',
        'Hub dengan Pasien',
        'Lama Menderita TB',
        'Lama Minum Obat',
        'Nama Obat',
        'Persentase Kepatuhan',
    ];

    public function __construct(private User $user)
    {
    }

    public static function templatePath(): string
    {
        return resource_path('templates/data-hasil-kepatuhan-pasien.xlsx');
    }

    public function spreadsheet(): Spreadsheet
    {
        $rows = [];
        $number = 0;

        Pasien::query()
            ->visibleTo($this->user)
            ->with(['user', 'puskesmas', 'pendamping', 'obats.obat'])
            ->orderBy('nama')
            ->orderBy('id')
            ->chunk(100, function ($pasiens) use (&$rows, &$number) {
                foreach ($pasiens as $pasien) {
                    $number++;
                    $lamaTb = ReportValueFormatter::durationMonths($pasien->tgl_diagnosa);
                    $kepatuhan = $pasien->kepatuhanMinumObat();

                    $rows[] = [
                        $number,
                        (string) ($pasien->puskesmas?->nama_puskesmas ?? ''),
                        (string) $pasien->nama,
                        ReportValueFormatter::ageYears($pasien->tgl_lahir),
                        (string) $pasien->jenis_kelamin,
                        ReportValueFormatter::phone($pasien->user),
                        (string) ($pasien->pendamping?->name ?? ''),
                        ReportValueFormatter::phone($pasien->pendamping),
                        (string) ($pasien->hub_dg_pasien ?? ''),
                        $lamaTb,
                        $lamaTb,
                        $pasien->obats
                            ->map(fn ($obatPasien) => $obatPasien->obat?->nama_obat)
                            ->filter()
                            ->unique()
                            ->values()
                            ->implode(', '),
                        ReportValueFormatter::adherencePercent($kepatuhan),
                    ];
                }
            });

        return ExcelTemplateFiller::fill(
            self::templatePath(),
            self::SHEET_NAME,
            self::DATA_START_ROW,
            $rows,
            ['F', 'H'],
        );
    }
}
