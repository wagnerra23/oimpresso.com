<?php

namespace Modules\PontoWr2\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class RelatorioController extends Controller
{
    public function index(): View
    {
        $relatorios = [
            ['chave' => 'afd',         'titulo' => 'AFD (Portaria 671/2021)', 'descricao' => 'Arquivo Fonte de Dados'],
            ['chave' => 'afdt',        'titulo' => 'AFDT',                     'descricao' => 'Arquivo Fonte de Dados Tratados'],
            ['chave' => 'aej',         'titulo' => 'AEJ',                      'descricao' => 'Apuração Eletrônica de Jornada'],
            ['chave' => 'espelho',     'titulo' => 'Espelho de Ponto',         'descricao' => 'PDF mensal por colaborador'],
            ['chave' => 'he',          'titulo' => 'Horas Extras',             'descricao' => 'Relatório consolidado do mês'],
            ['chave' => 'banco-horas', 'titulo' => 'Banco de Horas',           'descricao' => 'Saldos e movimentações'],
            ['chave' => 'atrasos',     'titulo' => 'Atrasos e Faltas',         'descricao' => 'Por colaborador/departamento'],
            ['chave' => 'esocial',     'titulo' => 'Eventos eSocial',          'descricao' => 'S-1010/S-2230/S-2240'],
        ];

        return view('pontowr2::relatorios.index', compact('relatorios'));
    }

    public function gerar(Request $request, string $chave)
    {
        abort(501, "Implementar geração de '{$chave}' em ReportService.");
    }
}
