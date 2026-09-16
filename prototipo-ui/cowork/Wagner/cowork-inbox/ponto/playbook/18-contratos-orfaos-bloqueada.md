<!-- SESSÃO FRIA · abra esta thread sozinha. Read-order mínimo e prompt de abertura: `_SESSAO-FRIA.md` (linha "contratos órfãos (bloqueada)").
     Os ids de decisão (D-*) só existem em `ATA-DECISOES-2026-09-14.md` — leia a ata antes, ou as siglas ficam órfãs.
     Não leia as outras threads: cada uma é 1 PR e o contexto delas não é pré-requisito desta. -->

# 18 · Contratos órfãos: Fechamento, Conformidade e REP-P — BLOQUEADA até a proposal

> **Estado: BLOQUEADA — e agora com data de desbloqueio.**
> Quando esta thread nasceu (14/09), o motivo era *"9 `data-contract` existem no build e não há Page nem rota"*. No mesmo dia [W] **ratificou as 5 decisões** da proposal `ponto-contratos-retidos` — então o bloqueio **mudou de natureza**: não espera mais decisão, espera **execução** (thread 30).

## Os 9 contratos órfãos, e para onde vão

| arquivo do build | `data-contract` | destino depois da thread 30 |
|---|---|---|
| `ponto-fechamento.jsx` | `fechamento-pre-checagem` · `fechamento-passos` · `fechamento-acoes` · `fechamento-totais` | **PR 2** (Fechamento, passos 1–3) |
| `ponto-fechamento.jsx` | `conformidade-regras` | **PR 1** (Conformidade read-only — D0) |
| `ponto-mobile.jsx` | `repp-gps` · `repp-tipos` · `repp-nota-regras` | **PR 4** (REP-P) |
| `ponto-mobile.jsx` | `repp-fila-validacao` | **PR 3** (ValidacaoMobile) |

## O que NÃO fazer (é o que a proposal mediu)

❌ **Não descer os 2 `.contract.json` antes da tela.** Não existe estado "em espera" no `contract.schema.json`; o job varre `git ls-files '*.contract.json'` e **todo contrato não-`EXEMPLO` é ativo**. Contrato com `alvo` inexistente nasce **vermelho permanente** e, como o job dispara em qualquer `.tsx` tocado, **pinta todo PR de UI do projeto**. *"Contrato vermelho permanente treina o time a ignorar gate — custo maior que a ausência do contrato."*

❌ **Não criar Page só para hospedar o contrato.** Era o atalho tentador e é o que a proposal chama de *"redigir do zero um fluxo com efeito jurídico"*.

## Desbloqueio

Esta thread **fecha sozinha** quando os PRs 1–4 da thread 30 entrarem: cada um leva **tela + contrato no mesmo PR**, que é o que mantém o gate verde.
