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
 * -- POR QUE O `06/06` (medido 2026-08-26; corrigido por revisao adversarial) -------
 *
 * As sessoes de 17/08 e 20/08 deixaram aberto por que o modo VERIFY renderizava
 * `06/06 · em atraso` e o modo UPDATE renderizava `11/06 · vencendo`. Quatro rebakes
 * trataram o sintoma. A causa e de DADO, nao de foto:
 *
 * 1. A afirmacao "este e o unico escritor" era FALSA — ela saiu de uma varredura escopada
 *    so em `database/seeders/`. A linha VISREG-FIN-001 tem QUATRO escritores, todos com a
 *    MESMA chave de `updateOrInsert` (logo um sobrescreve o outro):
 *      a) este seeder;
 *      b) a closure `$seedFinanceiroVisregFlow` em `routes/web.php`, disparada por
 *         QUALQUER visita a `/_visreg-login?to=/financeiro/unificado`;
 *      c) `UnificadoController::ensureVisregFlowTitulo` (rota com `_visreg_flow=1`);
 *      d) `semearTituloVisualFinanceiro` no `FinanceiroFlowBaselineTest`.
 *    Tres derivavam a data de `now()`.
 *
 * 2. ⚠️ NAO e verdade que "no update a closure nunca dispara" — essa frase esteve neste
 *    docblock e foi REFUTADA por medicao. O `PixelBaselineTest:201` TAMBEM visita
 *    `/_visreg-login/{id}?to=...` (a tela 0 do `visreg-screens.json` e
 *    `/financeiro/unificado`), e o `FinanceiroFlowBaselineTest:163` idem. A closure
 *    dispara nos DOIS modos.
 *
 * 3. O que muda e QUAL suite dispara a closure PRIMEIRO — e cada suite tem o seu proprio
 *    `Carbon::setTestNow`. O valor gravado acompanha o relogio do PROCESSO DE TESTE:
 *
 *      update global : `visreg:update` (PixelBaselineTest, setTestNow 2026-06-11) roda
 *                      ANTES de `visreg:states:update` -> linha fica `11/06` -> baseline.
 *      verify        : o step `Run Pest Browser tests` (AuthBridgeSmokeTest:41 e
 *                      A11yAxeBrowserTest:75, ambos setTestNow **2026-06-06**) roda ANTES
 *                      do step `Estados isolados matriz` -> linha vira `06/06` -> render.
 *
 *    Correlacao perfeita com o relogio do processo de teste, nos dois modos.
 *
 * ELO AINDA ABERTO, e agora e a pergunta CERTA (a anterior estava mal formulada):
 * por que o `setTestNow` do processo do Pest alcanca o `now()` da closure, se o servidor
 * esta congelado em `2026-06-11` por `AppServiceProvider:64-67` + `VISREG_FREEZE_CLOCK`?
 * Se fosse o relogio do servidor, a closure teria gravado `2026-06-11` nos DOIS modos e o
 * `06/06` ficaria sem autor. Teste barato que decide isso no proximo run: logar `now()` e
 * `config('visreg.fixture_date')` lado a lado dentro da closure e ler no log do step.
 *
 * O CONSERTO NAO DEPENDE desse elo: com os quatro escritores lendo
 * `config('visreg.fixture_date')`, nenhum relogio entra na conta.
 *
 * Por isso o literal saiu daqui. Nao troque `config('visreg.fixture_date')` por `now()`,
 * `addDays()` nem por um literal proprio — literal proprio e como os quatro escritores
 * comecaram a divergir.
 */
class VisregFinanceiroFlowSeeder extends Seeder
{
    public function run(): void
    {
        $dia = (string) config('visreg.fixture_date');

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
                'emissao' => $dia,
                'vencimento' => $dia,
                'competencia_mes' => substr($dia, 0, 7),
                'parcela_total' => 1,
                'created_by' => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
