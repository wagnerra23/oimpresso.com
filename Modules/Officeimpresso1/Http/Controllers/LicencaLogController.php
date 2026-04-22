<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LicencaLog;

class LicencaLogController extends Controller
{
    public function index()
    {
        $logs = LicencaLog::all();
        return view('licenca_log.index', compact('logs'));
    }

    public function create()
    {
        return view('licenca_log.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'licenca_id' => 'required|exists:licenca,id',
            'solicitacao' => 'required',
        ]);

        LicencaLog::create($request->all());

        return redirect()->route('licenca_log.index')
                         ->with('success', 'Log registrado com sucesso.');
    }
}
