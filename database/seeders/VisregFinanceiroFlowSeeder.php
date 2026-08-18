<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dado mínimo e determinístico para os fluxos visuais de Financeiro.
 *
 * Ele é semeado pelo workflow antes do servidor Browser iniciar; por isso é
 * visível tanto ao processo que executa o Pest quanto ao que renderiza Inertia.
 *
 * ── DETERMINISMO: SÓ DATA PASSADA OU NULL. NUNCA FUTURA. ────────────────────
 *
 * `Carbon::setTestNow()` do teste **NÃO alcança o browser** — ele renderiza num
 * subprocesso separado, com o relógio REAL. Congelar o relógio no Pest não torna
 * esta tela determinística; quem decide "vencendo" × "em atraso" é o calendário
 * de verdade. A regra é a mesma que o `VisregOficinaBoardSeeder` já documenta:
 *
 *   data passada → segue passada pra sempre → estável
 *   NULL         → segue NULL               → estável
 *   data FUTURA  → vira passada no dia X    → a baseline APODRECE SOZINHA
 *
 * ⚠️ ESTE FIXTURE JÁ CAIU NISSO — 2026-08-17. O `vencimento` abaixo é literal
 * `2026-06-11`. Quando a baseline foi assada, essa data ainda era FUTURA: a tela
 * mostrava "vencendo", chip "Só atrasados" = 0. O calendário andou, a data
 * venceu, e o render passou a mostrar "em atraso" com o chip = 1 — diff de
 * 0,3448% (Δmax=253) que caiu na zona cinza e passou a exigir carimbo manual
 * `visreg-gray-approved` em TODO PR que tocasse UI. Foram 4 PRs num só dia.
 *
 * A data de hoje em diante é permanentemente passada, então o estado é estável
 * e a baseline só precisa de UM rebake. **Não troque este literal por
 * `now()`/`addDays()` nem por uma data futura** — seria reintroduzir o mesmo
 * apodrecimento com outro número.
 *
 * ⚠️ O QUE NÃO FOI EXPLICADO, e fica registrado em vez de virar certeza falsa:
 * no diff, a data EXIBIDA na coluna Vencimento aparece como `06/06`, não
 * `11/06`. Medido em 2026-08-17: este é o ÚNICO seeder que escreve `fin_titulos`,
 * e ele grava o literal `2026-06-11`; `2026-06-06` não aparece em seeder nenhum
 * (só como `setTestNow` de `A11yAxeBrowserTest`/`AuthBridgeSmokeTest`). Leitura
 * estática não explica a diferença — quem for rebakar a baseline confira o valor
 * renderizado antes de assumir que está certo.
 *
 * ── CONFERÊNCIA FEITA (2026-08-17, sessão do rebake) ────────────────────────
 *
 * O pedido acima foi cumprido pelo lado que dá pra medir sem runner. Decodificando
 * o `.snap` commitado de `Financeiro/Unificado` (base64 → PNG) e OLHANDO a imagem:
 *
 *   coluna Vencimento = `11/06` · sub-label `vencendo` · pill `Vencendo`
 *   chip "Só atrasados" = 0 · "1 lançamento"
 *
 * Ou seja: a BASELINE está coerente com o literal `2026-06-11` e com o estado
 * data-futura. O `06/06`, se real, está no lado ACTUAL — não na baseline.
 *
 * Duas hipóteses foram levantadas e MORRERAM na medição, e ficam aqui pra ninguém
 * gastar a mesma hora:
 *
 *   1. Vazamento cross-teste (a família do `oficina-os`, que o workflow documenta):
 *      REFUTADA. `fin_titulos` tem UM só escritor em `tests/Browser/`
 *      (`FinanceiroFlowBaselineTest`), e ele roda DEPOIS do `PixelBaselineTest`
 *      nos DOIS modos — update e verify. Não há como contaminar o pixel.
 *   2. Versão antiga do fixture com `2026-06-06`: REFUTADA. O arquivo tem 2
 *      commits (#4230 e #5870) e o literal sempre foi `2026-06-11`.
 *
 * Correção de um detalhe da frase acima, pra ela não induzir a próxima varredura:
 * `Modules/Financeiro/Database/Seeders/FinanceiroDemoSeeder.php` TAMBÉM escreve
 * `fin_titulos` (com datas relativas a `Carbon::now()`). Ele não muda nada aqui —
 * não está na cadeia do `DatabaseSeeder` nem é chamado pelo workflow, logo não roda
 * no gate. Mas "único seeder do repositório" é falso; o verdadeiro é "único que
 * RODA no job visual-regression".
 *
 * O que continua aberto: por que o actual renderiza `06/06`. `Index.tsx:1142-1147`
 * deriva o `dd/mm` de `row.vencimento.split('-')`, então só um `vencimento` em
 * 6 de junho produz aquilo — e nada no caminho de seed do job grava essa data.
 * Confira na baseline NOVA (decodifique o `.snap` do PR de rebake e olhe) antes
 * de tratá-la como certa.
 */
class VisregFinanceiroFlowSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('fin_titulos')->updateOrInsert(
            ['business_id' => 1, 'origem' => 'manual', 'origem_id' => 987654, 'parcela_numero' => 1],
            [
                'numero' => 'VISREG-FIN-001',
                'tipo' => 'receber',
                'status' => 'aberto',
                'cliente_descricao' => 'Cliente de prova visual',
                'valor_total' => 1500.00,
                'valor_aberto' => 1500.00,
                'moeda' => 'BRL',
                'emissao' => '2026-06-11',
                'vencimento' => '2026-06-11',
                'competencia_mes' => '2026-06',
                'parcela_total' => 1,
                'created_by' => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
