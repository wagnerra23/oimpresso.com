---
date: "2026-09-13"
topic: "ds-atomos thread 02 — KpiCard variant=filter aditivo, e o D-KPI-LABEL que não sobreviveu à medição"
authors: ["C"]
prs: [7251]
---

# ds-atomos · thread 02 — `KpiCard` `variant="filter"`

Execução da thread **02** do playbook `ds-atomos`, recuperado de `4f51a9ec78^` (a pasta
`prototipo-ui/design-docs/**` foi apagada do `main` pela #7224 / ADR 0397 D5).

## O que foi entregue

`resources/js/Components/shared/KpiCard.tsx` ganhou `variant="filter"` + `filterTone`, estritamente
aditivos, e `tests/js/kpicard-variant-filter.test.tsx` (24 casos) trava a guarda. Nenhuma tela
adotou a variante — adoção é outra onda.

## Três correções ao playbook, todas medidas

O playbook avisa que sha diferente na abertura obriga a remedir. O `base` dele era
`2b4a3ec3b48a` (arquivo em `670b3f645b9b`); hoje o arquivo está em `1a9a4200`. Remedi, e três
números não sobreviveram:

**1 · O raio de explosão é 40, não 3.** O playbook declarava Backup + Financeiro/Advisor +
Financeiro/Unificado (e admitia ser piso, varredura parcial de 360/928). Contado hoje:
`git grep -l "shared/KpiCard" origin/main -- '*.tsx' '*.ts'` → **40 de 40**, rc=0, em 4 módulos
(`Forja`, `KB`, `Officeimpresso`, `Whatsapp`) + core. Sem barrel em `Components/shared/`, então
todo import é direto por path e a varredura fecha.

**2 · O `D-KPI-LABEL` não existe.** O playbook o registra como conflito aberto na fila do [W]:
label do tile em "13.3px/400 em accent `oklch(0.70 0.15 295)`" contra a ADR 0110 (11px/600
uppercase muted). Medido, **nenhuma fonte do repo produz o 13.3/400 em accent**:

| fonte | label |
|---|---|
| âncora `KpiFilterTile` (`design-system/components/KpiCard/KpiCard.jsx`) | `--fs-1` (10.5px) · 600 · uppercase · `--color-muted-foreground` |
| fallback CSS legado (`cowork/Wagner/ponto-page.css:50 .pt-kpi small`) | 9.5px · uppercase · `--text-dim` |
| ADR 0110 (canon do repo, documentado no próprio componente) | 11px · 600 · uppercase · muted |

A âncora **concorda** com a ADR. E o "accent" ainda esbarraria em `cockpit.css:42-45`, que
registra que `--accent` é **reescrito pelo `AppShellV2`** — `--color-primary` é o roxo estável.
O label ficou no canon, com um caso de teste travando.

**3 · O alvo delega, não descreve.** O playbook aponta `ponto-ui.jsx :: Kpi` como alvo de layout.
Lido: ele **delega** ao DS (`<KpiCard variant="filter" … tone={TOM_KPI_FILTRO[tom]} />`). Ou seja
o alvo real é o `KpiFilterTile`, e o "13.3/400 accent" provavelmente descrevia um render do CSS
antigo, não o ramo que roda.

## Divergência declarada: `filterTone` separado × `tone` compartilhado

A âncora **reusa o mesmo prop `tone`** para os dois vocabulários — e o DS do protótipo carrega os
dois enums declarados contra o mesmo campo (`KpiCard.d.ts` = `default|success|warning|danger|info`
× `KpiFilterCard.d.ts` = `primary|amber|rose|emerald|violet`). Segui o playbook (`filterTone`
próprio) porque ampliar `tone` mudaria o tipo público para os 40 consumidores, e o playbook manda
parar se o encaixe exigir mexer em `tone`. Registrado no docblock.

## Cores — token por hue, sem cor crua e sem token novo

`primary`→`--color-primary` (Δ0) · `amber`→`--color-warning` (Δ5) · `rose`→`--color-destructive`
(Δ2) · `emerald`→`--color-success` (Δ7) · `violet`→`--color-primary` (Δ0).

**`violet` e `primary` colapsam.** A âncora define os dois em hue 295 e o repo não tem roxo
secundário no `@theme`. `--stage-violet` (288) existe mas vive escopado em `.cockpit`
(`_generated-cockpit-*.css`) e sumiria em portal Radix — a lápide §5 2026-07-10 é exatamente
sobre isso. Separá-los exige **token novo = decisão [W]**.

## O bite-test mudou duas coisas que eu já tinha escrito

Rodado no CT 100 (`/opt/oimpresso-staging/code`, node v20.20.2, vitest 2.1.9) — nunca local. O
container `oimpresso-staging` tem `node_modules` mas **não tem binário `node`**; o host tem, e o
código é bind em `/opt/oimpresso-staging/code`. Copiei os dois arquivos como **arquivos novos**
temporários (o checkout tem alterações de outra sessão — §5 2026-07-27), rodei, e removi.

**24 passed / 24.** E o bite-test não foi carimbo:

| mutação | mordeu | natureza |
|---|---|---|
| valor default `--fs-7`→`--fs-6` | ✅ 1 failed | `AssertionError` |
| `data-variant` carimbado sempre | ✅ 7 failed | `AssertionError` |
| label do filter vira accent 13.3/400 | ✅ 1 failed | `AssertionError` |
| `variant` em `defaultVariants` **+** `default` não-vazio | ✅ 7 failed | `AssertionError` |
| `variant` em `defaultVariants` (só) | ➖ inerte | — |
| `default: ''` → `'flex-row'` (só) | ➖ inerte | — |
| controle restaurado | 24/24 | — |

**Correção 1:** eu tinha escrito no código que a ausência de `variant` em `defaultVariants` *era*
a guarda. O bite-test mostrou que sozinha ela é **inerte** — a guarda é a conjunção dela com
`default: ''`. Comentário corrigido para dizer isso, com o número.

**Correção 2:** o caso do label caía por `TypeError` (o seletor `span.uppercase` sumia junto com a
mutação que ele existia para pegar). Refeito para localizar pelo texto — falha de natureza errada
não prova nada (§5 2026-09-05).

## Achado fora de escopo — `aria-pressed` ausente (a11y herdado)

O primeiro run reprovou num caso meu, e o errado era **minha expectativa**:
`aria-pressed={selected}` com `selected === undefined` faz o React **omitir** o atributo, então um
`<button>` clicável não se anuncia como toggle. Vem do `main`, não desta variante.

Não consertei, e a razão é a lei da thread: `aria-pressed={!!selected}` passaria a carimbar
`aria-pressed="false"` em quem hoje não tem o atributo — `Pages/Financeiro/Unificado/Index.tsx:1031`
usa `onClick` **sem** `selected`. Mudaria o markup de quem não pediu nada. O comportamento herdado
ficou **pinado** por caso de teste, com o porquê no comentário; o conserto é de quem tocar o eixo
a11y do componente.

## Pendência para o [W] — o `_saida-02.md` não tem endereço

O playbook pede `_saida-NN.md` por thread, e o `placar-indice.mjs` dele deriva o placar desses
arquivos. O endereço sumiu: `design-docs/**` foi apagado pela #7224 e o `cowork-ssot-guard` R3 só
admite `.md` flat em `prototipo-ui/cowork/<dono>/handoffs/`. O recibo desta thread está no corpo
do PR e aqui. **Onde o `_saida-NN.md` passa a morar é decisão [W]** — sem isso o placar do
playbook fica sem fonte, e as threads 01/03 (independentes, ainda abertas) caem no mesmo vão.

## Observação de ambiente, sem causa medida

O checkout compartilhado do CT 100 tinha **8** arquivos sujos quando comecei e **6** quando
terminei. Zero resíduo meu (`git status | grep -i guardtmp` → rc=1), HEAD inalterado
(`755f6de798`), e nenhum dos 6 restantes tem mtime da minha janela (os mais recentes são 16:43 e
11:53; trabalhei às 20:17). Não tenho a lista dos 8 originais — contei sem listar, e essa é a
falha de método. Não atribuo causa.

## Escopo

Prefixo `${SH}/KpiCard.tsx` respeitado; `nao_toca` (`Pages/**`, `Components/ui/**`, `KpiGrid.tsx`)
conferido no `git status` — zero violação. Diff **135+/4−** no componente (as 4 removidas são
linhas que reescrevi; nenhuma do corpo default) + 226 do teste. Threads 01 e 03 seguem livres.
