---
date: "2026-08-03"
time: "0734 BRT"
slug: "triagem-quarentena-financeiro"
tldr: "Quarentena da lane Financeiro (REQUIRED) caiu 33→25 em 12 PRs mergeados, um arquivo por PR. Nenhum dos 10 triados era regressão de produto. 3 decisões [W] pendentes (uma toca VALOR) e 3 erros meus registrados, um deles mutou o banco compartilhado do staging."
decided_by: [W]
cycle: null
prs: [5192, 5195, 5196, 5197, 5198, 5200, 5206, 5209, 5212, 5214, 5215, 5216, 5217]
us:  []
next_steps:
  - "Decidir TituloAutoService:100 — leitura morta de total_remaining_amount (toca VALOR, REGRA MESTRE)"
  - "Decidir re-clone do staging (12 tabelas removidas por migração acidental minha)"
  - "Decidir rumo do bucket A: molde cadeia-de-wiring (#5214/#5216) ou Playwright"
related_adrs: ["0264-governanca-executavel-trio-dominio-e2e", "0093-multi-tenant-isolation-tier-0", "0101-tests-business-id-1-nunca-cliente"]
---

# Handoff 2026-08-03 07:34 BRT — Triagem da quarentena Financeiro: 33→25, e o que a lane cobrou

## TL;DR

**Quarentena da lane required caiu 33 → 25** em 12 PRs mergeados, um arquivo por PR, cada um com
veredito medido no CT 100 e recibo antes→depois. **Nenhum dos 10 triados era regressão de produto.**
Três decisões suas ficaram pendentes, uma delas toca **cálculo de valor**. Registrei 3 erros meus
sem apagar nenhum — o mais sério mutou o **banco compartilhado do staging**.

## Cronologia desta sessão

| Quando | Evento |
|---|---|
| 23:30 | Ambiente de medição no CT 100: worktree em `origin/main` + DB `oimpresso_qa` pela receita do CI |
| 23:40 | **Erro meu**: `migrate` caiu no staging COMPARTILHADO — 12 tabelas removidas |
| 00:20 | Medição fresca dos 33 arquivos, 1 por processo; controle bate o baseline documentado |
| 00:30–05:00 | Triagem 1–10, um PR por arquivo |
| 02:15 | `RetencaoLoopE2ETest` 100% skipado na lane required |
| 03:30 | `total_remaining_amount` não existe e o produto lê |
| 04:35 | `dup-detector` pega meu PR duplicado → fechado |
| 05:20–06:10 | Três modos de falha do `merge=union` descobertos, dois silenciosos |
| 06:10 | Lane vermelha por causa de arquivo do #5200, já mergeado |
| 06:45 | Autocorreção #5217 |
| 07:20 | Último PR entra. Quarentena: **25** |

## Estado atual dos artefatos

### PRs

| PR | Status | Conteúdo |
|---|---|---|
| [#5192](https://github.com/wagnerra23/oimpresso.com/pull/5192) | merged | `TituloCriadoEventTest` — fixture (append-only) · 1F/1P → 2P |
| [#5195](https://github.com/wagnerra23/oimpresso.com/pull/5195) | merged | `ExtratoControllerTest` — o caso **[T0]** cross-tenant morria na FK · 2→3 assertions |
| [#5196](https://github.com/wagnerra23/oimpresso.com/pull/5196) | merged | `ProvaVivaControllerTest` — asserções mutuamente exclusivas · 3→9 assertions |
| [#5197](https://github.com/wagnerra23/oimpresso.com/pull/5197) | merged | `ProvaVivaContractTest` — guard acusava a própria documentação |
| [#5198](https://github.com/wagnerra23/oimpresso.com/pull/5198) | merged | `Wave23SaturationTest` — cobrava que o `health` não crescesse |
| [#5200](https://github.com/wagnerra23/oimpresso.com/pull/5200) | merged | `Onda23OcrBoletoTest` — artefato de ambiente (**revertido pelo #5217**) |
| [#5206](https://github.com/wagnerra23/oimpresso.com/pull/5206) | merged | `FluxoControllerTest` — 3 drifts de teste · 39→46 assertions |
| [#5209](https://github.com/wagnerra23/oimpresso.com/pull/5209) | merged | motivo REAL do `TituloAutoServiceExpenseTest` (arquivo FICA) |
| [#5212](https://github.com/wagnerra23/oimpresso.com/pull/5212) | merged | `RetencaoLoopE2ETest` — 3 skipped (0 assertions) → 3 passed (14) |
| [#5214](https://github.com/wagnerra23/oimpresso.com/pull/5214) | merged | `Onda9ResumirMesTest` — 1º do bucket A, sintaxe → cadeia de wiring |
| [#5215](https://github.com/wagnerra23/oimpresso.com/pull/5215) | **closed** | duplicata pega pelo `dup-detector` |
| [#5216](https://github.com/wagnerra23/oimpresso.com/pull/5216) | merged | `Onda7bTroubleshootPresentTest` — mesmo molde |
| [#5217](https://github.com/wagnerra23/oimpresso.com/pull/5217) | merged | **autocorreção**: `Onda23OcrBoletoTest` volta (flake por ordem) |

## Bloqueios / pendências

- [ ] **`TituloAutoService:100`** — a leitura `?? $tx->total_remaining_amount` é **código morto** (sonda: coluna NAO, accessor NAO, `appends` []). Opções (a) só o teste ou (b) remover a leitura. **(b) toca VALOR** → REGRA MESTRE exige dupla conferência + antes→depois + aprovação. Motivo completo no rodapé da quarentena — owner: **W**
- [ ] **Staging** — minha migração acidental removeu **12 tabelas** (`docs_*` do SRS + `mcp_*` do ADS dual-brain). Prod intocada; re-clonar apaga ambiente compartilhado que outras sessões usam — owner: **W**
- [ ] **Bucket A** — 8 arquivos restantes: seguir o molde cadeia-de-wiring (#5214/#5216) ou ir direto pra Playwright — owner: **W**
- [ ] **Vazador de permissão** — causa do 403 do `Onda23OcrBoletoTest`; 20+ arquivos da lane mexem em permissão. Exige bissecção com seed fixo — owner: **W**

## Próximos passos (ordem)

1. Decidir as 3 pendências acima (a de VALOR primeiro — bloqueia o `TituloAutoServiceExpenseTest`).
2. Continuar a triagem: **25 arquivos** restantes, mesmo rigor, um por PR.
3. Se o bucket A seguir o molde: 8 arquivos, todos com o mesmo padrão de sintaxe drifada.

## Estado MCP no momento do fechamento

> **Obrigatório (ADR 0130 §6)** — snapshot do que as tools devolveram, NÃO promessa.

### cycles-active
```
Nenhum cycle ATIVO em COPI. Use `cycles-list project:COPI` para ver todos.
```

### my-work
```
# Tasks ativas — @wagner   ·   Total: 8   ·   todas em REVIEW
US-COPI-123 p0 Remover startMockStream da rota live /ia/dashboard
US-TR-309   p1 Triage — lista de tasks órfãs
US-TR-310   p1 Triage — atribuir owner + prioridade inline
US-PG-008   p1 Linkage cobranca_id no webhook genérico + re-resolve do órfão
US-PROD-027 p1 [V0] Travar o acidente do 0-row
US-INFRA-023 p1 Zod schemas em endpoints JSON não-Inertia
US-TR-305   p1 Inbox — marcar lido
US-TR-306   p1 Inbox — deep-link pra task/DetailSheet
```

### sessions-recent limit:3
```
N/A — tool não consultada (sessões recentes lidas direto do filesystem):
  2026-08-02-2100-b7-cobertura-travas-de-prova.md      (handoff imediatamente anterior)
  2026-08-02-1916-e5-ads-b3-rag-redactor-ciclo-fechado.md
  2026-08-02-b7-cobertura-conciliacao-quarentena-ledger.md  (session log base desta sessão)
```

### decisions-search since:2026-08-02
```
N/A — nenhuma ADR nova consultada nesta sessão. A ADR relevante (0365, trio colocado)
já estava registrada no handoff anterior e não foi tocada aqui.
```

### whats-active (se houver sessão paralela)
```
Sessões paralelas CONFIRMADAS por evidência, não por tool: o `dup-detector` acusou
os PRs #5205 e #5210 (de outras sessões) tocando `governance/MAQUINAS-INVENTARIO.md`
ao mesmo tempo que o meu #5215 — que fechei por ser duplicata.
```

## Referências

- Session log: [2026-08-03-triagem-quarentena-financeiro.md](../sessions/2026-08-03-triagem-quarentena-financeiro.md)
- Handoff anterior: [2026-08-02-2100-b7-cobertura-travas-de-prova.md](2026-08-02-2100-b7-cobertura-travas-de-prova.md)
- Lista: [`.github/financeiro-pest-quarantine.list`](../../.github/financeiro-pest-quarantine.list)
