<?php

namespace App\Http\Controllers;

use App\Exports\DataPasienExport;
use App\Models\Pasien;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
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

        Log::info('[PATIENT_EXPORT_START]', [
            'user_id' => $user->id,
            'role' => $user->getPermissionNames()->implode(','),
            'timestamp' => now()->timezone($timezone)->toDateTimeString(),
        ]);

        try {
            $count = Pasien::query()->visibleTo($user)->count();
            Log::info('[PATIENT_EXPORT_QUERY]', [
                'user_id' => $user->id,
                'count' => $count,
                'scope' => $user->canManageAllPatients() ? 'all' : 'puskesmas',
            ]);

            $response = Excel::download(new DataPasienExport($user), $filename);

            Log::info('[PATIENT_EXPORT_SUCCESS]', [
                'user_id' => $user->id,
                'filename' => $filename,
                'count' => $count,
            ]);

            return $response;
        } catch (Throwable $e) {
            Log::error('[PATIENT_EXPORT_FAILED]', [
                'user_id' => $user->id,
                'exception_class' => $e::class,
                'sanitized_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
