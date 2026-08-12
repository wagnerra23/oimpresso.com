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

### 2. Reescrever os imports — nas DUAS direções

**(a) De dentro para fora** — o que foi movido importando o que ficou: rode o resolvedor por
existência (passo 0) de novo, agora sobre a área já movida.

**(b) De fora para dentro** — ⚠️ **o que ficou importando o que saiu.** Esta é a direção que passa
despercebida, porque esses arquivos **não estão no seu diff**. Pior: se o import usa o alias `@/`
(e não `../`), o resolvedor do passo 0 nem olha para ele — o alias aponta para `resources/js`, e o
arquivo simplesmente deixou de existir lá.

```bash
rg --hidden -n "from '@/Pages/$NS/" -g '*.tsx' -g '*.ts' resources Modules
```

Se houver resultados, alguma coisa está errada no **recorte**: um componente compartilhado ficou
do lado errado da fronteira. Na onda `team-mcp` foram 7 imports do mesmo `ForjaHub` — 3 de telas
que ficaram no núcleo e **4 de dentro do próprio módulo**. A causa raiz era o recorte:
`Pages/Forja/**` também é do Forja e tinha ficado para trás. Migrar os dois juntos resolveu, e os
imports viraram relativos dentro do módulo.

Regra: **se o namespace A importa de B e ambos são do mesmo módulo, migre A e B na mesma onda.**

### 3. Re-keyar os baselines path-keyed

Substituição **ancorada no path completo** — substring solta come informação vizinha
(§5 2026-08-02). No piloto isso gerou um path duplicado (`Modules/<Mod>/Modules/<Mod>/…`) num
docblock — repare que o exemplo usa placeholder de propósito: escrever a sigla real de um módulo
que não existe faz o `knowledge-drift` acusar ghost, porque ele casa o **token literal**. O controle é
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

## Namespace COMPARTILHADO é permitido — a chave é por arquivo

A unidade de posse é a **tela**, não a pasta. `Site` e `ads` têm mais de um dono e mesmo assim
migraram, cada tela para o seu módulo, **sem renomear nada** (o nome `ads` está congelado por
[ADR 0363](../../decisions/0363-governance-incorpora-ads-nucleo-sem-receptor.md) — `route('ads.admin.*')`
e permissions Spatie; renomear as revoga *em silêncio*).

```
Modules/Cms/Resources/js/Pages/Site/{Home,Blogs,BlogPost,Page}.tsx
Modules/Superadmin/Resources/js/Pages/Site/Pricing.tsx
resources/js/Pages/Site/{Login,Register}.tsx        ← auth: fica no núcleo, de propósito

Modules/Forja/Resources/js/Pages/ads/Admin/{Projects,ProjectShow,Tools,TeamScopes}.tsx
Modules/KB/Resources/js/Pages/ads/Admin/Graph.tsx
```

Colisão só existe quando **o mesmo arquivo** é declarado duas vezes — é o que `pages-colisao` mede.

## Estado da migração

| Namespace | Dono(s) | Estado |
|---|---|---|
| `Settings` | PaymentGateway | ✅ 12 arquivos (piloto) |
| `Atendimento` | Whatsapp | ✅ 38 arquivos |
| `ads` | Forja (4 telas) + KB (1) | ✅ dividido por dono |
| `Site` | Cms (4) + Superadmin (1) | ✅ dividido; **auth fica no núcleo** |
| `team-mcp` + `Forja` | Forja | ✅ migrados **juntos** — `Pages/Forja` importava `ForjaHub` de `team-mcp` |
| demais | homônimos | ⏳ o namespace já bate com o módulo; migram quando alguém tocar |

**73 de 445 páginas** já moram no módulo dono (2026-08-12). Um módulo por PR, sempre com o total
do `pages-colisao` conferido antes e depois — ele é o canário.
