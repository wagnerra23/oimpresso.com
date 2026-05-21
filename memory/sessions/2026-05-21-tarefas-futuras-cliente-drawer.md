---
slug: 2026-05-21-tarefas-futuras-cliente-drawer
title: "Tarefas futuras — Wave Cliente drawer 760px fechada 2026-05-21"
type: backlog
date: 2026-05-21
related_prs: [1339, 1347, 1348, 1349, 1351, 1355, 1356, 1358]
related_adrs: [0179]
parent_handoff: memory/handoffs/2026-05-21-1623-cliente-drawer-760-wave-a-g-3-prs-encadeados.md
status: backlog
---

# Tarefas futuras — Wave Cliente drawer 760px

> Wave entregue + validada em prod biz=1 ao vivo. Itens abaixo são **opcionais** (não-blocking pro uso atual). Wagner aprova individualmente quando vir necessidade — não criar tasks MCP sem confirmação.

## Bloco A — Canary biz=1 → biz=4 Larissa (alta prioridade)

### A.1 Canary 7 dias biz=1 verde antes biz=4
- **Quando:** após 7 dias de uso prod sem regressão biz=1 (Wagner WR2 Sistemas)
- **Ação:** habilitar `MWART_CLIENTE_INDEX=true` para biz=4 ROTA LIVRE (Larissa)
- **Estimate:** 0h código + monitoramento passivo 7d
- **Riscos:** Larissa não-técnica 1280×1024 — drawer 760 pode parecer estreito em monitor pequeno; testar viewport antes
- **Critério:** zero issue GitHub/zero rollback solicitado em 7d biz=1

### A.2 Smoke biz=4 Larissa pós-canary
- **Quando:** após A.1 aprovado
- **Ação:** ligar com Larissa, demo guiada drawer 760px (NÃO smoke Claude — cliente real, risco WhatsApp/OS)
- **Estimate:** 30min call + 1h follow-up
- **Output:** feedback voice em `memory/sessions/YYYY-MM-DD-feedback-larissa-cliente-drawer.md`

## Bloco B — Smoke residual 4 tabs não validadas hoje

### B.1 Smoke tabs Contato + Comercial + Classificação + OSs
- **Quando:** próxima sessão Cliente OU sob demanda
- **Ação:** Chrome MCP via Claude — abrir drawer + clicar cada tab + screenshot
- **Estimate:** ~15min via Chrome MCP
- **Justificativa:** estrutura idêntica às 4 testadas hoje (Identificação/Endereço/IA/Auditoria); baixo risco
- **Critério:** screenshots em `prototipo-ui/screenshots/` + append `SYNC_LOG.md` `[W2] approved`

### B.2 Smoke OssTab sub-tabs Wave Final (Ledger/Sales/Payments/Documents/Activities/Pessoas/Subscriptions/Rewards)
- **Risco real:** PaymentsTab + SalesTab + DocumentsTab originais desenhados pra 1200px+ podem apertar em 640px content area do drawer 760
- **Estimate:** 30min smoke + 1-3h fallback se quebrar
- **Fallback:** dropdown `Ver: [SalesTab ▼]` substituindo vertical pills 120px (ADR 0179 já prevê)
- **Critério:** se PaymentsTab/SalesTab travarem responsivo, abrir hotfix `wave-z-3-osstab-dropdown-fallback`

## Bloco C — Polimentos visuais (5% residual ao protótipo Cowork)

### C.1 Animations fade-in cards IA
- **Cowork:** `transition: opacity 150ms ease-in` nos cards IA quando spinner→ok
- **Meu:** render instant (sem transition)
- **Estimate:** ~10min — adicionar `transition-opacity duration-150` nos cards `IATab.tsx`
- **Impacto:** baixo (cosmético)

### C.2 Border-radius + micro-spacing
- **Diferença:** 1-2px em alguns componentes (header drawer padding, button heights, gap entre tabs)
- **Estimate:** ~30min com lupa em DevTools comparando lado a lado
- **Impacto:** muito baixo (pixel-peeping)

### C.3 Dark mode theme switcher protótipo `data-theme`
- **Cowork:** atributo `data-theme="light|dark"` no `.cockpit` + CSS variáveis trocam
- **Meu:** Tailwind `dark:` prefix em cada classe (funciona idêntico mas modelo diferente)
- **Estimate:** ~2-4h se Wagner quiser unificar com protótipo (não justifica)
- **Recomendação:** manter Tailwind dark:; não tocar

## Bloco D — Backend / payload completude

### D.1 ContactController::index payload tags + segmento + vip
- **Hoje:** payload tem `tipo`, `fantasia`, `tags`, `segmento`, `vip` opcional via `$hasWaveBCols`
- **Próximo:** rendering chips coloridos das tags na coluna Tags da listagem (TagChip está pronto, só precisa array `tags` chegar populated)
- **Estimate:** ~30min — confirmar migration Wave B rodou em prod (já confirmado pelo `php artisan migrate --force`); seed tags em alguns clientes pra testar; screenshot
- **Critério:** ver chips amarelo/roxo/azul/etc na coluna Tags em vez de `—`

### D.2 Tab IA telemetria custo Brain B
- **Hoje:** `\Log::info('cliente.ia.call', [...])` já está em todos 3 endpoints LLM
- **Próximo:** dashboard `Modules/Jana/Http/Controllers/CustosController` com agregado por dia + alerta se > $5/dia/biz
- **Estimate:** ~3h
- **Justificativa:** Q4 Wagner = Default ON sem gate. Sem dashboard, não dá pra detectar quando custo virar problema pra regredir pra gate.
- **Quando:** após 30d uso prod (dados suficientes)

### D.3 Auditoria timeline — eventos custom além CRUD
- **Hoje:** Spatie Activity captura `created` / `updated` / `deleted` / `restored` automaticamente via `LogsActivity` trait
- **Próximo:** logar evento `accessed_by_team` quando atendimento WhatsApp toca a ficha (Modules/Whatsapp)
- **Estimate:** ~1h — `Activity::performedOn($contact)->log('Atendimento via Inbox')` no `WhatsappController`
- **Impacto:** LGPD Art. 18 mais completo

## Bloco E — Footer drawer "1 pendência" calcular real

### E.1 Contagem pendências real
- **Hoje:** placeholder hardcoded "1 pendência"
- **Próximo:** estado `dirty` por tab — quando user edita campo mas não salva (autosave debounce 800ms), conta
- **Estimate:** ~1.5h
- **Impacto:** UX honesta (não mente pro usuário)

## Bloco F — Migrar pattern drawer 760 pra outros módulos

### F.1 Fornecedor drawer 760
- **Hoje:** `/contacts/{id}` legacy Blade (similar Cliente pré-Wave A)
- **Próximo:** mesma estrutura — drawer 760 + 8 tabs cadastrais reusando 5 TabComponents do Cliente (DRY)
- **Estimate:** ~3-5h (90% reuso dos componentes Cliente + ajustes lookup CNPJ fornecedor)
- **Quando:** quando Wagner pedir

### F.2 Funcionário drawer 760
- **Hoje:** `/users/{id}` Blade
- **Próximo:** drawer 760 + tabs RH (Dados pessoais / Contato / Endereço / Cargo+Permissões / Histórico / Documentos / IA / Auditoria)
- **Estimate:** ~6-8h
- **Quando:** quando módulo HR/Funcionários ganhar priorização

### F.3 Produto drawer 760
- **Hoje:** `/products/{id}` Blade fat
- **Próximo:** drawer 760 + tabs (Identificação / Estoque / Preços / Variações / Fiscal / Documentos / IA / Auditoria)
- **Estimate:** ~10-15h (Produto tem mais complexidade — variações, fiscal NCM/CFOP, imagens)
- **Quando:** após F.1 + F.2 validados

## Bloco G — Cleanup técnico

### G.1 Delete Show.tsx código morto
- **Hoje:** Q1 Wagner = sunset zero, mas Show.tsx ainda existe em main porque rota redireciona pra drawer
- **Próximo:** `git rm resources/js/Pages/Cliente/Show.tsx` + deletar testes feature/Cliente/ClienteShowTest.php
- **Estimate:** ~30min + verificar nenhum import órfão
- **Quando:** após canary biz=1 + biz=4 OK por 14d (rollback impossível depois)

### G.2 Commit pendente ADR 0180 sidebar local
- **Hoje:** branch local `backup-adr-0180-sidebar` tem commit `7dd50fb74 docs(cockpit): ADR 0180 sidebar v3` que NÃO foi pushado (ficou orfão durante merge cascade Wave A)
- **Próximo:** abrir PR separado pra esse ADR — não relacionado à Wave Cliente
- **Estimate:** ~10min — `git checkout backup-adr-0180-sidebar; git push -u origin HEAD; gh pr create`
- **Quando:** quando lembrar; Wagner decide se merge ou abandona

### G.3 ActiveChip integration tests
- **Hoje:** `ActiveChip` criado e funcionando, mas sem Pest test específico
- **Próximo:** test em `tests/Feature/Cliente/ClienteListagemTurbinadaTest.php` GUARD novo: ActiveChip removível existe + variant danger pra saldo devedor
- **Estimate:** ~15min
- **Impacto:** baixo (CI guard contra regressão futura)

## Bloco H — Skill `precisao-literal` evolução

### H.1 Validar skill ativa corretamente em próxima migração MWART
- **Quando:** próxima Wave migração (Fornecedor F.1 OU Funcionário F.2 OU outra)
- **Critério:** Wagner pergunta "que % literal?" e skill ativa automática + Claude segue 6 fases canon
- **Output:** `memory/sessions/YYYY-MM-DD-precisao-<componente>.md`

### H.2 Skill `precisao-literal` V1.1 — adicionar tooling automático
- **Hoje:** Claude lê arquivos manualmente em Fase 1 (inventário paralelo)
- **Próximo:** comando bash que gera tabela pareada `prototipo:linha ↔ atual:linha` automaticamente via `diff --unified=0`
- **Estimate:** ~2h script + integração skill
- **Impacto:** acelera Fase 1 de 5min → 30s

## Priorização sugerida

| # | Bloco | Prioridade | Quando | Estimate |
|---|---|---|---|---|
| 1 | A.1 Canary 7d biz=1 | ALTA | Passivo agora | 0h |
| 2 | A.2 Smoke biz=4 Larissa | ALTA | Após A.1 | 1.5h |
| 3 | D.1 Tags chips populated listagem | MÉDIA | Antes B.1 (visual final) | 30min |
| 4 | B.1 Smoke 4 tabs residuais | MÉDIA | Próxima sessão Cliente | 15min |
| 5 | G.2 Push ADR 0180 sidebar | BAIXA | Quando lembrar | 10min |
| 6 | E.1 Footer pendência real | BAIXA | Polimento UX | 1.5h |
| 7 | F.1 Fornecedor drawer 760 | BAIXA | Quando Wagner pedir | 3-5h |
| 8 | D.2 Tab IA telemetria custo | BAIXA | Após 30d uso prod | 3h |
| 9 | C.1+C.2 Polimentos visuais 5% | MUITO BAIXA | Se Wagner pedir paridade 100% | 40min |
| 10 | G.1 Delete Show.tsx morto | MUITO BAIXA | Após canary 14d OK | 30min |

## Não fazer (Tier 0)

- ❌ Habilitar `MWART_CLIENTE_INDEX=true` em biz=4 Larissa SEM 7d canary biz=1 verde
- ❌ Tocar `_show/*.tsx` (Wave Final 2026-05-21 preservada via OssTab wrapper)
- ❌ Quebrar KB-9.75 atalhos (⌘K + ?+ J/K + Enter + /)
- ❌ Mudar paradigma do drawer (760px lateral é canon ADR 0179)
- ❌ Adicionar gate quota IA sem dados (D.2 = monitor 30d ANTES de decidir)

## Refs

- Handoff fechamento sessão: [memory/handoffs/2026-05-21-1623-cliente-drawer-760-wave-a-g-3-prs-encadeados.md](../handoffs/2026-05-21-1623-cliente-drawer-760-wave-a-g-3-prs-encadeados.md)
- ADR 0179 paradigma drawer 760: [memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md](../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)
- Skill precisao-literal V1: [.claude/skills/precisao-literal/SKILL.md](../../.claude/skills/precisao-literal/SKILL.md)
- Visual-comparison validated: [memory/requisitos/Crm/cliente-drawer-760-visual-comparison.md](../requisitos/Crm/cliente-drawer-760-visual-comparison.md)
- PRs sessão: #1339, #1347, #1348, #1349, #1351, #1355, #1356, #1358
