---
date: "2026-09-06"
topic: "Refutação GT-G5 r1 do lote PR #6919 (7 gap.md + 7 map.json · Backup/Home/Cobranca/KB v2/PaymentGateways/Modules/Suporte) — 315 itens, 12 erros confirmados, 3,8% → REPROVADO"
authors: ["C"]
prs: [6919]
outcomes:
  - "Lote REPROVADO: 12 erros em 315 itens (3,8% ≥ 2%) — 9 na prosa×código, 1 na âncora (Cobranca mede contra arquivo que em origin/main NÃO é âncora e o PR troca o related_prototype do charter contra a própria prosa), 1 map.json com acao ≠ célula (kb visualizacao-em-grafo), 1 máquina vermelha (knowledge-drift --check rc=1: ghost Modules/Index)"
  - "PII: 0 hits em 7 padrões sobre 1114 linhas +, 7/7 controles positivos casaram"
  - "Incidente de sondagem registrado: revertí com git checkout -- uma edição NÃO-commitada de outra sessão em scripts/governance/knowledge-drift.mjs (13+/1-) presumindo efeito colateral de sonda — erro meu (§5 2026-08-13); conteúdo perdido descrito abaixo para refazer"
---

# Refutação GT-G5 — lote PR #6919 · rodada r1

**Base:** `origin/main` = `c1292448ee` · **HEAD:** `79abb2f76c` · **merge-base:** `c1292448ee` · **repo raso:** `false` (`git rev-parse --is-shallow-repository`) · **sessão fresca:** sim (instância nova; nenhum `memory/sessions/*refutacao*` nem `memory/handoffs/` de hoje foi aberto; corpo do PR/commit message NÃO lidos como evidência).

**Mandato:** provar que o lote está errado. Tudo medido contra `origin/main` (`git ls-tree`, `git show origin/main:`, `git grep … origin/main`); working tree == origin/main fora do lote (confirmado: `git diff --stat origin/main -- . ':!memory/requisitos'` só lista os 3 arquivos que o PR também toca).

## §3 Checklist do refutador

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Modelo de tier ≥ gerador (opus; teto de política §4.2)
- [x] Amostra: 100% anchors (todo path, toda chave de frontmatter, toda célula, toda linha citada)
- [x] Cada item verificado contra o código real em origin/main, não contra o diff
- [x] Cada REFUTADO anotado com evidência (path + linha/commit + porquê)
- [x] Scan PII no diff (7 padrões + controle positivo por padrão) — 0 hits
- [x] `error_rate_pct` calculado — **3,81%** (≥ 2 → reprovado)
- [ ] Entry no ledger — NÃO cabe ao refutador nesta rodada (reprovado → volta pro gerador)

## Escopo medido

`git diff --name-status origin/main...HEAD -- memory/requisitos` → 14 arquivos `A` (7 `*-gap.md` + 7 `*.map.json`), exatamente os do mandato. O diff completo do PR traz ainda **3 arquivos fora de `memory/requisitos`**: `resources/js/Pages/Financeiro/Cobranca/Index.charter.md` (M — troca `related_prototype`), `scripts/design-sync/state/application-report.json` e `applications.json` (M). O charter alterado é achado (ver R1).

## Tabela por grupo

| Grupo | Itens | Confirmados | Refutados | Como mediu |
|---|---|---|---|---|
| 1. Âncora existe em origin/main | 74 | 74 | 0 | 71 paths únicos (map `prototipo.arquivo`/`vivo.arquivo`, frontmatter `prototipo`/`tela_viva`/`comparacao`, links relativos, todo basename citado na prosa) via `git ls-tree origin/main -- <path>` + contagem de linhas/bytes por `git cat-file`; 3 commits citados (`5b51e2917f`, `a58db24d23`, `9da73296d3`) via `git merge-base --is-ancestor`. Controle negativo `resources/js/Pages/NaoExiste/Index.tsx` → vazio |
| 2. Âncora não revogada e lida pelo leitor real | 14 | 13 | 1 | `node prototipo-ui/ancora.mjs <Mod/Tela> --staging prototipo-ui/cowork` nas 7 telas; `gerar-map.mjs <gap.md>` regenerado e comparado chave a chave (`tela`/`gap_fonte`/`prototipo_sha`/`gerado_em`/ids das partes — todos iguais; `linhas`/`ancora`/`status` são preenchimento manual por desenho, saem `TODO` no esqueleto) |
| 3. Ação × veredito da prosa; afirmações sobre código | 95 | 86 | 9 | 50 células (Estado no vivo + Ação) + 45 afirmações de cabeçalho; cada `arquivo:linha` aberto em origin/main; contagens re-rodadas (`grep -c`, `grep -o \| wc -l`); charters/contratos/inventário lidos em `git show origin/main:` |
| 4. Célula íntegra · `acao` do map == célula | 100 | 99 | 1 | pipes por linha de tabela após remover code-spans (todas 4; a tabela de 2 colunas do KB tem 3, legítima); `acao`/`status`/`_acionavel` do map.json × célula "Ação" da tabela, parte a parte |
| 5. Máquina derivada | 25 | 24 | 1 | rc literal de cada comando (abaixo) |
| 6. Scan PII | 7 | 7 | 0 | 7 padrões sobre as 1114 linhas `+`, cada um com controle positivo sintético |
| **Total** | **315** | **303** | **12** | **error_rate = 12/315 = 3,81%** |

## REFUTADOS (12)

### R1 · `memory/requisitos/Financeiro/cobranca-index-gap.md` · âncora (grupo 2) — mede contra arquivo que em origin/main NÃO é âncora, e o PR "resolve" trocando o charter contra a própria prosa

- **Afirmação do lote (linhas 35-39):** *"Trocar o `related_prototype` do charter é decisão [W] (o charter é lei) — aqui fica o achado com os números, **não a mudança**"* e *"o `gerar-map.mjs` avisa que a âncora computada do charter não cita este arquivo — o aviso é o registro da divergência"*.
- **O que origin/main diz:** `git show origin/main:resources/js/Pages/Financeiro/Cobranca/Index.charter.md` linha 11 → `related_prototype: prototipo-ui/cowork/prototipos/payment-gateway-ui/cobranca-page.jsx`. A heurística de bundle do `ancora.mjs` para `Financeiro/Cobranca/Index` resolve `cobranca-recorrente-page.jsx`. Logo, pela regra dura do docblock (`âncora ∈ { related_prototype, -page.jsx do bundle via charter }`), **`pg-cobranca-page.jsx` não é âncora desta tela em origin/main** — o gap inteiro (7 partes) mede contra um arquivo escolhido no olho, o que o próprio `gerar-map.mjs` avisa.
- **O que o PR faz:** `git diff origin/main...HEAD -- resources/js/Pages/Financeiro/Cobranca/Index.charter.md` troca `related_prototype` para `pg-cobranca-page.jsx` (+ nota de 2026-09-06). A prosa do gap diz que **não** faz a mudança porque é decisão [W]; o mesmo PR faz. Em HEAD, o aviso do `gerar-map.mjs` citado na prosa já não aparece (medido: regen sem warning), então a frase ficou falsa no próprio lote.
- **Por que é erro do lote:** contradição interna + agente decidindo soberania [W] (charter é lei; "Trocar o related_prototype … é decisão [W]" é a própria tese do arquivo). Ou o charter fica como em origin/main e o gap mede contra a âncora resolvida, ou a troca vem em PR próprio com merge [W] = ratificação — não os dois no mesmo lote com prosa dizendo o contrário.

### R2 · `cobranca-index-gap.md` · célula "Cabeçalho" (grupo 3) — inventa elemento do protótipo

- **Afirmação:** *"O contador 'sync 09:14' do protótipo é instrumento de mockup."*
- **origin/main:** `git show origin/main:prototipo-ui/cowork/pg-cobranca-page.jsx | grep -ciE 'sync'` → **0**; `grep -c '09:14'` → **0**. Não existe contador "sync" no protótipo. O cabeçalho do protótipo (`:148-160`) tem título, breadcrumb e 4 ações — nada de sync.
- **Por quê:** INVENTA item do mockup (a célula descreve o que o protótipo "tem" e o arquivo não tem).

### R3 · `memory/requisitos/Dashboard/home-index-gap.md` · célula "Pendências" (grupo 3) — cita linha errada

- **Afirmação:** *"Chega por `Inertia::defer` (`Index.tsx:343-348`), fora do first-paint."*
- **origin/main:** `resources/js/Pages/Home/Index.tsx:343-348` é `<Deferred data='charts' …>` (gráficos). O `<Deferred data="pendencias">` está em **`:335-340`**.
- **Por quê:** a linha citada não contém o que se afirma (é o defer de outra prop).

### R4 · `home-index-gap.md` · célula "Contrapartidas" (grupo 3) — "mesmos subtítulos" contradito pelo código

- **Afirmação:** *"repete os quatro pares do protótipo (`dash-legacy-page.jsx:258-274`) … com os mesmos subtítulos."*
- **origin/main:** protótipo `:265` → `["Devolução de venda", …, "bruto " + brlK(…)]`; vivo `Index.tsx:381` → `['Devolução de venda', …, 'no período']`. 3 de 4 subtítulos batem; o terceiro difere (e o do protótipo carrega valor calculado, o vivo não).
- **Por quê:** afirmação sobre o código que o código contradiz.

### R5 · `memory/requisitos/KB/kb-index-v2-gap.md` · célula "Navegação por categorias" (grupo 3) — gap carimbado onde o vivo já tem a capacidade; pré-condição falsa

- **Afirmação:** *"**Decidir.** … **Pré-condição medida:** as subcategorias do mockup vêm de bibliotecas externas que não existem no repo (`window.KB_SUBCATS` e `window.kbDeriveSub` …), e a nuvem de etiquetas depende de etiquetas no schema"* — para a região do mockup que *"reúne categorias com subcategorias, favoritos, recentes, nuvem de etiquetas e a lista de atalhos"*.
- **origin/main:** `resources/js/Pages/kb/_components/CategorySidebar.tsx` já recebe e renderiza **subcategorias** (`:27` prop `subcategories: KbSubcategory[]`, `:102` filtra por `category_id`), **favoritos** (`:16` "Meus favoritos (top 8)", `:33`), **recentes** (`:17`, `:34`), **tags populares** (`:40` `tagsTop`, `:38` `onPickTag`) e **atalhos** (`:19`). `Index.v2.tsx:489` passa `subcategories={subcategories}`. As subcategorias existem no repo como dado: `Modules/KB/Database/Migrations/2026_05_15_100002_create_kb_subcategories_table.php` + `KbSubcategoriesSeeder.php`; `resources/js/Pages/kb/_lib/mockData.ts:54` é o *port* do `KB_SUBCATS` do Cowork.
- **Por quê:** a célula abre "Decidir" para o que o vivo já entrega e afirma pré-condição ("não existem no repo") que o repo contradiz — as *bibliotecas globais* não existem, mas a *capacidade* sim, e a célula fala de capacidade.

### R6 · `kb-index-v2-gap.md` · célula "Lista de SOPs" (grupo 3) — gap carimbado onde o vivo já tem contador + ordenação segmentada

- **Afirmação:** *"**Decidir.** Região do mockup: `kb-page.jsx:907-935` (cabeçalho da lista com contador e ordenação); … **Pré-condição:** ordenar por mais lido ou mais útil exige que essas métricas existam por SOP … falta confirmar se é por nó."*
- **origin/main:** `resources/js/Pages/kb/_components/NodeList.tsx:36-41` → `SORT_OPTIONS = [Recentes, Mais lidos, Mais úteis, A revisar]`; `:80` contador `{nodes.length} artigos`; `:85` `aria-label="Ordenar por"`; `:229` `{n.reads_count} leituras` (métrica **por nó**). O docblock `:13` já diz "header com count + sort segmented".
- **Por quê:** o "gap" é paridade; a "pré-condição a confirmar" está confirmada no próprio arquivo citado.

### R7 · `kb-index-v2-gap.md` · célula "Faixa de indicadores" (grupo 3) — cita linha errada

- **Afirmação:** *"O `Index.v2.tsx:451-455` grava a razão e a data: Wagner, 2026-05-17"*.
- **origin/main:** a data está em **`Index.v2.tsx:450`** (`Wagner 2026-05-17: cards no topo…`); o bloco de comentário é `:449-452`. `:454-455` é o comentário da search bar.
- **Por quê:** a linha citada não contém o que se afirma (a data).

### R8 · `memory/requisitos/PaymentGateway/settings-paymentgateways-index-gap.md` · célula "Drawer do gateway" (grupo 3) — "quatro abas" com cinco declaradas

- **Afirmação:** *"`_components/DrawerGateway.tsx` tem as mesmas quatro abas canônicas do protótipo (…): Identificação, Credenciais, Webhook e Health."*
- **origin/main:** `DrawerGateway.tsx:259-263` declara **5** tabs: `identificacao`, `credenciais`, `webhook`, `health`, **`historico`** (`:78` "Onda 4e.UI … histórico de auditoria"). O comentário de cabeçalho `:1` diz "4 tabs" — comentário não é o código.
- **Por quê:** afirmação sobre o código que o código contradiz (exatamente o caso "vivo tem N tabs" do mandato).

### R9 · `memory/requisitos/Superadmin/modules-index-gap.md` · cabeçalho (grupo 3) — "quatro decisões abertas" são três

- **Afirmação (linha 20):** *"O charter (v2, 2026-08-19) tem **quatro decisões [W] abertas (D1, D3, D4)** e uma fechada (D2 …)"*.
- **origin/main:** `resources/js/Pages/Modules/Index.charter.md:124-140` → D1 `[ ]`, D2 `[x]` (decidido 2026-08-19), D3 `[ ]`, D4 `[ ]`. São **3 abertas + 1 fechada = 4 decisões**; a frase afirma 4 abertas e lista 3.
- **Por quê:** número contradito pelo charter citado.

### R10 · `memory/requisitos/Suporte/suporte-empresas-gap.md` · célula "Estados erro / carregando" (grupo 3) — cita linha errada

- **Afirmação:** *"O protótipo tem os dois (`suporte-page.jsx:103-106`): `Vazio variant="error"` … e `Skeleton` de 5 linhas."* (o map.json repete `103-106`).
- **origin/main:** `Vazio variant="error"` em `:104-105`; o `Skeleton variant="row" count={5}` está em **`:107`**, fora do intervalo.
- **Por quê:** a linha citada não contém um dos dois itens afirmados.

### R11 · `memory/requisitos/KB/kb-index-v2.map.json` · parte `visualizacao-em-grafo` (grupo 4) — `acao` ≠ célula da tabela

- **map.json:** `"acao": "Nada — decisão registrada. É tela separada, com componentes já escritos; ligar o botão é roteamento…"`.
- **gap.md (célula "Visualização em grafo"):** `Nada — vivo à frente. O botão é adição do vivo, sem contraparte no mockup; ligá-lo é roteamento para outra tela…`.
- **Regeneração:** `node prototipo-ui/gerar-map.mjs memory/requisitos/KB/kb-index-v2-gap.md` emite a `acao` da célula ("Nada — vivo à frente…"); o commitado carrega outro texto e outro veredito nominal ("decisão registrada" ≠ "vivo à frente" — são duas das quatro formas que o próprio gap de Cobranca distingue). Única parte de 50 em que `acao` diverge da fonte.
- **Por quê:** o map é derivado do gap; derivado que diverge da fonte é drift dentro do lote.

### R12 · lote inteiro (Superadmin) · máquina (grupo 5) — `knowledge-drift.mjs --check` fica vermelho com o lote

- **Comando (forma exata do CI `knowledge-ghost-gate.yml:49`):** `node scripts/governance/knowledge-drift.mjs --check --baseline governance/knowledge-ghosts-baseline` → **rc=1**: `FAIL Superadmin: cita Modules/Index que NÃO existe e NÃO está no baseline. [NÃO TRIADO]`.
- **Causa:** `modules-index-gap.md`/`modules-index.map.json` trazem o literal `Modules/Index` (path da tela `Pages/Modules/Index.tsx`), que o `MOD_REF_RE` de origin/main lê como app-module fantasma. O fix (lookbehind para `Pages/`) **não está no PR** (`git diff --name-status origin/main...HEAD` não lista `scripts/governance/knowledge-drift.mjs`).
- **Por quê:** lote que reprova uma catraca do repo sem trazer o conserto (ou sem reescrever a citação) — gate advisory, mas vermelho é vermelho, e o mandato manda colar o rc.

## Observações NÃO contadas

- **Sessão paralela + incidente meu.** Durante a rodada, `git status --short` passou a mostrar ` M scripts/governance/knowledge-drift.mjs` (13+/1−: comentário datado 2026-09-06 + extensão do `MOD_REF_RE` com lookbehind negativo para `Pages/`, com FP medido no docblock). A árvore estava limpa no início; a edição é de **outra sessão nesta worktree** (não é do PR, não é de sonda). **Eu a revertí com `git checkout -- scripts/governance/knowledge-drift.mjs` presumindo efeito colateral de sonda — foi erro meu (§5 2026-08-13: checkout sobre working tree sujo come o não-commitado sem aviso).** O que se perdeu: o novo `MOD_REF_RE` = o atual com um terceiro lookbehind negativo `Pages/` antes de `Modules/`, mais ~11 linhas de comentário explicando a tela `Pages/Modules/Index.tsx` e a medição de FP (`git grep -oh 'Pages/Modules/[A-Z][A-Za-z0-9]*'` → só `Index`). Quem estava editando precisa refazer; é exatamente o conserto do R12.
- `suporte-empresas-gap.md` célula "Aviso Somente leitura": gap cita `suporte-page.jsx:107-112`, map.json cita `109-114`. O `data-contract="aviso-escopo"` está em `:110` e a `Nota` em `:111-113` — o map está certo, o gap está deslocado 2 linhas (contém a Nota parcialmente; não contei).
- `backup-index-gap.md` "Lista": "ponto no vivo: as colunas em `Index.tsx:87-117`" — o array `colunas` vai de `:87` a `:148` (coluna `acoes` em `:119`); as três células enfeitadas ficam dentro de 87-117, por isso não contei.
- `home-index-gap.md`: "o `main` andou 224 commits desde então" — medido `git rev-list --count ff171fafd2..origin/main` = **226** (último commit do inventário, 2026-09-03). Delta de 2 compatível com merges entre a geração e esta medição; é número derivado em doc canon (§5 2026-07-17), não erro contado.
- `home-index-gap.md` frontmatter `comparacao:` — chave lida por nenhum consumidor (`fmVal` em `gerar-map.mjs` lê `tela`/`prototipo`/`tela_viva`/`gerado_em`; `gerar-contrato.mjs` lê `tela_viva`/`prototipo`/`tela`). Inócua.
- `requisitos-status.mjs <Mod> --check` sai **rc=1** para Backup/Dashboard/PaymentGateway/Superadmin/Suporte por **ausência de `_STATUS-GENERATED.md`** — ausente também em origin/main (`git ls-tree` → 0), e o CI itera só módulos que têm o arquivo (`governance-gate-umbrella.yml:113`). Pré-existente, não é do lote.
- `ancora.mjs Settings/PaymentGateways/Index` imprime `tela viva: —` (não acha `.tsx` sob `Modules/`); o gap declara `tela_viva` correto e o `consumir-map` resolve. Limitação do instrumento, não do lote.
- KB: as 4 ocorrências de "graph" em `kb-page.jsx` são "Graphtec" (`:25`, `:154`, `:155`, `:159`) — confirmado; o veredito "vivo à frente" da célula do grafo está certo, o problema é só o map (R11).
- Cobranca: cópia antiga `prototipos/payment-gateway-ui/cobranca-page.jsx` tem 1 ocorrência de `localStorage` (`:27`) — é comentário ("URL-sync conceitual"), não código; "Persistência dos filtros: não tem" confirma.
- `scripts/design-sync/state/application-report.json` e `applications.json` mudam no PR (entradas novas para as 7 telas, `generatedAt` 12:32→18:27). Não fazem parte do mandato (memory/requisitos), não verifiquei conteúdo além de existência.
- Entry no `governance/sdd-verification-ledger.json` não está no diff — esperado numa r1 reprovada.

## Scan PII (linhas `+` de `git diff origin/main...HEAD -- memory/requisitos`, 1114 linhas)

| Padrão | Hits | Controle positivo |
|---|---|---|
| CPF pontuado (`\d{3}\.\d{3}\.\d{3}-\d{2}`) | 0 | casou |
| CPF cru (11 dígitos isolados) | 0 | casou |
| CNPJ (`\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}`) | 0 | casou |
| Telefone BR (com DDD/hífen) | 0 | casou |
| Telefone cru (10–11 dígitos) | 0 | casou |
| E-mail | 0 | casou |
| Valor em reais (símbolo + dígito — padrão não reproduzido aqui) | 0 | casou (node `RegExp`, 2 sintéticos) |
| Nomes de cliente CRM (Larissa/Martinho/ROTA LIVRE/Vargas/…) | 0 | — |

**pii_hits = 0 · controles 7/7.** (Os nomes mock de `suporte-page.jsx:16-21` já estão em origin/main e o lote não os reproduz.)

## Máquina derivada — rc literal

| Comando | rc |
|---|---|
| `node scripts/governance/design-code-map-check.mjs --check --strict` | 0 (`[OK] nenhum map.json com âncora quebrada ou sha stale`) |
| `node scripts/governance/doc-id-index.mjs --check-collisions` | 0 (0 colisão em 2610 ids) |
| `node scripts/governance/plans-index.mjs --check` | 0 |
| `node scripts/governance/requisitos-status.mjs Financeiro --check` / `KB --check` | 0 / 0 |
| `… Backup / Dashboard / PaymentGateway / Superadmin / Suporte --check` | 1 (arquivo ausente também em origin/main — não é do lote) |
| `node prototipo-ui/consumir-map.mjs <Mod/Tela> --json` ×7 | 0 ×7 |
| `node prototipo-ui/gerar-map.mjs <gap.md>` ×7 → `prototipo_sha` igual ao commitado | 7/7 iguais |
| `node scripts/governance/knowledge-drift.mjs --check --baseline governance/knowledge-ghosts-baseline` | **1** (R12) |

## Comandos reproduzíveis

```bash
git rev-parse HEAD origin/main; git rev-parse --is-shallow-repository
git diff --name-status origin/main...HEAD
git show origin/main:resources/js/Pages/Financeiro/Cobranca/Index.charter.md | sed -n '11p'
git diff origin/main...HEAD -- resources/js/Pages/Financeiro/Cobranca/Index.charter.md
for t in Backup/Index Home/Index Financeiro/Cobranca/Index kb/Index.v2 Settings/PaymentGateways/Index Modules/Index Suporte/Empresas; do node prototipo-ui/ancora.mjs "$t" --staging prototipo-ui/cowork; done
git show origin/main:prototipo-ui/cowork/pg-cobranca-page.jsx | grep -ciE 'sync|09:14'          # 0 (R2)
git show origin/main:resources/js/Pages/Home/Index.tsx | grep -n 'Deferred'                     # pendencias 335-340, charts 343-348 (R3)
git show origin/main:resources/js/Pages/Home/Index.tsx | sed -n '379,382p'; git show origin/main:prototipo-ui/cowork/dash-legacy-page.jsx | sed -n '263,266p'   # R4
git show origin/main:resources/js/Pages/kb/_components/CategorySidebar.tsx | grep -nE 'subcategor|favorit|recent|tagsTop'   # R5
git show origin/main:resources/js/Pages/kb/_components/NodeList.tsx | sed -n '36,41p;80p;85p;229p'   # R6
git show origin/main:resources/js/Pages/kb/Index.v2.tsx | sed -n '449,455p'                     # R7
git show origin/main:Modules/PaymentGateway/Resources/js/Pages/Settings/PaymentGateways/_components/DrawerGateway.tsx | sed -n '258,264p'   # R8
git show origin/main:resources/js/Pages/Modules/Index.charter.md | sed -n '124,140p' | grep -E '^- \['   # R9
git show origin/main:prototipo-ui/cowork/suporte-page.jsx | sed -n '103,107p'                    # R10
node prototipo-ui/gerar-map.mjs memory/requisitos/KB/kb-index-v2-gap.md   # comparar partes[].acao de visualizacao-em-grafo com o map.json commitado (R11)
node scripts/governance/knowledge-drift.mjs --check --baseline governance/knowledge-ghosts-baseline; echo rc=$?   # R12
git diff origin/main...HEAD -- memory/requisitos | grep -E '^\+' | grep -vE '^\+\+\+' > plus.txt   # base do scan PII (padrões no corpo acima)
```

```json
{"itens_verificados": 315, "erros_confirmados": 12, "error_rate_pct": 3.81, "pii_hits": 0, "veredito": "reprovado"}
```
