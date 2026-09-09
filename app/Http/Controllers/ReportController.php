<?php

namespace App\Http\Controllers;

use App\Exports\DataPasienExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index()
    {
        if (auth()->user()->hasAnyPermission(['Superadmin', 'Admin'])) {
            return inertia('report/index');
        } else {
            abort(403);
        }
    }
    public function data_pasien()
    {
        $user = auth()->user();
        if ($user->hasanypermission(['Superadmin', 'Admin'])) {
            return Excel::download(new DataPasienExport(auth()->user()), 'Data Pasien.xlsx');
        } else {
            abort(403);
        }
    }
}
