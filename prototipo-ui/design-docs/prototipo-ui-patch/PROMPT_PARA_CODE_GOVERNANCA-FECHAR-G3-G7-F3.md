# PROMPT PARA CLAUDE CODE — Fechar a governança executável (G-3 verde → required · G-7 com prova · F3 ratchet)

> **Cole UMA vez no Claude Code.** Zero-toque [W]. Autorizado no chat 2026-06-09 ("gere os comandos").
> Continuação direta do que você já executou (ADR 0264 + 0261, casos-gate Fase 2 required). Tudo referenciado já está em `@main` — não há arquivo a transportar.

## Estado confirmado (✓lido @main 2026-06-09 tarde, por [CC])
- `casos-gate.yml` = Fase 2, required always-run ✅
- `dominio-gate.yml` + `memory/dominio/oficina-auto.md` ✅
- `e2e-gate.yml` = **workflow_dispatch MANUAL, não-required, 1º run verde nunca validado** ← elo aberto
- `scripts/casos-test-results.json` = **vazio (`ucs: 0`)** — todo `Status: ✅` de UC é unverified (G-7)
- `scripts/casos-coverage-baseline.json` = 32KB de dívida legada (F3 não começou a descer)

## Tarefa 1 — G-3: primeiro run verde do E2E (prioridade, destrava G-7)
```bash
gh workflow run e2e-gate.yml --ref main
gh run watch $(gh run list --workflow=e2e-gate.yml --limit 1 --json databaseId -q '.[0].databaseId')
```
- Se FALHAR: depurar até verde — provável: seeder OficinaAuto (o yml já tolera `|| true`, mas os specs precisam dos dados), `wait-on` ausente (add devDep se faltar), `.env.example` incompleto, porta/baseURL. Iterar em branch, PRs pequenos, CI verde. **NÃO** afrouxar os specs pra passar (L-24) — conserta o harness, não o teste.
- Quando VERDE: baixar o artifact `casos-test-results`, commitar o manifesto conscientemente:
```bash
npm run casos:results && git add scripts/casos-test-results.json
```
  DoD-1: manifesto com `ucs >= 3` (UC-06 Oficina · UC-V05 Vendas · UC-F02 Financeiro) e os ✅ desses UCs deixam de ser unverified no `casos:check`.

## Tarefa 2 — flip G-3 pra required (SÓ depois de 2 runs verdes seguidos)
- `e2e-gate.yml`: `workflow_dispatch:` → `pull_request:` (+ manter dispatch), padrão ADR 0261 (always-run; sem `paths:` filter).
- Promover a required status check junto de casos/dominio (mesmo mecanismo que você usou no flip do casos-gate).
- PR-canário de controle-negativo: quebrar de propósito 1 UC e confirmar que o CI BLOQUEIA (vê 🔴 e 🟢 — Regra 5 do Cowork).
- Registrar o flip como amend/nota na ADR 0264 (você numera/versiona; append-only).

## Tarefa 3 — dicionários de domínio das verticais vivas (G-4 completo)
- Mesmo formato de `memory/dominio/oficina-auto.md`, 1 PR cada: **vendas** (order FSM, tipos de pedido, split NF-e/NFS-e) · **financeiro** (FSM de título, conciliação OFX, régua de cobrança) · **fiscal** (docs, status SEFAZ).
- Fonte = migrations/enums reais + ADRs — **nunca** inventar termo; divergência enum↔dicionário é exatamente o que o `dominio:check` cobra.

## Tarefa 4 — F3: ratchet até zerar (autônomo, entre revisões)
- Regra já decidida: todo PR que tocar uma tela fecha o trio + teste daquela tela (baseline DESCE, nunca sobe).
- Ordem: telas em produção primeiro (OficinaAuto → Vendas → Financeiro → Fiscal núcleo).
- DoD-final (ADR 0264 F3): `casos:check` baseline = 0 nas telas vivas · `dominio:check` = 0 · Playwright cobre os UCs críticos de cada vertical.

## Ordem
T1 (verde) → commit manifesto → T2 (flip+canário) → T3 e T4 em paralelo, PRs pequenos, merge autônomo com CI verde. Tier 0 (DB/seeder do Martinho biz=164, segredos, required-checks novos além do combinado) → para e escala [W].

## Não fazer
- ❌ Afrouxar/skipar spec pra ficar verde (L-24).
- ❌ Flipar G-3 pra required antes de 2 runs verdes (lição ADR 0261 — gate instável required = deadlock).
- ❌ Termo de domínio inventado no dicionário — só o que existe em migration/ADR.
- ❌ Commitar manifesto G-7 gerado de run vermelho como se fosse prova de ✅.
