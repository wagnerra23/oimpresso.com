---
title: "RUNBOOK — migrar Pages para dentro do módulo dono"
name: "RUNBOOK — migrar Pages para dentro do módulo dono"
description: "Receita reproduzível para mover telas Inertia de resources/js/Pages/<Ns> para Modules/<X>/Resources/js/Pages/<Ns>, com as armadilhas medidas no piloto PaymentGateway."
type: reference
authority: canonical
lifecycle: ativo
owner: W
last_validated: "2026-08-12"
related_adrs: ["0104-processo-mwart-canonico-unico-caminho", "0256-knowledge-survival-meia-vida-catraca-sentinela", "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes"]
---

# 🚚 RUNBOOK — migrar Pages para dentro do módulo dono

> **Decisão [W] (2026-08-12):** *"eles têm que ficar nos seus respectivos módulos e o Inertia tem
> que achar e o Vite tem que compilar"*. O motivo é ownership: hoje quem procura "o frontend do
> módulo X" não acha pelo nome — `Modules/Whatsapp` renderiza 9 das 12 telas sob
> `Pages/Atendimento/`, e o inventário do módulo mostrava **3 telas de 29**.

## O que torna isso possível

Onde as Pages vivem é **convenção nossa**, não imposição do Inertia — o `resolve` do
`createInertiaApp` é callback arbitrário ([`.claude/rules/pages.md`](../../../.claude/rules/pages.md)).
Cada ponta (`app.tsx` client, `ssr.tsx` SSR) declara **dois** globs e normaliza a chave do módulo
para o namespace do núcleo.

**Consequência que define o desenho:** o namespace **não** muda com o local do arquivo, logo
**nenhum dos 232 `Inertia::render(...)` muda ao migrar**. Mover é operação de arquivo + baseline,
não de call-site.

## Receita (ordem importa)

```bash
MOD=PaymentGateway; NS=Settings          # ajuste os dois
```

### 0. Pré-requisito — eliminar imports que cruzam a fronteira

Imports relativos que **saem** da pasta quebram ao mover. Meça a **família inteira** (qualquer
número de `../`), nunca um comprimento — no piloto o `../../../` foi corrigido e o `../../` passou
batido, e só o build acusou.

O critério correto é **existência do alvo**, não prefixo de string: `../../Financeiro/...` a partir
de `Pages/Settings/PaymentGateways/` resolve para um path que *começa* com a raiz movida e mesmo
assim não existe lá. Resolva cada import e, se o alvo real está no núcleo, troque por `@/Pages/...`
(alias absoluto — imune ao move; 1.785 imports do repo já usam isso).

### 1. Mover, respeitando o casing

```bash
git mv resources/js/Pages/$NS Modules/$MOD/Resources/js/Pages/$NS
```

⚠️ **`Resources` MAIÚSCULO.** É a convenção nWidart deste repo (**711** arquivos contra 12) e o
glob do Vite é case-**sensitive**. No Windows o `mkdir .../resources/...` **funde** com o
`Resources/` existente e o git registra o casing errado: funciona local, quebra no CI Linux.
A autoridade é o git, não o filesystem:

```bash
git ls-files "Modules/$MOD/*esources/js/Pages/**" | head -3
```

Rename só de casing exige dois passos (`git mv X __tmp && git mv __tmp Y`).

### 2. Reescrever os imports que sobraram

Rode o resolvedor por existência (passo 0) **de novo**, agora sobre a área já movida.

### 3. Re-keyar os baselines path-keyed

Substituição **ancorada no path completo** — substring solta come informação vizinha
(§5 2026-08-02). No piloto isso gerou `Modules/PG/Modules/PG/...` num docblock; o controle é
procurar `Modules/[A-Za-z]+/Modules/` depois de rodar.

Baselines afetados no piloto (68 referências): `config/eslint-baseline.json`,
`config/ui-lint-baseline.json`, `config/pageheader-shared-baseline.json`,
`scripts/layout-primitives-baseline.json`, `memory/governance/scorecards/screens/*.yaml`.

### 4. Regenerar e validar

```bash
node scripts/governance/module-surface.mjs --all --write
node scripts/governance/pages-colisao.mjs --check
node scripts/governance/module-surface.mjs --namespaces --check
npx vite build --config vite.inertia.config.mjs
```

## Como provar que funcionou (build verde NÃO é prova)

Medido no piloto: **sem** o glob de módulos o build **também** sai exit 0 — a tela apenas não entra
no bundle. O exit code não distingue "compilou" de "ignorou".

| Prova | Comando | Esperado |
|---|---|---|
| a tela entrou no bundle | `rg -o "Modules/$MOD/Resources/js/Pages/[^\"]*\.tsx" public/build-inertia/manifest.json` | os paths do módulo |
| o chunk existe | `ls public/build-inertia/assets/ \| rg -i <NomeDaTela>` | 1 chunk |
| nenhuma colisão | `node scripts/governance/pages-colisao.mjs --check` | exit 0, total **igual** ao de antes |

⚠️ **O total do `pages-colisao` é o canário.** No piloto ele caiu de 445 para 438 quando um regex
ficou com o casing velho — as 7 telas sumiram do índice **sem erro nenhum**. Compare o total antes
e depois; um detector que para de ver não avisa que parou.

## Armadilhas catalogadas (todas medidas no piloto, 2026-08-12)

1. **Colisão é silenciosa.** Duas fontes na mesma chave → build exit 0, uma vence, a outra some.
   O gate `pages-colisao` existe por isso.
2. **Casing quebra só no CI.** Windows é case-insensitive; Linux não.
3. **Prefixo de string ≠ existência** ao decidir se um import saiu da área.
4. **Um comprimento de `../` corrigido não é a família toda.**
5. **Reescrita sem âncora come o vizinho.**

## Estado da migração

| Módulo | Namespace | Estado |
|---|---|---|
| PaymentGateway | `Settings` | ✅ migrado (piloto, 12 arquivos) |
| Whatsapp | `Atendimento` | ⏳ 38 arquivos — a maior dívida |
| Forja | `team-mcp` | ⏳ 23 arquivos |
| — | `Site`, `ads` | ⛔ **multi-dono**: a quem pertencem é decisão [W], não do script |

Ordem sugerida: um módulo por PR, sempre com o total do `pages-colisao` conferido antes e depois.
