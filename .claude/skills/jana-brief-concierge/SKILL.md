---
name: jana-brief-concierge
mission: "Transformar snapshot JSON do BriefDiarioService em brief executivo markdown PT-BR pronto pra Wagner enviar manualmente pro cliente JANA Pro via WhatsApp ou email. Modo Concierge MVP (caminho B, ADR 0140 §Alternativa-C) — Wagner é o agente até ter $ pra ligar provider LLM automatizado."
description: ATIVAR quando user (Wagner) colar/citar um JSON com chaves `version`, `business_id`, `sources` (vendas/inadimplencia/tickets/nfe/oportunidades) OU pedir "gera brief concierge", "/jana-brief", "brief diário pra biz X", "transforme esse snapshot em brief", "brief JANA Pro modo manual". Gera narrativa markdown ~250-400 palavras seguindo as MESMAS instructions do BriefDiarioAgent (Modules/Jana/Ai/Agents/BriefDiarioAgent.php) — quando Wagner ligar provider Groq/Haiku automatizado depois, a saída é idêntica. Trust L1 — só escreve narrativa, NÃO envia mensagem nem altera DB.
type: process-skill
status: active
version: 0.1.0
trust_level: L1
owner: wagner
created_at: 2026-05-11
updated_at: 2026-05-11
parent_mission: "JANA Pro como produto comercial SaaS (ADR 0140) — modo Concierge MVP enquanto não tem caixa pra automatizar."
charter_adr: 0140

triggers_on:
  - "gera brief concierge"
  - "brief concierge biz {N}"
  - "/jana-brief"
  - "/brief-concierge"
  - "transforme esse snapshot em brief"
  - "brief JANA Pro manual"
  - "brief diário pra biz {N}"
  - "PADRÃO JSON: snapshot com chaves version/business_id/sources colado pelo user"

tier: B
tags: [jana-pro, concierge, mvp, brief-diario]
related_adrs: [0140, 0141]
related_files:
  - Modules/Jana/Services/BriefDiarioService.php
  - Modules/Jana/Ai/Agents/BriefDiarioAgent.php
  - memory/requisitos/Jana/RUNBOOK-jana-pro-concierge.md
---

# jana-brief-concierge — Skill modo Concierge MVP

## Quando ATIVAR

Sempre que Wagner trouxer um snapshot JSON do JANA Pro pra eu transformar em brief executivo pronto pra envio manual.

**Padrões de entrada que disparam:**

1. Snapshot colado completo no chat:
   ```json
   {"ok":true,"snapshot":{"generated_at":"...","business_id":N,"version":"0.1.0","sources":{...}}}
   ```
2. Comando explícito: `/jana-brief biz=4` (Wagner traz snapshot via tool MCP ou fetch curl)
3. Wagner descrevendo dados: "vendas hoje 4 sells R$ [redacted Tier 0] ontem nada, 5 NFe rejeitadas, antonella sumiu há 322 dias"

## Quando NÃO ativar

- Conversa genérica sobre JANA Pro produto (use leitura ADR 0140 direto)
- Refactor/feature de código `Modules/Jana/` (use grep/edit normal)
- Pedido de análise de ticket individual (use skill `ticket-triage` — diferente escopo)

## Output obrigatório (formato fixo — Versão A Dashboard/Email)

Markdown profissional ~300-500 palavras, formato Dashboard/Email rico (renderiza em GitHub, email HTML, web preview). **Mesma estrutura do prompt no `BriefDiarioAgent::instructions()`** — quando provider LLM ligar, output é idêntico (zero retrabalho).

Wagner aprovou Versão A em 2026-05-12 ("encanta a demonstração"). NÃO usar formato compacto WhatsApp aqui — esse vira derivativo gerado a partir do Dashboard se solicitado.

```markdown
# 🌅 Brief Diário — [Nome da empresa]

**[Dia da semana], [DD] de [mês] de [YYYY]** · gerado às [HHhMM] BRT

---

## ⭐ Destaque do dia

> [Frase forte com o número mais relevante. Tom confiante, BR.]

---

## 📊 Operação

| Período | Vendas | Receita | Ticket médio |
|---|---:|---:|---:|
| Ontem | X | R$ X | R$ X |
| Semana atual | X | R$ X | R$ X |
| Mês até hoje | X | R$ X | R$ X |
| [Mês anterior] fechado | X | R$ X | R$ X |

[1 frase de interpretação do ticket médio / volume]

---

## 📈 Projeção do mês

USAR `projecao_fechamento_mes` + `delta_projetado_pct` da tool. NUNCA cite `delta_mes_pct` cru sozinho (compara mês incompleto vs completo = falso alarme).

"No ritmo atual (X vendas/dia), [mês] deve fechar em ~R$ X (vs R$ Y mês anterior → ±Z%)."

---

## ✅ Status geral

| Indicador | Estado |
|---|---|
| Inadimplência | 🟢/🔴 [valor] |
| Atendimento Inbox | 🟢/⚪ [pendências] |
| Movimento fiscal | 🟢/🔴 [emitidas/rejeitadas] |

---

## ⭐ Oportunidade-foco do dia

### [NOME LITERAL EM CAPS]

| Métrica | Valor |
|---|---|
| LTV histórico | **R$ X** |
| Última compra | data |
| Tempo ausente | X dias |

[2-3 frases racional do foco — janela de retorno, valor relativo]

**📱 Mensagem sugerida (copia e cola no WhatsApp):**

> [Mensagem pronta, voz da loja, 30-60 palavras]

---

## 💡 Ideia da semana

| Produto | Saídas em 90d |
|---|---:|
| NOME LITERAL | X |

Sugestão: [ação + racional + estimativa impacto]

---

## 🎯 Plano do dia

1. **[Ação 1]** (~X min) — [racional]
2. **[Ação 2]** (~X min) — [racional]

Resto do dia segue normal.

---

*JANA PRO · análise gerada automaticamente · próximo brief: amanhã, 8h*
```

## Regras duras (anti-fabricação)

1. **NUNCA invente dados.** Se source retornou `ok:false` ou vazio, OMITA a seção (não force preencher).
2. **NUNCA cite `delta_mes_pct` cru** se mês corrente está incompleto. USE `projecao_fechamento_mes` + `delta_projetado_pct` (presentes desde fix US-COPI-202c).
3. **NUNCA cite combo de cliente walk-in** (contact com `is_default=1` no UltimatePOS) — é produto best-seller agregado, não combo individual. Esses contacts NÃO devem aparecer em `combo_candidatos`/`reativacao_candidatos` desde fix US-COPI-202c, mas se aparecer, ignore.
4. **Cite valores LITERAIS do JSON** — R$ formatado PT-BR (R$ [redacted Tier 0]) + porcentagens 1 casa (5,2%).
5. **Cite NOMES literais** das tools (contact_name, product_name) — não generalize "um cliente sumiu".
6. **Tom advisor sênior, brasileiro, sem corporativês.** "Conforme análise dos indicadores apresentados" PROIBIDO.
7. **Mensagem WhatsApp sugerida tem voz da loja**, não do consultor JANA.
8. **Se TODAS sources voltaram vazias:** responda "Sem movimento relevante hoje. Bom dia pra começar do zero. ☕"
9. **Tier 0:** snapshot tem `business_id` — nunca misture com outro biz nem ofereça cross-tenant.

## Algoritmo (mesma lógica que vai pro LLM automatizado depois)

### Passo 1 — Parse snapshot

Identifica chaves: `vendas`, `inadimplencia`, `tickets`, `nfe`, `oportunidades`. Cada uma tem `ok:true|false` + dados.

### Passo 2 — Priorizar sinais por severidade

Ordem fixa pra montar narrativa:

| Sinal | Quando virar destaque |
|---|---|
| **Crítico** (Alertas) | `nfe.taxa_rejeicao_pct > 20` OU `inadimplencia.total_devido_atrasado > 0` OU ticket `prioridade=P1` |
| **Atenção** (Alertas) | `vendas.delta_semana_pct < -20%` OU ticket com `tem_palavra_critica=true` |
| **Positivo** (Vendas) | `vendas.delta_mes_pct > 30` OU `vendas.ticket_medio` em recorde |
| **Oportunidade** | `oportunidades.combo_candidatos[]` OU `reativacao_candidatos[]` com LTV > R$ [redacted Tier 0] |

### Passo 3 — Cortar gordura

Brief é pra celular. Cada seção MAX 60 palavras. Se 2 alertas similares, agrupa. Se vendas e oportunidades não tem nada notável, omite a seção.

### Passo 4 — Validar antes de devolver

Checklist mental antes de output final:
- [ ] Citei dados reais ou inventei algo?
- [ ] Algum CPF/CNPJ/dado sensível vazou? (não deveria — `BriefDiarioService` não retorna PII)
- [ ] Tom é brasileiro direto ou virou corporativês?
- [ ] Cabe na tela do celular (~400 palavras)?
- [ ] Tem 1 ação concreta no final?

## Pós-output

Não envio nada nem mexo em DB. Wagner copia o markdown e:

- **WhatsApp**: cola direto no chat do cliente
- **Email**: marcad o convert markdown → HTML (ou usa cliente email que renderiza markdown)

## Promoção pra produto operacional automatizado

Esta skill é **espelho dev** do `BriefDiarioAgent` PHP. Quando Wagner ligar provider LLM automatizado (caminho A — Groq/Haiku):

1. `Modules/Jana/Ai/Agents/BriefDiarioAgent.php` roda em produção via `BriefDiarioJob` schedule 8h BRT
2. Esta skill **continua válida** pra debug/preview manual sem queimar tokens
3. **Mesma estrutura de output** garante: brief gerado pelo LLM real é indistinguível do gerado por mim aqui

## Anti-patterns (NÃO fazer)

- ❌ **Enviar mensagem WhatsApp/email por mim** — sou skill de geração, não delivery
- ❌ **Persistir em DB** (`mcp_briefs`) — Sprint A US-COPI-204 vai fazer isso quando ligar agent
- ❌ **Inventar dado faltante** "ah deve ter pelo menos 1 inadimplente, vou citar genérico"
- ❌ **Resposta em inglês** ou tom corporativo "Conforme análise dos indicadores apresentados..."
- ❌ **Adicionar info que não tá no JSON** "também notei que tua margem deve estar caindo" — sem fonte = sem fala

## Como Wagner ativa

Opção 1 — colar JSON puro:
```
{cola o JSON completo no chat}
```
Skill detecta padrão automático.

Opção 2 — slash:
```
/jana-brief biz=4
```
Eu peço Wagner buscar via `curl https://oimpresso.com/copiloto/admin/jana-pro/preview?business_id=4` (ou ele mesmo busca + cola).

## Versionamento

- **0.1.0** — versão inicial Concierge MVP (2026-05-11)
- **0.2.0** — quando provider Groq ligar, adicionar comando `/jana-brief --provider=groq` que valida que o agent PHP produz output equivalente
- **1.0.0** — quando JANA Pro tiver ≥5 clientes pagantes + agent automatizado em prod estável 30d → skill fica como debug-only

## Referências

- **[ADR 0140](memory/decisions/0140-jana-pro-produto-comercial-saas.md)** — JANA Pro produto SaaS (alternative C — concierge MVP)
- **[ADR 0141](memory/decisions/0141-agents-tool-use-pattern-claude-code.md)** — Pattern "estilo Claude Code" Camada B v2
- **`Modules/Jana/Services/BriefDiarioService.php`** — gera o snapshot JSON
- **`Modules/Jana/Ai/Agents/BriefDiarioAgent.php`** — versão automatizada (dormente até ligar provider)
- **`memory/requisitos/Jana/RUNBOOK-jana-pro-concierge.md`** — playbook operacional (esta sessão)
