---
slug: 0402-denominador-de-tela-inclui-pages-de-modulo
number: 402
title: "Denominador de tela inclui as Pages de modulo (Modules/*/Resources/js/Pages)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-09-16"
module: governance
quarter: 2026-Q3
tags: [governanca, gates, tela, charter, casos, cobertura, modulos]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0290-fidelity-lock-v0-recusado
pii: false
review_triggers: []
---

# ADR 0402 - o denominador de tela ignora as Pages de modulo

## Contexto

Telas Inertia vivem em **dois** lugares neste repo. As reguas de cobertura contam **um**.

### Medido (2026-09-16)

| Medicao | Resultado |
|---|---|
| telas em `resources/js/Pages/**/*.tsx` | **225** |
| telas em `Modules/*/Resources/js/Pages/**/*.tsx` | **37** |
| denominador do `casos-coverage-guard` | **220** |
| denominador do `screen-coverage:report` | **220** |
| telas de modulo cobradas pelo `casos-gate` | **0** |
| das 37 de modulo: **com charter** | **37 (100%)** |
| das 37 de modulo: com `casos.md` | **14** |
| arquivos em `scripts/` + `.github/workflows/` que citam `resources/js/Pages` | **165** |
| destes, que tambem citam o caminho de modulo | **40** |

Os 7 modulos com tela propria: **Forja (31 arquivos)**, Whatsapp (26), Superadmin (8),
PaymentGateway (7), Cms (4), Officeimpresso (3), KB (1) - contagem de arquivos `.tsx`,
inclusive `_components`; as **37** acima ja excluem componentes e testes.

### Nao e exclusao deliberada - e omissao

Nenhuma ADR trata do assunto. E o canon **manda** criar tela de modulo la:
[`CODE_NOTES.recusados-canon.md`](../reference/prototipo-ui/CODE_NOTES.recusados-canon.md)
prescreve, repetidamente, `criar-tela.mjs <Mod/Tela> <PT-0X>` com destino
`Modules/Cms/Resources/js/Pages/Admin/Content/Index.charter.md` e
`Modules/Connector/Resources/js/Pages/Api/Index.charter.md`.

O sinal mais forte esta nos proprios artefatos: **37 de 37 tem charter**. Alguem fez o
trabalho de trata-las como telas de primeira classe. O que falta e a regua conta-las.

### Como apareceu

Ao regenerar os 9 `proto-baseline`, o `--gerar` recusou `team-mcp/Forja/Cockpit` com
*"charter sem tela viva .tsx"*. A tela **existe** - em
`Modules/Forja/Resources/js/Pages/team-mcp/Forja/Cockpit.tsx`. O mecanismo deriva destino
so de `resources/js/Pages/`.

⚠️ **Correcao de um numero que circulou antes desta ADR:** a primeira contagem falou em
"80 telas". Aquilo incluia `_components/**` e `*.test.tsx`. O numero de TELAS e **37**.

## Decisao

**E1 - o denominador de tela e a UNIAO dos dois caminhos.** Toda regua que responde
"quantas telas existem" passa a contar `resources/js/Pages/**` **e**
`Modules/*/Resources/js/Pages/**`, excluidos `_components/**` e `*.test.tsx`.

**E2 - entrada FORWARD-ONLY e grandfathered.** As **23** telas de modulo sem `casos.md`
entram como **divida herdada no baseline**, nunca como violacao nova. O `casos-gate` e
**required**: por-las no denominador sem grandfather pintaria o CI de vermelho por trabalho
de terceiros, que e a lapide §5 2026-07-12 (tocar legado acorda gate diff-aware) e a regra
"nasce advisory e forward-only" (ADR 0275).

**E3 - a regra de precedencia nao muda.** Charter, casos e teste das telas de modulo
seguem morando **ao lado do `.tsx`**, dentro do modulo. Esta ADR nao move arquivo.

**E4 - fora de escopo, declarado:** esta decisao **nao** promove gate a required, **nao**
migra tela entre caminhos e **nao** exige `casos.md` das 23 num prazo. Cada uma dessas e
decisao [W] propria.

## Consequencias

- o numero "220 telas" que o brief e os relatorios repetem passa a **262**, e a diferenca
  deixa de ser invisivel;
- as 23 telas de modulo sem `casos.md` ficam **visiveis como divida**, em vez de ausentes
  do denominador;
- o `render-proto-baseline --gerar` deixa de precisar de `--out` manual para tela de modulo;
- reguas que hoje medem so um universo passam a declarar qual medem.

## Residuo declarado

**Nao medi quantos dos 125 arquivos que citam apenas `resources/js/Pages` de fato PRECISAM
ver o outro universo.** O grep mede **mencao**, nao cobertura - varios desses 125 leem
charter, nao arvore de telas, e para eles o caminho e irrelevante. Transformar 125 em lista
de trabalho exigiria classificar um a um. **O que esta medido e o efeito de ponta:** dois
denominadores (`casos-gate`, `screen-coverage`) dizem 220 quando o repo tem 262, e o
`casos-gate` cobra zero das 37.

## Analise previa (pre-adr-introspect)

- **Pattern canon similar:** nenhum. `rg 'Resources/js/Pages' memory/decisions/` = zero.
- **Quem ja ve os dois:** `ancora.mjs`, `detectar-telas.mjs`, `design-diff-lote.mjs`,
  `design-spec-gen.mjs` e o `design-memory-gate.yml` **ja citam** o caminho de modulo - a
  extensao segue um padrao existente, nao inventa um.
- **Decisao pos-introspeccao:** ESTENDER o denominador dos donos existentes
  (`casos-coverage-guard`, `screen-coverage-map`), **nunca** abrir regua paralela (LC-19).
- **Descartado:** migrar as 37 para `resources/js/Pages/` - contraria o canon que manda
  cria-las no modulo, e seria mover 37 telas vivas para satisfazer um glob.
