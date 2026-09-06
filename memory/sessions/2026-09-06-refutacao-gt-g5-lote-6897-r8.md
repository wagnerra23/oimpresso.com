---
date: "2026-09-06"
topic: "Refutação GT-G5 r8 — PR #6897 (5 gap.md com Tabela de partes + 5 map.json + STATUS/PLANS regenerados): 62 itens, 2 erros, 3,2% → REPROVADO"
authors: ["C"]
prs: [6897]
outcomes:
  - "REFUTADO 1 — Compras/Drawer: Ação declara 'gap 6 estava coberto' citando só o array TABS; Drawer.tsx em origin/main NÃO tem footer de ações por estágio, nem Registrar pagamento, nem XML/DANFE/Manifestar (l.501/520 dizem que ficam na tela Fiscal, Wave 6) — §Veredito #6 (G, Tier 0) virou 'Nada' por evidência parcial"
  - "REFUTADO 2 — Produto/Filtros: Ação cita `products.brand_id` em `mysql-schema.sql:2657`; a linha 2657 é `discounts.brand_id`. O `products.brand_id` real está em l.7577 — a conclusão é verdadeira, o recibo é de outra tabela"
  - "60/62 CONFIRMADOS: 10 âncoras de map (todas em origin/main, nenhuma revogada — ancora.mjs), 10 chaves de frontmatter (lidas por fmVal l.123-124 do gerar-contrato), 32 linhas de tabela, 7 checks de comando rc=0, design-code-map-check --strict rc=0"
  - "5 map.json regenerados por gerar-map.mjs em sessão fresca: byte-idênticos aos versionados (exceto gerado_em) — acao == tabela, prototipo_sha == contentHash"
  - "PII: 0 hits em 927 linhas adicionadas, 6 padrões com controle positivo 1/1 cada"
related_adrs: ["0324-frescor-espelho-cowork-dispatch-sla-limite-plataforma", "0093-multi-tenant-isolation-tier-0"]
---

# Refutação GT-G5 — PR #6897 · rodada 8

> Protocolo: [`PROTOCOLO-REFUTADOR-BACKFILL.md`](../requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md) §2-§4. Sessão fresca, sem leitura de nenhum `*refutacao*` anterior. Base medida: `origin/main = 26ac293f46` · `HEAD = cb03fd73a8` (branch `claude/q6-gap-md-tabela-e-11-mapas`). Clone completo (`is-shallow-repository = false`).

## Checklist §3

- [x] Sessão fresca (sem nenhum contexto do gerador; arquivos `*refutacao*` do branch NÃO abertos)
- [x] Modelo de tier SUPERIOR ao gerador — refutador: fable (claude-fable-5-1); gerador declarado no PR como opus/sonnet ([C])
- [x] Amostra: 100% anchors (tipo `anchors`) — nada de prosa amostrada, logo sem seed
- [x] Cada item verificado contra o código real em `origin/main` (`git show origin/main:<path>` com `MSYS_NO_PATHCONV=1`, `git ls-tree origin/main -- <path>`), não contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha + porquê) — §Refutados abaixo
- [x] Scan PII no diff — 6 padrões, cada um com controle positivo; hits = 0
- [x] `error_rate_pct` calculado = 3,23 (**≥ 2 → reprovado**)
- [ ] Entry no ledger — NÃO escrita por este refutador (só lê/mede; a entry é do gerador no mesmo PR, com este arquivo como `evidencia`)

## Escopo medido

`git diff --name-only origin/main...HEAD -- memory/requisitos` = 16 arquivos: 5 `*-gap.md` (Cliente, Compras, KB, Produto, RecurringBilling), 5 `*.map.json` novos, 6 `_STATUS-GENERATED.md` (Crm e RecurringBilling novos; Estoque/Fiscal/Ponto/Repair modificados). Conferido que `_DesignSystem/*-gap.md` (2), `OficinaAuto/kanban-producao-gap.md`, `Sells/vendas-*-gap.md` (2) e `Crm/clientes-gap.md` **não aparecem no diff** (blob-idênticos ao main) e que nenhum deles ganhou `.map.json` neste branch (`Sells/vendas.map.json` já existia em `origin/main`, blob `7fa8173cb0`). `prototipo-ui/` e `scripts/` sem diff no branch — os leitores usados são os de `origin/main`.

## Tabela por grupo

| # | Grupo | Itens | Confirmados | Refutados |
|---|---|---|---|---|
| 1 | Âncoras dos `.map.json` (`prototipo.arquivo` + `vivo.arquivo`, 1 por path distinto por mapa) | 10 | 10 | 0 |
| 2 | Frontmatter dos `-gap.md` (`tela_viva` + `prototipo`, existem em main e são lidos por `fmVal`) | 10 | 10 | 0 |
| 3 | Linhas da "Tabela de partes" — Ação × prosa × código (Cliente 7 · Compras 6 · KB 6 · Produto 6 · RB 9) | 34 | 32 | **2** |
| 4 | `requisitos-status.mjs <Mod> --check` ×6 + `plans-index.mjs --check` | 7 | 7 | 0 |
| 5 | `design-code-map-check.mjs --check --strict` | 1 | 1 | 0 |
| — | **Total** | **62** | **60** | **2** |

### Grupo 1 — âncoras (10/10 CONFIRMADO)

`git ls-tree origin/main` devolve blob para os 10 paths: `prototipo-ui/cowork/{clientes,compras,kb,produtos,cobranca-recorrente}-page.jsx` e `resources/js/Pages/{Cliente,Compras,kb,Produto,RecurringBilling}/Index.tsx`. Revogação pelo charter dono, medida pela porta viva `node prototipo-ui/ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork`:

| Tela | `related_prototype` (charter main) | ancora.mjs |
|---|---|---|
| Cliente/Index | `prototipo-ui/cowork/clientes-page.jsx` | ✓ related_prototype + ✓ bundle_source |
| Compras/Index | `prototipo-ui/cowork/compras-page.jsx` | ✓ related_prototype + ✓ heurística startsWith |
| kb/Index | `prototipo-ui/cowork/kb-page.jsx` | ✓ related_prototype + ✓ bundle_source |
| Produto/Index | `n/a (herda PT-01 Lista; segue o Padrão de Tela)` + `bundle_source: produtos-page.jsx` | "sem âncora: n/a (declaração legítima)" **+ ✓ `[-page.jsx (bundle · bundle_source)] produtos-page.jsx`** |
| RecurringBilling/Index | `prototipo-ui/cowork/cobranca-recorrente-page.jsx` | ✓ related_prototype + ✓ bundle_source |

Nota Produto: `gerar-map.mjs` imprime `⚠️ âncora computada do charter não cita prototipo-ui/cowork/produtos-page.jsx` (compara string cheia vs `bundle_source` curto). Não é revogação — `ancora.mjs` resolve `produtos-page.jsx` como âncora válida via `bundle_source`, e o §5 2026-08-28(c) fixa que `n/a` **coexiste** com a âncora de bundle. CONFIRMADO, com o aviso registrado (o gerador deveria tê-lo citado no PR).

### Grupo 2 — frontmatter (10/10 CONFIRMADO)

Os 10 valores (`tela_viva`/`prototipo` × 5 arquivos) apontam para os mesmos 10 paths do Grupo 1 (existem em main). Leitor real: `prototipo-ui/gerar-contrato.mjs` l.36 `fmVal`, l.120 `frontmatterBlock`, l.123 `pagesPath(fmVal(fm,'tela_viva'))`, l.124 `fmVal(fm,'prototipo')`, l.133 `fmVal(fm,'tela')`; `gerar-map.mjs` l.154-155 lê as mesmas chaves. Prova de que são lidos de fato: regenerei os 5 mapas com `node prototipo-ui/gerar-map.mjs <gap.md>` em `HEAD` → **idênticos aos versionados** (exceto `gerado_em`), incluindo `prototipo_sha` (`8f284ad79fb3` · `1096106a9f83` · `3f2c78b83b65` · `07a4cea7662a` · `40e51d3a1e6a`) — logo os campos foram lidos e o hash é `contentHash` do arquivo apontado. Frontmatter de Produto nasceu inteiro neste PR (o arquivo não tinha bloco `---`); nos outros 4 só entraram `tela/prototipo/tela_viva` (o `id:` já existia).

### Grupo 3 — linhas da tabela (32/34)

Integridade de célula: as 34 linhas têm exatamente 3 células (`awk` contando `|`), pipes internos escapados com `／`. `acao` do `.map.json` == coluna Ação da tabela (só `**` removido, por desenho: `gerar-map.mjs:173`) — provado pela regeneração idêntica; `_acionavel` reflete `Nada`→false.

**Cliente (7/7 CONFIRMADO):** todas "Nada" citando o banner l.14-26 (l.19 = *"registro datado de 2026-06-30 medido por rota inválida — não é base para decidir hoje"* ✓ em HEAD) e o dono vigente `PARIDADE-area-cliente-diagnostico-e-ondas.md`, que em `origin/main` l.199 diz literalmente *"**Não** adotar o card 'Faturamento +12% vs ontem' do mockup"* ✓. Rebaixar até os ADOTAR-PARCIAL #2/#3 da Síntese a "Nada" é coerente com o próprio banner do arquivo (a prosa se auto-invalida como base de decisão) e a nota r3 declara isso.

**Compras (5/6):** Header — §Veredito #2 l.70 ✓, `<header className="hd"` em `Index.tsx:239` ✓, e `cowork-compras-bundle.css` tem **0** `os-page-h` (a Ação manda "conferir antes", não afirma — ✓, e a cautela era necessária). KPIs — #4 l.72 ✓, `_pendente_` preservado ✓. Filtros — #1 l.69 ✓. Tabela — #3 l.71 ✓; `Row` em `Index.tsx:27` sem `items_count`/`has_xml` (grep 0) ✓ `_pendente_` preservado. Ações por linha — `AcoesDropdown.tsx` main tem exatamente 9 entradas com `label:` em l.100/109/115/127/135/142/151/158/170 (Ver · Impressão · Editar · Excluir · Rótulos · Ver pagamentos · Reembolso de compra · Atualizar status · Elementos pendentes de notificação) ✓ "l.100–170" ✓. **Drawer — REFUTADO** (abaixo).

**KB (6/6 CONFIRMADO):** Header #4 l.91 ✓ + NÃO adotar l.93 ✓. Navegação #2 l.89 ✓. Busca #3 l.90 ✓ + "regressão proibida" l.93 ✓. Lista — sort por frescor está só em Parte 4 l.61 (P, dado já existe) e de fato **não** consta em Adotar #1-#4 ✓ (a Ação diz isso honestamente). Editor #1 l.88 + #3 l.90 ✓; `history_count` existe no vivo (`kb/Index.tsx:99,481-483`, botão `disabled title="Em breve (O11)"`) e no backend (`Modules/KB/Http/Controllers/KbController.php:229`) ✓. Drawer #4 l.91 ✓.

**Produto (5/6):** Header #1 l.87 ✓. KPIs "Nada" = NÃO adotar Parte 2 l.92 ✓. **Filtros — REFUTADO** (abaixo). Tabela #3 l.89 ✓; `ProdutoRow` em `Produto/Index.tsx:46-56` tem `cost`/`margin`/`stockQty` ✓ e nenhum `supplier|fornecedor|variant` no arquivo ✓. Ações — Parte 5 l.68 "precisa charter/Wagner" ✓ + Pendências l.97 ✓. Drawer "Nada" = NÃO adotar Parte 6 l.94 ✓.

**RecurringBilling (9/9 CONFIRMADO):** as 6 "Nada" citam `— · —` que a prosa tem em l.36/40/43/44/45/46 ✓ (texto truncado com `…` é cópia literal do início da célula "Vivo-à-frente", não altera o veredito). KPIs "Decidir" = micro-gap l.38 (P-M · Tier 0, só propor) + Conclusão l.56 ✓; `failed_count` existe (`RecurringBilling/Index.tsx:81,603-604` label "Retentado falhos") ✓. Filtros = ADOTAR-PARCIAL #1 l.27 ✓. Ações/drawer = ADOTAR-PARCIAL #2 l.28 + l.42 "P · baixo (só com gate F1.5)" ✓.

### Grupo 4 — comandos (7/7 CONFIRMADO)

`node scripts/governance/requisitos-status.mjs <Mod> --check` → rc=0 "em dia" para Crm, Estoque, Fiscal, Ponto, RecurringBilling, Repair. `node scripts/governance/plans-index.mjs --check` → rc=0 (7 registrados, 24 pendentes).

### Grupo 5 — design-code-map-check (1/1 CONFIRMADO)

`node scripts/governance/design-code-map-check.mjs --check --strict` → rc=0: 17 map.json, "nenhum map.json com âncora quebrada ou sha stale". Lista os 6 gap.md sem map (Crm, OficinaAuto, Sells×2, _DesignSystem×2) — exatamente os que o lote diz ter deixado fora ✓.

## Refutados — lista completa

### R-1 · Compras · linha "Drawer / Sheet — maior gap" (`compras-gap.md:93` · `compras.map.json` id `drawer-sheet-maior-gap`)

**Ação diz:** *"Nada — o `_pendente_` da Parte 6/§Veredito #6 foi resolvido lendo o código: Drawer.tsx declara as 5 abas (l.22–26 …) e `initialTab` (l.95/154). A prosa assumia '2 tabs' sem ler o componente; **o gap 6 estava coberto**."*

**Código em `origin/main` (`resources/js/Pages/Compras/components/Drawer.tsx`, 608 linhas):**
- TABS l.21-27 com ids em l.22-26 ✓; `fsm-track` l.198 ✓ (essa metade é verdadeira).
- `initialTab` está em **l.96** (campo) e l.154; l.95 é o JSDoc. Off-by-one no recibo — anotado, não conta separado.
- **Footer de ações por estágio** ("Enviar pedido → / Marcar em trânsito → / Pagar agora"): `grep -iE 'footer|Enviar|Pagar|Marcar em'` → **0 hits**. Os únicos `<button>` são fechar (l.192/238) e troca de tab (l.214).
- **Tab Documentos** com botões XML/DANFE/Manifestar: l.501 *"Importe o XML pela tela Fiscal (Wave 6 vai trazer atalho aqui)"* e l.520 *"Download de XML / DANFE e manifestação fiscal ficam na tela Fiscal. Wave 6 traz atalhos integrados aqui."* — o próprio componente declara a ausência.
- **Tab Pagamentos** "Registrar pagamento"/"Agendar no financeiro": 0 hits; l.533 só mostra "Sem pagamentos lançados". **Tab Itens** com coluna margem: `margem` 0 hits.

**Por quê é erro:** o §Veredito #6 (l.74) é *"(G, ⚠️ valor FORTE — Tier 0) Drawer 5 tabs completo (Itens/Documentos/Histórico + **FSM track + footer ações por estágio**)"* e a Parte 6 (l.57) lista explicitamente footer + botões fiscais + "Registrar pagamento" como gap real. A Ação resolveu a pendência binária "5 tabs ou 2" e generalizou para "gap 6 coberto", omitindo as regiões que o código nega em texto. O item Tier 0 "G" virou "Nada" com `_acionavel:false` — é o caso "afirma algo que o código contradiz + omite/trunca o veredito" do item 3. A frase de escape ("diferença por REGIÃO dentro de cada aba é trabalho de âncora do map") não cobre o footer, que é região do drawer, não de aba.

### R-2 · Produto · linha "Filtros / Busca" (`produtos-gap.md:107` · `produtos.map.json` id `filtros-busca`)

**Ação diz:** *"confirmado no código: `products.brand_id` existe (`database/schema/mysql-schema.sql:2657`), campo OEM não existe (0 ocorrências no schema)"*.

**Código em `origin/main` (`database/schema/mysql-schema.sql`, idêntico em HEAD):**
- l.2653 `CREATE TABLE \`discounts\` (` … **l.2657 `\`brand_id\` int(11) DEFAULT NULL`** — é a tabela **`discounts`**, não `products`.
- `CREATE TABLE \`products\`` começa em l.7569; o `brand_id` dela está em **l.7577** (`int(10) unsigned`, FK l.7656 → `brands`).
- `grep -ic oem` → 0 ✓ (essa metade está certa).

**Por quê é erro:** a Ação resolve um `_pendente_` da prosa ("confirmar schema") citando linha de outra tabela como recibo. A conclusão (marca é P) sobrevive por acidente — `products.brand_id` existe — mas o recibo é fabricado por olho, exatamente o vetor que o `_doc` do map proíbe (*"grep -n real, nunca fabricar"*). Item 3: "cita linha errada".

## Scan PII (item 6)

Corpus: `git diff origin/main...HEAD -- memory/requisitos | grep '^+'` = **927 linhas**. Cada padrão rodado antes contra uma linha de controle sintética contendo um exemplo de cada classe (CPF pontuado, CPF cru 11 dígitos, CNPJ, telefone com e sem DDD entre parênteses, e-mail, valor em reais com e sem espaço):

| Padrão | Regex (ERE) | Controle | Hits no lote |
|---|---|---|---|
| CPF pontuado | `[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}` | 1/1 | **0** |
| CPF cru | `(^\|[^0-9])[0-9]{11}([^0-9]\|$)` | 1/1 | **0** |
| CNPJ | `[0-9]{2}\.[0-9]{3}\.[0-9]{3}/[0-9]{4}-[0-9]{2}` | 1/1 | **0** |
| Telefone BR | `\(?[0-9]{2}\)?[ -]?9?[0-9]{4}-?[0-9]{4}` | 1/1 | **0** |
| E-mail | `[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}` | 1/1 | **0** |
| Reais | `R\$ ?[0-9]` | 1/1 | **0** |

`pii_hits = 0`. (As menções "R$ a recuperar" no RB são texto sem dígito; "Faturamento +12%" não é valor.)

## Veredito

`error_rate = 2 / 62 = 3,23%` ≥ 2% → **lote REPROVADO inteiro** (§2.6: volta ao gerador; re-verificação do lote todo na próxima rodada). Os dois erros têm a mesma forma — **resolver um `_pendente_` da prosa com leitura parcial do código e apresentar como recibo** — que é a lápide §5 2026-07-15 (achado a partir de leitura sem varredura) e o motivo de o map exigir `grep -n` real. Correção mínima: (R-1) Ação volta a "Decidir" citando §Veredito #6 com o que o código já cobre (5 tabs + FSM track) e o que falta (footer por estágio · botões fiscais Wave 6 · Registrar pagamento · margem em Itens), mantendo Tier 0; e trocar `l.95` por `l.96`. (R-2) trocar `:2657` por `:7577` (tabela `products`).

```json
{"itens_verificados": 62, "erros_confirmados": 2, "error_rate_pct": 3.23, "pii_hits": 0, "veredito": "reprovado"}
```
