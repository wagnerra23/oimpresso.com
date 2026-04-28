# ADR 0046 — `ChatCopilotoAgent` precisa de contexto rico + tools (gap descoberto)

**Status:** ✅ Aceita — gap formalmente reconhecido, prioridade de Cycle 02
**Data:** 2026-04-28
**Escopo:** Modulo Copiloto — agente de chat
**Relacionado:** [ADR 0035 stack canônica IA](0035-stack-ai-canonica-wagner-2026-04-26.md), [ADR 0037 roadmap memória Tier 7+](0037-roadmap-evolucao-tier-7-plus.md), [ADR 0042 Reverb](0042-reverb-substitui-pusher-cloud.md)

---

## Contexto

Em 2026-04-28 (sessão Reverb + Meilisearch + OpenAI), conseguimos plugar **IA real em produção** (OpenAI gpt-4o-mini via `laravel/ai`) + memória vetorial via Meilisearch. Wagner testou na conta da Larissa (biz=4) e o resultado foi:

```
Wagner: "qual faturamento?"
Copiloto: "Para te ajudar melhor, pode especificar a que faturamento você está se referindo?"

Wagner: "Faturamento da rota livre desse mes"
Copiloto: "Para fornecer informações sobre o faturamento da Rota Livre do mês, preciso que você me informe alguns dados, como o período exato (por exemplo, de que data a que data) e, se possível, informações prévias ou comparativas. Você tem esses dados disponíveis?"
```

Wagner: *"meio burrinho mas funcionou"*

**Diagnóstico:** o `ChatCopilotoAgent` tem `instructions()` enxuto (system prompt genérico de Copiloto) e o histórico de `Conversa::mensagens()`. **Não recebe**:

1. **`ContextoNegocio`** (faturamento_90d, clientes_ativos, módulos_ativos, metas_ativas) — ele existe em `Modules\Copiloto\Support\ContextoNegocio` e é usado pelo `BriefingAgent` (mensagem inicial fixture), mas o `ChatCopilotoAgent` ignora.
2. **Tools/function-calling** — não pode consultar `transactions`, `metas`, `customers` em runtime. Por isso pede pra Larissa "informar período exato" — porque ele LITERALMENTE não tem como saber.
3. **Memória recall** já funciona (sprint 5 entregue), mas só recupera o que foi extraído de conversas passadas — ainda não cobre dados estruturados do ERP.

## Decisão

Tratar como **GAP de produto formal** com prioridade de **Cycle 02** (não Cycle 01 — Cycle 01 é desbloquear conversa real Larissa-style, que está feito).

### Caminho proposto (3 opções, decidir em Cycle 02 abertura)

**Opção A: Injetar `ContextoNegocio` no system prompt** (mais simples)
- Override de `ChatCopilotoAgent::instructions()` pra incluir snapshot do business
- Esforço: 0.5 sprint
- Custo: ~500 tokens extras por chamada
- Limitação: snapshot estático — não responde "vendas de ontem" sem refresh

**Opção B: Tools/function-calling via `laravel/ai`** (estado-da-arte)
- `laravel/ai` tem suporte a tools nativo (Tool interface)
- Implementar 4-6 tools: `consultarFaturamento(periodo)`, `consultarTopClientes(n)`, `consultarMetas()`, `consultarVendas(filtros)`, etc.
- Esforço: 1.5-2 sprints
- Custo: tools só executam quando pedido (mais eficiente que A)
- Vantagem: agente pode encadear (consulta faturamento → compara com meta → sugere ação)

**Opção C: Híbrido A+B**
- Injetar contexto resumido (Opção A) — agent não precisa "perguntar" o básico
- Tools para dados específicos sob demanda (Opção B)
- Esforço: 2 sprints
- Provável escolha pra v2 do Copiloto

### Métrica de fé

Re-rodar 5-10 perguntas Larissa-style. Sem mudanças, ela vai abandonar (UX ruim). Validar com:
- *"qual o faturamento desse mês?"* → resposta com número, não pergunta de retorno
- *"quem é meu top cliente?"* → nome + valor, sem "me informe"
- *"quanto falta pra bater minha meta?"* → cálculo automático

Sprint 7 (RAGAS) **deve** ter cenários assim no golden set — sem isso, baseline mensura só o agent burro.

## Justificativa

1. **A IA real só não basta** — `gpt-4o-mini` é capaz de raciocinar e escrever bem, mas é um LLM puro. Sem tools/contexto, **alucina ou pede info que ele já deveria ter** (Larissa nunca vai informar período exato — ela quer "do mês").
2. **Modules/Financeiro já tem dados** — `transactions`, `summary`, `relatorios` — só falta exposição como tools pro agent.
3. **Diferencial competitivo do ADR 0026** — *"ERP gráfico com IA"* só faz sentido se a IA conhece o ERP. Hoje conhece zero.
4. **Validação direta com Larissa pendente** (A1 no Cycle 01) — esse gap pode aparecer naturalmente nos 3 cenários de teste e justifica priorizar tools.

## Consequências

✅ Cycle 02 tem 1 task de produto bem definida (Caminho A/B/C — Wagner decide).
✅ Sprint 7 RAGAS golden set pode incluir perguntas que **só** funcionam com tools — força a evolução.
✅ Honestamente, o Copiloto mergeado em 2026-04-26 (PR #13) cobriu sprint 1 (driver IA) — sprint 2/3 era exatamente Vizra ADK + tools, foi adiado por incompat L13.
⚠️ Se Larissa testar e desistir antes do Cycle 02 — virou risco comercial. Wagner avaliar comunicação ("estamos validando por 2 semanas, segue conversando que cada dia melhora").
⚠️ Tools = mais tokens = +custo. Manter cap em `gpt-4o-mini` enquanto não validar valor.

## Alternativas consideradas

- **Esperar Vizra ADK suportar L13** (rejeitado): sem ETA upstream — Wagner não pode bloquear Cycle 02 nisso.
- **Migrar pra `openai-php/laravel`** (rejeitado): tem tools mas é abandonado/legacy. ADR 0034 já decidiu `laravel/ai` como camada A.
- **Servidor MCP + Claude Desktop** (rejeitado pra esse gap): MCP é caso de uso diferente (admin/superadmin). Larissa quer chat direto no app dela.

## Refs

- `Modules/Copiloto/Ai/Agents/ChatCopilotoAgent.php` (linhas 31-48 — `instructions()` enxuto)
- `Modules/Copiloto/Support/ContextoNegocio.php` (existe mas só `BriefingAgent` usa)
- [laravel/ai docs — Tools](https://github.com/laravel/ai) (procurar `Tool` interface)
- Conversa real Larissa-style 2026-04-28: `memory/sessions/2026-04-28-meilisearch-vaultwarden.md`
