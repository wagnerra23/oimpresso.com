---
date: "2026-08-12"
slug: arquitetura-react-modulos-e-as-3-claims-derrubadas
hour: "16:17 UTC"
topic: "Onde mora o React dos módulos — 2 adversários derrubaram 3 claims minhas, e o conserto que sobrou foi de doc"
authors: [C, W]
prs: [5673]
us: []
tldr: "[W] perguntou onde ficam os React dos módulos e relatou bagunça. Eu respondi 'arquitetura respeitada' medindo 82 de 527 arquivos. Dois adversários refutaram 3 claims; o conserto real foi components.md parar de afirmar enforcement e de repetir número alheio."
outcomes:
  - "PR #5673 mergeado — components.md aponta pro dono do 'required' e pro guard, em vez de afirmar e enumerar"
  - "3 claims minhas REFUTADAS por medição adversarial — a pior repetia canon errado sem medir"
  - "6 chips abertos e TODOS iniciados por [W] em sessões frescas"
  - "whats-active se declarou CEGO (ingest sem heartbeat) em vez de reportar 'nada ativo'"
---

# Onde mora o React dos módulos — e as 3 claims que caíram

## O pedido

[W]: *"Onde ficam localizados os react dos módulos? Tá tudo uma bagunça? Tem regras? Como deveria ser?"*,
depois *"a arquitetura foi respeitada?"* e *"notei uma certa bagunça nos arquivos, sem lógicas claras de
onde os arquivos são permitidos e construídos"*.

Respondi **"não está uma bagunça"**. [W] pediu adversário antes do conserto. Foi a decisão certa.

## As 3 claims que os adversários derrubaram

Dois agentes read-only, mandato explícito de **refutar** — um pelo eixo Laravel modular, outro pelo
React/Inertia.

**1. "A arquitetura foi respeitada" — PARCIAL, por DENOMINADOR errado.**
Medi `resources/js/Components/` (**82** arquivos, a pasta que tem gate) e concluí sobre a árvore
inteira (**527** `.tsx`). O lado que [W] usa — `Pages/`, **445** — mal olhei. Frase honesta:
*a pasta global está limpa e enforçada; o bagunçado é o lado das telas, e lá o gate olha uma coisa só.*

**2. "É imposição do Inertia — glob único sobre `./Pages/**`" — REFUTADA, e é a pior.**
São **dois** globs (`app.tsx:104`, `ssr.tsx:17`), sincronizados à mão; o `resolve` do
`createInertiaApp` é callback arbitrário, logo o glob é **escolha do projeto**; e existe um Pest
(`Modules/Financeiro/Tests/Feature/CoworkBundleIntegralTest.php`) que **crava a string exata** —
ninguém fixa por teste o que o framework impõe.
⚠️ **A imprecisão já estava em canon** (`memory/sessions/2026-05-15-wave3-b6-repair.md:26`:
*"Inertia resolve global resources path"*). **Repeti canon sem medir** — LC-08 na forma mais barata
de evitar.

**3. "O único problema é o doc" — REFUTADA.** Havia **18 build configs órfãos** dentro de `Modules/`
(15 `webpack.mix.js` + 3 `vite.config.js`, nenhum invocado por script; o do Financeiro ainda com o
placeholder `$STUDLY_NAME$` do scaffold). Isso contradiz frontalmente a claim 2 — o repo tem 18
declarações de build apontando pra dentro de `Modules/`.

**E um erro de unidade meu:** falei *"197 `_components` contra 4 legadas"* — comparando **arquivos com
pastas**. O reproduzível é **26 pastas contra 4** (87%) ou **197 arquivos contra 7** (96,6%).

## O que a medição mostrou de verdade

O frontend de módulo está **partido em dois**, e é daí que vem a sensação de bagunça:

| | |
|---|---|
| Inertia/React | `resources/js/Pages/<Modulo>/` — **0** dentro de `Modules/` (confirmado por 4 métodos) |
| Blade | `Modules/<Mod>/Resources/views/` — **430** arquivos, **231** endpoints servidos |

`return view(` **263** × `Inertia::render` **213** dentro de `Modules/`. Contar só `.tsx` mede **a
metade que migrou** e chama de arquitetura. `Modules/Crm` tem 47 endpoints Blade e **zero** Inertia.

⚠️ **Mas não é caos: é dívida COM FILA.** `npm run migracao:report` já ordena (Crm 47 · Essentials 38 ·
Repair 28 · Superadmin 25). Não acuse de bagunça o que tem dono e ordem — os adversários também
inocentaram 4 suspeitos (`Pages/ads/` = congelamento deliberado de URL com teste de contrato;
`_cowork-bundle` = inerte de propósito, guardado por Pest; `Pages/Modules/Index.tsx`; Pages sem
módulo = core UltimatePOS).

## O que entrou (PR #5673)

`.claude/rules/components.md` parou de fazer três coisas:

1. **Afirmar enforcement em presente** (*"gate CI valida"*, 6 catracas *"ativas"*) — medido: união
   `classic_protection ∪ rulesets` = **45 contexts**, nenhum contém "component"; 4 das 6 não
   bloqueiam. Agora aponta pro dono (`governance/required-checks-baseline.json`) e diz que **rodar ≠
   bloquear**, citando a [ADR 0314](../decisions/0314-poda-gates-onda-2-lei-fusoes.md) que torna isso
   deliberado. (LC-10.)
2. **Repetir número alheio** (*"contador 104"*) — baseline dizia **97**, árvore **90**. Aponta pro
   baseline e manda não decorar.
3. **Descrever 4 destinos** quando o guard autoriza 8+4 — causa direta do *"sem lógicas claras"*. A
   correção **não foi copiar a lista** (drift igual): foi explicar a REGRA (pasta de topo nova exige
   justificativa no `ALLOWED_DIRS` no mesmo PR) e deixar a enumeração no guard.

Ganhou seção **"o que esta regra NÃO cobre"**: os prefixos `_lib`/`_show`/`_drawer`/`_shared`/`_form`
existem sem regra, import cross-módulo acontece. Descrição, não convenção nova — isso é decisão [W].

## Chips abertos — todos iniciados por [W]

`Repair→Sells` Tier-0 (único que toca preço) · 18 build configs órfãos · 20 componentes sem
importador · convenção dos 7 prefixos (decisão [W]) · canon errado do Inertia + grandfather LC-15 ·
nome módulo↔Pages.

Cada um leva comando de re-medição e as armadilhas já mordidas. **Não abri chip pros 231 Blade** —
tem dono e fila; chip ali duplicaria processo (LC-19).

## Estado MCP no momento do fechamento

Diferente do handoff de ontem, o MCP **respondeu**: `brief-fetch` OK (Brief #504), `sessions-recent`
OK. Mas ⚠️ **`whats-active` se declarou CEGO** — *"pipeline de ingest SEM heartbeat fresco (fresh=0 ·
stale=0 · dead=95) — posso estar CEGO, NÃO assuma escopo livre"*. Ele reportou "nenhuma sessão em 3h"
e **estava errado**: há **6 sessões** de [W] rodando agora (chips). O instrumento avisou que não
conseguiu medir em vez de vender o vazio como saúde — é o comportamento que o §5 2026-07-29 exige.
`sessions-recent` também parece atrasado (devolve logs de junho/julho indexados hoje; o meu de ontem
não aparece).

Nada foi registrado em `mcp_tasks`.

## Aberto

- Os 6 chips (em curso).
- **O achado do README chegou tarde e não está no chip iniciado**: `Pages/Financeiro/_cowork-bundle/README.md:11`
  afirma que *"underscore prefix = excluído do auto-discovery"*. **Falso** — o glob é `*.tsx`, então
  `.tsx` sob pasta `_` **entra**; o que exclui aqueles 10 arquivos é serem `.jsx`. Quem criar
  `_rascunho/Foo.tsx` achando que está fora vai descobrir que está dentro. Passei o texto a [W] por
  relay (tentei substituir o chip, mas ele já tinha sido iniciado e não é retirável).
