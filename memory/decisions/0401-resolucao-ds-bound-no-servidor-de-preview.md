---
slug: 0401-resolucao-ds-bound-no-servidor-de-preview
number: 401
title: "Resolucao do DS bound no servidor de preview - emenda parcial a 0397 D4"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-09-16"
module: governance
quarter: 2026-Q3
tags: [design, cowork, prototipo, design-system, preview, ssot]
supersedes: []
supersedes_partially: [0397-prototipo-minimo-por-dono-e-ds-direto]
superseded_by: []
related:
  - 0397-prototipo-minimo-por-dono-e-ds-direto
  - 0374-emenda-0315-espelho-cowork-e-rota-prevista
  - 0299-figma-nao-e-fonte-de-design
pii: false
review_triggers: []
---

# ADR 0401 - resolucao do DS bound no servidor de preview

## Contexto

A [ADR 0397](0397-prototipo-minimo-por-dono-e-ds-direto.md) **D4** estabeleceu que
`prototipo-ui/design-system/` e a unica copia fisica do Design System, que os payloads
`_ds/**` sao normalizados para la, e que `cowork/Wagner/_ds/` foi removido. Tres dessas
quatro sentencas seguem de pe. A segunda - *"O shell Wagner referencia esse diretorio
diretamente"* - e **descritiva e esta falsa**, e a consequencia dela deixou o preview local
sem mecanismo de resolucao.

### O que foi medido (2026-09-16)

| # | Medicao | Resultado |
|---|---|---|
| 1 | refs no shell do espelho | **3** `_ds/<slug>/` e **0** `../../design-system/` |
| 2 | `cowork-ssot-guard` com o cache `_ds/` presente | **rc=1**, 8 violacoes (R2 + R4 duplicata) |
| 3 | `payloadDependencyGraph` sobre o shell | `complete:false`, 273 missing, **3 deles `_ds/`** |
| 4 | `--preview-ds` | `return` na 1a linha do bloco; 56 linhas inalcancaveis, inclusive o `process.exit(1)` do portao |
| 5 | `render-proto-baseline --check` | **materializa** o cache por conta propria (linhas 447 e 533) |
| 6 | shell **vivo** no Cowork (via `DesignSync`) | carrega os mesmos 3 refs `_ds/<slug>/` |

### A decisao [W] que ja existia, e que ninguem tinha lido

O shell **vivo** documenta a escolha, assinada, **dois meses antes da 0397**:

> `DS VIVO: os tokens de IDENTIDADE (neutros, radius, status, tipo) vem DIRETO do design
> system bound. Linkado, NAO copiado -> nunca mais apodrece com o tempo ([W] 2026-07-10).`

`_ds/<nome-do-projeto-DS>-<projectId>/` e a forma do Claude Design para um projeto
referenciar os assets de **outro** projeto. O projeto de Design System e um tipo proprio
(`PROJECT_TYPE_DESIGN_SYSTEM`, imutavel na criacao) e sua raiz contem exatamente os arquivos
que o shell pede: `_ds_bundle.js`, `colors_and_type.css`, `cockpit_domains.css` e
`assets/fonts/*.woff2`.

### Por que a premissa da D4 morreu em 3 dias

O [#7224](https://github.com/wagnerra23/oimpresso.com/pull/7224) (11/09) reescreveu o shell
do espelho **a mao** para `../../design-system/` e, sobre essa premissa, desligou o
`--preview-ds`. O [#7261](https://github.com/wagnerra23/oimpresso.com/pull/7261) (14/09)
reverteu - editar `prototipo-ui/cowork/**` e ilegitimo por construcao (espelho de leitura,
[ADR 0374](0374-emenda-0315-espelho-cowork-e-rota-prevista.md)) e o import seguinte desfaz.
A premissa caiu; a consequencia ficou.

### As tres saidas, e por que duas caem

- **Materializar o cache `_ds/`** - proibido pela propria **D5**: a medicao 2 mostra o
  `cowork-ssot-guard` em `rc=1` no instante em que o cache existe.
- **Reescrever as refs do shell** (na promocao ou na origem) - contraria a decisao [W] de
  2026-07-10, quebra a fidelidade byte-a-byte que o `--compare` mede, e ja foi tentado e
  revertido.
- **Resolver no servidor de preview** - nao copia byte nenhum, nao edita artefato nenhum,
  e e o que o proprio Claude Design faz em runtime.

## Decisao

**E1 - `_ds/<slug>/` e forma canonica, nao legado.** O shell referencia o DS bound por
decisao [W] de 2026-07-10. Nenhum artefato sob `prototipo-ui/cowork/**` e reescrito para
eliminar essas refs - nem a mao, nem por codemod, nem na promocao do bundle.

**E2 - a resolucao e do servidor de preview.** Todo servidor que serve o espelho mapeia o
prefixo `_ds/<slug>/<path>` para `prototipo-ui/design-system/<path>`. O dono da regra de
caminho continua sendo `dsRuntimeRelPath` - o mapeamento nao e reimplementado por consumidor.

**E3 - o cache fisico segue proibido.** A D5 fica intacta: nenhum `_ds/` materializado sob
`prototipo-ui/cowork/**`. Consumidor que hoje materializa por conta propria passa a resolver
pelo servidor ou a injetar a origem na medicao - nunca a escrever no espelho.

**E4 - o que a D4 mantem.** Sentencas 1, 3 e 4 seguem vigentes: copia fisica unica em
`prototipo-ui/design-system/`, `mirror-snapshot/` removido, payloads `_ds/**` normalizados
para o DS canonico. **Cai apenas a sentenca 2** (*"O shell Wagner referencia esse diretorio
diretamente"*), substituida por: *o shell referencia `_ds/<slug>/`, e o servidor de preview
resolve esse prefixo para o DS canonico*.

## Consequencias

- o preview local volta a renderizar com tokens, tipografia e componentes do DS;
- o espelho permanece byte-identico ao vivo, entao `--compare` e a rotina de frescor seguem
  medindo fidelidade real;
- o `cowork-ssot-guard` deixa de depender de ordem de step para sair verde;
- nenhuma copia fisica nova entra no repositorio.

## Residuo declarado

**Abrir o shell por `file://`, sem servidor, continua sem resolver o DS.** Medido: nenhuma
maquina do repo depende desse caminho (os hits de `file:` no eixo design sao fixture de
selftest). **Nao foi medido** se alguem do time abre o arquivo direto - e a
[ADR 0374](0374-emenda-0315-espelho-cowork-e-rota-prevista.md) justifica o espelho com
*"computadores que nao vao ter acesso ao design dessa maquina e vao trabalhar so com o git"*.
Se esse caso existir, ele **nao** e coberto por esta decisao e exige reabertura - a saida
seria materializacao opt-in, que hoje a D5 proibe.

## Analise previa (pre-adr-introspect)

- **Patterns canon similares:** `dsRuntimeRelPath` (`cowork-mirror-freshness.mjs`) ja e o dono
  da regra `_ds/<slug>/X -> X`; `servirEspelho` (`design-diff-lote.mjs`) ja implementa alias de
  servidor, so que para o prefixo do #7224. **Reusar**, nao criar.
- **Prior art externa:** a tecnica tem nome e tres implementacoes maduras - Storybook
  `staticDirs: [{ from, to }]`, Vite `server.fs.allow` mais public dirs em monorepo, e o
  `Alias` do Apache. A regra recorrente na literatura e *dev = alias/symlink, build = copia*.
- **Decisao pos-introspeccao:** REUSAR (`dsRuntimeRelPath`) e ESTENDER (alias nos servidores).
  Nenhuma dependencia nova.
- **Descartado:** symlink/junction - lapide Tier 0 no Windows (`vendor/` 318MB para 0 em
  2026-05-11; `node_modules` em 2026-07-14).
