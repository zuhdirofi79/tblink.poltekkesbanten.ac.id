<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BerobatController extends Controller
{
    public function index(Request $r)
    {
        return inertia('pasien/berobat/index');
    }
}
