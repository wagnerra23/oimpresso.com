---
date: "2026-11-16"
slug: "associacoes-merge-das-6-ondas"
topic: "Merge das 6 ondas do Modules/Associacoes no main, com smoke antes e depois"
title: "Associações: merge das 6 ondas no main (ADR 0451, Uniafibro)"
module: Associacoes
us: [US-ASSOC-001, US-ASSOC-002, US-ASSOC-003, US-ASSOC-004, US-ASSOC-005]
prs: [10862, 10878, 10883, 10882, 10881, 10880]
related_adrs:
  - "0451-associacoes-rede-uniafibro-area-de-membros"
  - "0093-multi-tenant-isolation-tier-0"
---

# Associações: merge das 6 ondas no main

## TL;DR

As 6 ondas do `Modules/Associacoes` (ADR 0451) estão no main, na ordem 1→6, e a onda 6 está em
produção. O smoke de depois saiu igual ao de antes e não houve revert. O módulo segue desligado
até o [W] criar a empresa Uniafibro e o pacote `associacoes_module`.

## O que foi pedido

Mergear as 6 ondas do `Modules/Associacoes` (ADR 0451, rede Uniafibro) na ordem 1→6, uma por PR,
cada uma só com CI required verde, sem conflito e sem pedido de mudança do [W]. Smoke antes e depois
de cada merge em `/`, `/login`, `/catalogue/1/1`, `/nfe-brasil/tributacao` e `/fisio`. Não ativar o
módulo no superadmin: criar a empresa Uniafibro e o pacote `associacoes_module` é do [W].

## Resultado

| Onda | PR | Commit no main | Merge (UTC) |
|---|---|---|---|
| 1 · ADR 0451 aceita | #10862 | `8ff2fd6f8f` | 2026-11-15 16:09 |
| 2 · esqueleto + camada de dados (US-ASSOC-001) | #10878 | `b59046a765` | 2026-11-15 16:45 |
| 3 · associações e documentos (US-ASSOC-002) | #10883 | `16eb9083ea` | 2026-11-15 17:10 |
| 4 · associadas e consentimentos (US-ASSOC-003) | #10882 | `5163f8c9b3` | 2026-11-15 18:15 |
| 5 · painel da rede (US-ASSOC-004) | #10881 | `e9853feae7` | 2026-11-16 14:55 |
| 6 · área da associada por link + PIN (US-ASSOC-005) | #10880 | `ead16928be` | 2026-11-17 01:18 |

A onda 6 entrou em produção pelo deploy 43995018644 (`f6482e6b12`, success). O deploy do próprio
commit dela (43994521656) foi cancelado porque o seguinte o substituiu.

Smoke depois da onda 6, igual ao de antes:

| URL | Antes | Depois |
|---|---|---|
| `oimpresso.com/` | 200 | 200 |
| `/login` | 200 | 200 |
| `/catalogue/1/1` | 200 | 200 |
| `/nfe-brasil/tributacao` | 302 → `/login` | 302 → `/login` |
| `/fisio` | 302 → `/login` | 302 → `/login` |
| `uniafibro.com.br/` | 200 | 200 |
| `uniafibro.com.br/direitos` | 200 | 200 |
| `uniafibro.com.br/associacoes/m/x` | 302 → `/` | 200 |

O link de teste agora abre `Associacoes/Public/Membro` com `erro: link_invalido` e nenhum dado de
associada. Nenhuma regressão, nenhum revert.

## O que deu trabalho

- **Conflitos em arquivo gerado.** O main andou várias vezes durante a fila e o PR da onda 6 voltou
  a ficar `DIRTY` três vezes. Todos os conflitos eram em arquivo gerado (`catalog.json`,
  `SUPERFICIE.md` de Arquivos, Manufacturing e Superadmin). Receita: versão do main, regenerar com o
  gerador dono, rodar o `--check`. Sempre merge de `FETCH_HEAD`, nunca rebase.
- **Arquivo gerado velho dentro do PR.** Depois de um desses merges, o PR ainda levava versões
  antigas de `MAQUINAS-INVENTARIO`, `ONBOARDING-AGENTE-GERADO`, `PAINEL-SISTEMA` e do
  `_STATUS-GENERATED` do Manufacturing, que teriam regredido o main. Voltaram para a versão do main
  antes do merge. Ficaram só os gerados do próprio módulo e o `_BACKLOG-GENERATED.md` (o título da
  US-ASSOC-005 mudou nesta onda).
- **PHPStan barrou `env()` no config do módulo** (`noEnvCallsOutsideOfConfig`): `url_base` virou
  literal, porque o endereço é decisão fixa da D6.
- **Casos-coverage marcou `Membro.casos.md` como desatualizado**: `last_run` atualizado.
- **Limite da API do GitHub estourou** com dois laços de polling. Os acompanhamentos passaram a
  consultar a cada 4 a 5 minutos.

## Pendente

- **UC-ASSOC-01 vermelho no main** (lane Verticais · Pest (MySQL), que não é required). A diretoria
  recebe a prop deferida `associacoes` vazia no teste. Ver o handoff desta sessão para o estado do
  diagnóstico.
- **[W]**: criar a empresa Uniafibro e o pacote `associacoes_module` no superadmin. Só depois disso
  o módulo pode ser ligado para alguém.
- `PAINEL-SISTEMA.md` e o `_STATUS-GENERATED` do Manufacturing estão desatualizados no próprio main;
  não foram regenerados aqui, porque não são deste trabalho.
