---
modulo: ds-atomos
titulo: Átomos compartilhados — o visual do protótipo, aplicado no primitivo (não na tela)
dono: "[CL]"
base: 2b4a3ec3b48a
reemitido: 2026-09-09 (v2 — alvo remedido pós-limpeza · largura declarada · guardas de método)
---
# DS-átomos · playbook

## Leia antes (read-order desta pasta)
1. `prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md` · `PRE-FLIGHT-TELA.md` — no `main`, nunca de cópia.
2. `memory/LICOES_CC.md` (erro catalogado) **+ `prototipo-ui/design-docs/COLAR-NO-CODE-ACERTOS-E-LICOES.md`** (acerto catalogado — bloco *Ciclo 2026-09-09 · Ponto*). A1–A8 dizem o que **não** se refaz; os erros 1–5 dizem como **não** medir.
3. Este índice — §Alvo é a **única** fonte dos números. As threads 13/14/15 do `ponto/playbook/` citam esta seção por nome; nenhuma delas repete os valores (regra copiada envelhece em paralelo).

## Por que isto NÃO é um playbook do Ponto
[W] pediu (2026-09-09): *"deixe o visual igual ao seu"*. Medido no `main` no mesmo dia: o Ponto de produção **já é React e já compõe o DS** (21 Pages, imports `@/Components/ui/*` + `@/Components/shared/*` — acerto A1). Logo a diferença **não é escolha de componente** — é a **anatomia dos átomos**. Mexer tela por tela seria 21 PRs pintando por cima do mesmo primitivo; mexer no primitivo é 3 PRs e as 21 telas herdam.

**Raio de explosão declarado:** `ui/card.tsx` e `shared/KpiCard.tsx` são consumidos fora do Ponto (medido: Backup, Financeiro/Advisor, Financeiro/Unificado; `EmptyState`/`StatusBadge` também em Arquivos, Auditoria, Cliente — varredura **parcial**, 360 de 928 arquivos, então trate como piso, não teto). Daí a lei deste playbook:

> **ADITIVO OU NADA.** Prop nova, variante nova, default **inalterado**. Nenhuma tela existente muda de pixel sem opt-in. Quem furar isto reprova na prova de guarda da própria thread.

## Alvo — medido, não lembrado · **a 1280px** (persona Larissa)
Protótipo servido, tema `.cockpit` dark, `getComputedStyle` no elemento (nunca a classe declarada), após duas leituras iguais de `querySelectorAll('*').length` (T1 estável nas 10 views: 907–1062 nós). Medido **depois** da limpeza dos spacers fantasma — ver §Defeitos.

| átomo | alvo resolvido |
|---|---|
| **Widget** (meu `Card`) | radius **12px** · border **1px `--border`** `oklch(0.34 0.008 240)` · bg **`--surface`** `oklch(0.30 0.008 240)` · shadow `0 1px 2px rgba(0,0,0,.04)` · pad **14px** · título **13.5px/600 `--text`** · badge **11.5px mono `--text-dim`** à direita do título · `note` **abaixo** do título |
| **KPI-filtro** (meu `Kpi` com `onClick`) | **`BUTTON`** + `aria-pressed` · pad **12px** · radius **8px** · gap 12px · h **85px** · placa de ícone · label **13.3px/400 em accent** `oklch(0.70 0.15 295)` · valor **18px/600 `--text` tabular-nums** · grid `gap:10px`, trilha ~194px |
| **Toolbar** (minha `Barra`) | flex · wrap · gap **8px** · pad **9px 12px** · align center · bg `--surface` · moldura no PAI: radius 12px + border 1px `--border` · **a 1280px: 1215×71px, 6 filhos, uma faixa** · regime de flex **lido**, não suposto: `0 1 auto` nos controles, `1 1 260px` no campo de busca (o único que estica), `1 1 0%` no spacer |
| **tabela densa** | `th` **11px** uppercase `.07em` **`--text-dim`** 600, pad 8px 10px · `td` 12.5px pad 7px 10px, border-bottom `--border`/60% · hover `accent 5%` · linha 65px com sub-linha |

**Reflow não é defeito.** A mesma barra a 599px dá 158px/5 faixas — correto. Número sem largura declarada é provisório, não alvo (erro 5, terceira reincidência do vício).

## §Defeitos — o alvo NÃO era sagrado: três correções antes de virar pedido
Bateria de contraste com **caso de sanidade** (branco sobre `--surface` = **13,62**, plausível):
- `th` em `--text-mute` = **3,18** → falha AA. Trocado por `--text-dim` = **5,49** ✓, e **9,5px → 11px**.
- os outros **24** `color:var(--text-mute)` do `ponto-page.css` tinham o mesmo 3,18 → todos para `--text-dim`.
- label roxo do KPI = **4,85** ✓ passa; **não** foi mexido.

Exportar o alvo anterior seria exportar 3,18 com selo de aprovação (§5-bis). O alvo desta pasta é o **corrigido**.

**Terceiro defeito (thread 03):** a migração `.pt-toolbar → PtBarra` arrastou 7 `<span className="pt-sp">` do CSS antigo, que passaram a conviver com o spacer do próprio `Toolbar` — dois spacers na mesma barra, 82px de gap fantasma. Removidos (`ponto-page.jsx` 1 · `ponto-telas.jsx` 5 · `ponto-fechamento.jsx` 1) e o bloco C do `03` foi **remedido depois** da limpeza; o alvo anterior mentia em 3 números. Lição: migração mecânica preserva lixo que só fazia sentido no regime antigo — medir depois da limpeza, nunca antes.

## Guardas de método — valem para quem executar estas threads
- **Ausência exige controle positivo.** Antes de escrever "o DS não tem X", buscar um termo que **tem** de aparecer e conferir o estilo do repo (aspas simples, alias `@/`, extensão). "No matches" com aspas erradas já custou uma afirmação falsa sobre 21 arquivos (erro 1).
- **O espelho `_ds/` não é evidência sobre o `main`.** "O DS não tem X" exige o `.tsx` real no turno; senão a frase é "o bundle do espelho não tem X" (erro 2).
- **O comentário do arquivo manda mais que este pedido** em conflito de tipografia — `shared/KpiCard.tsx` :100–:160 carrega ADR 0110, a medição de 2026-08-24 e a errata adversarial (acerto A6).
- **"Apague X" autoriza X.** Vizinho de bullet com o mesmo diagnóstico continua precisando de "sim" (erro 3).
- **Produção pode estar à frente.** 2 de 2 vezes que a hipótese "produção atrás" foi testada contra leitura, ela falhou (Fiscal 03/09 · Ponto 09/09).

## Threads
| # | thread | prefixo | ficha | veredito |
|---|---|---|---|---|
| **01** | `ui/card.tsx` — `badge` · `note` · `flush` | 1 arquivo | leitura **1.987 B** · escrita ~60 ln · 3 símbolos · 0 decisões | **CABE** |
| **02** | `shared/KpiCard.tsx` — `variant="filter"` | 1 arquivo | leitura **11.135 B** · escrita ~70 ln · 3 símbolos · 0 decisões | **CABE** |
| **03** | `shared/Toolbar.tsx` — CRIAR (3 zonas) | 1 arquivo | leitura **2.764 B** (`PageFilters`, só p/ não duplicar) · escrita ~90 ln · 2 símbolos · 0 | **CABE** |
| 04 | tabela densa | — | — | **BLOQUEADA** por `D-GRADE` |
| 05 | `StatusBadge` kinds | — | 17.674 B **não recortados** | **NÃO MEDIDA** |

**Ordem:** 01 → 02 → 03 são independentes (3 PRs paralelos). 04 só depois de `D-GRADE`. 05 exige eu recortar o `StatusBadge` antes — é minha, não do [CL].

**Consumidores declarados:** `ponto/playbook` **13** (depende de 01 · 02), **14** e **15** (dependem de 01 · 03). Nenhuma das três abre antes de os átomos estarem no `main` — está escrito no "PARAR SE" de cada uma.

**`PageFilters` fica.** `shared/Toolbar` entra **onde não há moldura**, não substitui (acerto A7) — prova de guarda nas threads 14/15.

## Frescor — a árvore andou durante a geração
Comecei em `a0db7b0177b8` e as últimas leituras já vieram de `2b4a3ec3b48a`. Os sha por arquivo nas threads são de **`2b4a3ec3b48a`**; sha diferente na abertura ⇒ **remedir antes de escrever** e dizer isso no `_saida`.

## O que este playbook NÃO resolve
- **`D-KPI-LABEL`**: aditivo faz os dois vocabulários coexistirem, mas não diz qual é o canon (label em accent 13.3/400 do bundle × 11px/600 uppercase muted da ADR 0110). Fila de [W].
- **Paridade**: nada aqui afirma "igual". Só o T7 (`design-diff --compare --check` nos dois renders, prod deployada) afirma — e ele não roda daqui.
- **Adoção nas telas**: trocar `Card`→`Card badge/note` e `KpiCard`→`variant="filter"` **nas 21 Pages do Ponto** é outra onda, tela por tela, depois de 01–03. Este playbook entrega o primitivo, não o consumo.
- **Este arquivo não é estado.** O placar sai do `_saida-NN.md` por thread, derivado por `placar-indice.mjs` — ninguém escreve estado à mão.

## Fonte da máquina (o `placar-indice.mjs` lê o bloco abaixo)
```json
{
 "modulo": "ds-atomos",
 "sha": "2b4a3ec3b48a",
 "gerado": "2026-09-09",
 "nota_caminho": "reemitido em 2026-09-09 (v2): alvo remedido pos-limpeza dos spacers · largura 1280px declarada · guardas de metodo. Leitura do main em 2026-09-09T10:53Z.",
 "variaveis": {
  "UI": "resources/js/Components/ui",
  "SH": "resources/js/Components/shared",
  "ALVO": "prototipo-ui/cowork"
 },
 "decisoes": [
  {
   "id": "D-KPI-LABEL",
   "pergunta": "KPI-filtro: label em accent 13.3px/400 (bundle, look CRM) OU 11px/600 uppercase muted (ADR 0110 + shared/KpiCard)? Aditivo nao resolve o canon.",
   "respondida": false,
   "afeta": ["02"]
  },
  {
   "id": "D-GRADE",
   "pergunta": "Tabela densa vira DataGrid no cliente ou segue LengthAwarePaginator no servidor? (W11)",
   "respondida": false,
   "afeta": ["04"],
   "destrava": ["04"]
  }
 ],
 "threads": [
  {
   "id": "01",
   "titulo": "ui/card.tsx — badge · note · flush",
   "dono": "CL",
   "arquivo": "01-card-anatomia.md",
   "vaga": 3,
   "prefixo": [
    "${UI}/card.tsx"
   ],
   "nao_toca": [
    "resources/js/Pages/**",
    "${SH}/**"
   ],
   "depende_threads": [],
   "depende_decisoes": [],
   "provas": [
    { "tipo": "contem", "path": "${UI}/card.tsx", "padrao": "badge" },
    { "tipo": "contem", "path": "${UI}/card.tsx", "padrao": "flush" },
    { "tipo": "execucao", "path": "${UI}/card.tsx", "testes": ["npm run test -- card"], "nota": "Card sem as props novas renderiza markup identico ao de 733033864088 (guarda de default)" }
   ]
  },
  {
   "id": "02",
   "titulo": "shared/KpiCard.tsx — variant=filter",
   "dono": "CL",
   "arquivo": "02-kpicard-filter.md",
   "vaga": 3,
   "prefixo": [
    "${SH}/KpiCard.tsx"
   ],
   "nao_toca": [
    "resources/js/Pages/**",
    "${UI}/**"
   ],
   "depende_threads": [],
   "depende_decisoes": [],
   "nota_provas": "tone default/success/warning/danger/info tem de sobreviver sem variant — e a guarda de Backup e Financeiro/Unificado",
   "provas": [
    { "tipo": "contem", "path": "${SH}/KpiCard.tsx", "padrao": "filter" },
    { "tipo": "arquivo", "path": "resources/js/Pages/Backup/Index.tsx", "guarda": true },
    { "tipo": "execucao", "path": "${SH}/KpiCard.tsx", "testes": ["npm run test -- KpiCard"], "nota": "sem variant preserva os 5 tones; Backup e Financeiro/Unificado sem diff" }
   ]
  },
  {
   "id": "03",
   "titulo": "shared/Toolbar.tsx — CRIAR (3 zonas)",
   "dono": "CL",
   "arquivo": "03-toolbar-criar.md",
   "vaga": 4,
   "prefixo": [
    "${SH}/Toolbar.tsx"
   ],
   "nao_toca": [
    "${SH}/PageFilters.tsx",
    "resources/js/Pages/**"
   ],
   "depende_threads": [],
   "depende_decisoes": [],
   "nota_provas": "alvo medido a 1280px: 1215x71px, 6 filhos, uma faixa — remedido depois da limpeza dos 7 spans pt-sp",
   "provas": [
    { "tipo": "arquivo", "path": "${SH}/Toolbar.tsx" },
    { "tipo": "contem", "path": "${SH}/Toolbar.tsx", "padrao": "right" },
    { "tipo": "arquivo", "path": "${SH}/PageFilters.tsx", "guarda": true },
    { "tipo": "execucao", "path": "${SH}/Toolbar.tsx", "testes": ["npm run test -- Toolbar"], "nota": "3 zonas left/center/right; PageFilters intacto" }
   ]
  },
  {
   "id": "04",
   "titulo": "tabela densa",
   "dono": "CL",
   "arquivo": "04-tabela-densa.md",
   "prefixo": [],
   "nao_toca": [],
   "depende_threads": [
    "01"
   ],
   "depende_decisoes": [
    "D-GRADE"
   ],
   "bloqueio": "D-GRADE — decisao de W (DataGrid no cliente x paginator no servidor)",
   "provas": []
  },
  {
   "id": "05",
   "titulo": "StatusBadge kinds",
   "dono": "CC",
   "arquivo": "05-statusbadge-kinds.md",
   "prefixo": [],
   "nao_toca": [],
   "depende_threads": [],
   "depende_decisoes": [],
   "bloqueio": "nao medida — shared/StatusBadge.tsx (17.674 B) nao foi recortado; e minha, nao do CL",
   "provas": []
  }
 ]
}
```
