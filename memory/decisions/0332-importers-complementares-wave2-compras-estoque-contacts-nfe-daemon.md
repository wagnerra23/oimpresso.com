---
slug: 0332-importers-complementares-wave2-compras-estoque-contacts-nfe-daemon
number: 332
title: "Importers complementares Wave 2 (compras/estoque/contacts-NFe-fornecedores/daemon-sync) + reflexão arqueológica · amends ADR 0197 + 0198 + 0203"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-05-27"
accepted_at: "2026-05-27"
accepted_via: "Trabalho EXECUTADO e merjado: #1765 (pipeline canônico 0203, Felipe Wave 29-1) + #1766 (importers complementares Wave 2 deste ADR — cherry-pick dos 6 importers + 2 updates v0.2.0 + docs). Ratificação (flip proposed→aceito + saída de proposals/) autorizada por Wagner 2026-07-09 ('b ratifique ou apague', US-GOV-050 follow-up); número 204 preservado."
module: officeimpresso
tags: [migracao-legacy, python-standalone, importers-complementares, daemon-sync-experimental, sync-checkpoint, anti-pattern, arqueologia, wave2]
supersedes: []
superseded_by: []
amends:
  - 0197-extend-contacts-absorcao-pessoas-legacy
  - 0198-hot-cold-tiering-migracao-transacional-legacy
  - 0203-legacy-migration-pipeline-firebird-oimpresso-w29
related:
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0119-paralelismo-sessoes-whats-active-tier-1
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0137-modules-oficinaauto-qualificada
  - 0171-oficinaauto-ativacao-piloto-martinho-faseada
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
  - 0197-extend-contacts-absorcao-pessoas-legacy
  - 0198-hot-cold-tiering-migracao-transacional-legacy
  - 0200-contacts-sync-canon-amends-0197-0199
  - 0203-legacy-migration-pipeline-firebird-oimpresso-w29
---

# ADR 0332 — Importers complementares Wave 2 + reflexão arqueológica

> **Renumerado 0204→0332 + movido de proposals/ + flip proposed→aceito em 2026-07-09** (Wagner 'b ratifique ou apague', US-GOV-050 follow-up). **Motivo do renumber:** o número 204 pertence ao [ADR 0204 — WhatsApp whatsmeow Go driver](0204-whatsmeow-driver-substituto-baileys.md) (aceito, canônico) — este ADR era uma colisão pré-existente escondida em proposals/. Regra ADR 0028/0304: renumera o novato, não toca o legado; nenhum outro ADR referencia o slug antigo. Conteúdo original 100% intacto (só frontmatter slug/number/status + heading + esta nota). Referências históricas a "ADR 0204" no corpo abaixo (execução 2026-05-27) eram ESTE ADR. O trabalho já estava executado e merjado (#1765 + #1766) — o flip só formaliza o registro. Os links `NNNN-*.md` (sem `../`), quebrados enquanto o arquivo vivia em proposals/, passam a resolver pros ADRs-irmãos no top-level.

## Status

`proposed` 2026-05-27 (ratificado `aceito` 2026-07-09 — ver nota acima) — Complementa [ADR 0203](0203-legacy-migration-pipeline-firebird-oimpresso-w29.md) (pipeline canônico Wave 29-1 entregue pelo Felipe). Atende pedido Wagner "consolide as memórias, organize e tome a decisão de salvar o padrão" — Wave 2 cobre importers que ADR 0203 não atacou + reflexão sobre origem biz=164 + 5 branches órfãs catalogadas.

## Contexto

[ADR 0203](0203-legacy-migration-pipeline-firebird-oimpresso-w29.md) (Felipe, aceito 2026-05-26, mergeado #1765) entregou pipeline end-to-end Firebird → oimpresso pra Martinho biz=164 prod: import-produtos, import-venda-itens (resolve gap 92.5% sub-linhas), import-notas-fiscais, enrich-produtos + enrich-produtos-completo, migrar-tudo orquestrador 8 steps, fix WireCrypt FB 3.0.12, OfficeimpressoImporterService PHP ampliado.

Em paralelo (descoordenado, sem [whats-active MCP](0119-paralelismo-sessoes-whats-active-tier-1.md)), arqueologia 2026-05-27 (sessão `frosty-greider-83ab2f` · [session log completo](../../sessions/2026-05-27-consolidacao-migracao-martinho-arqueologia.md)) revelou:

1. **5 branches órfãs paralelas** com importers concorrentes — principal `claude/wip-martinho-canary-2026-05-14` (93 arquivos · 22.892 LOC · 3 semanas órfã)
2. **biz=164 existe desde 2024-11-08** (não 2026-05) — originalmente "JAIR UMBELINA VARGAS ME" → renomeado MARTINHO em 2026-05-15. 10 funcionários reais (Kamila/Evandro/Andre/Luiza/etc.) cadastrados manualmente 2024-12-10. 1.838 produtos manuais 2024-12/2025-01 + 1.971 produtos 2026-05-26.
3. **DROP biz=164** autorizado por Wagner ("teste sem problemas") **REVERTIDO** — 6+ meses operação real, perderia funcionários + produtos + feedback Kamila Sicoob esta semana
4. **4 importers + 2 ferramentas** na branch órfã que ADR 0203 não cobre

## Decisão

**Recuperar 6 arquivos órfãos da branch `claude/wip-martinho-canary-2026-05-14` que ADR 0203 não atacou + documentar pattern conceitual de 13 fases (9 originais + 4 ADR 0203) + catalogar anti-patterns descobertos.**

### Importers complementares recuperados (Wave 2 deste ADR)

| Importer | LOC | Cobre | Status |
|---|---:|---|---|
| `import-compras.py` | 846 | NFe entrada → transactions tipo=purchase | Recuperado (não em ADR 0203) |
| `import-estoque.py` | 552 | ESTOQUE → product_stock_movements | Recuperado |
| `import-contacts-from-nfe.py` | 553 | NFe emitente → contacts type=supplier | Recuperado (complementa `import-contacts-from-venda.py` ADR 0203) |
| `daemon-sync-martinho.py` | 536 | Sync incremental dual-system Delphi ↔ MySQL | **Experimental, manual-run only** — NÃO scheduled. Quando aparecer dor real → mover pra `app/Console/Commands/` + scheduled em **CT 100** ([ADR 0062](0062-separacao-runtime-hostinger-ct100.md)) |
| `migrar-martinho.py` | 210 | Orquestrador específico cliente (avaliar overlap com `migrar-tudo.py` ADR 0203) | Manter como template `migrar-<cliente>.py` ou deletar pós-revisar overlap |
| `lib/sync_checkpoint.py` | 230 | State `--delta-since-last-sync` por (alias, sync_type) | Reusado pelos v0.2.0 importers |

### Updates v0.1.0 → v0.2.0 (compatíveis com ADR 0203)

- `import-contacts-from-venda.py` (+71 lines · `--delta-since-last-sync`)
- `import-vendas.py` (+67 · chunk pagination + JSON_MERGE_PATCH)

Os 3 arquivos em CONFLITO direto com ADR 0203 (`import-produtos.py`, `import-financeiro.py`, `lib/firebird_reader.py`) foram **REMOVIDOS** deste PR — versão canônica é a de ADR 0203 (#1765).

### Pattern conceitual de 13 fases (combinação ADR 0203 + Wave 2)

Felipe ADR 0203 entregou execução prática Wave 29-1. Este ADR formaliza **pattern conceitual prescritivo** pra Vargas/Gold/Extreme + 33 clientes remanescentes — combinação dos 2 waves:

```
Fase 0 (1x global)         → migration Bucket A contacts (ADR 0197 · ADR 0200)
Fase 1 (empresas)          → import-empresas.py · sem pré-req
Fase 2 (vehicles)          → import-vehicles.py · sem pré-req · SÓ OficinaAuto
Fase 3 (contacts VENDA)    → import-contacts-from-venda.py v0.2.0 · Fase 0
Fase 4 (vendas)            → import-vendas.py v0.2.0 · Fases 2 + 3
Fase 5 (financeiro)        → import-financeiro.py · Fase 4
Fase 6 (produtos)          → import-produtos.py (ADR 0203) · sem pré-req
Fase 7 (estoque)           → import-estoque.py (Wave 2) · Fase 6
Fase 8 (compras)           → import-compras.py (Wave 2) · Fases 1 + 6
Fase 9 (contacts NFe)      → import-contacts-from-nfe.py (Wave 2) · Fase 0
Fase 10 (venda-itens)      → import-venda-itens.py (ADR 0203) · Fase 4 + 6 · resolve gap 92.5% sub-linhas
Fase 11 (notas-fiscais)    → import-notas-fiscais.py (ADR 0203) · Fase 4
Fase 12 (enrich produtos)  → enrich-produtos.py + -completo.py (ADR 0203) · pós-Fase 6
```

**Ordem prática recomendada Vargas/Gold/Extreme:** 0 → 1 → 6 → 12 → 3 → 9 → 2 (se oficina) → 8 → 4 → 10 → 7 → 5 → 11

### Daemon-sync experimental

`daemon-sync-martinho.py` (536 LOC) recuperado em estado **manual-run only**:
- Sync incremental dual-system Delphi LOCAL ↔ oimpresso ONLINE (per [ADR 0200](0200-contacts-sync-canon-amends-0197-0199.md))
- Reusa `lib/sync_checkpoint.py` pra state per (alias, sync_type)
- **NÃO scheduled** — rodado manualmente em PowerShell durante maratona 2026-05-14 18:25 BRT
- Quando sinal qualificado [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) aparecer (cliente reporta dor de drift Delphi↔oimpresso > 1d), promover pra `app/Console/Commands/` + scheduled em [CT 100 Proxmox](0062-separacao-runtime-hostinger-ct100.md), **NÃO Hostinger**

### Anti-patterns documentados (Wave 2 adições à §5 do pattern)

1. **Branch órfã guardando trabalho não-fatiado >7d** — `claude/wip-martinho-canary-2026-05-14` ficou 3 semanas órfã. Mitigação: checkpoint WIP só sobrevive 7d; fatiar em PRs A-F na mesma semana
2. **"Daemon" que é script manual disfarçado** — logs `daemon-<tabela>-biz164-{ts}.log` sugerem scheduler mas são `import-*.py --target dry-run` manuais. Mitigação: daemon real = scheduled em `app/Console/Kernel.php` em CT 100; script manual com flag `--daemon-mode` ≠ daemon
3. **Anti-pattern §5 do pattern recursivamente acontecendo** — múltiplos agentes Claude paralelos sem `whats-active`. Aconteceu 3 vezes documentadas: 2026-05-13 (Wave 0 Martinho rename), 2026-05-14 (5 branches órfãs maratona), 2026-05-27 (PR #1765 vs PR #1766 1m26s diferença). Mitigação: hook `whats-active` MCP ainda não está Tier A always-on

### Reflexão arqueológica biz=164

Catalogado em [session log 2026-05-27 §"Origem temporal biz=164"](../../sessions/2026-05-27-consolidacao-migracao-martinho-arqueologia.md#origem-temporal-biz164-descoberta-crítica):

| Data | Evento |
|---|---|
| 2024-11-08 | User `officelocal25db7` "Sistema" — biz=164 nasceu |
| 2024-12-10 | 10 users nomeados (Kamila/Evandro/Andre/etc.) cadastrados manualmente |
| 2024-12-26 / 2025-01-14 | 1.838 produtos manuais cadastrados |
| 2026-05-13 | Fase 2 vehicles importado |
| 2026-05-14 02:48 → 19:02 | Maratona 16h: vendas+financeiro+produtos+estoque+compras+contacts-NFe (alguns prod, maioria dry-run) |
| 2026-05-15 | Business renomeado "JAIR UMBELINA VARGAS ME" → "MARTINHO CAÇAMBAS LTDA" |
| 2026-05-26 14:00→15:00 | 1.971 produtos novos cadastrados manualmente |
| 2026-05-27 | ADR 0197 Bucket A + ADR 0200 canon sync + ADR 0203 pipeline Wave 29-1 + ADR 0204 (este) complementares Wave 2 |

## Consequências

### Positivas

- Pattern conceitual de 13 fases documentado pra novos clientes
- 6 arquivos órfãos recuperados que ADR 0203 não cobre — daemon-sync + sync_checkpoint = base pra sync incremental futuro
- 5 branches órfãs catalogadas (a deletar após merge ADR 0203 + ADR 0204) — drift git ↔ prod resolvido
- Reflexão arqueológica preserva história biz=164 — futuros agentes não vão sugerir DROP por achar que é teste
- Anti-patterns §5 ampliados com 3 casos reais

### Negativas

- 2 ADRs (0203 + 0204) sobre mesmo domínio aumenta complexidade leitura — mitigado via tabela `pattern de 13 fases` que mostra origem de cada importer
- `migrar-martinho.py` (este ADR) overlap conceitual com `migrar-tudo.py` ampliado (ADR 0203) — decisão deferida pra revisar pós-merge: manter ambos OU deletar `migrar-martinho.py` em favor de `migrar-tudo.py --alias MartinhoServidor`

### Risco mitigado

- **"E se outro agente paralelo trabalhar mesmo problema?"** — anti-pattern §5 catalogado com 3 casos reais. Mitigação real exige ativar `whats-active` MCP Tier A always-on (próximo step backlog)

## Implementação

1. **Pré-req:** Wagner aprova merge ADR 0203 canon (Felipe PR #1765) — fonte de verdade do pipeline
2. **Este PR #1766 reduzido** (pós force-push): cherry-pick limpo dos 6 importers complementares + 2 v0.2.0 updates compatíveis + 4 docs canon (ADR 0204 + session log + pattern.md + perfil.md + RUNBOOK)
3. **Pós-merge ambos PRs:** **NÃO deletar branches automaticamente** — re-classificação 2026-05-27 18:10 BRT revelou que (a) `feature/legacy-migration-pessoas-sql` (PR #1204 SupportWR) é **trabalho ATIVO do time** com 5 commits 20/05 + US-VEST-020 misturado, (b) `claude/wip-martinho-canary-2026-05-14` tem **82 arquivos não-extraídos** valiosos (sells/edit + MWART /contacts + /products + sidebar custom + cliente-funcionario collector + tests), (c) outras 3 branches `claude/*` precisam confirmação Wagner antes de delete (podem ser sessões cloud agentes). Criar issue tracking "Branches a triagem pós-#1765+#1766" em vez de delete cego
4. **Backlog MCP:**
   - US-OFICINA-XXX (futura): ativar daemon-sync-martinho.py via scheduler `app/Console/Kernel.php` em CT 100 quando sinal qualificado aparecer
   - US-OFICINA-YYY: avaliar archive job opt-in pra 76.7% inadimplência legacy ([ADR 0198 §Mitigação 3](0198-hot-cold-tiering-migracao-transacional-legacy.md))
   - US-MEMORIA-ZZZ: promover `whats-active` MCP pra Tier A always-on pra evitar recorrência do anti-pattern §5

## Refs

- [ADR 0203 canon](0203-legacy-migration-pipeline-firebird-oimpresso-w29.md) (Felipe Wave 29-1 · pipeline end-to-end) — fonte de verdade do pipeline
- [Session consolidação arqueologia](../../sessions/2026-05-27-consolidacao-migracao-martinho-arqueologia.md) — parent deste ADR
- [Session diagnóstico Hostinger](../../sessions/2026-05-27-diagnostico-hostinger-martinho-biz164.md) — origem do exercício
- [Handoff migração completa 2026-05-17 17:22](../../handoffs/2026-05-17-1722-migracao-martinho-completa-perfil-canon.md)
- [Pattern canônico migração](../../reference/migracao-officeimpresso-pattern.md) §2-bis Fases 6-9 — atualizado deste PR
- [Perfil Martinho](../../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md) §7 §8 §9
- [RUNBOOK historical](../../requisitos/Officeimpresso/RUNBOOK-migracao-martinho-fase3-fase4.md) banner pattern v2
- ADRs amendados: [0197](0197-extend-contacts-absorcao-pessoas-legacy.md), [0198](0198-hot-cold-tiering-migracao-transacional-legacy.md), [0203](0203-legacy-migration-pipeline-firebird-oimpresso-w29.md)
- 5 branches identificadas na arqueologia inicial (re-classificadas 2026-05-27 18:10 BRT pós-questionamento Wagner "tem certeza que são órfãs?"):
  - `claude/wip-martinho-canary-2026-05-14` — Wagner author · 12d sem update · 93 arquivos · **11 cherry-picked neste PR · 82 NÃO-EXTRAÍDOS valiosos** (sells/edit + MWART /contacts + /products + sidebar custom + cliente-funcionario collector + tests · ~22k LOC). Manter até extrair resto OU explicitamente desistir
  - **`feature/legacy-migration-pessoas-sql` (PR #1204) — TRABALHO ATIVO** SupportWR/Felipe · 5 commits 20/05 · US-VEST-020 etiqueta + skill migration-status + Page Inertia /vestuario/etiquetas misturados · CI quase verde. **NÃO TOCAR**
  - `claude/plano-migracao-entidades-v2` (PR #812) — Wagner author · 13d sem update · 4.277 LOC · PR aberto. Confirmar Wagner antes de fechar
  - `claude/fix-bookings-route-name-conflict` — 13d sem update · SEM PR · sessão Claude cloud automática? Confirmar Wagner
  - `claude/fix-route-collisions-batch` — 13d sem update · SEM PR · idem
- ⚠️ **Erro de classificação cometido**: ADR original chamou todas de "órfãs a deletar". Wagner corrigiu — só `claude/wip-martinho-canary` tem trabalho recuperável neste PR (parcial 11/93). Demais exigem triagem caso-a-caso
