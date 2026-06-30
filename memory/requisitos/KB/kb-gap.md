# KB — Gap mockup (Cowork) × tela viva

> **Fase 1 (read-only) skill `aplicar-prototipo`.** Mapeamento do que o protótipo Cowork propõe que a tela VIVA não tem, e do que está STALE no mockup.
> Gerado 2026-06-30.

## Arquivos comparados

| | Caminho | Linhas | Natureza |
|---|---|---|---|
| **Vivo** | `resources/js/Pages/kb/Index.tsx` | 705 | Browser de docs canônicos servidos via MCP server (`mcp.oimpresso.com`) — ADRs/sessions/references/specs sincronizados de `memory/*` via webhook GitHub. Inertia/React/shadcn. |
| **Mockup** | `_cowork-handoff-staging/.../project/kb-page.jsx` | 1537 | "Base de Conhecimento" de uma GRÁFICA — artigos SOP (Roland, HP Latex, ICC, SEFAZ, boleto Inter), troubleshooter, trilhas, IA. React puro (window.* libs externas), localStorage. |

> ⚠️ **DIVERGÊNCIA DE PROPÓSITO (a achadura central).** O mockup é MAIOR mas NÃO é a mesma tela: é uma KB **editorial de conteúdo operacional** (operador da gráfica escreve/lê SOPs, vota útil, anexa a OS). A tela viva é um **admin/browser read-mostly de governança canônica** (Wagner navega docs de memory/*, faz soft-delete LGPD, vê PII redactions). São dois produtos diferentes com o mesmo nome "KB". Por isso a maioria das "features novas" do mockup **não são gap a adotar 1:1** — são um escopo de produto distinto. Trato cada parte abaixo com essa lente.
> _O `dados`/path do backend que serviria os "artigos" editoriais do mockup é **_pendente_** — não existe controller/model de artigos KB editoriais no repo (só `mcp_memory_documents`)._

---

## Parte a parte

### 1. Header / página

- **Mockup tem:** título + linha de stats (artigos · leituras totais · vínculos OS · desatualizados) + barra de 6 botões de ação (Trilhas, Perguntar ao KB/IA, Saúde do KB, Troubleshooter, ⌘K Buscar, **+ Novo artigo**) + faixa de 4 `os-stat` cards (mais lido / pinados / recém-atualizados / a revisar).
- **Vivo tem:** `PageHeader` canônico (icon book-open + título + descrição) + `KpiGrid` de 4 `KpiCard` (docs ativos / docs com PII / tipos / último sync).
- **Gap real:** botões de ação de criação editorial (Novo artigo, Trilhas, Saúde, Troubleshooter) — mas pertencem ao escopo editorial, não ao browser de governança. O **"Perguntar ao KB" (IA/RAG sobre os docs)** é o único gap genuinamente desejável também pra tela viva.
- **Vivo-à-frente (stale no mockup):** o vivo usa **PageHeader + KpiGrid canônicos** (ADR UI-0013 Constituição UI v2); o mockup usa `os-page-h`/`os-stat` do bundle Cowork bruto. KPIs do vivo (PII redactions, último sync webhook) são reais do backend MCP; os do mockup são derivados de `localStorage`/mock.
- **POR QUÊ:** vivo serve dado real multi-tenant via Inertia; mockup é protótipo client-only.
- **Esforço/risco:** adotar botão IA = **M** / risco M (precisa endpoint RAG backend). Demais = **G** / risco G (produto novo).

### 2. Navegação / categorias

- **Mockup tem:** coluna-1 (`kb-side`) tri-pane com: árvore de **categorias com hue por vertical gráfica** (Produção, Equipamentos, Pré-impressão, Atendimento, Fiscal, Sistema, Pessoas) + sub-categorias expansíveis (`window.KB_SUBCATS`/`kbDeriveSub` — _libs externas pendentes_) + **Favoritos** + **Recentes** (localStorage) + **nuvem de etiquetas (tags)** + cheat-sheet de atalhos.
- **Vivo tem:** filtros lineares no topo (Select Tipo, Select Módulo, toggle "só com PII", busca) — sem sidebar de navegação, sem favoritos, sem recentes, sem tags.
- **Gap real:** **Favoritos** + **Recentes** + **nuvem de tags** + **sidebar de categorias** são features de navegação ausentes no vivo. Categorias do mockup (hue gráfica) **NÃO se aplicam** — vivo categoriza por `type` (adr/session/reference/spec) e `module`, que é o eixo correto da governança.
- **Vivo-à-frente:** filtro por `type`/`module` real do backend; o mockup não tem esse eixo (categoriza por tema editorial mock).
- **POR QUÊ:** taxonomias diferentes (tema gráfico vs tipo-de-doc canônico).
- **Esforço/risco:** Recentes (localStorage) = **P**; Favoritos = **P/M**; tag-cloud = **M** (precisa tags no schema `mcp_memory_documents` — _pendente_). Risco baixo (UI-only, multi-tenant não afetado pois docs são globais de governança).

### 3. Busca

- **Mockup tem:** **Command palette ⌘K** (modal `CommandPalette`) com fuzzy match (título+excerpt+tags+autor), navegação ↑↓/Enter, e **fallback "Perguntar à IA"** quando 0 resultados. Atalho `/` também abre palette.
- **Vivo tem:** input de busca inline com debounce 350ms (server-side via `router.get` only:['docs','filters']) + atalho `/` que foca o input + keyboard j/k/Enter/Esc na lista.
- **Gap real:** **command palette ⌘K modal** (busca global flutuante) + **fallback IA**. O vivo tem busca server-side real (melhor pra 352+ docs grandes) mas não tem a UX de palette flutuante.
- **Vivo-à-frente (stale no mockup):** busca do vivo é **server-side com debounce** (escala pra milhares de docs + busca em conteúdo via FULLTEXT/Meilisearch — ADR 0053); a do mockup é client-side `filter()` sobre array em memória (não escala, não busca corpo do doc).
- **POR QUÊ:** vivo carrega paginado do MCP; mockup tem 18 artigos hardcoded em memória.
- **Esforço/risco:** palette ⌘K = **M** / risco baixo. NÃO substituir a busca server-side por client-side (regressão).

### 4. Lista de artigos / docs

- **Mockup tem:** cards ricos (`kb-row`) — pill de categoria colorida, badge de nível (iniciante/inter/avançado), equipamento, "fixo", "revisar", título, excerpt, meta (autor/updated/readTime/reads/OS vinculadas) + **segmented sort** (Recentes/Mais lidos/Mais úteis/A revisar) + pin-no-topo + pílula de filtro ativo.
- **Vivo tem:** **tabela densa** (type badge, título+slug mono, módulo, indexado relativo, PII count, tamanho) + paginação server-side + colunas que colapsam quando o preview abre + contador filtrado.
- **Gap real:** ordenação por popularidade/utilidade/frescor (mockup) — mas depende de métricas (reads/helpful/outdated) que **o vivo não coleta** (docs de governança não têm "leituras"/"votos"). **Pinning** de docs essenciais poderia ser útil. Cards-vs-tabela é escolha de densidade, não gap.
- **Vivo-à-frente:** tabela densa + paginação real + colunas PII/tamanho/git_sha = dado de governança que o mockup não tem. Layout responsivo (colunas colapsam no preview aberto) é mais sofisticado que o mockup.
- **POR QUÊ:** métricas editoriais (reads/helpful) não existem no domínio de docs canônicos.
- **Esforço/risco:** sort por frescor (`updated_at`/`indexed_at`) = **P** (dado já existe); sort por popularidade = **G** (exige telemetria nova). Risco baixo.

### 5. Editor / detalhe (leitor)

- **Mockup tem:** `ArticleReader` muito rico — TOC "nesta página", **resumo por IA** (`window.claude.complete`), corpo em blocos (para/h2/list/callout com tons), **comentários por bloco** (`KBCommentBlock`), favoritar, navegação prev/próximo, footer com votação útil/desatualizado, tags clicáveis, e ações: **Resumir IA, Re-verificar, Histórico de versões, Anexar a OS, Apresentar (slides), Imprimir SOP, Editar**. Plus `KBRelated` (artigos relacionados). Composer full (`KBComposer` _lib externa pendente_) cria/edita artigo com snapshot de versão.
- **Vivo tem:** `PreviewPanel` — render markdown via `ReactMarkdown`+`remarkGfm` (syntax/anchors/links externos nova aba), badges (type/módulo/scope_required/admin_only/**PII redacted**/deletado), meta (slug/git_sha/indexado), botões: **GitHub** (link git_path), **N versões** (disabled "em breve O11"), **Soft-delete LGPD** (modal CONFIRMO) / **Restaurar**.
- **Gap real (mockup à frente):** **resumo IA do doc**, **TOC navegável**, **comentários inline**, **artigos relacionados**, **histórico de versões funcional** (o vivo tem o botão mas está disabled "em breve"), **modo apresentação/imprimir**, **prev/próximo no leitor**. Vários são desejáveis também pra governança (TOC, related, IA-summary, prev/next, histórico funcional).
- **Vivo-à-frente (stale no mockup):** **render markdown real** (mockup renderiza blocos estruturados próprios, não markdown); **soft-delete LGPD com auditoria** (`mcp_audit_log`, restore 30d) — mockup não tem LGPD; **PII redactions** visíveis; **link GitHub do git_path** (rastreabilidade canônica). Edição editorial do mockup **NÃO se aplica** — docs canônicos são editados via PR no git, não inline na UI (ADR 0061 zero-automem / git é canônico). Isso é Tier 0: editar doc na UI violaria o pipeline append-only git→webhook→MCP.
- **POR QUÊ:** vivo é read-mostly por design (a fonte é o git); mockup é editor editorial.
- **Esforço/risco:** TOC/prev-next = **P**; related = **M**; histórico funcional = **M** (backend já tem `history_count`); IA-summary = **M** (endpoint RAG). **Composer/editar inline = NÃO ADOTAR** (viola Tier 0 git-canônico).

### 6. Drawer / modais auxiliares

- **Mockup tem:** **Troubleshooter** (árvore de decisão sim/não → fix, `TroubleDialog` + editor visual `KBTroubleEditor`), **Saúde do KB** (`HealthPanel`: desatualizados/parados/mais-lidos/solitários), **Trilhas de aprendizado** (`KBPathsDialog`), **Modo apresentação** (`KBPresenter` slides), **Imprimir SOP** (header Oimpresso), **AI Dialog** (perguntar ao KB), **toast** próprio. Muitos via `window.KB*` libs externas (_pendentes — não estão no .jsx_).
- **Vivo tem:** apenas `AlertDialog` de soft-delete LGPD (digite CONFIRMO) + `sonner` toast.
- **Gap real:** **Saúde do KB** (diagnóstico de docs stale/desatualizados) é genuinamente útil pra governança canônica — adaptável usando `indexed_at`/`updated_at`. **Perguntar ao KB (IA/RAG)** idem. Troubleshooter/Trilhas/Apresentação/Imprimir SOP = escopo editorial gráfico, **não aplicável** ao browser de governança.
- **Vivo-à-frente:** soft-delete LGPD auditado é capacidade de governança ausente no mockup.
- **POR QUÊ:** mockup serve operação gráfica; vivo serve governança documental.
- **Esforço/risco:** "Saúde do KB" adaptado = **M**; IA = **M/G**. Demais = não-adotar.

---

## Veredito: **ADOTAR-PARCIAL** (seletivo, com forte filtro de escopo)

O mockup é maior, mas **é outro produto** (KB editorial de gráfica) montado sobre o mesmo nome. A tela viva é o **browser de governança canônica do MCP server** e está **à frente** no que importa pro seu domínio: busca server-side escalável, render markdown real, PII/LGPD, link GitHub/git_sha, paginação, layout responsivo. Não é MOCKUP-STALE no geral (o mockup tem muita feature nova), mas grande parte dessas features **não pertence** à tela viva.

**Adotar do mockup (compatível com governança, ordenado por ROI):**
1. **TOC "nesta página" + navegação prev/próximo no preview** — esforço **P**, risco baixo, puro UI.
2. **Recentes + Favoritos (localStorage) + nuvem de tags** na navegação — esforço **P/M**; tags exigem campo no schema `mcp_memory_documents` (_pendente_).
3. **Command palette ⌘K** (mantendo busca server-side por baixo) + **histórico de versões funcional** (botão já existe disabled; backend tem `history_count`) — esforço **M**.
4. **"Saúde do KB"** adaptado (docs stale/sem-sync) e **"Perguntar ao KB" (IA/RAG sobre os docs)** — esforço **M/G**.

**NÃO adotar (Tier 0 / fora de escopo):** edição inline / Composer de artigos (viola git-canônico ADR 0061 append-only), categorias/níveis/equipamentos de gráfica, Troubleshooter, Trilhas, Apresentação, Imprimir SOP, votação útil/desatualizado, comentários inline, anexar-a-OS, métricas de leitura. Substituir busca server-side por client-side = regressão proibida.

> **Multi-tenant:** docs de governança (`mcp_memory_documents`) são globais (não scopados por `business_id`); nenhuma adoção acima introduz vazamento cross-tenant. Favoritos/Recentes em localStorage são por-navegador (ok). Tier 0 intocado.
