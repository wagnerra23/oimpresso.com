<?php

namespace Modules\DocVault\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\DocVault\Services\RequirementsFileReader;

class ModuloController extends Controller
{
    public function show(string $module, RequirementsFileReader $reader): Response
    {
        $data = $reader->readModule($module);

        if ($data === null) {
            abort(404, "Módulo '{$module}' não tem arquivo em memory/requisitos/.");
        }

        return Inertia::render('DocVault/Modulo', [
            'module'      => $module,
            'frontmatter' => $data['frontmatter'],
            'stories'     => $data['stories'],
            'rules'       => $data['rules'],
            'raw'         => $data['raw'],
            'size_kb'     => round($data['size_bytes'] / 1024, 1),
            'mtime'       => date('Y-m-d H:i', $data['mtime']),
        ]);
    }
}
