# Session 2026-05-12 — JANA Pro Brief funcional em produção (sessão manhã)

> Companion narrativo do [handoff 2026-05-12 08:48](../handoffs/2026-05-12-0848-jana-pro-brief-funcional-prod.md).

## Quem trabalhou

- **[W]** Wagner — operador solo manhã
- **[C]** Claude Code Max — pair IA

## Timeline

### 07h-08h — Quality fixes pós-validação biz=4 (PR #608)
- Wagner pediu "documentar e corrigir o erro antes" → fix walk-in is_default=1 + projeção mês + Versão A canonical
- Pest 16/16 PASS

### 08h-09h — Bug "criar meta" + URLs legacy (PR #609)
- Wagner reportou botão criar meta dando erro silencioso
- Root cause: redirect 301 `/copiloto/` → `/jana/` perde POST body
- Fix: 7 arquivos, 11 occ `/copiloto/` → `/jana/`

### 09h-10h — OpenAI ligado + brief no chat (PR #611)
- Wagner: "mimi quero testar na Jana o que pergunto e responde formatadinho"
- Rotação `OPENAI_API_KEY` (local + Hostinger via PHP server-side com BOM strip)
- `#[Provider('openai')] #[Model('gpt-4o-mini')]` no BriefDiarioAgent
- `BriefDiarioChatTrigger` (9 regex unicode) + ChatController interceptors
- Pest R-COPI-203-001..004 (4/4 PASS, 42 assertions)

### 10h-10h30 — Hotfix bug crítico /jana sem feedback (PR #612)
- Wagner: "não deu erro mas não responde e nem fica ali no chat"
- Investigação: `AssistantUiChat.tsx` fetch `/copiloto/conversas/X/mensagens/stream` — PR #609 NÃO pegou
- Fix + 2 outros refs

### 10h30-11h — Polimento UX final (PR #616)
- Wagner re-testou: brief gerou MAS tabelas viraram pipes cru + bubble vazio durante geração
- Fix #1: `ReactMarkdown + remarkGfm` (substitui MarkdownTextPrimitive sem GFM)
- Fix #2: `TypingIndicator` (3 dots animados + "Jana está pensando…")
- `useThread.isRunning` (não `useMessage.status` que não funciona em ExternalStore)

### 11h — Validação final
- Wagner anexou screenshot prod: "ficou ótimo pode fechar"

## Métricas

| Métrica | Valor |
|---|---|
| Duração | ~3-4h |
| PRs merged | 5 |
| Linhas adicionadas | ~600 |
| Pest tests novos | 8 (R-COPI-202c 4 + R-COPI-203 4) |
| Pest passando | 100% (16/16 + 4/4) |
| Bugs introduzidos e detectados em prod | 2 (PR #611 + #612) — fixados mesma sessão |
| Custo direto sessão | R$ 0 |
| Custo operacional brief | R$ 0,003 (gpt-4o-mini) |

## O que funcionou bem

- **Iteração rápida** com Wagner feedback real em prod (não fixture)
- **5 PRs sequenciais** sem worktree (lições paralelização frustrada aplicadas)
- **Pest tests sempre antes do PR** — pegaram bugs latentes (R-JANA-001 anti-flakiness horário)
- **Skill `jana-brief-concierge` como espelho dev** funcionou — zero retrabalho prompt quando ligou OpenAI

## O que correu mal

- **PR #609 não-exaustivo** — 1 hora desperdiçada com chat travando sem feedback
- **Boomerangue UX** — bug critical descoberto SÓ quando Wagner testou em prod (3 idas e voltas)
- **Custom credentials rotation** — PowerShell pipe BOM bug consumiu ~10min debug
- **Documentação visual** — formato Versão A precisava de screenshot de referência pra Wagner aprovar mais cedo

## Lições incorporadas

1. **Refator URL prefix exige sweep recursivo + componentes filhos** — `grep -rn` em pages + components TODOS
2. **PowerShell pipe stdin SSH → adiciona BOM UTF-8** — sempre transfer via SCP temp file pra credenciais
3. **ExternalStoreRuntime sem streaming nativo → `useMessage.status` morto** — `useThread.isRunning` é fonte de verdade
4. **MarkdownTextPrimitive sem GFM** — `ReactMarkdown + remarkGfm` é caminho certo pra tabelas
5. **Wagner valida em prod, não fixture** — protocolo: feature flag OFF → smoke biz=1 → flag ON cliente → validação Wagner → polish

## Tasks MCP afetadas

- **US-COPI-202c** quality fixes ✅ DONE
- **US-COPI-203** brief invocável chat ✅ DONE (mais 1 hotfix + 1 polimento UX no caminho)
- **US-RB-048** (cycle p0) — paused durante toda sessão (Wagner pausou trabalho cycle pra JANA Pro)

## Conclusão estratégica

JANA Pro Brief Diário **funcional em produção** com OpenAI gpt-4o-mini. **Margem 99,95% no plano R$ 149**. Tecnicamente pronto pra cobrar de cliente — falta só Sprint A US-COPI-204/205 (job scheduling + dashboard history) pra automação completa.

Cliente piloto definido: **Larissa ROTA LIVRE** pode receber brief manual via Concierge (Wagner roda `/jana` digita "brief" + envia print/markdown) OU automatizado se Wagner ligar Job 8h BRT em CT 100 Horizon.

---

**Status final:** ✅ FUNCIONAL EM PROD, validado Wagner, sessão encerrada.
