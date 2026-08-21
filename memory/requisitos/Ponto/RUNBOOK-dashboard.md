---
owner: W
last_validated: "2026-08-21"
slug: ponto-runbook-dashboard
title: "Ponto — Runbook do Painel (/ponto · Dashboard/Index)"
type: runbook
module: Ponto
tela: Ponto/Dashboard/Index
status: ativo
date: 2026-08-21
---

# RUNBOOK — Painel do Ponto (`Ponto/Dashboard/Index`)

> **Por que este arquivo existe.** O hook `block-mwart-violation` barra `Edit`/`Write` em
> `resources/js/Pages/Ponto/Dashboard/Index.tsx` enquanto não houver um `RUNBOOK-<kebab>.md`
> na pasta do módulo — é o **único** enforcement de RUNBOOK desde a [ADR 0271](../../decisions/0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) onda 2, e
> **não tem override** (o `/mwart-override` que a mensagem antiga anunciava nunca teve handler —
> lápide §5 2026-08-08). Este é o F1 PLAN do processo MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).

## 1. O que é a tela

Painel de abertura do módulo Ponto (rota `/ponto`, aba *Painel*). Mostra o estado do dia e o que
trava o fechamento da competência: KPIs do mês, presença ao vivo, série de 7 dias, atividade de
marcações, caixa de alertas e a fila de intercorrências aguardando decisão.

Audiência: **gestor de RH / DP** do business. Acesso escopado por `business_id` (Tier 0,
[ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).

## 2. Fontes canônicas (ordem de precedência)

| Ordem | Fonte | Papel |
|---|---|---|
| 1 | `memory/requisitos/Ponto/SPEC.md` · `SDD-espelho-e-jornada-v1.0.md` | US e casos de uso |
| 2 | `prototipo-ui/contrato/ponto-painel.contract.json` | **contrato visual** — âncoras + copy literal |
| 3 | `prototipo-ui/cowork/ponto-page.jsx` | protótipo Cowork (fonte de design, [ADR 0299](../../decisions/0299-figma-nao-e-fonte-de-design.md)) |
| 4 | `Modules/Ponto/Resources/views/dashboard/index.blade.php` | Blade legado — contrato de paridade |

O contrato declara a própria proveniência: *"Protótipo F1 [CC] importado das telas Blade do módulo
— os KPIs e a fila de aprovações são os mesmos do small-box/widget AdminLTE, traduzidos pro DS vivo."*

## 3. Contrato de tela — as 4 seções

> **Dono do mecanismo:** [`RUNBOOK-contrato-de-tela`](../_DesignSystem/RUNBOOK-contrato-de-tela.md)
> (v1 — determinístico, sem render, sem auth). O v0 foi recusado na
> [ADR 0290](../../decisions/0290-fidelity-lock-v0-recusado.md); o princípio da catraca semântica
> vem da [ADR 0286 §5](../../decisions/0286-channel-health-corroborado-por-mensagem-real.md)
> — ⚠️ ADR cujo TÍTULO é sobre outro assunto (channel health), então cite sempre com o §5.

`prototipo-ui/contrato/ponto-painel.contract.json` exige `data-contract="<id>"` no elemento que
envolve cada seção, **na ordem do contrato**, e cada string de `copy` presente **literalmente** no
alvo `resources/js/Pages/Ponto/Dashboard/Index.tsx`.

| Seção | Copy exigida | Estados |
|---|---|---|
| `painel-nota-fechamento` | `DIVERGENCIA` | com-pendencia · sem-pendencia · so-divergencia |
| `painel-kpis` | Colaboradores ativos · Presentes agora · Atrasos hoje · Faltas hoje · HE do mês · Aprovações pendentes | — |
| `painel-fila-aprovacoes` | Fila de aprovações · Ver fila completa · Nenhuma intercorrência aguardando decisão. | com-pendentes · vazio |
| `painel-atividade` | Atividade recente · marcações de hoje | — |

## 4. Estado MEDIDO em 2026-08-21 (o F3 pendente)

Rodado `node scripts/contrato-de-tela.mjs --contract prototipo-ui/contrato/ponto-painel.contract.json`
→ **12 falhas**: as 4 âncoras ausentes (`grep -c data-contract` = 0) + 8 copies.

O que a medição mostrou — e **corrige a leitura fácil de que faltam seções**: as seções **existem**
e as props **já chegam**. Salvo uma exceção, é alinhamento de copy, não construção.

| Contrato exige | A tela renderiza hoje | Ação |
|---|---|---|
| `Colaboradores ativos` | `label="Colaboradores"` | renomear |
| `Aprovações pendentes` | `label="Aprovações"` | renomear |
| `Fila de aprovações` | `<CardTitle>Aprovações` | renomear |
| `Ver fila completa` | `Ver todas` | renomear |
| `Nenhuma intercorrência aguardando decisão.` | `Nenhuma pendência` | renomear |
| `Atividade recente` | `<ActivityFeed title="Atividade de hoje">` | renomear |
| `marcações de hoje` | já existe em `_components/ActivityFeed.tsx` | mover/repetir no alvo |
| `DIVERGENCIA` (`painel-nota-fechamento`) | **a seção não existe** | **construir** |

**A exceção que custa backend:** a nota de fechamento precisa da contagem de dias em estado
`DIVERGENCIA` na competência. Medido: não há `divergencias` em `interface Props` nem no controller
do dashboard. É a única parte do F3 do Painel que não é copy.

Redação da nota, nos 3 estados, lida do protótipo (`ponto-page.jsx` §`Nota contrato="painel-nota-fechamento"`):

- **sem-pendencia** — "Nenhuma intercorrência aguardando decisão e nenhum dia em divergência — a competência pode consolidar."
- **so-divergencia** — "Nenhuma intercorrência aguardando decisão, mas N dia(s) está/estão em **DIVERGENCIA** na apuração — o espelho não consolida assim, e o AFD gerado sai com a jornada errada."
- **com-pendencia** — "N intercorrência(s) espera(m) decisão e N dia(s) está/estão em **DIVERGENCIA** na apuração. Enquanto isso, o espelho do mês não consolida — e o AFD gerado sai com a jornada errada."

## 5. Por que o gate está vermelho no `main` (e não é bug)

O contrato desceu por decisão [W] (opção B, 2026-08-21): entram só os contratos cujo **alvo existe**;
`ponto-fechamento` e `ponto-rep-p` ficaram retidos porque suas telas não existem. O commit é explícito:
*"Os 2 que ficam SEGUEM VERMELHOS — as âncoras `data-contract` ainda não estão nos .tsx. A diferença é
que agora o vermelho tem conserto: é o F3 dessas duas telas."*

Consequência operacional a saber: o job `Preflight + contratos ativos` varre **todos** os
`*.contract.json` sempre que qualquer `.tsx` muda — então este vermelho aparece em **todo PR de UI**
até o F3 fechar. Ele **não** está entre os required (medido 2026-08-21 na união
`classic_protection.contexts ∪ rulesets.contexts`), logo não bloqueia merge.

## 6. Passos do F3

1. Ler este runbook + `Index.charter.md` ao lado do `.tsx` (charter é lei — [precedência](../../proibicoes.md)).
2. Backend: expor `divergencias` (contagem de dias em `DIVERGENCIA` na competência, escopada por
   `business_id`) como prop **deferida** (`Inertia::defer`, é query agregada — regra do
   [RUNBOOK-inertia-defer-pattern](../_DesignSystem/RUNBOOK-inertia-defer-pattern.md)).
3. Frontend: construir `painel-nota-fechamento` com os 3 estados; anexar `data-contract` nas 4
   seções na ordem do contrato; alinhar as 7 copies renomeadas.
4. Rodar a verificação (§7). Só então abrir PR.

## 7. Verificação

```bash
node scripts/contrato-de-tela.mjs --contract prototipo-ui/contrato/ponto-painel.contract.json
```

Verde = `✅ limpo.` · Vermelho lista seção/copy faltando, uma por linha.

Pest e PHPStan rodam no **CT 100**, nunca local nem no Hostinger
([proibicoes §Ambiente](../../proibicoes.md)).

## 8. Não fazer

- ⛔ Marcar `data-contract` numa seção e **não** entregar a copy — o gate cobra os dois, e âncora
  sem copy é a forma de passar parecendo que passou.
- ⛔ Alterar o contrato pra caber na tela. O contrato é a fonte; se ele estiver errado, isso é
  decisão [W] e vira PR do contrato, com razão escrita — não ajuste silencioso.
- ⛔ Ler o vermelho deste gate como "alguém quebrou": é dívida declarada, com dono e conserto.
