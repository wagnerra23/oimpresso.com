---
id: requisitos-crm-cliente-index-visual-comparison
inertia_target: resources/js/Pages/Cliente/Index.tsx
date: 2026-05-15
---

# Visual Comparison — Cliente/Index (W1-B3)

## Blueprint Cowork
`prototipo-ui/cowork/Wagner/clientes-page.jsx`

## Approval status (ADR 0114)
- ✅ Pattern reuse aprovado via ADR 0149 (Index = canon do blueprint)
- ⏳ Screenshot Wagner pendente (Wave 1 batch sync após canary biz=1)

## Telas derivadas que herdam aprovação
- Cliente/Index (esta — canon)
- Cliente/Create, Cliente/Show, Cliente/Edit (mesma família visual)

## Telas com divergência declarada
- Cliente/Import (wizard upload)
- Cliente/Ledger (tabela financeira)
- Cliente/Map (split-pane)

## 15 dimensões mwart-comparative V4

| Dimensão | Cowork | Inertia | Status |
|---|---|---|---|
| 1. Hierarchy header | h1 22-24px font-semibold | ✅ aplicado | OK |
| 2. KPI cards | 4 cards rounded-xl border | ✅ aplicado | OK |
| 3. Filter pills | rounded-full canon | ✅ aplicado | OK |
| 4. Table density | px-4 py-3 | ✅ aplicado | OK |
| 5. Status badge | rounded-full border 11px | ✅ aplicado | OK |
| 6. Avatar | rounded-md monocromático | ✅ aplicado | OK |
| 7. Drawer 480px right | Sheet UI canon | ✅ aplicado | OK |
| 8. Cores semânticas | rose/sky/stone/emerald | ✅ aplicado | OK |
| 9. Tabular nums | tabular-nums em dinheiro/data | ✅ aplicado | OK |
| 10. Empty state | mensagem PT-BR centrada | ✅ aplicado | OK |
| 11. Loading state | spinner + label | ✅ aplicado | OK |
| 12. Pagination | controls + per_page select | ✅ aplicado | OK |
| 13. Search debounce | 300ms | ✅ aplicado | OK |
| 14. localStorage prefix | oimpresso.cliente.* | ✅ aplicado | OK |
| 15. PII mask | tax_number_masked server | ✅ aplicado | OK |

## Acessibilidade (a11y)
- ARIA labels em filter pills + sort buttons + nav
- aria-sort em SortableTh
- aria-current em pill ativa
- aria-label em search input + actions menu

## Performance targets
- p95 first-paint < 1200ms (com 50 customers)
- KPIs defer < 800ms
- Customers paginate defer < 1500ms

## Gate F1.5 (Wagner aprova screenshot)
- ⏳ Pendente — Wagner valida após canary biz=1 mostrar render correto em prod

## Medição 2026-10-14 (thread cliente/02) — `design-diff` nos dois lados

Mesma sonda (`node scripts/design/design-diff.mjs --probe`, sha256 `a4c1cbf6…8a861c27`) injetada em:
- **prod:** `https://oimpresso.com/cliente` (sessão logada, tema escuro, 1280×900);
- **protótipo:** `prototipo-ui/cowork/Wagner/oimpresso.com.html`, rota `clientes`, tema escuro, 1280×900, servido a partir da raiz do repo (o shell só acha o DS em `../../design-system/` assim — servindo a pasta do dono, 2 folhas voltam vazias e o `--compare` acusa `NÃO MEDI`).

| Item | prod | protótipo | veredito |
|---|---|---|---|
| Título `h1` | 22px · 700 | 22px · 600 | tamanho IGUAL; o "18px" de 2026-09-18 era a página errada (`_saida-02`) |
| Altura da linha | 55,56px | 58,69px (68–71px com 2ª linha de dado) | segue o conteúdo da célula |
| Colunas | foto · Cliente · Tipo · Documento · Cidade/UF · Frescor · Saldo · OS · Tags · ★ · ⋮ | Cliente (com foto) · Tipo · Documento · Cidade/UF · Frescor · Saldo · OS · Tags · **Última OS** · ★ · ⋮ | **DIVERGE** — coluna deslocada em 1 |

Com a coluna deslocada, o `--compare` pareia `col1` da prod (Cliente) com `col1` do protótipo (Tipo): os 12 `DIVERGE (bug)` por célula daquela rodada são, na maioria, efeito do deslocamento, não da célula.

**Decisão [W] 2026-10-14:** seguir o protótipo nas duas — foto dentro da célula Cliente e coluna "Última OS" (UC-CIDX-05). A re-medição depois do deploy fica registrada aqui.

### Re-medição pós-deploy do #9414 (2026-10-14, mesma sonda, prod × protótipo)

- **Colunas alinhadas:** `Cliente(+foto) · Tipo · Documento · Cidade/UF · Frescor · Saldo · OS · Tags · Última OS · ★ · ⋮` nos dois lados. Somem os `DIVERGE` de alinhamento das colunas 5 e 7, que eram efeito do deslocamento.
- **Última OS:** com `?sort=total_os&dir=desc`, 6 de 50 linhas têm OS > 0, e as 6 mostram o link (`/sells/{id}`, IBM Plex Mono 11.5px, peso 600); as 44 com OS = 0 mostram "—" (0 linhas com OS e sem link). Na ordenação padrão (recentes) nenhuma das 50 tem venda, e por isso a sonda, que lê só a 1ª linha, registra `col8 sem elemento clicável`. É efeito do dado, não defeito.
- **Altura da linha:** 55,56px, sem mudança.
- **O que segue divergindo** (10 `DIVERGE (bug)`, fora da decisão desta thread): Tipo, Documento e Cidade em mono no protótipo; pílulas/dot de Frescor e Tags; alinhamento das colunas ★ e ⋮ (center no protótipo). Candidatos a uma próxima thread.

## Medição 2026-11-25 — todas as pendências de Contatos (prod × protótipo), lista de execução

> Escrita para o Claude que vai resolver: cada item diz onde está, o que a produção faz hoje, o que deve
> fazer e como conferir. Os números de linha foram lidos em `origin/main` `cb823b8294` (2026-11-25) e
> podem ter andado: confirme com `grep` antes de editar.

### Como foi medido

- **Protótipo:** `node scripts/design/ancora.mjs Cliente/Index` → `prototipo-ui/cowork/Wagner/clientes-page.jsx`
  (+ `cliente-drawer760.jsx` e `cli-tabs.jsx`), rota `clientes` do `oimpresso.com.html`.
  ⚠️ O protótipo só carrega o Design System se a URL contiver `/prototipo-ui/cowork/` (o host decide a base
  do DS por `location.pathname`). Servido de dentro de `prototipo-ui/`, o `CliTabs` não acha o `TabBar` e a
  faixa de abas **some sem erro**. Sirva uma pasta que tenha `prototipo-ui/cowork/Wagner` e
  `prototipo-ui/design-system` com esses nomes.
- **Produção:** `https://oimpresso.com/cliente`, empresa 1, perfil Operacional, só leitura (nada foi salvo).
- Os dois lados no tema escuro, 1600×960, mesma sonda (`design-diff.mjs --probe`) e mesmo extrator de estilo
  calculado por região. **D0 (mesma tela): 24/24 textos do contrato nos dois lados.** Canário: a sonda pegou o
  roxo do botão principal (0,55 × 0,70), diferença já conhecida.
- Saída do `--compare` (aba Clientes, gaveta aberta): `DIVERGE(bug)` 4 · gaveta a classificar 37 · gaveta
  `63 de 102` propriedades iguais.

### Como executar (regras)

- Forma (layout, cor, tipografia, ícone, rótulo, estado visual): o protótipo manda ([UI-0029](../_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)).
  Comportamento provado por teste, dado e permissão seguem o código e os testes ([proibicoes.md](../../proibicoes.md) §precedência).
- Itens da seção **B** dependem de decisão do [W]. Não implemente antes.
- Uma onda por PR (≤300 linhas). Ordem sugerida: **A** inteira → C.1 cabeçalho/abas/KPIs → tabela/linha/rodapé →
  filtros → gaveta (cabeçalho, abas, rodapé) → gaveta (conteúdo de cada aba) → aba Todos → abas por papel (depois de B-05).
- Antes de dizer pronto: re-medir com `design-diff --compare` nos dois lados no tema escuro e abrir a tela em
  produção (Regra 0 do [PROTOCOLO-COMPARACAO-RUNTIME](../_DesignSystem/PROTOCOLO-COMPARACAO-RUNTIME.md)).
  Atualizar `Cliente/Index.casos.md` e o charter quando o comportamento mudar.

### A · Defeitos de produção (corrigir com ou sem o protótipo)

- **A-01 — A gaveta diz "Pessoa jurídica" para todo contato.** `Pages/Cliente/Index.tsx:2165` cria
  `useState<'PF'|'PJ'>('PJ')` e não há nenhuma chamada a `setTipo`. Medido: contato com CPF, seletor
  "Pessoa física" marcado na aba Identificação, cabeçalho "Pessoa jurídica · cadastrado há…".
  Deve: ler o tipo do mesmo campo que a `IdentificacaoTab` usa. Conferir abrindo um PF e um PJ.
- **A-02 — Operações › Pessoas fica eternamente em "Carregando pessoas de contato…".**
  `Pages/Cliente/_drawer/OssTab.tsx:198` passa `contact_persons={undefined}`;
  `_show/PessoasContatoTab.tsx:23` mostra o carregando enquanto o valor for `undefined`. Nenhuma requisição sai
  (conferido na aba Rede). Deve: buscar por endpoint JSON próprio, como `SalesTab` e `PaymentsTab` já fazem
  (comentário "Fix 2026-06-08" no mesmo arquivo), e cair no estado vazio que o componente já tem.
- **A-03 — Inglês na aba Classificação.** `_drawer/ClassificacaoTab.tsx:562`: "Sells e Financeiro impedem checkout
  e cobrança quando ativo". Protótipo: "Bloquear cobrança/venda · Vendas e Financeiro impedem a venda e a cobrança
  enquanto estiver ligado".
- **A-04 — Aba IA sem acentos.** `_drawer/IATab.tsx:202, 212, 229, 266` e `_show/RiscoClienteCard.tsx:144`:
  "analises", "propoe", "voce", "e editavel", "historico", "sumario", "Proxima acao", "Saudavel". Revisar o
  arquivo inteiro.
- **A-05 — "Novo outros".** `Index.tsx:255` (`singular: 'outros'`) + `Index.tsx:1103` (`Novo ${singular}`).
  Protótipo: "Novo cadastro" (`clientes-page.jsx` `ROLE_DEFS.other.cta`).
- **A-06 — "1 ativos".** `Index.tsx:1011` não trata singular. Deve: "1 ativo".
- **A-07 — Inglês nos atalhos e no fechar da gaveta.** `Index.tsx:2855` "Busca rápida (Command Palette)" →
  protótipo "busca global do sistema". O × da gaveta tem nome acessível "Close"
  (`Components/ui/sheet.tsx:86`, componente compartilhado) → "Fechar".
- **A-08 — Cabeçalhos ordenáveis sem caixa alta.** `Index.tsx:1898-1930` (`SortableTh`): o `uppercase` está no
  `<th>`, mas o texto mora num `<button>`, que zera `text-transform`. Medido: "Cliente", "Frescor", "Saldo", "OS"
  em caixa normal; "TIPO", "DOCUMENTO", "CIDADE/UF", "TAGS", "ÚLTIMA OS" em caixa alta.
- **A-09 — "ÚLTIMA OS" quebra em duas linhas** a 1600px (coluna com 75px).
- **A-10 — KPIs com cores do tema claro no tema escuro.** `_components/KpiStripClickable.tsx:81-110`
  (`TONE_STYLES`) fixa oklch claros sem variante escura. Medido: quadrado do ícone L 0,92–0,93 e ícone L 0,45–0,50.
  Protótipo: quadrado L 0,275 (croma 0,05–0,07) e ícone L 0,80–0,84. O mesmo objeto define `bgActive`
  L 0,95–0,96: o card clicado deve ficar quase branco no escuro (lido no código, não medido na tela).
- **A-11 — Contador das abas sem ponto de milhar:** "13428" na aba e "13.428" no título. O contador é
  desenhado por `Components/shared/PageHeaderTabs.tsx:354-357` (usado por 29 arquivos): formatar onde o `Index`
  monta o número ou no componente, sabendo que o componente afeta todas as telas.
- **A-12 — Contato sem nome aparece em branco com avatar "?"** (aba Todos). Falta um texto de reserva.
- **A-13 — Trocar de aba leva cerca de 3,1 s** (requisição `/cliente?type=…`). No protótipo é instantâneo.
- **A-14 — Aba Contato abre com "Formato de e-mail inválido" sem ninguém digitar** quando o e-mail legado tem
  dois endereços separados por "; " (dado de migração). Decidir: separar em E-mail + E-mail comercial, aceitar a
  lista, ou só validar depois que a pessoa editar o campo.
- **A-15 — Conferir:** o subtítulo diz "N com saldo", mas lê `kpis.com_atraso` (`Index.tsx:1012-1016`).
  ✔ 2026-11-26, PR #A15PR: confirmado o defeito — `com_atraso` conta só venda vencida, e o protótipo conta quem tem
  saldo a receber. Chave nova `kpis.com_saldo` (contagem de contatos, nenhum valor muda) no subtítulo e no card
  "Com saldo"; `com_atraso` segue no payload. UC-CIDX-27. Achado ao lado, sem conserto: `com_os_aberta` ("ativos")
  usa o mesmo predicado de pagamento (due/partial), não OS aberta — os dois números saem iguais.

### B · Decisões do [W] antes de implementar

- **B-01 —** 6º card de KPI "Faturamento" (hoje · variação vs ontem). Pendente desde o #11306.
- **B-02 —** "Lançar crédito" no ⋮ da linha (modal no protótipo, `clientes-page.jsx:326-370`). Mexe em valor:
  regra mestre de [proibicoes.md](../../proibicoes.md) (dupla confirmação + antes→depois).
- **B-03 —** Botão primário ("Novo cliente", "Novo fornecedor"…): prod fundo oklch 0,55 0,15 295, texto 0,99,
  fonte do sistema (`ui-sans-serif`); protótipo fundo 0,70 0,15 295, texto branco, IBM Plex Sans 12,5px/500.
  E "Novo cliente" como página (`cli-novo`) × o que a prod faz hoje. Pendente desde o #11306.
- **B-04 —** Filtros e cliques nos KPIs só filtram a página carregada (registrado no #11309). Levar ao servidor
  é mudança de comportamento.
- **B-05 —** Visões próprias de Fornecedores, Funcionários, Representantes e Outros (detalhe em C-33 a C-36):
  pedem dado que a lista não traz hoje (lead time, a pagar, comissão, carteira, vínculo, aniversário, origem).
- **B-06 —** Aba IA: o protótipo tem 3 cartões e a prod 4, com endpoints vivos (detalhe em G-21). Decidir o que
  fica.
- **B-07 —** Se a troca de neutros (C-03) exigir mudar token (e não só trocar classe na tela), é Fundação:
  precisa de ADR.

### C.1 · Página — dívida a fechar (a produção converge ao protótipo)

**Cabeçalho**
- **C-01 —** Números do subtítulo em negrito (600) e na cor do texto principal, palavras em cinza. Prod: tudo
  400 e cinza (`Index.tsx:1008-1016`).
- **C-02 —** Unidade por papel: Funcionários "no quadro", Representantes "ativos", Todos "cadastros"; o
  "· N ativos" só aparece em Clientes. Prod mostra "cadastrados · N ativos" em todas as abas, inclusive Todos.
- **C-03 —** Neutros: prod usa `text-foreground`/`text-muted-foreground` (oklch 0,965 0,004 240 e 0,74 0,008 240,
  frios) no título, subtítulo, abas, cabeçalho da tabela e linhas; protótipo usa `--text` 0,94 0,005 90,
  `--text-dim` 0,72 e `--text-mute` 0,67 (quentes). Os KPIs da prod já usam os quentes: a tela está misturada.
  Espaçamento do título −0,55 × −0,33px. Ver B-07.

**Abas**
- **C-04 —** Aba inativa: peso 400 × 500 e cor fria × 0,72 0,005 90.
- **C-05 —** Número da aba em IBM Plex Sans × IBM Plex Mono 10,5px/600 (componente compartilhado, ver A-11).
- **C-06 —** Aba ativa: prod pinta fundo roxo translúcido (oklch 0,33 0,09 295 / 0,5); protótipo só sublinha.

**KPIs**
- **C-07 —** Card: cantos 6 × 10px; padding 12 × 12/13px; altura 75 × 79px; cursor padrão × mãozinha (são botões).
- **C-08 —** Quadrado do ícone 36px/cantos 6 × 38px/cantos 9 (as cores estão em A-10).
- **C-09 —** Texto de apoio 10 × 10,5px.
- **C-10 —** "Com saldo": o protótipo mostra o valor em reais ao lado da contagem (mono 11,5px/500, `--text-dim`).
  A prod mostra só a contagem.

**Busca e filtros**
- **C-11 —** Nenhuma opção mostra contagem; no protótipo todas mostram (Status, UF, Tags, Sem compra há, Grupo, Saldo).
- **C-12 —** Falta a primeira opção "Todos" / "Todas" / "Sem filtro".
- **C-13 —** Status: faltam "Cadastro ativo", "Inativo", "Bloqueado". Protótipo:
  Todos · Ativo (com OS aberta) · Atrasado · Sem OS · Cadastro ativo · Inativo · Bloqueado. ("Excluídos" é D-01.)
- **C-14 —** Saldo: falta "Com crédito a favor" (protótipo: Em débito · Com crédito a favor · Sem saldo).
- **C-15 —** Tipo: ordem Pessoa jurídica, Pessoa física × a inversa na prod.
- **C-16 —** UF: o protótipo ordena pelo nome do estado (AC AL AP AM BA…); a prod pela sigla (AC AL AM AP…).

**Tabela**
- **C-17 —** Cabeçalho 11px e espaçamento 0,55px × 10,5px e 0,63px (caixa alta em A-08).
- **C-18 —** Ordenação: prod põe o ícone ↑↓ (40% de opacidade) em toda coluna ordenável e nenhuma marcada; protótipo
  põe "↑" roxo só na coluna ativa, "↕" cinza nas outras, e o rótulo da ativa fica claro.
- **C-19 —** Ordem padrão: protótipo por Cliente ↑; prod por recentes, sem coluna marcada. Confirmar se a ordem
  por recentes é decisão.
- **C-20 —** Cabeçalho fixo ao rolar: protótipo `sticky`, prod `static`.
- **C-21 —** Rolagem: no protótipo a tabela rola dentro do cartão e o rodapé de paginação fica sempre visível;
  na prod a página inteira rola e o rodapé só aparece depois da última linha (com 50 linhas, a ~3.280px do topo).

**Linha**
- **C-22 —** Nome 14px/500 × 13px/600.
- **C-23 —** Selos ao lado do nome: prod só "VIP" (`Index.tsx:1388`, `bg-yellow-100 text-yellow-800`, cor fora do
  DS) e "Excluído em"; protótipo "VIP" (9,5px/700, caixa alta, âmbar oklch 0,84 0,13 75 sobre 0,275 0,07 80),
  "Inativo", "Bloqueado" e "Novo".
- **C-24 —** Segunda linha: protótipo sempre o telefone com ícone (11,5px/600, `--text-mute`); prod mostra a
  fantasia e só cai no telefone sem fantasia (`Index.tsx:1396-1416`, 11px/400 a 70%). O comentário no código
  trata a fantasia como escolha: confirmar com o [W].
- **C-25 —** Tipo: protótipo PJ azul (oklch 0,84 0,06 250 sobre 0,275 0,05 250, mono 10px/700, espaçamento 0,6px);
  prod PF verde, PJ roxo, peso 600, e "—" quando falta o tipo.
- **C-26 —** Saldo e OS em 14px × 13px; o "—" da prod a 50% de opacidade × `--text-mute`.

**Rodapé**
- **C-27 —** Protótipo: tecla "?" (IBM Plex Mono) + a palavra "atalhos". Prod: ícone de teclado + tecla "?" em
  `ui-monospace`, sem a palavra.
- **C-28 —** Texto 12 × 11,5px.
- **C-29 —** Por página padrão 50 × 25 (`Index.tsx:462-464` diz que 50 é o padrão do backend).

**Menus, modal e atalhos**
- **C-30 —** Excluir: no protótipo, se o contato tem OS, o botão vira "Desativar em vez de excluir"; sem OS, sai
  "Excluir contato" e depois um aviso com "Desfazer"; o nome vem numa linha própria. A prod (`Index.tsx:1599-1622`)
  mostra sempre "Excluir" em `bg-rose-600` (cor fora do DS) e quem recusa é o servidor.
- **C-31 —** Painel de atalhos: título "Atalhos" × "Atalhos de teclado"; descrições em minúsculas ("focar a busca",
  "descer e subir na lista", "abrir o cliente marcado", "busca global do sistema", "fechar modal ou cancelar foco",
  "mostrar ou esconder esta lista"); J e K numa linha só. A prod separa J e K e tem uma nota de rodapé a mais.

**Aba Todos (o protótipo tem uma visão própria; a prod repete a de Clientes)**
- **C-32 —** KPIs: 6 cards "No diretório · todas as pessoas", "Clientes", "Fornecedores", "Funcionários",
  "Representantes", "Outros", cada um com "abrir tela ›" e clicável (troca a aba). Aviso abaixo: "Esta é uma
  **visão consolidada** com campos comuns. Pra ver KPIs e colunas específicas de cada tipo, abra a aba dedicada."
  Filtro só "Tipo" (Todos · Outros · Clientes · Fornecedores · Funcionários · Representantes). Colunas:
  Nome ↑ · Tipo ↕ (pílula: Cliente verde, Representante roxo, Outros) · Documento ("sem documento" quando falta) ·
  Contato / Cargo / Categoria · Detalhe. Contador "N contatos encontrados". Sem botão "Novo" (igual à prod).

**Abas por papel (dependem de B-05)**
- **C-33 — Fornecedores.** KPIs: Fornecedores (cadastrados) · Críticos (atenção urgente) · Com pedido aberto
  (N ordens) · Lead time médio (da emissão à entrega) · A pagar (N fornecedores). Filtros: Categoria ·
  Status (Todos / Com pedido aberto / Críticos) · A pagar (Todos / Com saldo a pagar / Em dia). Colunas:
  Fornecedor ↑ · Categoria · CNPJ · Lead ↕ · Frequência · A pagar ↕ · Vencimento · Último pedido · Pedidos abertos ↕.
- **C-34 — Funcionários.** Subtítulo "N no quadro". KPIs: No quadro (N ativos) · Comercial · Produção ·
  Em férias · Aniversários (este mês). Filtros: Setor · Vínculo (CLT / PJ / Estagiário / Sócio) ·
  Status (Ativo / Em férias / Afastado). Colunas: Funcionário ↑ · Cargo ↕ · Setor · Vínculo · Admissão ↕ ·
  Turno · Acesso · Status · Aniversário.
- **C-35 — Representantes.** Subtítulo "N ativos". KPIs: Representantes (N ativos) · Carteira total (clientes sob
  representação) · Vendas no mês · Top performer · A pagar comissão. Filtros: Região · Status (Ativos / Ociosos).
  Colunas: Representante ↑ · CNPJ · Região · Comissão ↕ · Carteira ↕ · Vendas no mês ↕ · A pagar ↕ ·
  Última venda · Status.
- **C-36 — Outros.** Botão "Novo cadastro". KPIs: Em outros (prospects, leads e avulsos) · Sem documento
  (CPF/CNPJ não é obrigatório aqui) · Da migração (vieram do sistema antigo) · Sem contato há 90d (esfriaram).
  Filtros: Origem · Documento (Sem CPF/CNPJ / Com documento). Colunas: Contato ↑ · Documento · Origem ↕ ·
  Interesse · Cadastrado ↕ · Último contato ↕ · Responsável.

### C.2 · Gaveta de 760px — dívida a fechar (itens G)

- **G-01 — Cabeçalho.** Protótipo: linha 1 = avatar + nome (15px/600) + selo de cadastro (Ativo / Inativo /
  Bloqueado) + "VIP"; linha 2 = pílula PJ/PF + documento + cidade/UF + "cadastrado há…"; "Imprimir ficha" e
  "Falar com a Jana →" no topo à direita, ao lado do ×. Prod (`Index.tsx:2240-2298`): nome 18px; subtítulo
  "Pessoa … · cadastrado há…" com selo "Sem OS"/"Ativo" (situação de OS, não de cadastro); sem documento, cidade
  e VIP; os dois botões numa segunda linha à esquerda.
- **G-02 — Botões do topo.** Protótipo: "2 placas · Risco · IA · 0 anexos" e, à direita, "1–6 troca de aba".
  Prod: "0 placas · 0 anexos · IA" (outra ordem, sem "Risco", sem a dica).
- **G-03 — Abas da gaveta** (medido pela sonda): altura 42 × 30px; padding 10/12 × 0/14px; espaço 8 × 5px;
  fonte 14px/500 × 12px/600; altura de linha 20 × 12px; aba ativa em roxo × texto claro com sublinhado.
  Na prod aparecem setas de rolagem à direita da faixa mesmo com as 6 abas cabendo.
- **G-04 — Fundo e sombra.** Fundo oklch 0,19 × 0,26 0,006 240; sombra fraca (preto a 10%) × forte (42% / 40%);
  espaço entre blocos 16 × 0px.
- **G-05 — Rodapé.** Fundo transparente × `--bg-2` (0,23); padding 12/24 × 12/18px; espaço 0 × 8px.
  Botão Salvar: cantos 5 × 8px, altura 30 × 28px, borda 0 × 1px.
- **G-06 — Seletor PF/PJ** (`_drawer/IdentificacaoTab.tsx:605-640`): protótipo ocupa a largura toda, em duas metades,
  na ordem "Pessoa jurídica | Pessoa física"; prod é pequeno, com ícones, na ordem inversa.
- **G-07 — Rótulos dos campos** (todas as abas cadastrais): protótipo em CAIXA ALTA com a dica embaixo
  ("opcional", "Como o cliente é conhecido"); prod em minúsculas, com "(opcional)" na mesma linha e "*" nos obrigatórios.
- **G-08 — Identificação, pessoa física.** Protótipo: Nome completo · Como é conhecido · CPF + Buscar · RG ·
  Data de nascimento (opcional) · Contato principal (opcional) · Cargo do contato (opcional). Prod: Nome completo ·
  CPF · RG · Data de nascimento. Faltam "Como é conhecido", "Buscar" no CPF, Contato principal e Cargo (na prod esses
  dois são só de PJ, `IdentificacaoTab.tsx:853-885`).
- **G-09 — Identificação, pessoa jurídica.** Protótipo: Razão social · Nome fantasia · CNPJ + Buscar ("Busca na
  Receita preenche razão social e endereço") · Inscrição estadual · **Inscrição municipal** · Contato principal ·
  Cargo do contato. Prod (lido no código, não na tela, porque trocar o seletor grava): falta Inscrição municipal.
- **G-10 — Contato: falta o campo WhatsApp** ("Usado pela régua de cobrança e pelo atendimento").
- **G-11 — Contato: ordem.** Protótipo: Telefone principal · Telefone alternativo · Telefone 3 · WhatsApp ·
  Canal preferido (lista) · E-mail · E-mail comercial · E-mail NF-e · Site. Prod: telefones · e-mails · Site ·
  Canal preferido em botões, no fim.
- **G-12 — Endereço.** Protótipo: só a lista "Endereços de entrega / comerciais", com o principal como cartão
  (selos PRINCIPAL e ENTREGA · NOTA FISCAL; endereço; bairro · cidade/UF · CEP; botões copiar e editar) e
  "Adicionar". Prod: formulário do endereço principal (CEP + Buscar CEP, Número, Endereço, Complemento, Bairro,
  Cidade, UF) acima da lista, e a lista vazia "Nenhum endereço cadastrado ainda."
- **G-13 — Comercial: Tabela de preço** em lista no protótipo (Sem tabela · Padrão · Atacado · Parceiro · Evento —
  "Grupo de cliente do ERP"); botões na prod. Os campos a mais da prod estão em D-02.
- **G-14 — Classificação: ordem.** Protótipo: Papéis · Segmento · Status · Tags · VIP · Bloqueio comercial.
  Prod: Papéis · Segmento · Tags · Status · VIP · Bloqueio.
- **G-15 — Classificação: controles.** Segmento e Status são listas no protótipo (Status: Ativo · Inativo ·
  Bloqueado); botões na prod, e o Status mostra só "Ativo".
- **G-16 — Operações: sub-abas** na horizontal, abaixo das abas, no protótipo; trilho vertical de 120px à esquerda,
  com ícones, na prod (`OssTab.tsx:123-152`).
- **G-17 — Operações › Extrato.** Protótipo: resumo com 4 números (OS no total · Em aberto · Atrasadas · Valor
  total), botão "Abrir extrato completo" com a nota "período, formato e envio por e-mail ficam na página cheia",
  linha Frescor · Saldo em aberto, e a última OS (número, descrição, situação, data, valor). Prod: formulário
  Data inicial · Data final · Formato (Padrão / Resumido / Detalhado) · Aplicar · PDF · E-mail.
- **G-18 — Operações › Vendas.** Protótipo: tabela Nº · Item · Status · Valor. Prod: filtros Data inicial · Data
  final · Status pagamento · Buscar · Aplicar (o contato medido não tinha venda: conferir a tabela com um que tenha).
- **G-19 — Operações › Documentos.** Protótipo: área "Arraste os arquivos aqui — Comprovantes, contratos, arte
  aprovada · ou clique pra escolher" e o bloco "Anotações internas". Prod: abas "Notas | Anexos (0)" e botão "Enviar".
- **G-20 — Operações › Pessoas.** Protótipo: "1 pessoa de contato", "Adicionar pessoa" e tabela Nome · Usuário ·
  E-mail · Departamento · Cargo. Prod: ver A-02.
- **G-21 — IA** (ver B-06). Protótipo, 3 cartões: "Resumo do cliente" (Histórico, ritmo de compra e o que costuma
  pedir · Gerar resumo), "Risco de crédito" (Saldo em aberto vs. histórico de pagamento · Analisar), "Próxima oferta"
  (O que faz sentido oferecer agora, com base no mix · Sugerir). Prod, 4 cartões: "Resumo do relacionamento",
  "Reavaliar segmento & tags", "Próxima ação sugerida", "Score de relacionamento", com a introdução "Jana neste
  cliente — 4 análises…".
- **G-22 — Risco** tem botão próprio no protótipo: "Saudável · 0/10 · Nenhum sinal de risco neste cadastro ·
  Score determinístico — 9 sinais com peso fixo, sem IA. Serve pra priorizar cobrança e retomada, não pra recusar
  venda." Na prod o score fica dentro da IA ("Risco de relacionamento").
- **G-23 — Placas.** Protótipo: um cartão por veículo (placa, modelo, situação, tipo, ano, cor, combustível,
  km na entrada, última OS) e a nota "Somente leitura — o cadastro do veículo vive na Oficina…". Prod: "Placas do
  cliente (0 veículos)" (o contato medido não tinha veículo: conferir com um que tenha).

### D · Protótipo atrasado (a produção está à frente; quem anda é o protótipo)

- **D-01 —** Filtro Status › "Excluídos", selo "Excluído em" e "Restaurar" (#10945).
- **D-02 —** Comercial: "Desconto no boleto pago até o vencimento (%)", "Sinal mínimo para liberar a produção (%)"
  e "Tabela própria do cliente" × "Usar a tabela do grupo" (#10183, P-087).
- **D-03 —** O "Excluir contato?" da prod explica onde o excluído fica (Status › Excluídos) e quais vínculos
  impedem a exclusão. O protótipo pode absorver o texto.
- **D-04 —** A gaveta da prod não fecha com Esc (decisão registrada no código, `onEscapeKeyDown`, [W] 2026-05-27).
  O painel de atalhos do protótipo diz "Esc · fechar modal": alinhar o texto.

### E · Defeitos do próprio protótipo (não copiar)

- **E-01 —** "Salvar" da gaveta e "Mais ações" saem em Arial (botão sem fonte própria, medido).
- **E-02 —** A nota "Cada campo salva sozinho ao sair do foco (autosave por PATCH) — e volta ao valor anterior se o
  backend recusar" é jargão técnico na tela.
- **E-03 —** Saldo em débito em oklch 0,50 0,16 25 sobre fundo escuro: contraste não medido; medir antes de copiar.

### F · Dado, permissão ou configuração (não é dívida de forma)

- **F-01 —** "Grupos de cliente" e "Mapa de clientes" no Mais ações só aparecem com `customer.view` (#11310). O perfil
  medido não tem: viu só Importar e Exportar CSV.
- **F-02 —** "Última OS" sem link e Saldo "—": nenhuma das 50 linhas medidas tinha OS ou saldo. Com OS o link aparece
  (re-medição de 2026-10-14, acima).
- **F-03 —** Pontos ("Programa de pontos não habilitado neste negócio"), Auditoria ("Nenhum evento registrado ainda")
  e Placas vazios: configuração e dado do contato medido.
- **F-04 —** Há cadastros com o documento colado no nome e com dois e-mails num campo: dado de migração (a parte de
  tela está em A-14).
- **F-05 —** A sonda acusa "tokens mortos" na prod: falso alarme, os tokens moram no `.cockpit` e não na raiz.
  Dentro da gaveta os 9 tokens batem com a página (o #11307 funcionou).

### Já iguais (não mexer)

Título 22px/600 · rótulo do KPI mono 10px/600 em caixa alta e valor mono 21px/600, alinhados à esquerda · busca em
faixa própria com "/ pra focar · ⌘K global" · nomes e ordem dos 7 filtros · contador "N clientes encontrados" ·
Documento mono 11,5px · Cidade em cima e UF mono embaixo · Frescor em pílula com bolinha · ★ e ⋮ centralizados ·
avatar redondo colorido · os 6 nomes das abas da gaveta · Pagamentos e Assinaturas no estado vazio · fonte e tokens
dentro da gaveta.

### Não medido nesta rodada

Tema claro · 1280px (Larissa) e celular · ⌘K · página "Novo cliente" (rota `cli-novo` do protótipo) · botões de
paginação da prod em detalhe · KPI clicado (estado ativo, ver A-10) · gaveta de Fornecedor e Funcionário (o protótipo
não abre gaveta nessas abas) · seletor PF→PJ na prod (trocar grava) · chips de filtro ativo e "Limpar" · menu ⋮ e
"Excluir" num contato com OS.
