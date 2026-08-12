---
date: "2026-08-12"
topic: "Pages para dentro dos módulos — piloto PaymentGateway e o ciclo provado"
title: "Pages para dentro dos módulos — piloto PaymentGateway e o ciclo provado"
description: "Decisão [W] de mover as telas Inertia para os módulos donos; POC do resolver, piloto PaymentGateway migrado, 5 armadilhas medidas e 2 gates novos."
type: reference
authority: historical
lifecycle: ativo
owner: W
---

# Pages para dentro dos módulos — piloto PaymentGateway

## De onde veio

Revisão adversarial mediu o descasamento **nome do módulo × pasta de Pages**. Confirmado e ampliado
com as **duas** formas de render (`Inertia::render` 232 + o helper `inertia()` 2 — medir só a
primeira dava `Tarefas`/`_Showcase` como órfãs, conclusão falsa de detector incompleto):

| Módulo | Renderiza sob | Estava assim |
|---|---|---|
| `Whatsapp` | `Atendimento` ×9 | SUPERFICIE listava **3 telas de 29** |
| `PaymentGateway` | `Settings` ×2 | 12 arquivos fora do inventário |
| `Forja` | `team-mcp` ×10 | `Modules/TeamMcp` apagado em 2026-07-31 ([W]: *"MCP vai para forja"*); a lápide manda ler `Forja/SUPERFICIE.md`, que listava **zero** das 23 |

O `--all --check` (required) saía **exit 0** o tempo todo: quando o mapa não declara o namespace,
gerado e commitado erram **juntos**. Gate que não podia ficar vermelho para essa classe.

**Decisão [W]:** *"eles têm que ficar nos seus respectivos módulos e o Inertia tem que achar e o
Vite tem que compilar"*. Recomendação aceita: mover primeiro, renomear namespace depois (PRs
separados), piloto num módulo pequeno.

## O que foi provado antes de planejar (POC isolado, fora do repo)

| Prova | Resultado |
|---|---|
| Vite compila `.tsx` dentro de `Modules/<X>/Resources/js/Pages/**` | ✅ chunks emitidos |
| Inertia resolve pelo **nome que os controllers já usam** | ✅ **zero** dos 232 call-sites muda |
| Colisão entre dois módulos na mesma chave | ⚠️ **silenciosa** — build exit 0, uma vence, a outra some |

Custo medido: **1.785** imports usam `@/` (imunes) contra **83** relativos, dos quais **78** movem
junto; só **5** cruzavam a fronteira, num único caso.

## Sessão irmã no mesmo tema, no mesmo dia

O commit `86a4adce517` (PR #5679) landou horas antes: errata em `.claude/rules/pages.md` provando
que **o glob é escolha nossa**, teste cravando a string nas duas pontas e o gatilho da lane
corrigido. A rule já antecipava este caso — *"se você quer mover Pages pra dentro de `Modules/<X>/`,
o obstáculo é decisão de projeto (dois globs + o assert), não o Inertia"*.

Li antes de tocar. O assert usa `toContain`, então **adicionar** um segundo glob não o quebra; o
contrato foi **estendido** (UC-4), não contornado.

## As 5 armadilhas — todas só apareceram testando o ciclo

1. **Casing.** A convenção nWidart aqui é `Resources/` **maiúsculo** (711 arquivos contra 12) e o
   glob do Vite é case-sensitive. Meu `mkdir .../resources/...` **fundiu** com o `Resources/`
   existente no Windows e o git registrou o casing errado: funcionaria local, quebraria no CI Linux.
2. **Build verde não prova nada.** Controle negativo: **sem** o glob de módulos o build **também**
   sai exit 0 — a tela só não entra no bundle (0 chunks contra 1). A prova é o manifest.
3. **Prefixo ≠ existência.** `../../Financeiro/...` resolve para um path que *começa* com a raiz
   movida e mesmo assim não existe lá. Meu primeiro script usou `startsWith` e reescreveu 0.
4. **Um comprimento não é a família.** Corrigi `../../../Financeiro` e o `../../Financeiro` passou
   batido — só o build acusou (§5 2026-08-03).
5. **Reescrita sem âncora come o vizinho.** O re-key de baselines gerou
   `Modules/PG/Modules/PG/...` num docblock (§5 2026-08-02). Detectado por varredura de
   `Modules/[A-Za-z]+/Modules/` e corrigido.

O **total** do `pages-colisao` foi o canário do item 1: caiu de 445 para 438 quando um regex ficou
com o casing velho — as 7 telas sumiram do índice **sem erro nenhum**.

## O que ficou no repo

- **Piloto migrado:** `Pages/Settings/**` → `Modules/PaymentGateway/Resources/js/Pages/Settings/**`
- **Resolver de duas raízes** em `app.tsx` + `ssr.tsx`, com normalização de chave
- **`pages-colisao.mjs`** — gate novo (selftest 5 asserts + `--check`), nasce advisory
- **`module-surface --namespaces`** — o mapa módulo↔Pages vira derivado dos renders reais
- **UC-4** no `CoworkBundleIntegralTest` — cobre o glob de módulos nas duas pontas
- **RUNBOOK** com a receita e as armadilhas · errata estendida em `.claude/rules/pages.md`

## Provas

```
build real:        exit 0 · chunk CnabRetorno presente · manifest aponta pro path do módulo
controle negativo: sem o glob → exit 0 e 0 chunks (build verde não prova descoberta)
pages-colisao:     selftest 5/5 · check exit 0 · 445 páginas (7 no módulo) = total preservado
module-surface:    --all --check exit 0 · --namespaces exit 0
suites node:       93/93
```

## Fica em aberto (decisão [W])

- **`Site`** (`Cms`×6, core×2, `Superadmin`×1) e **`ads`** (`Forja`×4, `KB`×1) são **multi-dono** —
  a quem pertencem não é decisão do script.
- **Etapa 2** (renomear namespace `Settings` → `PaymentGateway`) fica para PR separado, como
  recomendado: se algo quebrar, sabe-se qual das duas causou.
- **Ondas seguintes:** `Whatsapp/Atendimento` (38 arquivos) e `Forja/team-mcp` (23).
