<?php

namespace Modules\KB\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Jana\Contracts\MemoriaContrato;
use Modules\Jana\Entities\MemoriaFato;
use App\Support\Privacy\PiiRedactor;

/**
 * MemoriaController — tela "O Copiloto lembra de você" (/ia/memoria, LGPD).
 *
 * Sprint 6 do roadmap canônico (ADR 0036 + ADR 0037).
 * Multi-tenant scope obrigatório via session('user.business_id') + auth()->id().
 * Integra com MemoriaContrato (PR #25) — driver default = MeilisearchDriver.
 *
 * RUNBOOK: memory/requisitos/Jana/RUNBOOK-memoria.md
 * Contrato UC: resources/js/Pages/Jana/Memoria.casos.md
 *
 * ⚠️ Os ids `US-COPI-MEM-005/008/012` que este arquivo citava NÃO existem no SPEC
 * da Jana (0 hits, medido 2026-08-07) — mesmo padrão do `US-JANA-PAINEL-001` que a
 * onda 1 da US-COPI-148 pegou. Removidos em vez de propagados.
 */
class MemoriaController extends Controller
{
    public function __construct(
        protected MemoriaContrato $memoria,
        protected PiiRedactor $pii,
    ) {
    }

    /**
     * Lista todas as memórias ativas do user logado no business atual.
     */
    public function index(Request $request)
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $userId     = (int) auth()->id();

        $memorias = $this->memoria->listar($businessId, $userId);

        return Inertia::render('Jana/Memoria', [
            'memorias' => collect($memorias)->map(fn ($m) => $m->toArray())->values()->all(),
            'businessId' => $businessId,
            'userId' => $userId,

            // Onda 2 da paridade da área Jana — ver a nota gêmea no `ChatController`.
            // `businessId` já vinha (a tela usa no corpo); o header precisa do NOME
            // junto, e do mesmo shape das outras duas telas da área.
            // Multi-tenant Tier 0 (ADR 0093): sai da SESSÃO.
            'janaContext' => [
                'businessId'   => $businessId,
                'businessName' => (string) ($request->session()->get('business.name') ?? ''),
                'userName'     => optional(auth()->user())->name,
            ],
        ]);
    }

    /**
     * Esquece uma memória (soft delete = LGPD opt-out).
     */
    public function destroy(Request $request, int $id)
    {
        $this->memoria->esquecer($id);

        // Apagar é "alteração" pro Alert da tela ("Toda alteração registra autor e
        // motivo no log de auditoria"). Trilha com edição mas sem exclusão é trilha
        // quebrada — dava pra apagar um fato sem deixar rastro. Não exige motivo:
        // o protótipo confirma o apagar inline, sem campo (JmMemoria, jana-merge.jsx).
        $this->registrarNaTrilha($id, 'jana_memoria_fato_esquecido', 'Fato de memória esquecido (LGPD opt-out)');

        if ($request->expectsJson() || $request->header('X-Inertia')) {
            return back()->with('flash.success', 'Memória esquecida.');
        }

        return redirect()->route('jana.memoria.index')
            ->with('flash.success', 'Memória esquecida.');
    }

    /**
     * Atualiza fato (supersedes — append-only via valid_until + cria novo).
     *
     * O `motivo` é OBRIGATÓRIO: o charter manda a edição "registrar autor/quando/motivo"
     * no activitylog e o Anti-hook proíbe "update direto sem activitylog". A UI desabilita
     * o Salvar sem motivo, mas quem garante é aqui — a UI é conveniência, o servidor é a
     * garantia (contornar a tela não contorna a regra).
     */
    public function update(Request $request, int $id)
    {
        $validado = $request->validate([
            'fato' => 'required|string|max:1000',
            'motivo' => 'required|string|min:3|max:255',
        ], [
            'motivo.required' => 'Descreva o motivo da correção — ele fica no log de auditoria (LGPD).',
            'motivo.min' => 'O motivo precisa de pelo menos 3 caracteres.',
        ]);

        $this->memoria->atualizar(
            memoriaId: $id,
            novoFato: $validado['fato'],
            metadata: $request->input('metadata', []) ?? [],
        );

        $this->registrarNaTrilha(
            $id,
            'jana_memoria_fato_editado',
            'Fato de memória corrigido pelo titular',
            ['motivo' => $this->pii->redact($validado['motivo'])],
        );

        return back()->with('flash.success', 'Memória atualizada.');
    }

    /**
     * Grava a trilha LGPD da mutação (spatie activitylog).
     *
     * O texto do fato NUNCA entra — `MemoriaFato::getActivitylogOptions()` já decidiu
     * isso pra esta tabela (`logOnly(['valid_from','valid_until','deleted_at'])`, comentário
     * "NÃO logga `fato`/`metadata` (PII livre)") e a mutação manual segue a mesma regra.
     *
     * O `motivo` passa por PiiRedactor porque é prosa digitada — o titular pode escrever
     * um CPF ali. ⚠️ Tensão consciente no docblock do PiiRedactor: ele lista "Persistir em
     * audit log" como caso de uso E avisa "não usar pra dados do próprio tenant". A leitura
     * adotada: dado de negócio legítimo (um CPF em `contacts`) não se redige; anotação de
     * auditoria retida pra sempre, sim. Revisitar se essa leitura mudar.
     *
     * O subject só é anexado quando o fato é encontrado — `MemoriaFato` tem `HasBusinessScope`,
     * então id de OUTRO business resolve pra null (Tier 0, ADR 0093), e o `NullMemoriaDriver`
     * do CI não persiste em `jana_memoria_facts`. Em ambos os casos a trilha ainda sai, com o
     * id nas properties: log sem subject > log ausente.
     */
    private function registrarNaTrilha(int $id, string $logName, string $descricao, array $extra = []): void
    {
        $logger = activity($logName)
            ->causedBy(auth()->user())
            ->withProperties(['memoria_id' => $id] + $extra);

        if ($fato = MemoriaFato::find($id)) {
            $logger = $logger->performedOn($fato);
        }

        $logger->log($descricao);
    }
}
