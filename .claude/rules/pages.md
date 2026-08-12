---
paths:
  - "resources/js/Pages/**/*.tsx"
---

# Rule path-scoped — `resources/js/Pages/**/*.tsx`

> Carrega quando Claude lê/edita página Inertia React. Complementa skills Tier A `mwart-process`, `mwart-comparative`, `charter-first`.
> **Fonte de design (ler 1º):** [INDEX-DESIGN-MEMORIAS.md §0](../../memory/requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md) — protótipo Cowork + Design System + charter são fonte; **Figma/Notion/screenshot NÃO são** (salvo Wagner dizer "figma" explícito; bloqueado por `block-figma-without-optin`). [ADR 0299](../../memory/decisions/0299-figma-nao-e-fonte-de-design.md).

## Onde as Pages vivem é CONVENÇÃO DO PROJETO — não imposição do Inertia

O `resolve` do `createInertiaApp` é **callback arbitrário**: o Inertia entrega o nome da página e
aceita qualquer componente de volta. Quem restringe a `resources/js/Pages/**/*.tsx` somos nós, em
**dois** globs mantidos em sincronia **à mão** — [`app.tsx`](../../resources/js/app.tsx) (client)
e [`ssr.tsx`](../../resources/js/ssr.tsx) (SSR). Mexeu num, mexa no outro.

## Duas raízes: uma tela pode morar no módulo dono (desde 2026-08-12)

Cada ponta declara **dois** globs, e o segundo é `../../Modules/*/Resources/js/Pages/**/*.tsx`.
Uma tela pode viver no núcleo **ou** dentro do módulo que a serve — decisão [W]: *"eles têm que
ficar nos seus respectivos módulos e o Inertia tem que achar e o Vite tem que compilar"*.

**O namespace não muda com o local do arquivo.** A chave do glob de módulo é normalizada para o
mesmo `./Pages/<Namespace>/…`, então `Inertia::render('Settings/PaymentGateways/Index')` resolve
igual esteja a tela onde estiver — **nenhum dos 232 call-sites muda ao migrar**.

Três coisas que só apareceram testando o ciclo completo, e que te economizam a mesma hora:

| Armadilha | O que acontece | Regra |
|---|---|---|
| **Casing** | a convenção nWidart aqui é `Resources/` **maiúsculo** (711 arquivos contra 12) e o glob do Vite é case-**sensitive**. Com `resources/` o mapa sai **vazio em silêncio** — e no Windows o `mkdir` funde os dois, então só o CI Linux acusa | o git é a autoridade de casing: confira com `git ls-files`, não com `ls` |
| **Colisão** | duas fontes na mesma chave: o build sai **exit 0**, uma vence e a outra **some sem erro** | [`pages-colisao.mjs`](../../scripts/governance/pages-colisao.mjs) `--check` barra no CI |
| **Build verde não prova nada** | sem o glob de módulos o build **também** sai exit 0 — a tela apenas não entra no bundle (medido: 0 chunks contra 1) | a prova é o **manifest**, não o exit code |

Ao migrar uma pasta: `git mv` → reescreva imports relativos que saem da área (o critério é
**existência do alvo**, não prefixo de string) → re-keye os baselines path-keyed → regenere
`module-surface --write` → rode `pages-colisao --check`. Receita completa no
[RUNBOOK](../../memory/requisitos/_DesignSystem/RUNBOOK-migrar-pages-para-modulo.md).

A restrição é **extensão + path, ambas escolhidas**: `.jsx` dentro de `Pages/` também não é
resolvido — é assim que `Pages/Financeiro/_cowork-bundle/` (10 `.jsx`) fica inerte de propósito.
O `describe('Cowork Bundle — discovery Inertia NÃO pega .jsx…')` em
[`CoworkBundleIntegralTest.php:161`](../../Modules/Financeiro/Tests/Feature/CoworkBundleIntegralTest.php)
crava a string exata e asserta `not->toContain('./Pages/**/*.jsx')` — ninguém fixa por teste o que
o framework impõe. Ele roda na lane `financeiro-pest` (MySQL real).

> ⚠️ **Errata datada (2026-08-12).** O session log
> [`2026-05-15-wave3-b6-repair.md:26`](../../memory/sessions/2026-05-15-wave3-b6-repair.md) afirma que
> *"Inertia resolve global resources path"*, como se o local fosse imposição do framework. **É falso**,
> e em 2026-08-12 um agente repetiu isso ao [W] como fato de arquitetura por ter lido canon sem medir.
> O session log é append-only e **fica como está** — a correção mora aqui, onde se lê antes de editar
> `.tsx`. Se você quer mover Pages pra dentro de `Modules/<X>/`, o obstáculo é decisão de projeto
> (dois globs + o assert acima), não o Inertia.

## MWART canônico — único caminho ([ADR 0104](../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md))

5 fases obrigatórias antes de qualquer Edit/Write em `<Tela>.tsx`:

1. **F1** — DISCOVERY (entender Blade legado se migração)
2. **F1.5** — Gate visual + protótipo `prototipo-ui/<modulo>/<tela>/` ([ADR 0107](../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md))
3. **F2** — BACKEND BASELINE com Pest 5+ fixtures do `store()` ANTES de mexer
4. **F3** — FRONTEND (este passo) — ler charter `<Tela>.charter.md` ao lado obrigatório
5. **F4** — QA com smoke biz=1 ([ADR 0101](../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md))

**RUNBOOK obrigatório:** Edit em `.tsx` SEM `memory/requisitos/<Modulo>/RUNBOOK-<tela-kebab>.md` existir é BLOQUEADO pelo hook [`block-mwart-violation.mjs`](../hooks/block-mwart-violation.mjs) (enforcement runtime — Node cross-plataforma; o `.ps1` que esta linha citava não existe mais). A cobertura de tela no CI é do `casos-gate` (required, ADR 0264) — o antigo `mwart-gate.yml` foi deletado na ADR 0271 onda 2 (era soft/teatro).

> ⚠️ **Não há escape mecânico.** Esta linha já anunciou `/mwart-override <razão>` como se fosse bypass
> do hook. Não é — medido em 2026-08-08 (§5 [`proibicoes.md`](../../memory/proibicoes.md)): zero
> `process.env`, zero leitura de marcador, e a única saída do veto é `process.exit(2)`. **Bloqueou?
> Crie o RUNBOOK** (`/cockpit-runbook`), ou leve a exceção ao [W]: o `/mwart-override` existe como
> **registro humano no PR** (vira ADR per-tela `lifecycle: historical`) — é exceção de *processo*,
> nunca comando que o hook honre. A mensagem do próprio hook é o dono desse texto; **leia o que ela
> disser**, não o que esta rule lembra dela.

## Loop Cowork ↔ Claude Code formalizado ([ADR 0114](../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md))

Skill `mwart-comparative V4` orquestra Claude Design plugin Anthropic (design-critique + design-system + design-handoff + ux-copy + accessibility-review + research-synthesis). 15 dimensões. Wagner aprova **SCREENSHOT** (não tabela markdown).

## Inertia::defer DEFAULT em props caras (Tier 0 desde 2026-05-15)

[RUNBOOK-inertia-defer-pattern.md](../../memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md): toda prop com `paginate()`, `count()`, `with()` eager-load, Service DB, subquery scalar, HTTP externo **DEVE** ser `Inertia::defer(fn () => $this->buildXxxPayload(...))`. Frontend wrap em `<Deferred data="..." fallback={skeleton}>`. Validado D-14: 300ms → 50ms.

## Anti-padrões F3 catalogados

Antes de Edit/Write em `<Tela>.tsx` ler [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) — 6 meta-anti-padrões + 15 técnicos catalogados sessão 2026-05-09 batch Financeiro rejeitado.

## Skills relacionadas

`mwart-process` (Tier A) · `mwart-comparative` (Tier A) · `charter-first` (Tier A) · `inertia-defer-default` (Tier B) · `migracao-blade-react` (Tier B)
