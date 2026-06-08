# JANA V2 — Roteiro Demo Piloto (15 minutos)

> **Tipo:** Roteiro de apresentação síncrona para 1 cliente piloto
> **Goal CYCLE-06 G3:** Jana V2 demo apresentável a 1 piloto
> **Data alvo demo:** semana 2026-05-19 a 2026-05-23 (cycle ativo)
> **Duração:** 15 minutos (10min demo + 5min Q&A)
> **Modo:** screen-share síncrono via Meet/Zoom + dados reais piloto
> **Apresentador:** Wagner (com Claude pair em background pra suporte recall)
> **Refs:** [BRIEFING.md](../BRIEFING.md) · [ARCHITECTURE.md](../ARCHITECTURE.md) · [ADR 0035](../../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md) · [ADR 0052](../../../decisions/0052-memoria-jana-3-angulos-faturamento.md) · [ADR 0053](../../../decisions/0053-mcp-server-governanca-como-produto.md)

---

## 0. Contexto & posicionamento (1min)

**Pitch de abertura (verbatim sugerido):**

> "Você sabe como hoje seu ERP te entrega *relatórios* — números frios, gráficos? A Jana é diferente: ela é uma **analista IA com memória persistente do seu negócio**. Toda manhã ela manda um brief de 4 parágrafos contando o que aconteceu ontem, o que pede atenção hoje e o que dá pra fazer agora. Ela lembra de tudo que você já conversou, integra com seu chat, suas vendas, sua produção, e nunca mistura dados com nenhuma outra empresa. Vou te mostrar em 10 minutos."

**Pontos a deixar implícitos (não vender ainda):**

- LGPD/multi-tenant Tier 0 (princípio duro silencioso — só cita se cliente perguntar)
- Stack técnica (laravel/ai, MCP server, Meilisearch hybrid) — vende valor, não arquitetura
- Brain B (Sonnet/Opus) — citar só se cliente perguntar custo

---

## 1. Login + landing — Brief Diário (3min)

**Tela:** `https://oimpresso.com/copiloto` logado como piloto

**Roteiro:**

1. "Aqui é o que a Larissa/Martinho/Vargas vê toda manhã ao abrir o ERP" — landing já carrega o brief do dia anterior gerado às 06h BRT
2. **Mostrar brief diário renderizado** — narrativa ~300 palavras estruturada em 4 parágrafos:
   - Parágrafo 1: faturamento ontem vs média 7d + comparação mesmo dia semana anterior
   - Parágrafo 2: vendas concluídas, em produção, em atraso (cruza Sells FSM stage)
   - Parágrafo 3: inadimplência ativa + vencimentos próximos 7d (Asaas + Inter)
   - Parágrafo 4: 1-2 oportunidades (cliente sem compra ≥30d, ticket sem follow-up, NFe pendente)
3. **Destacar:** "Esse texto não é template. A Jana lê os dados reais do seu negócio, hoje pela manhã, e escreve do zero. Se ontem nada vendeu, ela fala isso, não inventa."
4. **Footer auditável:** mostrar metadata footer — "gerado em HH:MM, modelo gpt-4o-mini, custo R$ 0,30, fontes: 3 ângulos faturamento + Sells stages + Asaas". Wagner enfatiza: "Você pode auditar de onde veio cada número."

**Tempo:** 3min — não overrun, brief é o gancho

**Se cliente engajar:** "Quer que eu mostre o brief de ontem ou de uma semana atrás?" — navegação rápida pra brief histórico (todos persistidos em `mcp_briefs`)

---

## 2. Chat estruturado — memória em ação (4min)

**Tela:** mesma `/copiloto` — clica no chat (single-thread por business)

**Pergunta 1 (warm-up):** "Jana, quanto vendi essa semana?"

- Jana responde em ~3-5s com número real do banco + comparação anterior
- Tom: "Faturamento da semana de 2026-05-12 a hoje: R$ X.XXX,XX, contra R$ Y.YYY,YY na semana anterior (+/- Z%)"
- **Destacar:** "Ela tem 3 ângulos canônicos de faturamento — bruto, líquido, recebido. Pergunta qualquer um e ela sabe a diferença."

**Pergunta 2 (memória de conversa anterior — pré-seedada):** "Lembra daquela cliente que reclamou da entrega atrasada semana passada?"

- Jana recall via `MemoriaContrato` + `MeilisearchDriver` hybrid (HyDE expander + LLM reranker + RRF + BGE)
- Resposta: "Sim — Maria Souza (CPF [REDACTED]), pedido #1234, atrasou 3 dias. Você anotou que ela aceitou 10% desconto no próximo pedido. Quer que eu cheque se ela já voltou a comprar?"
- **Destacar:** "Isso não é um arquivo de texto. É memória multi-tenant — só o seu negócio enxerga. Eu (Wagner) tenho outro, e a Jana NUNCA mistura."

**Pergunta 3 (sugestão de ação HITL):** "Quem tem boleto vencendo essa semana?"

- Lista 3-5 clientes com valores + datas vencimento
- Jana propõe: "Posso disparar lembrete WhatsApp pra esses 5 agora — você confirma?"
- **NÃO clicar confirmar** na demo (HITL existe — mostrar que tem trava antes de ação real)

**Destacar verbal:** "Toda ação que mexe com cliente, com dinheiro, com nota fiscal — passa pela sua aprovação. A Jana sugere, você decide."

**Tempo:** 4min

---

## 3. Dashboard de governança — diferencial silencioso (2min)

**Tela:** `/copiloto/admin/governanca` (mostrar 30s) + `/copiloto/admin/memoria` (mostrar 1min)

**Roteiro curto (não over-explicar):**

1. **Governança MCP:** "Esse painel mostra TODA decisão que a IA tomou nas últimas 24h — qual modelo usou, quanto custou, quais documentos consultou, qual resposta deu. Auditoria total."
2. **Memória 352+ docs:** "Aqui está o conhecimento que ela tem do seu negócio — 352+ docs indexados, sincronizados, com preview e link pro código fonte. Você sabe exatamente o que ela sabe."
3. **Health checks:** apontar dashboard "5 checks daily — vazamento multi-tenant: 0, PII leak: 0, brief uptime: 100%"

**Destacar verbal:** "Nenhum concorrente brasileiro tem isso. ChatGPT da OpenAI não tem multi-tenant LGPD. Bling/Tiny/Omie têm IA, mas sem memória persistente real."

**Tempo:** 2min

---

## 4. Integração FSM (Sells/Repair) — onde Jana virou agente (2min)

**Tela:** `/sells` lista de vendas + drawer SaleSheet aberto

**Roteiro:**

1. Abre uma venda no stage `producao_iniciada`
2. **Mostrar drawer FsmActionPanel** com botões dinâmicos (Iniciar Produção, Pausar, Concluir)
3. Pede pra Jana no chat: "Jana, essa venda #1234 tá parada há 5 dias na produção, o que rola?"
4. Jana cruza FSM history + stages + responde: "Última transição foi 2026-05-11, action `iniciar_producao` por João. Desde então sem movimento. Há 2 vendas mais antigas no mesmo stage. Sugiro priorizar por data limite — quer que eu monte a lista?"
5. **Destacar:** "FSM Pipeline LIVE em prod desde 2026-05-12 — toda mudança auditável, Jana usa essa fonte sem tunar nada."

**Tempo:** 2min

---

## 5. Encerramento + próximos passos (3min — Q&A flexível)

**Pitch de fechamento:**

> "Você viu 4 coisas: brief diário automático, chat com memória real, governança auditável, e Jana agente integrada ao chão de fábrica/loja. Tudo isso por R$ X/mês (mostrar tier preço). Próximo passo: 30 dias canary, seus dados, monitor diário comigo, sem compromisso de continuar."

**Perguntas esperadas — respostas curtas:**

| Pergunta | Resposta curta |
|---|---|
| "Quanto custa de OpenAI?" | "Brief diário R$ 0,30/dia. Chat varia uso — média R$ 5-15/mês por business piloto." |
| "Meus dados ficam onde?" | "MySQL no Hostinger BR. Embeddings cifrados, multi-tenant Tier 0. LGPD compliant. ADR pública." |
| "Posso desligar a IA?" | "Sim — toggle por business. Brief diário e chat são opt-in independentes." |
| "Funciona pra meu setor?" | (Vestuário: "Larissa biz=4 usa há 6+ meses." / Comunic. Visual: "Em construção — você seria piloto canary." / Oficina: "Em construção — você seria piloto canary.") |
| "Se a Jana errar?" | "HITL — toda ação cliente/dinheiro/NFe passa pela sua aprovação. Brief é só texto, errar não quebra nada." |

**NÃO prometer na demo:**

- ⛔ "Vai estar pronto em X semanas" (sem comprometimento de roadmap externo)
- ⛔ "Integra com Bling/Tiny" (não integra)
- ⛔ "Substitui contador" (não substitui — apoia)

---

## 6. Pós-demo (imediato)

1. **Mandar resumo Wagner→Cliente** em 24h: 1 página com print do brief + tier preço + opção 30d canary
2. **Spawn task MCP** `tasks-create module:Jana title:"Follow-up demo <cliente>" assignee:Wagner due:+2d`
3. **Session log** `memory/sessions/2026-05-16-demo-pilot-<cliente>.md` registrando: o que mostrou, o que perguntaram, o que ficou de homework
4. **Update BRIEFING.md** seção "Cliente piloto" se cliente aceitar canary

---

## 7. Checklist running demo (D-0)

Ver [JANA-V2-PILOTO-CHECKLIST.md](JANA-V2-PILOTO-CHECKLIST.md) — pré-flight obrigatório 2h antes da demo.
