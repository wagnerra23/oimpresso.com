---
date: "2026-09-13"
topic: "DS-átomos thread 03 — criar shared/Toolbar.tsx (barra de 3 zonas), portado da fonte canônica do DS"
authors: ["C"]
outcomes:
  - "resources/js/Components/shared/Toolbar.tsx criado (Toolbar + ToolbarSpacer, 2 símbolos — o teto da thread)"
  - "tests/js/toolbar.test.tsx: 13 testes, 2 bite-tests provando que morde"
  - "registry NÃO tocado — medido que o component-registry-check não exige (sem check reverso)"
  - "endereço canônico do recibo _saida-NN.md segue EM ABERTO para o [W]"
---

# DS-átomos · thread 03 — `shared/Toolbar.tsx`

## O pedido

Executar a thread 03 do playbook `ds-atomos` (apagado do `main` pela PR #7224, commit `4f51a9ec78`, ADR 0397 D5; recuperado por `git show 4f51a9ec78^:<path>`). É a única das três threads do pacote que **cria** arquivo.

## O que mudou de fato (2 arquivos, ambos novos)

| arquivo | o quê |
|---|---|
| `resources/js/Components/shared/Toolbar.tsx` | `Toolbar` + `ToolbarSpacer` — barra de 3 zonas (`left`/`center`/`right`) |
| `tests/js/toolbar.test.tsx` | 13 testes cobrindo as provas D-1…D-6 do playbook |

`PageFilters.tsx` **0 diff** (a guarda declarada da thread). Nenhum arquivo em `Pages/**` tocado.

## O achado que mudou o trabalho: a anatomia não foi inventada, foi PORTADA

O playbook declara o alvo medido (`gap 8px · pad 9px 12px · align center · wrap · bordered default`) mas o CSS de fallback do protótipo (`.pt-toolbar`, `ponto-page.css:14`) diz **outra coisa** (`gap 12px · pad 12px 14px · align flex-end`). Antes de fixar qualquer número, fui atrás de onde a medição tinha saído — e a fonte existe, versionada:

- **`prototipo-ui/design-system/components/Toolbar/Toolbar.jsx`** + **`Toolbar.d.ts`** — a peça canônica do DS, com `left`/`center`/`right`/`sticky`/`dense`/`tone`/`bordered`.
- **`prototipo-ui/cowork/Wagner/ponto-ui.jsx:299`** (`Barra`) — o consumidor que **já espera este contrato**: chama `<Toolbar bordered={false}>` dentro da própria moldura, e cai no `.pt-toolbar` só como fallback defensivo quando o DS não sobe.
- **`prototipo-ui/cowork/Wagner/cowork-pele-paralela.mjs:23,83`** — o repo já declarava em texto que *"o Toolbar do DS é de 3 zonas"* e listava telas com Toolbar local hand-rolado esperando essa peça.

Ou seja: o `.pt-toolbar` não é o alvo, é o que o protótipo usa **na falta** do alvo. Todos os números do bloco C do playbook batem 1:1 com `Toolbar.jsx`, inclusive o spacer (`flex:1` = `1 1 0%`, altura 0 — os 102px que o playbook mediu). O trabalho foi **port**, não autoria.

## Prova anti-LC-19 (não abri paralelo a dono existente)

- `npm run reuse:check "Toolbar"` → **❌ NÃO existe símbolo "Toolbar"**.
- `git grep -il "toolbar"` em `resources/js/Components/` → **2 de 87 arquivos**, ambos falso-positivo: `cockpit/Thread.tsx:268` é a className `composer-toolbar` do compositor de chat; `layout/inline.tsx:11` é menção em comentário. Controle positivo da sonda: `git grep -il "PageFilters"` devolve o arquivo.
- Os 3 vizinhos por nome foram abertos e **não são** a peça: `BulkActionBar` (barra fixa no bottom, condicionada a `selectedCount > 0`), `PeriodBar` (janela de período que navega via router, US-DASH-004), `PageFilters` (chips + grid de campos + "Limpar tudo", com moldura própria). Zero sobreposição de papel.

## A decisão de técnica que eu tomei (e o custo dela)

`resources/js/Components/layout/index.ts` é explícito: *"layout é COMPOSIÇÃO destes primitivos, **nunca** `<div className="flex gap-4">` solto"*. Então o Toolbar **compõe com `Inline`** (ADR 0253) em vez de reimplementar flex — e o comentário do próprio `Inline` já nomeia "toolbars" como caso de uso.

Isso obriga a expressar espaçamento por **token**, e aí aparece a única divergência da fonte:

| item | fonte do DS | aqui | por quê |
|---|---|---|---|
| gap da barra | 8px | `gap={2}` = **8px** | token exato, sem perda |
| padding | 9px 12px | `px-3 py-[9px]` = **9px 12px** | exato; o 9px é arbitrary porque a prova D-5 mede **71px de altura** (9+9+53) e `py-2` daria 69px |
| dense | 6px 10px | `px-2.5 py-1.5` = **6px 10px** | tokens exatos |
| gap **dentro** da zona | 6px | `gap={1}` = **4px** | 6px não é token do repo. Escolhi 4px em vez de 8px para preservar a intenção da fonte (dentro-da-zona mais apertado que entre-zonas), dentro do sistema de tokens |

O gap de zona **não foi exercitado pelo alvo medido**: a 1280px as zonas estavam vazias (o uso real do Ponto põe tudo em `children`), e o único gap que os 6 filhos exercem é o da raiz — 8px, exato. A divergência é de 2px num eixo não medido.

Cores por token semântico do tema gerado (`--color-card`/`--color-muted`/`--color-border`, em `resources/css/tokens/_generated-inertia-theme.css`) — os `--surface`/`--bg-2` da fonte só existem nos bundles Cowork por módulo, não no tema global.

## Adição sobre a fonte: spacer único (prova D-6)

A fonte canônica emite o spacer sempre que não há `center`. Se o consumidor passar o próprio `ToolbarSpacer`, ficariam **dois** — e dois spacers dividem a folga, então o `right` deixa de colar na borda. O playbook exige que não some (D-6), então o componente detecta `ToolbarSpacer` entre os filhos diretos e suprime o seu. Fronteira honesta, escrita no código: **só enxerga filho direto** — spacer dentro de Fragment ou wrapper não é detectado.

Também tornei `label` **obrigatório** (TS): `role="toolbar"` sem nome acessível não se distingue de outra barra na mesma tela. É o passo 3 do bloco 4-ter do playbook.

## Verificação — tudo no CT 100, nada local

Rodado em `/opt/oimpresso-staging/code` (host CT 100, node v20.20.2; o container `oimpresso-staging` não tem `node` no PATH). Os 2 arquivos foram para lá por base64 com **MD5 conferido nos dois lados** e removidos no fim; `git status --short` de lá **antes e depois** mostra os mesmos 6 itens de terceiros, intactos.

| prova | resultado |
|---|---|
| `npx vitest run tests/js/toolbar.test.tsx` | **13 passed (13)** — contagem, não `0 failed` (LC-13) |
| bite-test 1 — supressão do spacer duplo desligada | **1 failed / 12 passed**, `AssertionError: expected (2) to have a length of 1 but got 2` |
| bite-test 2 — `bordered` ignorado | **1 failed / 12 passed**, `expected '…' to contain 'border-b-0'` |
| `npx tsc --noEmit` | 0 erros nos 2 arquivos |
| `npx eslint` | 0 errors (o `.test.tsx` é ignorado pela config — a lane roda `eslint resources/js`) |

As duas falhas são `AssertionError` de contrato, não crash — é o veredito de natureza que §5 2026-09-05 exige de um bite-test.

**O que este teste NÃO prova, e está escrito no cabeçalho dele:** o vitest roda jsdom com `css: false`, então não há layout computado — `getComputedStyle` não resolveria `gap-2` em 8px. Os números do bloco C (1215×71px) são do **runtime**, pelo PROTOCOLO-COMPARACAO-RUNTIME (D2), e não por este arquivo. O que ele prova é contrato de API, árvore de zonas, semântica ARIA e o spacer único — que é comportamento puro.

## Registry: medido que NÃO exige

`governance/design/component-registry.json` **não foi tocado**. O enunciado condicionava a entrada a *"se o `component-registry-check` exigir"*; medido:

- o script não tem check reverso (`grep -qniE "sem entrada|orfao|orphan|nao registrado|missing entry"` → rc=1, com controle positivo `grep -qi "mapped"` → rc=0);
- `node scripts/governance/component-registry-check.mjs --check --strict` → **rc=0**, "registro íntegro", 69 entradas, sem a minha.

A condição não se cumpriu. Registrar o átomo lá seria defensável (o registry é o "Code Connect" do projeto e `.pt-toolbar`/`Barra` é exatamente um bloco de protótipo ganhando receptor), mas é decisão do [W] — e há um PR irmão em voo que pode tocar o mesmo `entries[]`.

## Sessão paralela detectada (thread 02, do mesmo playbook)

Durante a limpeza do CT 100 apareceram, no checkout compartilhado, `resources/js/Components/shared/KpiCard.guardtmp.tsx` e `tests/js/kpicard-variant-filter.guardtmp.test.tsx` — outra sessão executando a **thread 02** (`KpiCard variant="filter"`). Não toquei neles. Não há colisão: o índice do playbook declara 01/02/03 independentes (3 PRs paralelos) e os prefixos são disjuntos. O único ponto de contato possível seria o `component-registry.json` — mais um motivo para eu não ter mexido nele.

## Em aberto para o [W]

1. **O recibo `_saida-03.md` não tem endereço canônico.** O playbook pede `_saida-NN.md` por thread (e o `placar-indice.mjs` deriva o placar dele), mas `scripts/governance/cowork-ssot-guard.mjs` R3 só admite `.md` flat em `prototipo-ui/cowork/<dono>/handoffs/`. O recibo foi para o corpo do PR e para este log. **Onde o `_saida-NN.md` deve morar é decisão sua** — enquanto não houver endereço, o `placar-indice.mjs` não tem de onde derivar o placar das threads.
2. **Registry** — adiciono a entrada do Toolbar (é um `mapped` verificável) ou deixo fora?
3. **`gap` de zona 6px → 4px** — se os 2px importarem, a saída é um token novo nas Fundações, que é ADR, não PR.

## O que esta thread NÃO entrega (está no playbook, e continua valendo)

- **Adoção nas telas**: trocar as barras hand-rolled das 21 Pages do Ponto por este átomo é **outra onda, tela por tela**. Este PR entrega o primitivo, não o consumo — nenhuma tela existente muda de pixel.
- **`ToolbarButton`/`ToolbarSearch`/`ToolbarDivider`**: existem na fonte do DS, mas furam o teto de símbolos da thread. Outra thread.
- **Paridade visual**: nada aqui afirma "igual" ao protótipo. Só o T7 (`design-diff --compare --check` nos dois renders, com prod deployada) afirma isso, e ele não roda daqui.
