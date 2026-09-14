<?php

namespace App\Exports;

use App\Models\Pasien;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class DataPasienExport
{
    public const SHEET_NAME = 'Data Pasien';

    public const HEADER_ROW = 1;

    public const DATA_START_ROW = 2;

    public const HEADERS = [
        'No',
        'Nama Pasien',
        'NIK',
        'Email',
        'No HP',
        'Jenis Kelamin',
        'Tanggal Lahir',
        'Tanggal Diagnosis',
        'Type TB',
        'Mulai Pengobatan',
        'Jenjang Terapi',
        'Selesai Pengobatan',
        'Alamat',
        'Puskesmas',
        'Pendamping',
        'Status',
    ];

    public function __construct(private User $user)
    {
    }

    public static function templatePath(): string
    {
        return resource_path('templates/data-pasien.xlsx');
    }

    public function spreadsheet(): Spreadsheet
    {
        $rows = [];
        $number = 0;

        Pasien::query()
            ->visibleTo($this->user)
            ->with(['user', 'puskesmas', 'pendamping'])
            ->orderBy('nama')
            ->orderBy('id')
            ->chunk(100, function ($pasiens) use (&$rows, &$number) {
                foreach ($pasiens as $pasien) {
                    $number++;
                    $rows[] = [
                        $number,
                        (string) $pasien->nama,
                        (string) $pasien->nik,
                        (string) ($pasien->user?->email ?? ''),
                        (string) ($pasien->user?->no_hp ?? ''),
                        (string) $pasien->jenis_kelamin,
                        ReportValueFormatter::date($pasien->tgl_lahir),
                        ReportValueFormatter::date($pasien->tgl_diagnosa),
                        (string) ($pasien->tipe_tb ?? ''),
                        ReportValueFormatter::date($pasien->mulai_pengobatan),
                        $pasien->jenjang_terapi === null || $pasien->jenjang_terapi === ''
                            ? ''
                            : (int) $pasien->jenjang_terapi,
                        ReportValueFormatter::date($pasien->selesai_pengobatan),
                        (string) ($pasien->alamat ?? ''),
                        (string) ($pasien->puskesmas?->nama_puskesmas ?? ''),
                        (string) ($pasien->pendamping?->name ?? ''),
                        (string) ($pasien->status ?? ''),
                    ];
                }
            });

        return ExcelTemplateFiller::fill(
            self::templatePath(),
            self::SHEET_NAME,
            self::DATA_START_ROW,
            $rows,
            ['C', 'E'],
        );
    }
}
