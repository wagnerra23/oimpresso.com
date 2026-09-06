---
date: "2026-09-06"
topic: "Refutação GT-G5 rodada 2 — PR #6897 (10 gap.md com tabela derivada + 11 map.json + _STATUS regenerados): 141 itens, 4 refutados, 0 PII — REPROVADO (2,84%)"
authors: ["C"]
prs: [6897]
outcomes:
  - "Lote REPROVADO por error_rate 2,84% (4/141) — 3 defeitos de âncora nos 2 mapas de FUNDAÇÃO (_DesignSystem) + 1 linha de tabela que omite veredito aberto da prosa (PageHeader P7)"
  - "Os 8 mapas de TELA (Cliente/Compras/KB/Oficina/Produto/RecurringBilling/Sells×2) e as 76 linhas de tabela deles: 0 refutados"
  - "Mecânica limpa: 11 maps regeneram idênticos do HEAD; 7 checks derivados rc=0; design-code-map-check --strict rc=0; expurgo dos protótipos confirmado no commit 9da73296d3 (2026-06-23)"
---

# Refutação GT-G5 · rodada 2 · PR #6897 (branch `claude/q6-gap-md-tabela-e-11-mapas`)

> Protocolo: [`PROTOCOLO-REFUTADOR-BACKFILL.md`](../requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md) §2 · §3 · §4.
> Sessão fresca, sem leitura de `memory/sessions/*refutacao*` (o arquivo da r1 foi visto só no `ls` para não colidir nome; **não foi aberto**).
> Base medida: `origin/main` = `26ac293f46` · HEAD = `bd178f93b7` · `git rev-list --count HEAD..origin/main` = **0** (branch já contém o main) · `git rev-parse --is-shallow-repository` = **false** (datas de `git log` valem).
> Prosa: o diff dos 10 `-gap.md` só **adiciona** (frontmatter + seção) e remove **2 linhas** (`prototipo:` de pageheader e sidebar) — a prosa em HEAD é byte-idêntica à de `origin/main`, então "prosa acima" e "prosa em main" são a mesma coisa neste lote.

## Checklist §3

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier ≥ gerador — refutador **Fable 5.1** (`claude-fable-5-1`); gerador registrado como `[C]` nos commits (Opus/Fable; se o gerador foi Fable, vale o teto de política do §4.2)
- [x] Amostra: **100%** dos anchors (itens 1–2), **100%** das linhas das tabelas derivadas (item 3 — 88/88), 100% dos comandos (itens 4–5); sem seleção aleatória (não houve amostragem parcial → sem seed)
- [x] Cada item verificado contra `origin/main` (`git ls-tree origin/main -- <path>`, `git show origin/main:<path>`, `git log origin/main`), não contra o texto do PR
- [x] Cada REFUTADO com evidência (path + linha/commit + porquê) — seção "Refutados"
- [x] Scan PII no diff (`git diff origin/main...HEAD -- memory/requisitos | grep '^+'`, 2064 linhas) com **controle positivo** por padrão — 0 hits
- [x] `error_rate_pct` = **2,84** (≥ 2 → REPROVADO)
- [ ] Entry no ledger `governance/sdd-verification-ledger.json` — **não escrevi** (mandato: só este arquivo de evidência; a entry é do dono do PR, veredito `reprovado`)

## Tabela por grupo

| # | Grupo | Itens | Confirmados | Refutados | Como medi |
|---|---|---|---|---|---|
| 1 | `map.json` — `partes[].prototipo.arquivo` / `vivo.arquivo` ≠ TODO existe em main (1 item por path distinto por mapa) | 17 | 17 | 0 | `git ls-tree origin/main -- <path>` → 1 linha em 17/17; controle negativo `__nao_existe.jsx` → 0; controle positivo `CLAUDE.md` → 1. Crm (11 partes) e sidebar (17 partes) têm **0 paths ≠ TODO** (0 itens neste grupo — ver grupo 2). |
| 2 | `-gap.md` — frontmatter `prototipo` / `tela_viva` / `prototipo_nota` existe em main **e** é lido corretamente por `fmVal` (`gerar-contrato.mjs:36`) + `resolverArquivosPrototipo`/`resolverArquivoVivo` (`gerar-map.mjs:79`) | 22 | 19 | **3** | 8 telas × 2 chaves = 16 ✓ (16/16 paths existem; o mapa regenerado do HEAD é **byte-idêntico** ao commitado, `partes==true sha==true` nos 11); pageheader 3 chaves → **2 refutadas**; sidebar 3 chaves → **1 refutada** |
| 3 | Tabela derivada — coluna **Ação** reflete o veredito da prosa (1 item por linha) + integridade de célula | 88 | 87 | **1** | Cliente 7 · Compras 6 · KB 6 · Oficina 8 · Produto 6 · RecurringBilling 9 · Sells/Create 9 · Sells/Index 8 · PageHeader 12 · Sidebar 17. Integridade: awk contando `\|` (=4 em 88/88) e crases por célula (pares em 88/88) — **0 células quebradas/truncadas** |
| 4 | `requisitos-status.mjs <Mod> --check` (Crm, Estoque, Fiscal, Ponto, RecurringBilling, Repair) + `plans-index.mjs --check` | 7 | 7 | 0 | rc=0 nos 7 (`✓ … em dia`; plans-index: 7 registrados, 24 pendentes) |
| 5 | `design-code-map-check.mjs --check --strict` | 1 | 1 | 0 | rc=0 — 23/23 telas com gap.md têm map; `[OK] nenhum map.json com âncora quebrada ou sha stale. 70 âncora(s) TODO` |
| 6 | Scan PII nas linhas adicionadas (6 padrões, cada um com controle positivo) | 6 | 6 | 0 | ver seção PII |
| | **Total** | **141** | **137** | **4** | **error_rate = 4/141 = 2,84%** |

## Refutados (lista COMPLETA, com evidência)

### R-1 · grupo 2 · `_DesignSystem/pageheader-canon-v3-gap.md` → `tela_viva` lido pelo leitor real produz o arquivo ERRADO; `pageheader-canon-v3.map.json` ancora as 12 partes de fundação num CONSUMIDOR

- **Fato no lote:** `pageheader-canon-v3.map.json` tem `partes[].vivo.arquivo = resources/js/Pages/Cliente/Index.tsx` em **12/12** partes — inclusive `primary-roxo-295-universal`, `ghosts-overflow`, `geometria-3-zonas`, `titulo-suffix-subtitle`, cujo vivo, pela própria prosa (linhas 60, 62, 65, 66 em main), é `PageHeader.tsx` / `PageHeaderPrimary.tsx`.
- **Causa medida (rodei o leitor):** frontmatter em main linha 4: `tela_viva: resources/js/Components/PageHeader/PageHeader.tsx + PageHeaderPrimary.tsx + index.ts (consumo de referência: resources/js/Pages/Cliente/Index.tsx)`. `fmVal` devolve a linha inteira; `resolverArquivoVivo` (`gerar-map.mjs:79`) só aceita `resources/js/Pages|prototipo-ui/fixtures` → descarta os 3 arquivos do componente e pega o **exemplo de consumo**. Saída literal: `resolverArquivoVivo(...) = resources/js/Pages/Cliente/Index.tsx`.
- **Por que é erro do lote e não só limitação da ferramenta:** o autor normalizou o campo irmão `prototipo:` (→ `TODO` + `prototipo_nota`) exatamente porque o leitor lia errado (commit `6b60f0d5d9`: *"gerar-map lia 'TODO  # …' como caminho"*), mas deixou `tela_viva` num formato que o mesmo leitor lê errado — e commitou o mapa resultante. `resources/js/Components/PageHeader/PageHeader.tsx` e `PageHeaderPrimary.tsx` **existem em main** (`git ls-tree` = 1 cada). Âncora que aponta pra consumidor num artefato cujo único propósito é âncora = anchor errado, não TODO honesto.

### R-2 · grupo 2 · `_DesignSystem/pageheader-canon-v3-gap.md` → `prototipo_nota` cita o valor antigo TRUNCADO dentro da crase

- **Fato no lote (HEAD linha 4):** `prototipo_nota: "2026-09-06 [C]: era \`prototipo-ui/prototipos/pageheader-canon-v3/ (index.html · 3-familias.html · b-v2-roxo-kpi\` — pasta … expurgada …"`.
- **Valor real removido (diff, linha `-` em main:3):** `prototipo-ui/prototipos/pageheader-canon-v3/ (index.html · 3-familias.html · b-v2-roxo-kpis.html · clientes-filtros-amostra.html · SPEC.md · README.md)`.
- **Por quê:** a citação "era `X`" corta em 100 caracteres no meio de um nome de arquivo (`b-v2-roxo-kpi` não existiu; era `b-v2-roxo-kpis.html`), deixa parêntese aberto dentro da crase e omite 3 dos 6 arquivos. A alegação de expurgo em si está **CONFIRMADA** (`git show --stat --diff-filter=D 9da73296d3` apaga os 8 arquivos de `pageheader-canon-v3/` + `sidebar-v3-unificado/visual-source.html`, datado 2026-06-23; `prototipo-ui/design-system` existe em main) — o erro é só a fidelidade da citação. A nota do **sidebar** cita o valor inteiro corretamente (path curto, não truncou) → CONFIRMADA.

### R-3 · grupo 2 · `_DesignSystem/sidebar-v3-unificado-gap.md` → `tela_viva` (lista YAML de 4 arquivos) NÃO é lido pelo leitor real; `sidebar-v3-unificado.map.json` nasce com `vivo.arquivo = TODO` em 17/17 partes

- **Fato no lote:** frontmatter (main linhas 6-10) declara `tela_viva:` como lista — `AppShellV2.tsx`, `cockpit/Sidebar.tsx`, `cockpit/shared.ts`, `cockpit.css` — os 4 **existem em main** (`git ls-tree` = 4/4). O mapa commitado tem `vivo: {arquivo: "TODO"}` nas 17 partes e `prototipo_sha: sem-arquivo`.
- **Causa medida:** `fmVal(fm,'tela_viva')` devolve `"- resources/js/Layouts/AppShellV2.tsx"` (o `\s*` do regex atravessa a quebra de linha e pega o 1º item da lista, com o `- `); `resolverArquivoVivo` rejeita porque não está sob `resources/js/Pages/` → `null` → TODO.
- **Por quê é erro do lote:** o mesmo caso do R-1 — o PR tocou o frontmatter deste arquivo (trocou `prototipo:`, adicionou `prototipo_nota`) e gerou um mapa que **não carrega nenhum dos 4 vivos declarados**. O `design-code-map-check` conta os 17 como "TODO pendente (não é drift)", ou seja, o gate **não pega** — o número de cobertura 23/23 sobe sem uma âncora sequer neste mapa. Item 2 do mandato pergunta literalmente *"o frontmatter é lido corretamente pelo leitor real?"* — resposta medida: **não**.

### R-4 · grupo 3 · `_DesignSystem/pageheader-canon-v3-gap.md` tabela derivada, linha **P7 "Ghosts / overflow ⋮ (Zona R)"** → Ação `Nada — paridade (§3: já é canon)` OMITE veredito aberto da prosa

- **Ação no lote (HEAD linha 138):** `Nada — paridade (§3: já é canon)`.
- **Prosa em main:** linha 66 (P7): *"NÃO existe `<PageHeaderOverflow>` componentizado (Wave 3)"*, Esforço **M**, Risco *"Componentizar overflow = fundação serializada"*; linha 90: *"PASSO 3 — `<PageHeaderOverflow>` (P7): componentizar ⋮ com seções canônicas. (M)"*; linha 111: **"Onde propõe ALÉM do canon (precisa ADR + decisão Claude Design [W]): … `<PageHeaderOverflow>` componentizado (P7)"**; linhas 121-122: *"Tudo além (**P4/P7/P8** + SPEC §7-§28) requer decisão explícita de Wagner/Claude Design"*.
- **Por quê:** a prosa cita P7 nos DOIS blocos do §3 (núcleo já-canon **e** "propõe além, precisa decisão") — e a tabela pegou só o primeiro. O caso-controle é a própria tabela: **P4** (SubNav, mesmo padrão "componente listado como Wave 3", mesmo bloco do §3) e **P8** receberam `**Decidir.**`; P7, com o mesmo veredito na prosa, recebeu `Nada`. Pela regra declarada no cabeçalho da tabela (*"Decidir. só onde a prosa registra gap real em aberto"*), P7 tinha que ser `Decidir.` — a Ação **omite** um veredito que a prosa dá três vezes.

## Confirmados que merecem registro (não contam como erro, mas o dono do PR deve ler)

- **Sidebar #1 "Tema"** — Ação `Nada — decisão [W] 2026-07-16 … ADR UI-0023`: a prosa em main **não cita** UI-0023 (ela para em *"_pendente_ decisão"*, UI-0009/0014). A tabela trouxe a decisão de fora da prosa. **Verifiquei:** [`adr/ui/0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md`](../requisitos/_DesignSystem/adr/ui/0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md) existe em main, `Status: accepted`, 2026-07-16, supersede UI-0019 (que supersedia 0009/0014). Fato correto e canon → **CONFIRMADO**; só não é "derivada MECANICAMENTE da prosa acima" como o cabeçalho afirma.
- **Cliente (7 linhas)** — as 5 `Decidir.` batem com a prosa por parte (A2/A4/A5/A6/A7) **e** com a "Síntese de ações" do mesmo arquivo. Mas o **banner de 2026-08-26 no mesmo arquivo** (linhas 11-23) declara o diagnóstico inteiro *"registro datado … medido por rota inválida — não é base para decidir hoje"* e nomeia como dono vigente `PARIDADE-area-cliente-diagnostico-e-ondas.md` — cujo §6 já registra **"Não adotar o card 'Faturamento +12% vs ontem'"** (a linha KPIs da tabela mantém `Decidir.` para o "6º card Faturamento hoje"). Não contei como refutado porque (a) a Ação reproduz fielmente o veredito por-parte da prosa e (b) o PARIDADE não é ADR canon (regra do mandato). Fica como **aviso**: uma tabela "derivada da prosa" que ignora o banner da própria prosa serve `Decidir.` sobre um diagnóstico que o arquivo se declara inválido.
- **Crm/clientes.map.json** — o `gap_fonte` aponta `memory/requisitos/Crm/clientes-gap.md`, que **não foi tocado** neste PR (existe em main, última mudança `94d17c9d5a` 2026-08-26). O mapa é o esqueleto exato da tabela pré-existente (regenerado = idêntico), com `prototipo`/`vivo` = TODO em 11/11 (o gap.md do Crm não tem frontmatter `prototipo`/`tela_viva`). Zero anchors — coerente com o gerador, mas é mais um mapa que entra na cobertura 23/23 sem âncora alguma.
- **PageHeader P2 / P12 / P10** — Ações batem com a coluna Risco/Governança da prosa (linhas 61, 71, 69 em main): *"decidir se sticky+blur entra"* → `Decidir.`; *"Tratar como backlog, não gap … cada item precisa de ADR"* → `Decidir.`; *"Ignorar pra fins de fundação"* → `Nada`.
- **RecurringBilling (9 linhas)** — cada `Nada — \`— · —\`` confere com a célula Esforço·risco da prosa (linhas 33, 34, 36→37, 40, 41, 42, 43 = `— · —`); os 3 `Decidir.` batem com as 2 linhas `→` (P-M Tier 0 · P baixo) e com o ADOTAR-PARCIAL #1/#2 + "Conclusão: _pendentes_ de decisão do Wagner".
- **Sells/Index header** — `Nada — divergência intencional já decidida` confere com §1 (*"Não adotar sem decisão Wagner"*) + §"Não adotar" do veredito.
- **Oficina "Filtros — boxes/elevadores"** — `Nada — vivo à frente` confere com §3 (*"Esforço — (não adotar lógica; no máximo reskin)"*) e com a ausência da parte 3 na lista "Adotar".
- **`prototipo_sha`** — os 8 `sha256:` foram recomputados do HEAD e batem; `git diff origin/main HEAD -- prototipo-ui/cowork/ resources/js/Pages/` = vazio, logo os hashes valem também contra main.
- **`governance/sdd-scorecard-baseline.json`** (fora de `memory/requisitos`, fora do mandato): absorção 11→12 declarada com nota longa; não medi `measureDistillerFreshness` — não conto nem confirmo.

## Scan PII (linhas ADICIONADAS do diff em `memory/requisitos`, 2064 linhas)

| Padrão | Regex | Controle positivo (arquivo `/tmp/ctrl.txt`, fora do repo) | Hits no diff |
|---|---|---|---|
| CPF pontuado | `[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}` | CPF fictício pontuado → 1 | **0** |
| CPF cru (11 dígitos isolados) | `(^\|[^0-9])[0-9]{11}([^0-9]\|$)` | 11 dígitos fictícios → 1 | **0** |
| CNPJ | `[0-9]{2}\.[0-9]{3}\.[0-9]{3}/[0-9]{4}-[0-9]{2}` | CNPJ fictício → 1 | **0** |
| Telefone BR | `\(?0?[1-9]{2}\)?[ -]?9?[0-9]{4}-[0-9]{4}` | celular fictício com DDD → 1 | **0** |
| E-mail | `[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}` | `fulano@exemplo.com.br` → 1 | **0** |
| Valor em reais | `R\$ ?[0-9]` | `R$` + dígitos fictícios (só no arquivo de controle, nunca gravado no repo) → 1 | **0** — as 2 ocorrências de valor no lote já chegam como `R$ [redacted Tier 0]` (Compras KPIs, prosa e tabela) |

Todos os 6 controles positivos casaram (1/1); nenhum padrão casou no diff → `pii_hits = 0`.

## Veredito

```json
{"itens_verificados": 141, "erros_confirmados": 4, "error_rate_pct": 2.84, "pii_hits": 0, "veredito": "reprovado"}
```

**Onde o lote falha é concentrado e nomeável:** os 2 mapas de **fundação** (`_DesignSystem`) — o gerador só resolve `vivo` sob `resources/js/Pages/`, e nenhum dos dois vivos declarados mora lá (`Components/PageHeader`, `Layouts`, `Components/cockpit`). Resultado: um mapa ancora 12 partes no consumidor errado (R-1) e o outro nasce sem âncora nenhuma (R-3), os dois contando como "cobertos" no 23/23. Mais a tabela do PageHeader que classificou P7 pela metade do §3 (R-4) e uma citação truncada (R-2). Os **8 mapas de tela e suas 76 linhas de tabela** estão limpos (0 refutados) — o erro não é sistemático do prompt, é do encaixe fundação × leitor.

**O que a r3 precisa (não é minha decisão, é o que a evidência aponta):** (a) ou `tela_viva` em linha única com path que o leitor aceite, ou `resolverArquivoVivo` passar a aceitar `resources/js/{Components,Layouts}/` — e o mapa regenerado; (b) P7 → `Decidir.`; (c) `prototipo_nota` com o valor inteiro. Depois disso, re-refutar o lote todo (§2.6), não só os 4.
