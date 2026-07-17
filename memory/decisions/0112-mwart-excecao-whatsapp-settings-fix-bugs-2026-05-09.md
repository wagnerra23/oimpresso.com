---
slug: 0112-mwart-excecao-whatsapp-settings-fix-bugs-2026-05-09
number: 112
title: "MWART exceção — fix bugs UI Settings.tsx Whatsapp (sem migração nova)"
type: adr
status: aceito
authority: reference
lifecycle: arquivado
decided_by:
  - W
decided_at: '2026-05-09'
quarter: 2026-Q2
related:
  - 0096-modulo-whatsapp-meta-cloud-api-direto
  - 0104-processo-mwart-canonico-unico-caminho
emends: []
pii: false
---

# ADR 0112 — MWART exceção — fix bugs UI `Whatsapp/Settings.tsx` 2026-05-09

**Status:** ✅ Aceita (exceção registrada, `lifecycle: arquivado` — vocab controlado interno equivalente a "historical")
**Data:** 2026-05-09
**Decisão por:** Wagner Rocha (auto-detectada via `/mwart-override` em PR)
**Tela:** `resources/js/Pages/Whatsapp/Settings.tsx`
**PR aplicado:** [#282](https://github.com/wagnerra23/oimpresso.com/pull/282) — `fix(whatsapp): UI Settings — habilita Baileys, ícone nos alerts, LGPD aceito visível`

---

## Contexto

Logo após o merge da [US-WA-002 BaileysDriver custom](https://github.com/wagnerra23/oimpresso.com/pull/280), Wagner reportou 4 bugs ao testar a página `/whatsapp/settings`:

1. "Bayles não dá pra selecionar" — `disabled` hardcoded no DriverCard.
2. "Provedor já foi aceito mas ainda aparece zapi" — consequência do #1.
3. "Mensagem vermelha mal formatada" — Alert sem ícone vs com ícone.
4. "Ao abrir fica mostrando todos os campos" — Card LGPD com badge "obrigatório" permanente.

Os 4 bugs foram corrigidos no PR [#282](https://github.com/wagnerra23/oimpresso.com/pull/282) com mudanças cirúrgicas no Settings.tsx (+60/−17) e SettingsController.php (+1/−0).

## Por que pediu override do MWART gate

Per [ADR 0104 §F1 PLAN](0104-processo-mwart-canonico-unico-caminho.md), qualquer mudança em `resources/js/Pages/<Mod>/<Tela>.tsx` exige `memory/requisitos/<Mod>/RUNBOOK-<tela-kebab>.md` + SPEC.md com USs + visual-comparison.md aprovado. O gate é enforcement Camada 3 (CI workflow `mwart-gate.yml`).

**Esta mudança não é migração MWART:**
- O arquivo `Settings.tsx` **já existia em main** antes deste PR (criado em outro Lote MWART concluído).
- Não há migração Blade→React em curso — só correção de 4 bugs em arquivo já-Inertia.
- Nenhuma fase F1-F5 do processo MWART aplica (nem PLAN, nem BACKEND BASELINE, nem FRONTEND INCREMENTAL, nem QA/CUTOVER).
- Criar RUNBOOK + visual-comparison post-hoc seria burocracia sem valor — o artefato existe pra orientar migração futura, não pra fixar bug em código já-Inertia.

## Decisão

Override aprovado per [proibicoes.md §"Processo MWART canônico"](../proibicoes.md):
> Override: comentar `/mwart-override <razão>` em PR (vira ADR per-tela `lifecycle: historical`)

PR #282 mergeado em `main` em 2026-05-09 09:40Z após CI re-rodar com override ativo.

## Consequências

- Settings.tsx pode ser editado livremente para fix de bugs sem RUNBOOK extra.
- **Não cria precedente para mudança estrutural** — qualquer alteração que adicione função, tela, ou wizard novo na Settings exige RUNBOOK/SPEC/visual-comparison normalmente.
- Esta exceção é per-tela, per-incidente, `lifecycle: arquivado` (equivalente a `historical` no vocab informal). Não-replicável sem nova ADR.

## Bug observado no workflow durante este PR

O `mwart-gate.yml` quebrou com `SyntaxError: Unexpected identifier 'resources'` no step de comment, causado por interpolação `${{ steps.gate.outputs.report }}` direto em template string JS com backticks. Bug ortogonal a esta exceção; corrigido em [PR #283](https://github.com/wagnerra23/oimpresso.com/pull/283) (passar outputs via `env:` em vez de interpolação literal).

## Referências

- [ADR 0094 — Constituição V2](0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0096 emenda 4 — BaileysDriver custom autorizado](0096-modulo-whatsapp-meta-cloud-api-direto.md)
- [ADR 0104 — Processo MWART canônico](0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0107 — Visual comparison gate F3](0107-emendation-0104-visual-comparison-gate-f3.md)
- [proibicoes.md §Processo MWART canônico](../proibicoes.md)
