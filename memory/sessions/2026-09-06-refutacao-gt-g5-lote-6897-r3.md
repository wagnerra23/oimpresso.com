---
date: "2026-09-06"
topic: "Refutação GT-G5 rodada 3 — PR #6897 (8 gap.md com tabela de partes + 9 map.json + _STATUS-GENERATED)"
authors: ["C"]
prs: [6897]
outcomes: ["reprovado — 6 erros em 105 itens (5,71%); PII 0"]
---

# Refutação GT-G5 · lote PR #6897 · rodada 3

> Refutador em sessão fresca, sem contexto do gerador nem das rodadas r1/r2 (nenhum arquivo `memory/sessions/*refutacao*` foi aberto).
> Modelo do refutador: **Fable 5.1** (tier acima de opus — teto de política da §4.2 satisfeito por excesso).
> Base medida: `HEAD 5f57a63841` (branch `claude/q6-gap-md-tabela-e-11-mapas`) × `origin/main 26ac293f46`, `git fetch` feito no início da sessão.
> Protocolo: [PROTOCOLO-REFUTADOR-BACKFILL.md](../requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md) §2, §3, §4.

## Checklist §3

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier SUPERIOR ao gerador (fable > opus; §4.2 aceita opus×opus como teto, fable é acima)
- [x] Amostra: **100 % anchors** (tipo `anchors` — todos os paths, todas as chaves de frontmatter, todas as 59 linhas de tabela). Sem seleção aleatória (não há prosa amostrada) — seed n/a
- [x] Cada item verificado contra origin/main (`git ls-tree origin/main`, `git show origin/main:<path>`), não contra o texto do PR
- [x] Cada REFUTADO anotado com evidência (path + linha + porquê) — seção "Refutados"
- [x] Scan PII no diff (linhas adicionadas em `memory/requisitos`) com **controle positivo por padrão** — 0 hits
- [x] `error_rate_pct` calculado: **5,71 %** — **≥ 2 → lote REPROVADO**
- [ ] Entry no ledger `governance/sdd-verification-ledger.json` — **NÃO existe no PR** (`grep -c '"pr": 6897'` = 0). Pendente do gerador antes do merge (§2.7)

## Lote (medido, não lido do PR)

`git diff --name-status origin/main...HEAD -- memory/requisitos` → 23 arquivos: 8 `*-gap.md` modificados (Cliente/clientes, Compras/compras, KB/kb, OficinaAuto/kanban-producao, Produto/produtos, RecurringBilling/cobranca-recorrente, Sells/vendas-create, Sells/vendas-index) · 9 `*.map.json` novos (os 8 acima + `Crm/clientes.map.json`, derivado de `Crm/clientes-gap.md` que **já existia em main** e não mudou) · 6 `_STATUS-GENERATED.md` (Crm e RecurringBilling novos; Estoque, Fiscal, Ponto, Repair modificados). Fora de `memory/requisitos` o PR toca só `governance/sdd-scorecard-baseline.json` (absorção 11→12).

Conferências de perímetro:
- `_DesignSystem/pageheader-canon-v3-gap.md` e `sidebar-v3-unificado-gap.md`: **byte-idênticos a origin/main** (`git diff --quiet` = 0) e **sem** `.map.json` correspondente (0 em `_DesignSystem/`) — revert confirmado.
- `_processo/PLANS-INDEX-GENERATED.md`: **sem diff** vs main (regeneração foi no-op); `plans-index.mjs --check` rc=0.
- Regeneração dos 9 maps com `node prototipo-ui/gerar-map.mjs <gap.md>`: **9/9 idênticos** aos commitados (exceto `gerado_em`), i.e. os maps derivam mesmo das tabelas.

## Tabela por grupo

| # | Grupo | Itens | CONFIRMADO | REFUTADO |
|---|---|---|---|---|
| 1 | `map.json` — `partes[].prototipo.arquivo` / `vivo.arquivo` existem em origin/main (1 por path distinto por mapa; Crm = só `TODO`, 0 itens) | 16 | 16 | 0 |
| 2 | `-gap.md` — `tela_viva` / `prototipo` do frontmatter existem e são lidos (`fmVal` regex `^key:` multiline, `resolveGap` por path) | 16 | 15 | **1** |
| 3 | Tabela derivada — coluna "Ação" × veredito da prosa (1 por linha) + integridade de célula | 59 | 54 | **5** |
| 4 | `requisitos-status.mjs <Mod> --check` (Crm, Estoque, Fiscal, Ponto, RecurringBilling, Repair) + `plans-index.mjs --check` | 7 | 7 | 0 |
| 5 | `design-code-map-check.mjs --check --strict` | 1 | 1 | 0 |
| 6 | Scan PII (6 padrões, cada um com controle positivo) | 6 | 6 | 0 |
| | **Total** | **105** | **99** | **6** |

### Grupo 1 — paths dos maps (16/16 existem)

`git ls-tree origin/main -- <path>` = 1 linha para cada um: `prototipo-ui/cowork/{clientes,compras,kb,oficina,produtos,cobranca-recorrente,vendas-create,vendas}-page.jsx` e `resources/js/Pages/{Cliente,Compras,kb,Produto,RecurringBilling,Sells}/Index.tsx`, `Repair/ProducaoOficina/Index.tsx`, `Sells/Create.tsx`. `Pages/kb` é minúsculo em main (tree `1f8d1912…`) — o path do KB bate. `Crm/clientes.map.json` tem `prototipo.arquivo`/`vivo.arquivo` = `TODO` e `prototipo_sha: sem-arquivo` (frontmatter do gap de main não declara path) — sem item, e o `design-code-map-check --strict` aceita (24 TODO "pendente de preenchimento, não é drift").

### Grupo 2 — frontmatter (15/16)

Todos os 16 valores existem em origin/main (mesma lista do grupo 1) e são lidos: `gerar-contrato.mjs:36 fmVal` (regex `^key:\s*(.+)$` `im`) e `gerar-map.mjs:153-155` consomem `tela`/`prototipo`/`tela_viva`; o Produto, que ganhou frontmatter novo com `id:` na 1ª linha, é lido igual (a regex não exige ordem). Cross-check de âncora que o próprio `gerar-map` roda:
- Produto/Index: charter `related_prototype: n/a (herda PT-01)` + `bundle_source: produtos-page.jsx` + §Material visual cita `produtos-page.jsx` (charter l.5-6, 178) → o WARN é só pelo `n/a`; coerente. CONFIRMADO.
- Sells/Create: charter sem `related_prototype`/`bundle_source`; a prosa de main cita `vendas-create-page.jsx` 2×. CONFIRMADO (existe e é lido).
- **Repair/ProducaoOficina: REFUTADO** — ver abaixo.

### Grupo 3 — 59 linhas (54/59)

Integridade de célula (script sobre HEAD): 59/59 linhas com exatamente 3 células, crases pares em toda célula, coluna Ação não-vazia; nenhuma tabela `Parte`+`Ação` anterior à derivada no mesmo arquivo (o `parsePartes` pega a 1ª — conferido que a 1ª é a derivada nos 8). RecurringBilling trocou `\|` da prosa por `／` para não quebrar célula — conteúdo preservado.

Proveniência mecânica de cada "Ação" (trecho inicial procurado na prosa de origin/main):

| Arquivo | Linha da prosa que a Ação copia | Veredito |
|---|---|---|
| Compras (6) | `- Gap real:` l.24/30/36/42/48/54 — todos no "Adotar (ordem sugerida)" 1-6 | 6 ✓ |
| OficinaAuto (8) | `- Gap real (candidato)` l.30/36/48/55/75/86/96; "Nada — vivo à frente (STALE)" sintetiza l.42-43 (`Esforço — (não adotar lógica…)`) | 8 ✓ |
| Produto (6) | `\| Gap real \|` l.19/29/39/49/59/69 — adotar #1-3 ou "NÃO adotar **sem** ADR/SPEC+backend" (gated, não fechado) | 6 ✓ |
| Sells/Create (9) | `\| Gap real \|` l.37/46/55/64/73/82/91/100/109 — nenhum na lista "Não adotar" (centavos/toggle/método único) | 9 ✓ |
| Sells/Index (8) r2 | "Nada — divergência intencional" = l.27+l.95; demais copiam "Mockup tem que vivo NÃO tem" (que nesse arquivo É a linha de gap) + `_pendente_` onde a prosa diz | 8 ✓ |
| RecurringBilling (9) r2 | 6× "Nada — `— · —`" = coluna Esforço·risco l.33/34/36/37/40/41/42/43; 3× Decidir = l.35 (micro-gap A recuperar), l.24 (ADOTAR-PARCIAL #1), l.39 (única ideia visual) | 9 ✓ |
| Cliente (7) r3 | "Nada — banner de invalidade (l.19) + dono vigente" — HEAD l.19 = *"registro datado de 2026-06-30 medido por rota inválida — não é base para decidir hoje"*; `PARIDADE-area-cliente-diagnostico-e-ondas.md:199` = *"Não adotar o card Faturamento"* | 7 ✓ (o parêntese sobre Faturamento repete em 7 linhas, inclusive onde não há card — template, não inversão) |
| **KB (6)** | **`- Mockup tem:` l.26/35/44/53/62/71 — a coluna de DESCRIÇÃO, não a `- Gap real:` (l.28/37/46/55/64/73) nem o §Veredito l.84-90** | 1 ✓ · **5 ✗** |

### Grupos 4 e 5 — comandos

```
requisitos-status Crm/Estoque/Fiscal/Ponto/RecurringBilling/Repair --check  rc=0 (6×)
plans-index.mjs --check                                                   rc=0
design-code-map-check.mjs --check --strict                                rc=0
  → 21 map.json · [OK] nenhum com âncora quebrada ou sha stale · 24 âncoras TODO (não é drift)
  → gap.md SEM map: só os 2 de _DesignSystem (revertidos de propósito)
```

## Refutados (lista completa, com evidência)

### R-1 · Grupo 2 · `OficinaAuto/kanban-producao-gap.md` frontmatter `prototipo: prototipo-ui/cowork/oficina-page.jsx` → propagado a `kanban-producao.map.json` `partes[*].prototipo.arquivo`

- **O que o lote afirma:** o par âncora `oficina-page.jsx` ↔ `resources/js/Pages/Repair/ProducaoOficina/Index.tsx` (`tela: Repair/ProducaoOficina`) — o map se declara "ANCHOR-MAP POR REGIÃO … nunca fabricar".
- **O que o canon diz (origin/main):** `resources/js/Pages/Repair/ProducaoOficina/Index.charter.md:5-6` = `related_prototype: n/a (herda PT-05 Kanban)` + `bundle_source: repair-page.jsx`; **l.14-17**, comentário datado: *"2026-06-30 (musing-elion): removido related_prototype: oficina-page.jsx — MIS-ANCHOR. Esta tela serve Repair/JobSheet (vertical genérico), NÃO a OficinaAuto de veículo. oficina-page.jsx é o kanban de VEÍCULO da OficinaAuto → ancora em ServiceOrders/Board (parent OficinaAuto)."* `node prototipo-ui/ancora.mjs Repair/ProducaoOficina` → *"sem âncora: n/a — declaração legítima"*.
- **A máquina avisou e o lote seguiu:** `node prototipo-ui/gerar-map.mjs memory/requisitos/OficinaAuto/kanban-producao-gap.md` (stderr): *"⚠️ âncora computada do charter (…ProducaoOficina/Index.charter.md) não cita prototipo-ui/cowork/oficina-page.jsx — confira o frontmatter 'prototipo:' do gap.md (âncora nunca no olho)"*.
- **Por que é erro e não só "existe":** o path existe e é lido (essa metade passa), mas o lote **cristaliza num artefato versionado de âncora** o par que o charter dono revogou nominalmente como MIS-ANCHOR — precedência charter > SPEC e §5 2026-06-30 (*"âncora = computada do charter, nunca escolhida no olho"*). A prosa do gap (2026-06-30, mesma data da revogação) comparou esse par; o lote promove a comparação datada a âncora vigente. Conserto é decisão: ou `tela_viva`/`tela` → OficinaAuto ServiceOrders/Board (onde o charter manda ancorar `oficina-page.jsx`), ou `prototipo` → `repair-page.jsx`, ou não gerar este map.

### R-2..R-6 · Grupo 3 · `KB/kb-gap.md` tabela derivada — 5 linhas copiam a coluna de DESCRIÇÃO e reabrem vereditos fechados

Regra do próprio lote (cabeçalho da tabela): *"'Decidir.' repete o gap real já escrito na seção correspondente"*. Medido: as 6 linhas do KB copiam `- Mockup tem:` (descrição do protótipo), não `- Gap real:`; e o §Veredito de main (l.84-90) fecha como **"NÃO adotar (Tier 0 / fora de escopo)"**: *edição inline/Composer (ADR 0061), categorias/níveis/equipamentos de gráfica, Troubleshooter, Trilhas, Apresentação, Imprimir SOP, votação útil/desatualizado, comentários inline, anexar-a-OS, métricas de leitura*. É o mesmo defeito pelo qual a r1 foi reprovada (o cabeçalho r2 diz isso), corrigido em Cliente/RecurringBilling/Sells-Index e **não** no KB. O `acao` do `kb.map.json` carrega esse texto, e o `gerar-contrato.mjs` emitiria `_acao` para 6 seções "acionáveis" com itens vetados.

| # | Linha (HEAD `KB/kb-gap.md`) | Ação copia | Reabre (prosa main) |
|---|---|---|---|
| R-2 | l.103 Header / página | l.26 *"barra de 6 botões (Trilhas, …, Troubleshooter, ⌘K, + Novo artigo)"* | l.28: *"botões de criação editorial … pertencem ao escopo editorial, não ao browser"*; l.90 NÃO adotar Trilhas/Troubleshooter/Composer. Único gap aberto da parte = "Perguntar ao KB" (l.88 #4) — omitido como tal |
| R-3 | l.104 Navegação / categorias | l.35 *"árvore de categorias com hue por vertical gráfica (Produção, Equipamentos, Pré-impressão…)"* | l.37: *"Categorias do mockup (hue gráfica) NÃO se aplicam — vivo categoriza por type/module"*; gap aberto = Favoritos/Recentes/tags (l.86 #2) — omitido |
| R-4 | l.106 Lista de artigos / docs | l.53 *"badge de nível (iniciante/inter/avançado), equipamento … Mais lidos/Mais úteis"* | l.55: *"Cards-vs-tabela é escolha de densidade, não gap"*; l.90 NÃO adotar níveis/equipamentos/métricas de leitura |
| R-5 | l.107 Editor / detalhe | l.62 *"comentários por bloco … footer com votação … Composer full"* | l.65-67: *"Composer/editar inline = NÃO ADOTAR (viola Tier 0 git-canônico)"*; l.90 NÃO adotar votação/comentários inline. Abertos = TOC/prev-next/related/histórico/IA (l.85-88) |
| R-6 | l.108 Drawer / modais | l.71 *"Troubleshooter … Trilhas de aprendizado … Modo apresentação … Imprimir SOP"* | l.73: *"Troubleshooter/Trilhas/Apresentação/Imprimir SOP = escopo editorial gráfico, não aplicável"*. Abertos = Saúde do KB + IA (l.88 #4) |

Linha l.105 Busca (⌘K + fallback IA): copia l.44 "Mockup tem", mas o conteúdo coincide com o `Gap real` l.46 e com o adotar #3 (l.87); nada fechado é reaberto → CONFIRMADO (com a ressalva de que a prosa proíbe trocar a busca server-side, e a Ação não propõe isso).

## Scan PII (grupo 6)

Corpus: 1.562 linhas `^+` de `git diff origin/main...HEAD -- memory/requisitos`. Controle positivo = string com um exemplar de cada padrão passada pelo mesmo `grep -E`.

| Padrão | Regex | Controle positivo | Hits no diff |
|---|---|---|---|
| CPF pontuado | `[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}` | 1 | **0** |
| CPF cru (11 dígitos) | `(^\|[^0-9])[0-9]{11}([^0-9]\|$)` | 1 | **0** |
| CNPJ | `[0-9]{2}\.[0-9]{3}\.[0-9]{3}/[0-9]{4}-[0-9]{2}` | 1 | **0** |
| Telefone BR | `(\(?[0-9]{2}\)?[ -]?)?9?[0-9]{4}-[0-9]{4}` | 1 | **0** |
| E-mail | `[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}` | 1 | **0** |
| Valor em reais | `R\$[[:space:]]?[0-9]` | 1 | **0** |

`pii_hits = 0`. A linha nova do Compras escreve *"meta R$ [redacted Tier 0]"* — correto. Nomes que aparecem nas linhas novas (`pendentes:bruna`, "WR2 Sistemas", "Padaria, Auto Posto", "Eliana [E] / Larissa") são mock do protótipo/persona já presentes na prosa de main, não dado de cliente do CRM.

## Observações fora da contagem (não são itens do lote, mas o gerador deve ver)

1. **Referência pendurada em doc canon:** o cabeçalho r2 de Cliente, RecurringBilling e Sells/Index cita `memory/sessions/2026-09-06-refutacao-gt-g5-lote-6897.md` — **não versionado em HEAD** (`git ls-tree HEAD` = 0; há 2 arquivos untracked com esse prefixo no working tree, não abertos). Ou entra no PR, ou a citação sai.
2. **Ledger** sem entry para 6897 (§2.7) — obrigatória no mesmo PR antes do merge.
3. `governance/sdd-scorecard-baseline.json` `nota_absorcao_2026_09_06` diz *"os outros 9 modulos tocados"* e lista 8, incluindo `_DesignSystem` — que está byte-idêntico a main (revertido). Fora de `memory/requisitos`, não contado; é imprecisão de contagem numa nota que se apresenta como medida.
4. `Compras/compras-gap.md` l.30 (main, **não tocado** pelo lote) carrega um valor de meta em reais vindo do mock do protótipo. Não é hit do lote (linha não adicionada), mas é higiene pré-existente do hook `block-brl-values-in-memory`.
5. `Crm/clientes.map.json` nasce inteiro `TODO`/`sem-arquivo` porque o gap de main não declara `prototipo:`; `ancora.mjs` diz *"sem charter pra essa tela"*. Válido pelo `--strict`, mas é um map sem âncora nenhuma — vale perguntar se deve existir.

## Veredito

Dois defeitos independentes: (a) uma âncora que o charter dono revogou como MIS-ANCHOR entrou num map versionado apesar do WARN da máquina; (b) a correção da r1 (ler VEREDITO, não DESCRIÇÃO) não foi aplicada ao KB, que reabre 5 vereditos "NÃO adotar" — inclusive um Tier 0 (edição inline viola ADR 0061). 6/105 = 5,71 % ≥ 2 % → **REPROVADO**; volta ao gerador e o lote inteiro é re-refutado (§2.6).

```json
{"itens_verificados": 105, "erros_confirmados": 6, "error_rate_pct": 5.71, "pii_hits": 0, "veredito": "reprovado"}
```
