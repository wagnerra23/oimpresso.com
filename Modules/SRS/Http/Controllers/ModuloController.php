<?php

namespace Modules\SRS\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\SRS\Services\RequirementsFileReader;

/**
 * @deprecated since 2026-07-29 (ADR 0357) — módulo em deprecação, remoção prevista em E5.
 *             Sucessor: `Modules\Governance\Http\Controllers\ModuleGradeController`
 *             (visão por módulo é governança). DEPRECATION-PLAN §Fase 2 item 6.
 *             Não abrir feature nova aqui; segue servindo em prod até E5.
 */
class ModuloController extends Controller
{
    public function show(string $module, RequirementsFileReader $reader): Response
    {
        $data = $reader->readModule($module);

        if ($data === null) {
            abort(404, "Módulo '{$module}' não tem arquivo em memory/requisitos/.");
        }

        return Inertia::render('MemCofre/Modulo', [
            'module'       => $module,
            'format'       => $data['format'] ?? 'flat',
            'frontmatter'  => $data['frontmatter'],
            'stories'      => $data['stories'],
            'rules'        => $data['rules'],
            'raw'          => $data['raw'],
            'readme'       => $data['readme'] ?? null,
            'architecture' => $data['architecture'] ?? null,
            'changelog'    => $data['changelog'] ?? null,
            'glossary'     => $data['glossary'] ?? null,
            'runbook'      => $data['runbook'] ?? null,
            'adrs'         => $data['adrs'] ?? [],
            'diagrams'     => $data['diagrams'] ?? [],
            'contracts'    => $data['contracts'] ?? [],
            'audits'       => $data['audits'] ?? [],
            'size_kb'      => round($data['size_bytes'] / 1024, 1),
            'mtime'        => date('Y-m-d H:i', $data['mtime']),
        ]);
    }
}
