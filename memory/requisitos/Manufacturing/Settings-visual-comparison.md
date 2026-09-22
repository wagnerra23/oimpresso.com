---
id: requisitos-manufacturing-settings-visual-comparison
title: "Comparacao design x producao — Manufacturing/Settings (Configuracoes)"
module: Manufacturing
tela: Manufacturing/Settings
owner: W
status: rascunho
inertia_target: resources/js/Pages/Manufacturing/Settings.tsx
last_updated: "2026-09-22"
---

# Comparação design × produção — `Manufacturing/Settings` (Configurações)

> Um dos 5 primeiros registros do módulo (ver [Index-visual-comparison.md](Index-visual-comparison.md)).

## Âncora — computada, não escolhida no olho

```
node scripts/design/ancora.mjs Manufacturing/Settings --staging prototipo-ui/cowork
  âncora ✓: [related_prototype (charter)] prototipo-ui/cowork/Felipe/manufacturing-producao.jsx
```

O componente é `MfgConfig` (`:330` do protótipo). Passou de `Wagner/` para `Felipe/` em 2026-09-22
([PR #7696](https://github.com/wagnerra23/oimpresso.com/pull/7696), decisão [W]).

⚠️ **Três telas dividem este arquivo** — `Index` → `MfgProducaoView` (`:31`), `Report` →
`MfgRelatorio` (`:268`), `Settings` → `MfgConfig` (`:330`). A âncora aponta o **arquivo**; o
componente é o que separa as três.

> ⚠️ **Saída medida com o [#7696](https://github.com/wagnerra23/oimpresso.com/pull/7696)
> aplicado** (branch `claude/fabricacao-prototype-production-8fa4a8`, 2026-09-22). Enquanto ele
> não mergear, `main` ainda resolve esta tela em `Wagner/` — o que este bloco registra é a
> medição daquela branch, não o estado de `main`.

⚠️ **Sem selo de frescor** — `ancora.mjs:93` fixa `LUGAR_FIXO = 'prototipo-ui/cowork/Wagner'`.

## ⚠️ Esta tela NUNCA foi medida

Não existe artefato em `governance/design/targets/medidas/Manufacturing--Settings/`. A rodada de
[#7503](https://github.com/wagnerra23/oimpresso.com/pull/7503) (18/09) cobriu **2 das 5** telas
(`Index` e `Recipes`); esta ficou de fora. **Ausência de medição não é ausência de divergência** —
e nenhum gate que bloqueia merge mede fidelidade.

## Cobertura desta tela hoje

| camada | estado |
|---|---|
| charter | ✓ `Settings.charter.md` — **`status: draft`** |
| casos | ✓ **4 UCs** em `Settings.casos.md` — a menor cobertura da família |
| medição design×prod | ✗ **nunca rodou** |
| e2e / browser | ✗ nenhum |
| scorecard | ✗ nenhum |
| baseline de pixel | ✗ **0 `.snap`** |
| contrato de tela | ✗ nenhum |

## Pendências

1. **Primeira medição** — âncora nova, `sameTheme: true`, sonda nos dois lados.
2. ⚠️ **Tela de configuração governa o cálculo das outras.** Se a subida do protótipo alterar
   qualquer chave que entre em custo, margem ou baixa de estoque, vale a **regra mestre de
   valor/estoque** ([proibicoes.md](../../proibicoes.md)): dois caminhos + antes→depois + [W] antes
   do merge — aqui com o agravante de que o efeito aparece em tela **alheia**.
3. Visibilidade — o módulo só aparece com `manufacturing_module` no pacote do business **e** as
   permissões no perfil (`manufacturing.access_recipe`, `manufacturing.access_production`).
   Habilitar/desabilitar é pela UI canônica, **nunca** por `if ($business_id === N)`
   ([proibicoes.md §Multi-tenant](../../proibicoes.md)).
4. `status: draft` → `live` só depois de (1).

## Refs

- [`Settings.charter.md`](../../../resources/js/Pages/Manufacturing/Settings.charter.md) · [`Settings.casos.md`](../../../resources/js/Pages/Manufacturing/Settings.casos.md) · [RUNBOOK-settings.md](RUNBOOK-settings.md)
- [README do handoff](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/README.md) — **normativo** · [SDD](../../../prototipo-ui/cowork/Felipe/handoff_fabricacao/contexto/SDD-tela-fabricacao-v1.0.md)
