<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

/**
 * Programa de documentação — Trilha D (/documentacao/programa).
 *
 * A rota /documentacao é Blade e renderiza markdown; ESTA é a única página Inertia
 * da superfície, porque precisa cruzar o plano (git) com o estado das tasks (MCP).
 *
 * INVARIANTES (resources/js/Pages/Documentacao/Programa.casos.md):
 *  UC-PROGDOC-01 — status de onda vem das tasks MCP. Nunca literal aqui nem no .tsx.
 *  UC-PROGDOC-02 — texto vem do plano dono, parseado. Nenhum parágrafo do plano no código.
 *  UC-PROGDOC-05 — nada de segredo/host/business_id no payload; conteúdo é global.
 */
class DocumentacaoProgramaController extends Controller
{
    /** Plano dono — SSOT do conteúdo desta tela. */
    private const PLANO = 'memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md';

    private const SECAO = '## Trilha D — documentação técnica e operacional';

    private const TASK = 'US-INFRA-048';

    private const PARENT_PLAN = 'programa-ondas';

    public function __invoke(Request $request)
    {
        $caminho = base_path(self::PLANO);
        abort_unless(File::exists($caminho), 503, 'Plano indisponível nesta instância.');

        // TrilhaDParser: lê a § Trilha D e devolve estações, ondas (SEM status), caminhos,
        // camadas, DoD e batimento. É parse do dono — não há tabela duplicada em PHP.
        $plano = app(\App\Support\Documentacao\TrilhaDParser::class)->parse(File::get($caminho));

        // Estado vivo: projeção das tasks MCP. Se o MCP estiver fora, a tela mostra o plano
        // e declara o estado como indisponível — nunca inventa 'doing' (UC-PROGDOC-01).
        $estado = app(\App\Support\Documentacao\EstadoDasOndas::class)->paraPlano(self::PARENT_PLAN);

        return Inertia::render('Documentacao/Programa', [
            'plano' => [
                'git_url' => 'https://github.com/wagnerra23/oimpresso.com/blob/main/'.self::PLANO,
                'git_path' => self::PLANO,
                'secao' => self::SECAO,
                'atualizado_em' => $plano['atualizado_em'],
            ],
            'task' => [
                'codigo' => self::TASK,
                'status' => $estado->statusDaTask(self::TASK),
                'parent_plan' => 'parent_plan='.self::PARENT_PLAN,
            ],
            'estacoes' => $plano['estacoes'],
            'ondas' => $estado->aplicarEm($plano['ondas']),   // injeta status + tasks_abertas
            'caminhos' => $plano['caminhos'],
            'camadas' => $plano['camadas'],
            'dod' => $plano['dod'],
            'batimento' => $plano['batimento'],
            'estacao_de_retorno' => $plano['estacao_de_retorno'], // ['de' => '11', 'para' => '02']
        ]);
    }
}
