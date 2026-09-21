# Mapa do terreno — endereços medidos

> **Este arquivo é ponto de partida, nunca fonte de citação.** Serve para eu saber **onde olhar**,
> não para eu afirmar **o que tem lá**. Toda linha tem data: se a data é anterior ao último sync do
> DS, **remedir antes de usar**. Citar este mapa em vez do arquivo é repetir o erro do espelho com
> outro nome.
>
> **Não entra aqui:** regra de comportamento, decisão de produto, opinião. Isso é `CLAUDE.md`.
> **Não entra no `CLAUDE.md`:** número de linha, contagem, endereço. Isso é aqui.

Tudo abaixo medido em **10/09/2026**, salvo indicação.

## As quatro camadas — quem manda

| # | Camada | Endereço | Autoridade |
|---|---|---|---|
| 1 | Repo (SSOT) | pasta local `oimpresso.com/` | ganha de todos |
| 2 | Fonte viva do DS | `/projects/49a36f76-2672-43f6-b955-c6cbb52f7f86/` | ganha de 3 e 4 |
| 3 | Espelho local (cópia) | `_ds/` | nenhuma |
| 4 | Protótipo (o auditado) | raiz deste projeto | nenhuma |

## Camada 2 — por onde começar no DS

- `_ds_manifest.json` — mapa nome→`sourcePath`, **41 entradas**, `namespace: OfficeImpressoDesignSystem_49a36f`. **É o começo de qualquer auditoria**, não o bundle.
- `components/<Nome>/<Nome>.jsx` + `.d.ts` — o componente e o contrato de props.
- `_adherence.oxlintrc.json` — **795 linhas**, contrato de aderência legível por máquina (`no-restricted-imports` com as ~40 pastas + `react/forbid-elements`).
- `prototipo-ui/` — **1 arquivo só** (`Design System v4.html`). Histórico. Não é fonte.
- `Norte/` — 3 arquivos (`Norte - Fluxo do Caminhão.html`, `norte-app.jsx`, `norte-data.jsx`).

## Camada 3 — os três espelhos, e qual roda

| pasta | quem carrega | estado |
|---|---|---|
| `_ds/office-impresso-design-system-019dd02f-…` | **`oimpresso.com.html` L121 — é este que roda** | regenerado 09/09: 9.301 → **9.355 + shim** |
| `_ds/wagner-…-49a36f76-…` | o vinculado pela skill | regenerado 09/09: 9.290 → **9.355** |
| `_ds/office-impresso-atual-d7f88676-…` | **não medi quem consome** | não medi |

⚠️ O `019dd02f` tem um **shim de alias de 11 linhas no fim** (publica o global antigo apontando para
`window.OfficeImpressoDesignSystem_49a36f`, declarado no bundle L5). Regenerar **preservando o shim**.
Atualizar só o `wagner-…` **não muda nada em runtime** e o sintoma é silencioso.

## DataTable — os endereços que eu errei quatro vezes

| fato | camada 2 (bundle) | camada 2 (arquivo) | camada 1 (repo) |
|---|---|---|---|
| declaração | `_ds_bundle.js` **L2926** | `components/DataTable/DataTable.jsx` | `shared/DataTable.tsx` |
| prop `caption` (obrigatória) | L2930 | `.d.ts` **L43** `caption: string;` · `.jsx` **L57-60** `<caption>` oculto | **L126-142** |
| teclado da linha | **L3041-3052** | — | **L370-381** |
| nome da linha com `cli` cravado | **L3040** | — | — |
| largura mínima | — | — | `minTableWidth` **L173**, aplicada **L290** |
| `thead` sticky | não tem | não tem | não tem (L304) — é CSS de tela |
| equivalente no `DataTablePro` | **L3324-3335** | — | — |

Outros: `PageHeaderTabs.tsx` **L146-220** (repo) — `role="tablist"` + ←/→.

## Camada 4 — o protótipo da Fabricação

`manufacturing-page.jsx` (401L) · `-recipe.jsx` (267L) · `-producao.jsx` (383L) · `-insumos.jsx`
(111L) · `-print.jsx` (129L) · `-data.jsx` (191L) · `manufacturing-page.css` (221L).
Carregados por `oimpresso.com.html` **L195** (`text/oi-lazy`, `?v=mfg18`); CSS em **L68**.
Pacote de handoff em `handoff_fabricacao/design/` — **5 dos 6 `.jsx` divergem da raiz** (medido
09/09); o guia do pacote **não carrega `_ds_bundle.js`**.

## Máquinas — o que cada uma decide

- `scripts/design-sync/ds-push.mjs` — push **git→espelho**, direção livre.
- `scripts/governance/ds-mirror-drift.mjs` — sentinela. Compara tokens do git contra
  `scripts/design-sync/mirror-snapshot/colors_and_type.css` (**snapshot commitado, não o espelho
  vivo** — CI não tem login claude.ai, L7-11). Piso em `ds-mirror-drift-baseline.json`.
  **Advisory**; `--enforce` não é chamado em lugar nenhum (L28-33). **exit 2 = não conseguiu medir**,
  separado do código de drift.
- `scripts/governance/ancora-codigo-sync.mjs` — auto-sync de **ponteiro** de documentação. L26:
  *"só mexe no PONTEIRO, nunca na AFIRMAÇÃO"*. Não tem relação com aderência visual.

## Armadilhas de ferramenta (medidas na prática, 09-10/09)

- `grep` em caminho `/projects/…` **devolve vazio com o termo presente**. Cross-project é `read_file`.
- `local_grep` que estoura o tempo diz "results are incomplete" — **não é ausência**.
- Bundle compilado responde "existe?", mal responde "como se usa?". Manifest + `.jsx` primeiro.

## O que eu não medi (lista aberta, sempre não-vazia)

- Se o `sonner` está instalado no repo (afeta a conclusão sobre anúncio ao leitor de tela).
- Quem consome `_ds/office-impresso-atual-d7f88676-…`.
- Teclado de seta no `TabBar` da camada 2 (o repo tem; o espelho **não medi**).
- Os itens restantes das listas B e C da `auditoria-aderencia-fabricacao-v2.md` — levantados contra
  o espelho velho, **nenhum remedido contra as camadas 1 e 2**.
- O que "bubbles" significa neste projeto (única ocorrência medida: `\bbolha` em
  `scripts/governance/shipped-log-generate.mjs` L56, que é classificação de changelog).
