<?php

namespace App\Exports;

use App\Models\HistoryJadwalObatPasien;
use App\Models\Pasien;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DataPasienExport implements FromView, ShouldAutoSize, WithStyles, WithColumnWidths
{
    public $user;
    public function __construct(User $user)
    {
        $this->user = $user;
    }
    /**
     * @return \Illuminate\Support\Collection
     */
    public function view(): View
    {
        $col = collect();
        $psnq = Pasien::orderBy('nama');
        if ($this->user->can('Admin')) {
            $psnq->where('puskesmas_id', $this->user->puskesmas->id);
        }
        $psns = $psnq->get();
        foreach ($psns as $key =>  $psn) {
            $persen = 100;
            $lama_mdtb = round(abs(Carbon::parse($psn->tgl_diagnosa)->diffInMonths(now()))) . ' Bulan';
            $diminum = HistoryJadwalObatPasien::where('pasien_id', $psn->id)->where('sudah_minum', 1)->count();
            $persen = $diminum / (HistoryJadwalObatPasien::where('pasien_id', $psn->id)->count()) * 100;
            $obats = [];
            foreach ($psn->obats as $obat) {
                $obats[] = $obat->obat->nama_obat;
            }
            $col->push([
                'no' => $key + 1,
                'nama' => $psn->nama,
                'umur' => round(abs(Carbon::parse($psn->tgl_lahir)->diffInYears(now()))) . ' Tahun',
                'jenis_kelamin' => $psn->jenis_kelamin,
                'no_telp' => $psn->user->username,
                'nama_pmo' => $psn->pendamping ? $psn->pendamping->name : '-',
                'no_telp_pmo' => $psn->pendamping ? $psn->pendamping->username : '-',
                'hub_dg_pasien' => $psn->hub_dg_pasien,
                'lama_mdtb' => $lama_mdtb,
                'lama_minum_obat' => $lama_mdtb,
                'nama_obat' => join(', ', $obats),
                'puskesmas' => $psn->puskesmas->nama_puskesmas,
                'persentase_kepatuhan' => round($persen, 2),
            ]);
        }
        return view('reports.pasien', compact('col'));
    }
    public function styles(Worksheet $sheet)
    {
        return [
            'A:M' => [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
                ]
            ],
            'L'   => ['alignment' => [
                'wrapText' => true
            ]],
        ];
    }
    public function columnWidths(): array
    {
        return [
            'K' => 70
        ];
    }
}
