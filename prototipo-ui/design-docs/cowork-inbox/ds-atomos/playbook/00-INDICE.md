---
modulo: ds-atomos
titulo: Átomos compartilhados — o visual do protótipo, aplicado no primitivo (não na tela)
dono: "[CL]"
base: 2b4a3ec3b48a
---
# DS-átomos · playbook

## Por que isto NÃO é um playbook do Ponto
[W] pediu (2026-09-09): *"deixe o visual igual ao seu"*. Medido no `main` no mesmo dia: o Ponto de produção **já é React e já compõe o DS** (21 Pages, imports `@/Components/ui/*` + `@/Components/shared/*`). Logo a diferença **não é escolha de componente** — é a **anatomia dos átomos**. Mexer tela por tela seria 21 PRs pintando por cima do mesmo primitivo; mexer no primitivo é 3 PRs e as 21 telas herdam.

**Raio de explosão declarado:** `ui/card.tsx` e `shared/KpiCard.tsx` são consumidos fora do Ponto (medido: Backup, Financeiro/Advisor, Financeiro/Unificado; `EmptyState`/`StatusBadge` também em Arquivos, Auditoria, Cliente — varredura **parcial**, 360 de 928 arquivos, então trate como piso, não teto). Daí a lei deste playbook:

> **ADITIVO OU NADA.** Prop nova, variante nova, default **inalterado**. Nenhuma tela existente muda de pixel sem opt-in. Quem furar isto reprova na prova de guarda da própria thread.

## Alvo — medido, não lembrado
Protótipo servido, tema `.cockpit` dark, `getComputedStyle` no elemento, após duas leituras iguais de `querySelectorAll('*').length` (T1 estável nas 10 views: 907–1062 nós).

| átomo | alvo resolvido |
|---|---|
| **Widget** (meu `Card`) | radius **12px** · border **1px `--border`** `oklch(0.34 0.008 240)` · bg **`--surface`** `oklch(0.30 0.008 240)` · shadow `0 1px 2px rgba(0,0,0,.04)` · pad **14px** · título **13.5px/600 `--text`** · badge **11.5px mono `--text-dim`** à direita do título · `note` **abaixo** do título |
| **KPI-filtro** (meu `Kpi` com `onClick`) | **`BUTTON`** + `aria-pressed` · pad **12px** · radius **8px** · gap 12px · h **85px** · placa de ícone · label **13.3px/400 em accent** `oklch(0.70 0.15 295)` · valor **18px/600 `--text` tabular-nums** · grid `gap:10px`, trilha ~194px |
| **Toolbar** (minha `Barra`) | flex · wrap · gap **8px** · pad **9px 12px** · align center · bg `--surface` · moldura no PAI: radius 12px + border 1px `--border` · **a 1280px: 1215×71px, 6 filhos, uma faixa** |
| **tabela densa** | `th` **11px** uppercase `.07em` **`--text-dim`** 600, pad 8px 10px · `td` 12.5px pad 7px 10px, border-bottom `--border`/60% · hover `accent 5%` · linha 65px com sub-linha |

## O alvo NÃO era sagrado — dois defeitos corrigidos aqui antes de virar pedido
Bateria de contraste com **caso de sanidade** (branco sobre `--surface` = **13,62**, plausível):
- `th` em `--text-mute` = **3,18** → falha AA. Trocado por `--text-dim` = **5,49** ✓, e **9,5px → 11px**.
- os outros **24** `color:var(--text-mute)` do `ponto-page.css` tinham o mesmo 3,18 → todos para `--text-dim`.
- label roxo do KPI = **4,85** ✓ passa; **não** foi mexido.

Exportar o alvo anterior seria exportar 3,18 com selo de aprovação (§5-bis). O alvo desta pasta é o **corrigido**.

**Terceiro defeito, achado na verificação e já corrigido (thread 03):** a migração `.pt-toolbar → PtBarra` arrastou 7 `<span className="pt-sp">` do CSS antigo, que passaram a conviver com o spacer do próprio `Toolbar` — dois spacers na mesma barra, 82px de gap fantasma. Removidos (`ponto-page.jsx` 1 · `ponto-telas.jsx` 5 · `ponto-fechamento.jsx` 1) e o bloco C do `03` foi **remedido depois** da limpeza. Lição: migração mecânica preserva lixo que só fazia sentido no regime antigo — e o alvo emitido em cima dele mentiu em 3 números.

**Toda medida deste playbook cita a largura.** O alvo é **1280px** (persona Larissa). A mesma barra a 599px dá 158px/5 faixas — reflow correto, não defeito; medir na janela do preview e chamar de alvo é o erro que este parágrafo evita.

## Threads
| # | thread | prefixo | ficha | veredito |
|---|---|---|---|---|
| **01** | `ui/card.tsx` — `badge` · `note` · `flush` | 1 arquivo | leitura **1.987 B** · escrita ~60 ln · 3 símbolos · 0 decisões | **CABE** |
| **02** | `shared/KpiCard.tsx` — `variant="filter"` | 1 arquivo | leitura **11.135 B** · escrita ~70 ln · 3 símbolos · 0 decisões | **CABE** |
| **03** | `shared/Toolbar.tsx` — CRIAR (3 zonas) | 1 arquivo | leitura **2.764 B** (`PageFilters`, só p/ não duplicar) · escrita ~90 ln · 2 símbolos · 0 | **CABE** |
| 04 | tabela densa | — | — | **BLOQUEADA** por `D-GRADE` |
| 05 | `StatusBadge` kinds | — | 17.674 B **não recortados** | **NÃO MEDIDA** |

**Ordem:** 01 → 02 → 03 são independentes (3 PRs paralelos). 04 só depois de `D-GRADE`. 05 exige eu recortar o `StatusBadge` antes — é minha, não do [CL].

## Frescor — a árvore andou durante a geração
Comecei em `a0db7b0177b8` e as últimas leituras já vieram de `2b4a3ec3b48a`. Os sha por arquivo nas threads são de **`2b4a3ec3b48a`**; sha diferente na abertura ⇒ **remedir antes de escrever** e dizer isso no `_saida`.

## O que este playbook NÃO resolve
- **`D-KPI-LABEL`**: aditivo faz os dois vocabulários coexistirem, mas não diz qual é o canon (label em accent 13.3/400 do bundle × 11px/600 uppercase muted da ADR 0110). Fila de [W].
- **Paridade**: nada aqui afirma "igual". Só o T7 (`design-diff --compare --check` nos dois renders, prod deployada) afirma — e ele não roda daqui.
- **Adoção nas telas**: trocar `Card`→`Card badge/note` e `KpiCard`→`variant="filter"` **nas 21 Pages do Ponto** é outra onda, tela por tela, depois de 01–03. Este playbook entrega o primitivo, não o consumo.

## Fonte da máquina (o `placar-indice.mjs` lê o bloco abaixo)
```json
{
 "sha_base": "2b4a3ec3b48a",
 "lido_em": "2026-09-09T10:53Z",
 "variaveis": {
  "UI": "resources/js/Components/ui",
  "SH": "resources/js/Components/shared",
  "ALVO": "prototipo-ui/cowork"
 },
 "decisoes": {
  "D-KPI-LABEL": "KPI-filtro: label em accent 13.3px/400 (bundle, look CRM) OU 11px/600 uppercase muted (ADR 0110 + shared/KpiCard)? Aditivo não resolve o canon.",
  "D-GRADE": "Tabela densa vira DataGrid no cliente ou segue LengthAwarePaginator no servidor? (W11)"
 },
 "threads": [
  {
   "id": "01",
   "dono": "[CL]",
   "prefixo": [
    "resources/js/Components/ui/card.tsx"
   ],
   "nao_toca": [
    "Pages/**",
    "shared/**"
   ],
   "depende_threads": [],
   "depende_decisoes": [],
   "bloqueio": null,
   "provas": [
    "resources/js/Components/ui/card.tsx contém badge, note e flush",
    "Pest/vitest: Card sem as props novas renderiza markup idêntico ao de 733033864088"
   ]
  },
  {
   "id": "02",
   "dono": "[CL]",
   "prefixo": [
    "resources/js/Components/shared/KpiCard.tsx"
   ],
   "nao_toca": [
    "Pages/**",
    "ui/**"
   ],
   "depende_threads": [],
   "depende_decisoes": [],
   "bloqueio": null,
   "provas": [
    "shared/KpiCard.tsx aceita variant='filter'",
    "KpiCard sem variant preserva tone default/success/warning/danger/info",
    "Backup/Index.tsx e Financeiro/Unificado/Index.tsx sem diff visual"
   ]
  },
  {
   "id": "03",
   "dono": "[CL]",
   "prefixo": [
    "resources/js/Components/shared/Toolbar.tsx"
   ],
   "nao_toca": [
    "shared/PageFilters.tsx",
    "Pages/**"
   ],
   "depende_threads": [],
   "depende_decisoes": [],
   "bloqueio": null,
   "provas": [
    "shared/Toolbar.tsx existe com left/center/right",
    "shared/PageFilters.tsx intacto (guarda)"
   ]
  },
  {
   "id": "04",
   "dono": "[CL]",
   "prefixo": [],
   "nao_toca": [],
   "depende_threads": [
    "01"
   ],
   "depende_decisoes": [
    "D-GRADE"
   ],
   "bloqueio": "D-GRADE",
   "provas": []
  },
  {
   "id": "05",
   "dono": "[CC]",
   "prefixo": [],
   "nao_toca": [],
   "depende_threads": [],
   "depende_decisoes": [],
   "bloqueio": "não medida — shared/StatusBadge.tsx (17.674 B) não foi recortado neste turno",
   "provas": []
  }
 ]
}
```
