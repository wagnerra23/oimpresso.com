# Handoff 2026-05-12 08:48 — JANA Pro Brief Diário FUNCIONAL em prod (US-COPI-203 completa)

**Sessão:** manhã Wagner solo · Claude Code Max
**Duração:** ~3h iterativas
**PRs:** 5 (#608, #609, #611, #612, #616) — todas merged
**ADRs:** 0 (puro implementação Sprint A)
**Validação final:** Wagner aprovou em prod 2026-05-12 08h47 ("ficou ótimo pode fechar")

---

## Estado MCP no momento do fechamento

**Cycle ativo:** `CYCLE-05` — *Inter PJ prod + WhatsApp governança* (2026-05-11 → 2026-05-23, 12% decorrido).

**Tasks ativas Wagner:**
- US-RB-048 `p0` Inter PJ Banking RUNBOOK (DOING)
- US-WA-040 `p2`, US-COPI-096 `p2`, US-COPI-100 `p2` (DOING)

**JANA Pro Sprint A status (esta sessão):**
- US-COPI-201 (BriefDiarioService) ✅ done (sessão anterior)
- US-COPI-202 (BriefDiarioAgent foundation) ✅ done (sessão anterior)
- **US-COPI-202c (quality fixes walk-in + projeção mês + Versão A canonical) ✅ done** ← esta sessão
- **US-COPI-203 (brief invocável no chat /jana + OpenAI gpt-4o-mini) ✅ done** ← esta sessão
- US-COPI-204 (BriefDiarioJob + persistência mcp_briefs) — pending Sprint A continuação
- US-COPI-205 (Dashboard /jana/admin/jana-pro Inertia) — pending

---

## Cronologia narrativa

### Fase 1 (~07h-08h BRT) — Quality fixes JANA Pro (PR #608)

Wagner pediu via "versão A pode documentar e corrigir o erro antes" (após validar manualmente snapshot biz=4 ROTA LIVRE):

**2 bugs detectados em BriefDiarioService:**
1. Cliente Balcão (`contacts.is_default=1` UltimatePOS walk-in) vazando em `combo_candidatos` + `reativacao_candidatos` — várias clientes anônimas agregam no contact id=40 → falso combo individual
2. `delta_mes_pct` cru comparando mês incompleto (12d) vs completo anterior (30d) — gera falso alarme "-26.8%"

**PR #608 fixes:**
- `oportunidadesUpsell()` JOIN contacts + `whereRaw('c.is_default IS NULL OR c.is_default <> 1')` em ambas queries
- `vendasPeriodo()` retorna 5 campos novos: `dias_decorridos_mes`, `dias_restantes_mes`, `ritmo_diario`, `projecao_fechamento_mes`, `delta_projetado_pct`
- `BriefDiarioAgent::instructions()` reescrito Versão A canonical (~300-500 palavras, tabelas markdown, oportunidade-foco com mensagem WhatsApp pronta)
- Skill `jana-brief-concierge` espelhada com Versão A
- Pest R-COPI-202c-001..004 + R-JANA-001 anti-flakiness horário (4 novos + 1 regressão = 16/16 PASS)

### Fase 2 (~08h-09h BRT) — Bug crítico /jana "criar meta" + URLs legacy

Wagner reportou: botão "criar meta de faturamento mensal" no chat dá erro silencioso.

**Investigação:**
- Frontend `Chat.tsx` linha 142: `router.post('/copiloto/sugestoes/X/escolher')` — URL legacy
- Backend: prefix mudou pra `/jana/` ([ADR 0088 module rename](../decisions/0088-module-rename-php-only.md))
- Route::redirect `/copiloto/{any}` → `/jana/{any}` 301 está lá, MAS **POST via 301 PERDE o body** (HTTP semantics)
- Browser refaz como GET → 405 Method Not Allowed → fetch resp.ok=false → toast "Erro ao escolher meta"

**PR #609 fix:** 7 arquivos, 11 ocorrências `/copiloto/` → `/jana/`:
- `Chat.tsx` (POST escolher/rejeitar/conversas, GET nova conversa)
- `Pages/Jana/Dashboard.tsx`, `Memoria.tsx`, `Admin/{Custos,Governanca,Qualidade}/Index.tsx`
- `Components/cockpit/Sidebar.tsx`

### Fase 3 (~09h-10h BRT) — Wagner pediu testar brief na Jana com OpenAI

> "mimi e quero fazer o teste na Jana o que eu pergunto e se ele vai responder bonito formatadinho"

Decisão: ligar OpenAI gpt-4o-mini agora (Wagner mandou `D:\.credentials.txt` com nova key + revogou a antiga vazada em sessão noite anterior).

**Rotação OPENAI_API_KEY:**
- Local: PowerShell `[regex]::Replace` com `MatchEvaluator` (evita $-interpolation com chars especiais)
- Hostinger: PHP server-side script via SSH stdin + BOM UTF-8 stripping (PowerShell adiciona BOM em pipe)
- Validação Laravel `env('OPENAI_API_KEY')` retorna 164 chars ambos lados

**PR #611 (US-COPI-203):**
- `#[Provider('openai')] #[Model('gpt-4o-mini')] #[MaxSteps(10)]` no BriefDiarioAgent
- `BriefDiarioChatTrigger` service com 9 regex patterns unicode (ativa em "brief", "/brief", "como tá meu negócio", "como foi a semana", "manda o brief", etc)
- `ChatController.send()` + `sendStream()` interceptam antes do ChatCopilotoAgent
- Stream envia brief como 1 único chunk (agent não streaming nativo)
- Error handling: fallback amigável sem stack trace + log estruturado
- Pest R-COPI-203-001..004 (4/4 PASS, 42 assertions, 27 positivos + 9 negativos)

### Fase 4 (~10h-11h BRT) — Bug #2 críticos UX

Wagner testou pós-deploy `/jana` digitou "brief" → **CHAT TRAVOU sem feedback**.

**Investigação #1 (PR #612 hotfix):**
- `AssistantUiChat.tsx` linha 246 fazia `fetch('/copiloto/conversas/X/mensagens/stream')` — outra URL legacy que **PR #609 não pegou**
- POST via 301 → GET → 405 → silenciado no catch (timing UX comeu o toast)
- Fix: `/jana/conversas/` + 2 outros refs em Dashboard/Memoria

**Investigação #2 (PR #616 polimento):**
Wagner re-testou após hotfix → brief gerou OK mas:
1. **Tabelas markdown apareceram como texto cru** com pipes `| Período | Vendas |`
2. **Bubble assistant ficou vazio 5-8s** sem feedback durante geração

Root cause tabelas:
- `MarkdownTextPrimitive` (`@assistant-ui/react-markdown`) **NÃO suporta GFM tables**
- Substituí por componente local `MarkdownText` usando `ReactMarkdown + remarkGfm`
- Mapeamento Tailwind 4: table/thead/th/td com border + padding, h1/h2/h3 hierárquicos, blockquote com border-l-primary + bg-muted/30, hr/ul/ol/li/a externos

Root cause typing:
- `MessagePrimitive.Parts` direto sem fallback durante isRunning
- Fix: `TypingIndicator` (3 dots animados bounce stagger + "Jana está pensando…")
- `AssistantMessageContent` usa `useThread((s) => s.isRunning)` — confiável em ExternalStoreRuntime
- `useMessage().status.type='running'` NÃO funciona em external store sem streaming nativo (gotcha)

### Fase 5 — Validação Wagner em prod (08h47 BRT)

Wagner anexou screenshot de prod mostrando:
- Header "🌅 Brief Diário — WR2 Sistemas" renderizado
- "terça-feira, 12/05/2026 · gerado às 08h32 BRT"
- Blockquote "⭐ Destaque do dia" com border azul
- Tabela "📊 Operação" com **border + padding** (fix tomou efeito)
- Seções subsequentes hierárquicas

**Veredito Wagner:** "ficou ótimo pode fechar"

---

## PRs desta sessão (todos merged)

| PR | Conteúdo | Status |
|----|----------|--------|
| **#608** | fix(jana-pro) brief encanta demo — walk-in is_default=1 + projeção mês + formato Versão A canonical | ✅ merged |
| **#609** | fix(jana) URLs legacy /copiloto → /jana (7 arquivos, 11 occ) — primeira leva | ✅ merged |
| **#611** | feat(jana-pro) brief invocável do chat /jana — OpenAI gpt-4o-mini + BriefDiarioChatTrigger | ✅ merged |
| **#612** | hotfix(jana) URL legacy /copiloto stream travava chat sem feedback — segunda leva | ✅ merged |
| **#616** | fix(jana) brief markdown renderiza tabelas + typing indicator durante geração | ✅ merged |

**Total adicionado:** ~600 linhas (PHP + tests + TSX + skill update).
**Custo direto sessão:** R$ 0 (Max + Hostinger).
**Custo operacional brief produção:** R$ 0,003/brief gpt-4o-mini.

---

## Decisões importantes consolidadas

### 1. Versão A Dashboard é formato canonical do JANA Pro Brief
- ~300-500 palavras markdown profissional
- Estrutura fixa: Destaque do dia (blockquote) → Operação (tabela) → Projeção do mês → Status geral (tabela) → Oportunidade-foco com mensagem WhatsApp pronta → Ideia da semana → Plano do dia (2 ações)
- Aprovação Wagner 2026-05-12: "encanta a demonstração"

### 2. OpenAI gpt-4o-mini é provider default JANA Pro (até ter caixa pra Anthropic)
- `#[Provider('openai')] #[Model('gpt-4o-mini')] #[MaxSteps(10)]` na classe `BriefDiarioAgent`
- Custo R$ 0,003/brief × 30 dias × 100 clientes = R$ 7,94/mês (margem 99,95% no plano R$ 149)
- Quando ligar Anthropic Haiku 4.5 (futuro): apenas trocar atributos PHP, zero retrabalho prompt

### 3. Pattern intent shortcut no chat — interceptar antes do agent conversacional
- `BriefDiarioChatTrigger` regex-first (zero custo) → invoca BriefDiarioAgent direto
- 9 patterns unicode cobrindo: `/brief`, `brief`, `manda o brief`, "como tá meu negócio", "como foi a semana", etc
- Pattern reusável pra outros shortcuts futuros (chamar metas, atendimentos, etc)

### 4. UX brief não-streaming exige typing indicator
- Brief leva ~5-8s (tool loop pré-resposta) — bubble vazio = UX ruim
- `useThread.isRunning` em ExternalStoreRuntime (não `useMessage.status.type`)
- "Jana está pensando…" + 3 dots animados pulsing — sinal claro de progresso

### 5. Markdown tables exigem ReactMarkdown + remarkGfm (não MarkdownTextPrimitive)
- `@assistant-ui/react-markdown` MarkdownTextPrimitive não suporta GFM
- Substituir por componente local com `ReactMarkdown` + `remarkGfm` + Tailwind mapping
- Tabelas, blockquote, headings hierárquicos, hr, lists tudo renderiza bonito

---

## Lições aprendidas

### 1. PR #609 não foi exaustivo — bug crítico no AssistantUiChat
- `Chat.tsx` era só wrapper; componente real era `AssistantUiChat.tsx` (filho)
- Sweep `grep -rn "/copiloto/" resources/js` pegou só o pai
- **Pra próxima:** sempre grep recursivo + checar componentes pais E filhos quando refator URL prefix

### 2. PowerShell pipe stdin → SSH adiciona BOM UTF-8 silencioso
- `Get-Content -Raw | ssh ... 'cat > file'` resulta em BOM `\xEF\xBB\xBF` no início
- Solução: PHP server-side strip BOM (`preg_replace("/^\\x{EF}\\x{BB}\\x{BF}/u", "", $key)`)
- **Pra próxima:** preferir transferência via temp file SCP em vez de stdin pra credenciais

### 3. `useMessage.status` NÃO funciona em ExternalStoreRuntime sem streaming nativo
- Em external store, mensagens ficam status='complete' desde inserção
- Pra detectar "agent rodando", usar `useThread((s) => s.isRunning)` (passado via `useExternalStoreRuntime`)
- **Pra próxima:** documentar essa pegadinha na ADR se evoluir Sprint B chat agentic

### 4. 9 regex patterns unicode capturaram intent BR com folga
- `\b` word boundary precisa flag `/u` em PHP pra unicode multi-byte (`á`, `ê`, `ó`)
- Cobertura natural: 27 positivos detectados (Wagner curtiu variações), 0 falsos positivos em 9 negativos
- **Pra próxima:** quando expandir pra outros shortcuts (metas, atendimentos), seguir mesmo pattern

### 5. Wagner valida formato Versão A Dashboard sobre WhatsApp compact
- Tabelas + headings + blockquote + ações priorizadas — "encanta demonstração"
- WhatsApp compact (sem tabelas) seria derivativo gerado a partir do Dashboard se solicitado
- **Pra próxima:** se cliente final pedir, gerar versão compact via segundo agent que recebe Dashboard

---

## Próximos passos quando Wagner retomar

### Sprint A JANA Pro tasks remanescentes

- **US-COPI-204** — `BriefDiarioJob` schedule Horizon CT 100 8h BRT + persistência `mcp_briefs` + namespace memória `analises.brief_diario`
- **US-COPI-205** — Dashboard `/jana/admin/jana-pro` Inertia (card "Brief Diário" + history viewer)

### CYCLE-05 prioridades reais (fora JANA Pro)

- US-RB-048 `p0` Inter PJ Banking RUNBOOK (DOING há +2 dias)
- US-WA-051/052 WhatsApp FICHA v2 + AUDIT-LOG

### Higiene pendente

- `D:\.credentials.txt` pode ser deletado (key já rotacionada) ou movido pra Vaultwarden
- Backups `.env.bak-*` no Hostinger podem ser limpos após 7 dias

---

## Referências

- [ADR 0140](../decisions/0140-jana-pro-produto-comercial-saas.md) — JANA Pro produto SaaS
- [ADR 0141](../decisions/0141-agents-tool-use-pattern-claude-code.md) — Pattern Claude Code Camada B v2
- [ADR 0088](../decisions/0088-module-rename-php-only.md) — Module rename Copiloto→Jana (origem dos bugs URL legacy)
- `Modules/Jana/Services/BriefDiarioService.php` — 5 sources + fix walk-in + projeção mês
- `Modules/Jana/Ai/Agents/BriefDiarioAgent.php` — Provider OpenAI gpt-4o-mini + Versão A instructions
- `Modules/Jana/Services/BriefDiarioChatTrigger.php` — intent detection regex
- `Modules/Jana/Http/Controllers/ChatController.php` — integração send/sendStream
- `resources/js/Components/jana/AssistantUiChat.tsx` — MarkdownText + TypingIndicator
- [`.claude/skills/jana-brief-concierge/SKILL.md`](../../.claude/skills/jana-brief-concierge/SKILL.md) — espelho dev
- [Handoff anterior](2026-05-11-2230-jana-pro-foundation-concierge-pegadinha.md) — Sprint A foundation noite 11/05
