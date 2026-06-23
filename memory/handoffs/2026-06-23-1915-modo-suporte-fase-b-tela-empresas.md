---
date: "2026-06-23"
time: "19:15 BRT"
slug: modo-suporte-fase-b-tela-empresas
tldr: "2ª metade da sessão Modo Suporte: auditoria de scoping (Wagner 'me parece errado' matou o switch-de-sessão inseguro) → caminho read-only com business_id explícito (SupportClientViewService #3279) → F1 RUNBOOK+BRIEFING #3285 → F3 tela Empresas #3289 + fix ratchets #3291. 1ª tela do Modo Suporte no main, ratchet-clean, smoke pós-deploy pendente."
decided_by: [W]
cycle: "CYCLE-08"
prs: [3279, 3285, 3289, 3291]
related_adrs: ["0305-modo-suporte-cross-tenant-exceto-operador", "0093-multi-tenant-isolation-tier-0", "0104-processo-mwart-canonico-unico-caminho", "0253-layout-primitives", "0264-governanca-executavel-trio-dominio-e2e"]
next_steps: ["tela Suporte/Visao (destino do 'Entrar (suporte)') consumindo SupportClientViewService", "entrada no menu pra /suporte/empresas (hoje só por URL)", "atualizar BRIEFING (F3 agora existe)", "smoke biz=1 pós-deploy (R1)", "fase A 'atuar' só após auditoria das vias de scoping auth-user vs session"]
---

# Handoff — Modo Suporte fase B: auditoria de scoping + 1ª tela (Empresas) no main

> Continuação do [handoff 16:xx](2026-06-23-1735-modo-suporte-adr0305-backend-fase-b.md) (ratificação + backend). Aqui: do "qual a diferença?" do Wagner até a 1ª tela no ar.

## Estado MCP no momento
**MCP Oimpresso DESCONECTADO a sessão inteira** — git/gh foram a fonte. Webhook propaga docs ao MCP. CYCLE off-cycle.

## O que aconteceu
- **[W] "isso já tinha sido feito pra React, qual a diferença?"** → comparei com `Superadmin/Usuario360` (única tela React do Superadmin): é **user-centric** (acesso/segurança de UM usuário, superadmin-only, com lock/unlock). O Modo Suporte é **business-centric** (operação do cliente, equipe não-god, operador excluído). NÃO é duplicata.
- **[W] "isso me parece errado, pode abrir e conferir"** → **auditoria de scoping** (abri o código, não só grep): achei MEU ERRO da auditoria anterior. O **switch de contexto de sessão NÃO é seguro** — `CashRegisterUtil::getRegisterDetails`, `TransactionUtil::payContact` e criação-de-usuário leem **`auth()->user()->business_id`** (não a sessão) → trocar a sessão vazaria o operador / gravaria dinheiro no tenant errado (split-brain). Caminho seguro: **read-only com `business_id` EXPLÍCITO** (103 métodos dos Utils core já são parametrizados; padrão `Superadmin/BusinessAuditService`).
- **#3279** — `SupportClientViewService` (read-only, business_id explícito) + desenho seguro documentado na SPEC.
- **#3285 (F1)** — RUNBOOK-empresas + **BRIEFING.md**. O GT-G3 falhou (`front_door_coverage` 100→98.6); aprendi que a **"door"** no `knowledge-drift` é literalmente `existsSync(requisitos/<Mod>/BRIEFING.md)` — 2 docs sem BRIEFING derrubam a cobertura. BRIEFING resolveu.
- **#3289 (F3)** — tela `Suporte/Empresas` (trio page+charter+casos + controller `SupportController@index` + rota `/suporte/empresas` + Pest UC-SUP-01/02/03). Mergeou com ratchets **ESLint/Layout vermelhos (não-required)** → **#3291** consertou (`rounded-xl`→`rounded-lg`; header `flex` solto→`<Inline>`).

## Artefatos (no `main`)
`app/Services/Support/SupportClientViewService.php` · `resources/js/Pages/Suporte/Empresas.{tsx,charter.md,casos.md}` · `app/Http/Controllers/Support/SupportController.php` · rota `/suporte/empresas` · `memory/requisitos/Suporte/{RUNBOOK-empresas,BRIEFING}.md` + SPEC §Desenho seguro · `tests/Feature/Support/{SupportClientViewServiceTest,SupportEmpresasHttpTest}.php`.

## Persistência
git (4 PRs merged) · MCP desconectado (webhook sincroniza) · drafts charter/mockup em `~/.claude/oimpresso-local/suporte-drafts/`.

## Próximos passos
"continua Visao" → tela `Suporte/Visao` (destino do "Entrar"), consumindo `SupportClientViewService`, mesmo molde MWART (charter+casos+Pest+show route). Depois: nav, BRIEFING refresh, smoke biz=1 pós-deploy. Fase A "atuar" (write) só após auditoria das vias de scoping.

## Lições catalogadas
- **Verificação adversarial é o ativo (de novo):** o "me parece errado" do Wagner matou um switch-de-sessão **inseguro** que minha auditoria-por-grep tinha aprovado. **Abrir os arquivos** (não grep) revelou `auth-user` em paths de dinheiro. Grep mente; abrir confirma.
- **"door" do `knowledge-drift` = `BRIEFING.md`** existir. Todo módulo `requisitos/<Mod>/` com ≥2 docs precisa de BRIEFING senão derruba `front_door_coverage` (GT-G3).
- **Gates NÃO-required deixam regressão MERGEAR** — OrphanRender (#3266), ESLint/Layout (#3289). **3× nesta sessão** tive que perseguir fix-após-merge. Candidato a promover a required (decisão de governança do Wagner).
- **ESLint DS:** `rounded-xl+` banido (máx `rounded-lg`); `flex`/`grid` solto → primitivos `<Inline>/<Stack>/<Grid>/<Box>` (ADR 0253).
- **Switch de contexto cross-tenant = inseguro** nesse código (split-brain auth-user↔session). Suporte fica **read-only + business_id explícito**; "atuar" exige refatorar as vias de scoping antes.

## Pointers
[ADR 0305](../decisions/0305-modo-suporte-cross-tenant-exceto-operador.md) · [SPEC §Desenho seguro](../requisitos/Suporte/SPEC.md) · [BRIEFING](../requisitos/Suporte/BRIEFING.md) · [RUNBOOK Empresas](../requisitos/Suporte/RUNBOOK-empresas.md) · [Empresas.casos.md](../../resources/js/Pages/Suporte/Empresas.casos.md).
