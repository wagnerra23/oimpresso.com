# PageHeader · Diário de Aprendizado

> **Propósito:** capturar iterações, descobertas e "quase-decisões" do template PageHeader sem
> precisar de ADR formal pra cada anotação. Append-only por sessão. Quando uma anotação amadurece em
> decisão sólida, vira ADR e supersede a linha aqui.
>
> **Não confundir com:**
> - [PageHeader-canon-v3-1.md](./PageHeader-canon-v3-1.md) — SPEC oficial vivo do template
> - [ADR 0189](../../../decisions/0189-pageheader-canon-v3-1-cadastro-roxo.md) — decisão arquitetural snapshot
>
> **Formato:** ## SESSÃO YYYY-MM-DD — título curto. Cada sessão acrescenta no fim, nunca edita anteriores.

---

## SESSÃO 2026-05-24 — Iteração inicial até v3.1

### Contexto da sessão

Wagner pediu pra descrever o header de `/financeiro/cobranca`. Conversa evoluiu pra audit completa
do canon PageHeader, criação de spec v3 inicial, comparação com o REAL Cowork, descoberta de 5
dimensões erradas no spec inicial, escolha de família visual, calibragem fina do roxo, encurtamento
de nomes de tabs.

### O que aprendi

1. **Não confiar na minha própria leitura do "real" sem medir** — passei 3 PRs ajustando border magenta
   achando que tava resolvendo, quando o problema raiz era que `.cockpit` global definia `--accent: oklch(0.58 0.12 330)`
   magenta que vazava pra border do `.fin-cowork .os-btn.primary`. Só descobri depois de
   `getComputedStyle()` num PARENT chain — não dava pra ver no CSS local porque era cascading.

2. **Spec sem validação visual prévia = retrabalho garantido** — escrevi spec v3 cobrindo 17 dimensões
   ✅ tecnicamente, mas Wagner rejeitou 5 variantes propostas porque a família visual inteira (palette,
   density, tipografia, primary) estava desalinhada com Cowork. Lição: SEMPRE entregar protótipo HTML
   standalone ANTES de qualquer código.

3. **"Filtros avançados" como ghost incomodava Wagner** — testei 5 variantes (ghost, outline, soft,
   tinted, chip) e nenhuma resolveu. Solução final: REMOVER da Zona R e mover pra dentro do `⋮`
   overflow como item de menu com badge contador. Hierarquia visual fica mais limpa.

4. **Header e KPI strip são blocos distintos** — meu instinto era 1 card grande com header em cima
   + KPI strip embutido + lista embaixo. Wagner mostrou que separação visual (3 cards independentes
   com gap 12px) é melhor — cada bloco tem identidade própria e respira.

5. **Tabs com nomes longos + counter quebram em 1280px** — Larissa biz=4 trabalha em monitor 1280px.
   `[Clientes 22] [Fornecedores 5] [Funcionários 3] [Representantes 1]` + KPIs + actions = overflow.
   Solução: abreviar (`Fornec.`, `Repr.`) ou usar sinônimo curto completo (`Equipe` em vez de
   `Func.` que é ambíguo). Sempre `title="..."` pra a11y.

6. **Roxo é diferente** — pela primeira vez Wagner pediu cor fora do canon ADR 0182 (que define
   hue per grupo: cadastro=ciano 202). Quis roxo `oklch(0.55 0.15 295)` ("como pessoas" no modelo
   mental dele, embora SIDEBAR_GROUP_HUE.pessoas seja verde-limão 88). Sinal de que o canon ADR 0182
   pode estar errado, ou Wagner quer diferenciação visual vs concorrentes BR (Bling, Tiny, Omie todos
   azuis).

7. **Cowork canon real ≠ meu spec modern saas** — medi `cowork-canon-financeiro-bundle.css` ao vivo:
   palette warm hue 80, primary azul-marinho `rgb(31,58,95)` oklch 0.30, density compact 32px,
   font system. Meu spec era cool slate hue 220, primary ciano oklch 0.55, density cozy 36px, font
   IBM Plex. Família visual oposta. Wagner escolheu modificar B (modern saas) mantendo cool slate
   mas trocando ciano por roxo — meio do caminho híbrido.

### Decisões que viraram ADR

- [ADR 0189](../../../decisions/0189-pageheader-canon-v3-1-cadastro-roxo.md) — canon v3.1 completo

### Decisões que NÃO viraram ADR (ainda)

- Roxo 295 é universal ou só Cadastro — pending feedback Larissa 7d
- ⋮ overflow vs split-button "+ Novo X ▾" — escolhi ⋮ mas Wagner quer testar
- Dark mode tokens — não exploramos
- Sticky behavior — não exploramos
- Skeleton loading — defini no spec mas não validei visualmente

### O que NÃO funcionou (pra não repetir)

1. Variante A (Ghost puro) — "parece link"
2. Variante B (Outline) — "muito botão"
3. Variante C (Soft) — "muito anônimo"
4. Variante D (Tinted) — "estranho destacar filtro"
5. Variante E (Chip) — "fica pequeno"
6. Spec v3 cobertura 17 dimensões SEM family visual escolhida primeiro — começamos pelo lado errado
7. Auto-nota 9.85/10 antes de Wagner validar — over-confidence

### Métricas da sessão

- PRs mergeados: 3 (#1453, #1454, #1455) — todos pequenos, todos isolados, mas TODOS foram retrabalho do mesmo header
- Tempo até v3.1 fechada: ~3h de iteração
- Arquivos protótipo gerados: 5 (SPEC.md inicial, index.html, diagram.svg, 3-familias.html, b-v2-roxo-kpis.html, clientes-filtros-amostra.html)
- Variantes de Filtros avançados testadas: 5 (todas rejeitadas)
- Famílias visuais comparadas: 3 (C Cowork puro, A Warm corporate v3, B Modern SaaS — B escolhido)
- Calibres de roxo comparados: 4 (médio, escuro saturado, vivo, pastel — médio escolhido)

### Próxima sessão deve

1. Implementar componente React `<PageHeader>` em `resources/js/Components/PageHeader/`
2. Aplicar em Wave 1 piloto: Cliente/Index + Financeiro/Cobranca
3. Smoke prod 7d
4. Coletar feedback Larissa biz=4 visualmente
5. Decidir: roxo 295 universal ou só Cadastro

---

## SESSÃO 2026-05-25 — Postmortem Wave 1 piloto (Cliente/Index)

### Contexto

Apliquei canon v3.1 em `Pages/Cliente/Index.tsx` (PR #1457) e Wagner inspecionou em prod
(`/contacts?type=customer`). 2 anti-padrões aparentemente "pequenos" que pareciam OK no
protótipo mas QUEBRARAM em prod por ignorância de contexto.

### Anti-padrão #16 — Font herdada de AppShellV2 (NUNCA mais confiar em herança)

**O que aconteceu:**
- Spec v3.1 §3 dizia `font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI"`
- Meu protótipo standalone `b-v2-roxo-kpis.html` renderizou CORRETAMENTE (sem wrapper)
- Em prod com AppShellV2, font ficou **`"IBM Plex Sans"`** porque o app define isso globalmente
- Não forcei inline → herdou o IBM Plex global → quebrou a paridade com Cowork canon real

**Por que isso é grave:**
- Wagner escolheu família B Modern SaaS comparando 3 famílias visualmente (sessão 2026-05-24)
- B usava system fonts → ficou diferente do que ele aprovou
- "Quase certo" é diferente de "certo" — fidelidade ao spec é o ponto

**Regra dura pra próxima:**
> Quando spec define **font-family, color, sizing crítico** num componente isolado,
> SEMPRE force inline OU via `className` específica. Nunca confie que vai herdar.
> Especialmente em React+Inertia onde o wrapper (`<AppShellV2>`) pode redefinir tudo.

**Como detectar antes:**
1. Protótipo standalone roda SEM AppShellV2 → pode mascarar overrides
2. Antes de codar, abrir uma tela QUALQUER do projeto + DevTools → ver font computed real
3. Se font do app ≠ font do spec → spec precisa forçar OU canon precisa atualizar pra herdar
4. Pest browser test deveria assertar `getComputedStyle(h1).fontFamily.includes('ui-sans-serif')`

### Anti-padrão #17 — Overflow ⋮ com border (deve ser GHOST puro)

**O que aconteceu:**
- Usei `<Button variant="outline" size="icon">` do shadcn
- `variant="outline"` aplica `border border-input` (slate-200 visível)
- Em prod ficou um quadradinho com border ao lado do primary — peso visual alto demais
- Wagner corrigiu: `⋮` deve ser **GHOST puro** (transparent, NO border)

**Por que isso é grave:**
- O `⋮` é ação de DESCOBERTA secundária — usuário só nota quando precisa
- Border puxa atenção indevida — visualmente compete com primary que tem border colorida
- Pattern Linear/Stripe/Notion: `⋮` é sempre invisível até hover (ghost real)

**Regra dura pra próxima:**
> Overflow `⋮` no header é SEMPRE `variant="ghost"` (transparent, sem border).
> Outline button (com border) é pra ações secundárias EXPLÍCITAS (ex: "Cancelar" num modal).
> Hierarquia visual no header: **primary colorido** + **secondary ghost** (sem nada entre).

**Como detectar antes:**
1. Protótipo HTML que fiz tinha `.btn.icon-only` COM border — copiei o defeito
2. Cowork real medido (`Venda por Estagio FSM.html`) — não tinha overflow `⋮` visível pra comparar
3. Spec v3.1 §4.4 dizia "ghost-outline" — ambíguo (ghost OU outline?) — termo confuso
4. Pest browser test: `expect(getComputedStyle(overflow).border).toBe('1px solid transparent')`

### Update aplicado ao SPEC (v3.1 stays, micro-fixes)

- §3 (tokens canon): adicionar `--font-stack` explícito + nota "FORÇAR inline em React, não herdar"
- §4.5 (overflow): mudar de "icon-only ghost-outline" para "**icon-only GHOST puro · NO border · transparent bg**"
- §10 (decisões em aberto): adicionar "como override de font global do app (Tailwind config)"
- §30 (anti-padrões catalogados — protótipo SPEC): adicionar AP16 (font herdada) + AP17 (overflow com border)

### Outras métricas da sessão

- PRs Wave 1: 1 (#1457) — mergeado mas com 6 bugs detectados em prod
- Bugs encontrados: 6 (counter=0, KPI 5-cards legacy, header sem padding, border-duplo, toolbar separada, sufixo sumiu) + 2 desta sessão (font, overflow) = **8 bugs total**
- Quick Sync infra falha: 1 (lock órfão Hostinger — resolveu com rerun)
- Tempo até primeira validação visual em prod: ~10min após merge

### Próxima sessão deve

1. Aplicar fix dos 8 bugs num PR só (atomic) — não 8 PRs
2. ANTES de codar, abrir DevTools em uma tela existente do projeto pra MEDIR a font real
3. Forçar `style={{ fontFamily: ... }}` no `<header>` canon
4. Trocar overflow pra `variant="ghost"` + `className="border-0"` se shadcn ghost ainda tem border
5. Resolver counter das tabs via Controller (server-side counts) ou KPI strip já existente
6. Substituir KpiStripClickable por 4-cards-strip canon v3.1 (ou adaptar)

### Decisão canon #1 — Primary universal roxo 295 (ADR 0190 · supersede parcial 0182/0189)

**Contexto:**
Audit de conflito de memória 2026-05-25 revelou 4 fontes divergentes sobre hue per grupo
(código `shared.ts`, skill `pageheader-canon`, matriz `pageheader-matriz-diferencas.md`, ADR 0182/0189).
Wagner esclareceu de forma definitiva:

> *"deixe os grupos como estão, internos são 295 roxo médio"*

**Regra canon:**
- **Hue per grupo (SIDEBAR_GROUP_HUE)** continua existindo APENAS pra agrupamento visual no sidebar
  (header de grupo, ícones, dot indicator decorativo). 11 hues atuais permanecem inalterados no código.
- **Primary INTERNO das telas** = SEMPRE `oklch(0.55 0.15 295)` roxo médio universal —
  independente do grupo do módulo (Financeiro/Cadastro/Vendas/Produção/etc).
- Componentes legacy hue-per-grupo (`FinanceiroPrimaryButton` 145, `JanaPrimaryButton` 215,
  `PontoPrimaryButton` 88) ficam DEPRECATED — migrar pra roxo universal.

**Por que é importante:**
- Reconcilia 4 fontes conflitantes em 1 regra única
- Pattern Linear/Notion/Vercel/Stripe: sidebar varia, primary CTA = única cor
- Identidade visual diferenciada vs concorrentes BR (Bling/Tiny/Omie azul)
- Manutenção: 1 token CSS canon em vez de 11 wrappers

**Sintoma de detecção pra próximo agente:**
Se encontrar código `style={{ backgroundColor: 'oklch(0.55 0.15 ' + hueDoGrupo + ')' }}` ou similar
no primary button, é pattern PRÉ-ADR-0190. Migrar pra `oklch(0.55 0.15 295)` hard-coded.

### Anti-padrão #19 — Sidebar com popup-menu (dropdown sub-items)

**O que aconteceu:**
- Wagner inspecionou sidebar do `Cadastro > Contatos` em prod (2026-05-25) — abria popup com
  `Fornecedores · Clientes · Grupos de clientes · Importar contatos`
- Pergunta dele: "no sidebar deve abrir todos do cliente, os itens que estão no popup devem ir
  para `⋮`"
- Resposta: SIM — exatamente o que [ADR 0180](../../../decisions/0180-sidebar-v3-5-grupos-ghosts-header.md) (aceita 2026-05-21) DEFINE como canon v3
- ADR 0180 § Justificativa: "Sidebar persistente como mapa de DESTINOS, não de AÇÕES. Sub-funções
  são contextuais da tela — ghost ARIA tablist preserva acessibilidade"
- Migração das 17 DataControllers prevista na Fase 4 da ADR 0180 NÃO foi executada — `Modules/Crm`
  (Contatos) e outros continuam com padrão v2 popup em prod

**Por que isso é grave:**
- Duplica navegação: popup do sidebar mostra `Fornecedores · Clientes`, header da tela JÁ tem essas
  tabs (`Todos · Clientes · Fornec. · Equipe · Repr.`). Usuário não sabe qual usar.
- Viola Constituição UI v2 ADR UI-0013: "uma única forma de acessar uma página"
- Conflito ativo entre memórias: skill `sidebar-menu-arch` (2026-05-05) documentava popup como
  pattern canônico, mas ADR 0180 (2026-05-21) baniu — skill ficou desatualizada 4 dias depois

**Regra dura pra próxima:**
> **Sidebar é mapa de DESTINOS, não de AÇÕES.** Cada item sidebar é **SINGLE-LINK**
> (`'href' => '/destino'`) que abre a tela principal da entidade.
> **Sub-views (filtros por tipo, status, período) vão pra TABS no PageHeader Zona C** —
> NUNCA dropdown popup-menu do sidebar.
> **Power-user usa Cmd+K** pra pular direto pra uma ghost específica.
>
> Hierarquia in-screen escala 5→50 features sem restructure (padrão Linear/Notion/Vercel/Stripe).
> ADR 0180 Contrato DataController v2: `items()` retorna `['href' => ..., 'ghosts' => [...]]`.
> Ghosts viram tabs do PageHeader Zona C da tela destino.

**Como detectar antes:**
1. Code review: se DataController usa `Menu::dropdown('Label', $sub => $sub->url(...)->url(...))`
   é AP19. Migrar pra `items()` contrato v2.
2. Test visual: hover no item do sidebar → se abrir popup com sub-items → AP19.
3. Cross-check: se a tela destino TEM tabs no header (PageHeader Zona C), o sidebar NÃO precisa
   mostrar as mesmas sub-views.

**Conflito histórico documentado (lições meta):**
- ADR aceita ≠ migração executada. Plano de 9 fases da ADR 0180 está em F0-F3 (skill + tokens +
  PageHeader). F4 (17 DataControllers) e F5 (30 telas Inertia) pendentes.
- Skill `sidebar-menu-arch` foi escrita 2026-05-05 e não foi atualizada quando ADR 0180 aceitou
  superseder ela 16 dias depois. Sinal de gap no workflow: "quando ADR superseder skill, atualizar
  skill no mesmo PR da ADR".
- Hoje (2026-05-25) skill foi reconciliada com nota de conflito histórico + contrato v2 +
  AP19 catalogado. v1 mantida como DEPRECATED pra entender módulos legados ainda não migrados.

**Próxima ação:**
- Migrar `Modules/Crm/Sidebar/DataController.php` (Contatos) pro contrato v2 — `'href' => '/contacts?type=all'` + `'ghosts' => [todos, clientes, fornec., equipe, repr.]`
- Quando ghosts viram tabs, popup do sidebar SOME automaticamente (item single-link não tem children)
- Migrar 16 outros DataControllers (Sells, Financeiro, OficinaAuto, etc) — ADR 0180 Fase 4

### Anti-padrão #18 — `rows.filter()` sobre dados já server-side filtered

**O que aconteceu (smoke prod 2026-05-25):**
- Counter de cada tab mostrava `0` exceto a tab ativa: `Todos 31 · Clientes 0 · Fornec. 0 · Equipe 0 · Repr. 0`
- Wagner perguntou "tem alguma divergência nas regras?" — não tinha divergência, era bug puro
- Causa raiz: meu código `tabCounts.customer = rows.filter(r => r.type === 'customer').length`
- Em prod, `rows` traz APENAS o tipo ativo (filtrado server-side via `?type=X`)
- Filtrar por outros tipos sobre dados já filtrados retorna 0

**Por que isso é grave:**
- Bug visível, vergonhoso — 4 zeros do lado de tabs claramente populadas
- Confundiu o usuário ("achei que os nomes já estavam decididos") — bug visual escondeu bug funcional
- Eu cataloguei como "bug do counter" no smoke mas não corrigi — Wagner teve que apontar

**Regra dura pra próxima:**
> Quando frontend precisa de count POR CATEGORIA, e backend filtra POR CATEGORIA,
> os counts SÓ podem vir do BACKEND (`Inertia::defer` props.tab_counts).
> NUNCA `rows.filter()` no frontend sobre dados server-side filtered — isso é
> tentar reconstruir verdade de uma amostra parcial.
>
> Sintoma de detecção: se um valor que deveria ser fixo (count global por tipo)
> muda quando você muda outro filtro (`?type=X`) — é esse bug.

**Como detectar antes:**
1. Test plan: trocar `?type=customer` → `?type=supplier`; counter "Customer" deve continuar igual
2. Pest browser test: assert counters batem em 3 views consecutivas
3. Code review checklist: "Esse `filter()` no frontend opera sobre lista completa OU filtrada?"
4. Padrão arquitetural: backend devolve TODOS os counts (de uma SQL `COUNT() GROUP BY`), frontend só renderiza

**Fix aplicado:**
- `ContactController::buildClienteIndexTabCounts(business_id)` — 5 queries COUNT scoped business_id
- `Inertia::defer(fn () => $this->buildClienteIndexTabCounts(...))` no payload
- Frontend: `const tabCounts = props.tab_counts ?? {all:0, customer:0, ...}` (fallback enquanto defer carrega)
- Performance: 5 COUNT queries paralelas via defer · OK com índice `(business_id, is_X)` ou `(business_id, type)`

---

<!-- Próximas sessões abaixo desta linha. NUNCA editar sessões anteriores. -->
