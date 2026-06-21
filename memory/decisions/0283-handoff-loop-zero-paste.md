---
slug: 0283-handoff-loop-zero-paste
number: 283
title: "Loop de handoff zero-paste — repo fonte única, gate de conteúdo, sem auto-merge até a rede existir"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-17"
module: governance
related: [0114-prototipo-ui-cowork-loop-formalizado, 0107-emendation-0104-visual-comparison-gate-f3, 0094-constituicao-v2-7-camadas-8-principios, 0093-multi-tenant-isolation-tier-0, 0269-deploy-automatico-build-no-runner, 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura]
supersedes: []
---

# ADR 0283 — Loop de handoff zero-paste (repo-nativo · gate de conteúdo · sem auto-merge até a rede existir)

> **Status:** aceito por [W] em 2026-06-17. Endurece F1/F3 do `prototipo-ui/PROTOCOL.md` (ADR 0114).
> Proposta + dossiês adversariais (fonte única do detalhe): `memory/decisions/proposals/handoff-loop-zero-paste.md`,
> `memory/sessions/2026-06-17-adversario-handoff-loop.md` (r1) e `-r2.md` (r2).

## Contexto

O loop de handoff Cowork → Claude Code (F1 [CC] → F3 [CL]) tinha 3 falhas medidas:

1. **Humano como fio de integração** — [W] copiava/colava e ainda precisava *lembrar* de conferir o que chegava no Code.
2. **Duas línguas** — o protótipo Cowork usava CSS cru (`.om-*`, oklch na mão); o repo é Tailwind + tokens. Mandar `.om-*` pro Code fazia ele **improvisar** (saída sem cor/tipografia).
3. **Gate na memória do humano** — "pronto quando…" dependia de [W] lembrar de olhar.

Dois adversários ([AH]) atacaram a solução proposta e **verificaram contra os workflows reais** que: `multi-tenant-gate.yml` faz SKIP-AS-PASS em `.tsx`; `deploy.yml` publica em prod no push pra `main`; `scope-guard.yml` é controller-vs-`SCOPE.md` (não há `files_json`). E acharam **stored-XSS vivo em produção** (`Essentials/Messages`, `Todo`, `contact_address`) que passou pelos 17 required checks — prova de que **nenhum gate mordia conteúdo em `.tsx`**.

## Decisão

### R1 — Entrega SEMPRE repo-nativa e auditada contra o `main`
[CC] lê os arquivos reais do `main` antes da ponte e audita (existe / não existe / diverge). Entrega o diff **na língua do repo** (Tailwind + tokens existentes); proibido `.om-*` cru. **Pronto quando:** cita arquivo+linha do arquivo sobre o qual afirma.

### R2 — Barramento é o repo, não o clipboard de [W]
Canal canônico **único**: `prototipo-ui/COWORK_NOTES.md` (decisão de canal: COWORK_NOTES agora; sync/MCP como norte). Sem URLs efêmeras como canal.

### R3 — DoD por máquina, honesto sobre o que os gates NÃO checam
Gates required precisam passar — mas os atuais validam **canal** (lint/build/conformance), não **conteúdo**.

### Veredito (2 rodadas adversariais): humano-no-merge é estrutural
Os 5 controles propostos (lint render-only, scope-guard `files_json`, assinatura nonce+exp+bind, quarentena, digest fail-closed) validam **CANAL**, não **CONTEÚDO**. Para `.tsx` num ERP multi-tenant solo-founder, **humano-no-merge é estrutural** — a **Fase 0 (1-clique de [W]) é o ótimo real**, autorizada. **Auto-merge fica BLOQUEADO** até os 5 controles existirem e passarem fixture no `gate-selftest`.

### O ROI real é um ORÁCULO DE CONTEÚDO (não os 5 controles de canal)
Primeira parcela entregue: `dsih-gate` (ratchet anti-`dangerouslySetInnerHTML` + self-test). Próximas: href/scheme cru + **smoke autenticado cross-tenant** (isolamento entre 2 fixtures de business) — o gate que pega vazamento de dado.

## Decisões resolvidas (eram abertas na proposta)
- **Fase 0:** autorizada (R1+R2 + assinatura + `pending`/`ack`, **sem** auto-merge).
- **Canal R2:** `COWORK_NOTES.md` agora + MCP/sync como norte.
- **Auto-merge:** bloqueado até os 5 controles auto-testados.

## Consequências
- **Positiva:** [W] sai do transporte e da revisão linha-a-linha; Code recebe tokens reais; classe de stored-XSS em `.tsx` passa a ser barrada por gate (`dsih-gate`); 3 XSS vivos fechados (PRs #2891/#2893/#2895).
- **Negativa:** [CC] gasta mais por ponte (ler o `main` antes). Aceitável — troca minutos de leitura por horas de retrabalho.
- **Custo de oportunidade:** os 5 controles de canal são esforço grande de [CL]; o veredito redireciona o 1º investimento pro oráculo de conteúdo (maior ROI).

## Migração (incremental)
1. **Imediato:** R1 (ler `main` + tokens do repo) — já praticado.
2. **Curto:** padronizar bloco em `COWORK_NOTES.md` (R2) + assinatura básica.
3. **Médio:** construir Fase 0 (ingest/pending/ack via TeamMcp `McpTokenIssuer`) — auto-PR + 1-clique de [W].
4. **Norte:** os 5 controles + sync Cowork→repo → só então reabrir auto-merge (Camada-4 render-only).

## Responsabilidades
| Papel | Faz |
|---|---|
| **[W]** | decide regra/limiar; promove gates a required; aprova overrides; clica o merge (Fase 0) |
| **[CC]** | lê `main` antes de propor; entrega repo-nativo+auditado; nunca `.om-*` cru; propõe (não numera) |
| **[CL]** | aplica do repo; liga gates; numera ADR sob OK de [W] |

## Histórico
| Data | Autor | Mudança |
|---|---|---|
| 2026-06-17 | [CC]+[AH] | proposta + 2 rodadas adversariais (proposals/handoff-loop-zero-paste.md) |
| 2026-06-17 | [W] | **aceito** — Fase 0 autorizada, canal COWORK_NOTES, auto-merge bloqueado até 5 controles |
