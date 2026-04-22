<?php

namespace Modules\PontoWr2\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RelatorioController extends Controller
{
    /**
     * Lista de relatórios disponíveis.
     *
     * Chave `disponivel`: se o relatório já foi implementado em ReportService.
     * Hoje só `espelho` está pronto (funcional); os demais retornam RuntimeException.
     * Quando cada um for implementado, trocar `false` por `true`.
     */
    public function index(): Response
    {
        $relatorios = [
            ['chave' => 'afd',         'titulo' => 'AFD (Portaria 671/2021)', 'descricao' => 'Arquivo Fonte de Dados',          'icone' => 'FileText',      'cor' => 'blue',    'disponivel' => false],
            ['chave' => 'afdt',        'titulo' => 'AFDT',                     'descricao' => 'Arquivo Fonte de Dados Tratados', 'icone' => 'FileCheck',     'cor' => 'blue',    'disponivel' => false],
            ['chave' => 'aej',         'titulo' => 'AEJ',                      'descricao' => 'Apuração Eletrônica de Jornada',  'icone' => 'FileSpreadsheet','cor' => 'blue',    'disponivel' => false],
            ['chave' => 'espelho',     'titulo' => 'Espelho de Ponto',         'descricao' => 'PDF mensal por colaborador',      'icone' => 'ClipboardList', 'cor' => 'emerald', 'disponivel' => true],
            ['chave' => 'he',          'titulo' => 'Horas Extras',             'descricao' => 'Relatório consolidado do mês',    'icone' => 'Clock',         'cor' => 'amber',   'disponivel' => false],
            ['chave' => 'banco-horas', 'titulo' => 'Banco de Horas',           'descricao' => 'Saldos e movimentações',          'icone' => 'PiggyBank',     'cor' => 'emerald', 'disponivel' => false],
            ['chave' => 'atrasos',     'titulo' => 'Atrasos e Faltas',         'descricao' => 'Por colaborador/departamento',    'icone' => 'AlertCircle',   'cor' => 'red',     'disponivel' => false],
            ['chave' => 'esocial',     'titulo' => 'Eventos eSocial',          'descricao' => 'S-1010 / S-2230 / S-2240',        'icone' => 'Send',          'cor' => 'violet',  'disponivel' => false],
        ];

        return Inertia::render('Ponto/Relatorios/Index', [
            'relatorios' => $relatorios,
        ]);
    }

    public function gerar(Request $request, string $chave)
    {
        abort(501, "Implementar geração de '{$chave}' em ReportService.");
    }
}
