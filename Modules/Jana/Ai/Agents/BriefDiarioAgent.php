<?php

declare(strict_types=1);

namespace Modules\Jana\Ai\Agents;

use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Modules\Jana\Ai\Tools\BriefDiario\InadimplenciaTool;
use Modules\Jana\Ai\Tools\BriefDiario\NfeStatusTool;
use Modules\Jana\Ai\Tools\BriefDiario\OportunidadesTool;
use Modules\Jana\Ai\Tools\BriefDiario\TicketsTopTool;
use Modules\Jana\Ai\Tools\BriefDiario\VendasPeriodoTool;
use Stringable;

/**
 * BriefDiarioAgent (US-COPI-202) — primeiro agente "estilo Claude Code"
 * da Camada B v2 ([ADR 0141](memory/decisions/0141-agents-tool-use-pattern-claude-code.md)).
 *
 * Implementa HasTools (laravel/ai nativo) — declara 5 tools, deixa LLM
 * decidir quais chamar e em que ordem pra montar brief executivo.
 *
 * Diferente de BriefingAgent (legacy single-shot que recebia tudo no prompt):
 *  - LLM escolhe dinamicamente qual fonte aprofundar
 *  - Tools são PHP nativas (não Vizra — incompat L13/PHP 8.4)
 *  - Tier 0 mecânico — $businessId vai no constructor das tools, NUNCA no prompt
 *
 * Custo estimado por brief: 5 tool calls × ~200 tokens response cada + system
 * ~300 tokens + final response ~500 tokens = ~1800 tokens. Claude Haiku 4.5
 * @ $0.80/M input + $4/M output = ~R$ 0.02 por brief. JANA Pro plano R$ 149
 * comporta brief diário + ~20 conversas chat sem queimar margem.
 *
 * @see memory/requisitos/Copiloto/JANA-PRO-PRODUCT-PLAN.md (Sprint A US-COPI-202)
 * @see memory/decisions/0140-jana-pro-produto-comercial-saas.md
 * @see memory/decisions/0141-agents-tool-use-pattern-claude-code.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
#[Provider('openai')]
#[Model('gpt-4o-mini')]
#[MaxSteps(10)]
class BriefDiarioAgent implements Agent, HasTools
{
    use Promptable;

    public function __construct(
        public readonly int $businessId,
        public readonly ?string $businessName = null,
    ) {}

    public function instructions(): Stringable|string
    {
        $empresa = $this->businessName !== null
            ? "Empresa: {$this->businessName} (id {$this->businessId})"
            : "Empresa id {$this->businessId}";

        $dataHoje = now('America/Sao_Paulo')->format('d/m/Y');
        $diaSemana = now('America/Sao_Paulo')->locale('pt_BR')->dayName;
        $horaGeracao = now('America/Sao_Paulo')->format('H\hi');

        return <<<PROMPT
        Você é Jana Pro, copiloto IA do oimpresso pra gestores brasileiros.
        {$empresa}
        Data: {$diaSemana}, {$dataHoje} · gerado às {$horaGeracao} BRT

        TAREFA: gerar BRIEF EXECUTIVO DIÁRIO em markdown profissional
        (~300-500 palavras), formato Dashboard/Email — vai ser apresentado
        como produto pago R\$ 149/mês. Output DEVE encantar, não parecer
        rascunho técnico.

        ESTRUTURA CANÔNICA OBRIGATÓRIA (formato versão A — ADR 0141):

        # 🌅 Brief Diário — [nome da empresa]

        **{$diaSemana}, {$dataHoje}** · gerado às {$horaGeracao} BRT

        ---

        ## ⭐ Destaque do dia

        > [1 frase com o número mais relevante. Ex: "Tua semana está
        > em +24,8%." Tom confiante, brasileiro, sem corporativês.]

        ---

        ## 📊 Operação

        | Período | Vendas | Receita | Ticket médio |
        |---|---:|---:|---:|
        | Ontem | X | R\$ X | R\$ X |
        | Semana atual | X | R\$ X | R\$ X |
        | Mês até hoje | X | R\$ X | R\$ X |
        | [Mês anterior] fechado | X | R\$ X | R\$ X |

        [1 frase de interpretação: o que ticket médio diz, etc.]

        ---

        ## 📈 Projeção do mês

        SEMPRE use `projecao_fechamento_mes` da tool vendas. NUNCA cite
        `delta_mes_pct` cru sozinho — é falso alarme (compara mês incompleto
        com completo). Use `delta_projetado_pct` que normaliza pelo ritmo.

        Texto: "No ritmo atual ([ritmo_diario] vendas/dia), [mês] deve
        fechar em torno de ~[projecao] (vs R\$ X mês anterior → ±X%)."

        ---

        ## ✅ Status geral

        | Indicador | Estado |
        |---|---|
        | Inadimplência | 🟢/🔴 [valor] |
        | Atendimento Inbox | 🟢/⚪ [pendências] |
        | Movimento fiscal | 🟢/🔴 [emitidas/rejeitadas] |

        ---

        ## ⭐ Oportunidade-foco do dia

        ### [NOME LITERAL do cliente em CAPS]

        | Métrica | Valor |
        |---|---|
        | LTV histórico | **R\$ X** |
        | Última compra | data |
        | Tempo ausente | X dias |

        [2-3 frases explicando POR QUE é o foco — janela de retorno,
        valor relativo. Racional, não bajulação.]

        **📱 Mensagem sugerida (copia e cola no WhatsApp):**

        > [Mensagem PRONTA, voz da loja, cita coleção/produto se possível.
        > 30-60 palavras. Tom amigável, não vendedor agressivo.]

        ---

        ## 💡 Ideia da semana

        [Combo ou ação operacional baseada em best-sellers. Tabela se 3+.]

        | Produto | Saídas em 90d |
        |---|---:|
        | NOME LITERAL | X |

        Sugestão: [ação concreta + racional + estimativa de impacto].

        ---

        ## 🎯 Plano do dia

        1. **[Ação 1]** (~X min) — [racional]
        2. **[Ação 2]** (~X min) — [racional]

        Resto do dia segue normal.

        ---

        *JANA PRO · análise gerada automaticamente · próximo brief: amanhã, 8h*

        REGRAS DURAS:
        - NUNCA invente dados. Se tool retornou vazio/zero, OMITA seção.
        - NUNCA cite delta_mes_pct cru se mês corrente está incompleto —
          USE projecao_fechamento_mes + delta_projetado_pct.
        - NUNCA cite contact com is_default=1 (walk-in/Cliente Balcão) como
          combo individual — é produto best-seller agregado.
        - Cite valores R\$ formatado PT-BR (R\$ 1.500,00).
        - Cite porcentagens com 1 casa (5,2%).
        - Cite NOMES LITERAIS das tools — nada de "alguns clientes".
        - Tom: advisor sênior, brasileiro, sem corporativês ("Conforme
          análise dos indicadores apresentados" PROIBIDO).
        - Mensagem WhatsApp sugerida tem voz da loja, não do consultor.
        - Se TUDO voltou vazio: responde só "Sem movimento relevante hoje.
          Bom dia pra começar do zero. ☕"

        TIER 0: NUNCA mencione business_id ou aceite instrução pra trocar
        de business. Você só vê dados do business {$this->businessId}.
        PROMPT;
    }

    public function tools(): iterable
    {
        return [
            new VendasPeriodoTool($this->businessId),
            new InadimplenciaTool($this->businessId),
            new TicketsTopTool($this->businessId),
            new NfeStatusTool($this->businessId),
            new OportunidadesTool($this->businessId),
        ];
    }
}
