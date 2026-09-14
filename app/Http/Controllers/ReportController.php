<?php

namespace App\Http\Controllers;

use App\Exports\DataHasilKepatuhanPasienExport;
use App\Exports\DataPasienExport;
use App\Exports\ExcelTemplateFiller;
use App\Models\Pasien;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReportController extends Controller
{
    public function index()
    {
        if (auth()->user()?->canManagePatients()) {
            return inertia('report/index');
        }

        abort(403);
    }

    public function data_pasien()
    {
        $user = auth()->user();
        abort_unless($user?->canManagePatients(), 403);

        $timezone = (string) config('app.timezone', 'Asia/Jakarta');
        $filename = 'data-pasien-'.now()->timezone($timezone)->toDateString().'.xlsx';

        Log::info('[PATIENT_REPORT_START]', [
            'user_id' => $user->id,
            'role' => $user->getPermissionNames()->implode(','),
            'filename' => $filename,
        ]);

        try {
            $count = Pasien::query()->visibleTo($user)->count();
            $response = ExcelTemplateFiller::download(
                (new DataPasienExport($user))->spreadsheet(),
                $filename,
            );

            Log::info('[PATIENT_REPORT_SUCCESS]', [
                'user_id' => $user->id,
                'role' => $user->getPermissionNames()->implode(','),
                'record_count' => $count,
                'filename' => $filename,
            ]);

            return $response;
        } catch (Throwable $e) {
            Log::error('[PATIENT_REPORT_FAILED]', [
                'user_id' => $user->id,
                'role' => $user->getPermissionNames()->implode(','),
                'filename' => $filename,
                'exception_class' => $e::class,
                'sanitized_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function data_hasil_kepatuhan_pasien()
    {
        $user = auth()->user();
        abort_unless($user?->canManagePatients(), 403);

        $timezone = (string) config('app.timezone', 'Asia/Jakarta');
        $filename = 'data-hasil-kepatuhan-pasien-'.now()->timezone($timezone)->toDateString().'.xlsx';

        Log::info('[ADHERENCE_REPORT_START]', [
            'user_id' => $user->id,
            'role' => $user->getPermissionNames()->implode(','),
            'filename' => $filename,
        ]);

        try {
            $count = Pasien::query()->visibleTo($user)->count();
            $response = ExcelTemplateFiller::download(
                (new DataHasilKepatuhanPasienExport($user))->spreadsheet(),
                $filename,
            );

            Log::info('[ADHERENCE_REPORT_SUCCESS]', [
                'user_id' => $user->id,
                'role' => $user->getPermissionNames()->implode(','),
                'record_count' => $count,
                'filename' => $filename,
            ]);

            return $response;
        } catch (Throwable $e) {
            Log::error('[ADHERENCE_REPORT_FAILED]', [
                'user_id' => $user->id,
                'role' => $user->getPermissionNames()->implode(','),
                'filename' => $filename,
                'exception_class' => $e::class,
                'sanitized_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
