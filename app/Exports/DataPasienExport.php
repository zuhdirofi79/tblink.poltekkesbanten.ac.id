<?php

namespace App\Exports;

use App\Models\Pasien;
use App\Models\User;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DataPasienExport extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping, WithColumnFormatting, WithCustomValueBinder, ShouldAutoSize, WithStyles, WithTitle
{
    private int $rowNumber = 0;

    public function __construct(private User $user)
    {
    }

    public function title(): string
    {
        return 'Data Pasien';
    }

    public function query()
    {
        return Pasien::query()
            ->visibleTo($this->user)
            ->with(['user', 'puskesmas', 'pendamping'])
            ->orderBy('nama');
    }

    public function headings(): array
    {
        return [
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
    }

    /**
     * @param  Pasien  $pasien
     */
    public function map($pasien): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            (string) $pasien->nama,
            (string) $pasien->nik,
            (string) ($pasien->user?->email ?? ''),
            (string) ($pasien->user?->no_hp ?? ''),
            (string) $pasien->jenis_kelamin,
            $this->formatDate($pasien->tgl_lahir),
            $this->formatDate($pasien->tgl_diagnosa),
            (string) ($pasien->tipe_tb ?? ''),
            $this->formatDate($pasien->mulai_pengobatan),
            $pasien->jenjang_terapi,
            $this->formatDate($pasien->selesai_pengobatan),
            (string) ($pasien->alamat ?? ''),
            (string) ($pasien->puskesmas?->nama_puskesmas ?? ''),
            (string) ($pasien->pendamping?->name ?? ''),
            (string) ($pasien->status ?? ''),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function bindValue(Cell $cell, $value)
    {
        if (in_array($cell->getColumn(), ['C', 'E'], true)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    private function formatDate(mixed $value): string
    {
        if (! $value) {
            return '';
        }

        try {
            return Carbon::parse($value)->timezone((string) config('app.timezone', 'Asia/Jakarta'))->format('d/m/Y');
        } catch (\Throwable) {
            return '';
        }
    }
}
