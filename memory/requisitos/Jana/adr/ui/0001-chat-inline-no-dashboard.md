# ADR UI-0001 — Chat inline como coluna principal, não drawer/modal

**Data:** 2026-04-24
**Status:** Aceita
**Escopo:** Módulo Copiloto — layout das telas Chat + Dashboard
**Autor/a:** Claude

---

## Contexto

`adr/arq/0002` definiu que **conversa é o entry-point**, não o dashboard. Falta decidir **como** a conversa aparece na tela — há três padrões comuns:

1. **Modal overlay** — chat pop-up sobre o resto. Bom pra "helper" secundário; ruim pra feature principal.
2. **Drawer lateral** — chat numa sidebar deslizante. Ok pra feature secundária, esconde-se.
3. **Página dedicada inline** — chat ocupa região central da tela, como um editor ou um email client.

Também preciso decidir como chat e dashboard convivem: **mesma tela** (dois painéis) ou **telas separadas** (roteamento).

## Decisão

### 1. Chat é **página dedicada inline** (não modal, não drawer)

- Rota `/copiloto` renderiza `Pages/Copiloto/Chat.tsx`.
- Layout: **2 colunas em desktop** (≥ lg):
  - **Coluna esquerda (40%):** lista de conversas + botão "Nova conversa".
  - **Coluna direita (60%):** thread da conversa ativa + composer de mensagem no rodapé.
- **Mobile (< lg):** tela única com lista de conversas; tocar uma abre a thread full-screen.
- **Breadcrumb:** "Copiloto / Conversas / [título]".

### 2. Dashboard é **página separada**

- Rota `/copiloto/dashboard` renderiza `Pages/Copiloto/Dashboard.tsx`.
- **Não** é embutido na tela de chat (evita sobrecarga cognitiva + performance: scorecards com sparkline querem espaço).
- **Header do dashboard** tem um botão primário "Conversar com Copiloto" que leva a `/copiloto`.

### 3. **FAB (Floating Action Button) global** em todas as telas do módulo

- Botão flutuante "Conversar" no canto inferior-direito.
- Leva ao chat preservando `?context=<rota_origem>` pra IA saber onde estava (ex.: "estava vendo a meta #12").

### 4. **Briefing inicial = primeira mensagem do assistant**

- Quando uma conversa é criada, `ChatController@index` chama `SuggestionEngine::gerarBriefing()` sincronamente.
- Mensagem 0 é do `role=assistant` com o snapshot auto-gerado.
- Gestor **nunca vê tela em branco**.

### 5. **Propostas renderizadas como Cards lado a lado (não como lista de texto)**

- Quando a IA retorna `{propostas: [...]}`, cada proposta vira um Card shadcn:
  - Título da proposta
  - Métrica + valor-alvo + período (Badge)
  - Dificuldade (chip colorido: 🟢 fácil / 🟡 realista / 🔴 ambicioso)
  - Racional (3 linhas truncadas, expandível)
  - Botão primário "Escolher esta meta"
  - Botão secundário "Rejeitar" (loga rejeição pro prompt futuro)
- Cards em grid responsivo (1 col mobile, 2 cols tablet, 3 cols desktop).

### 6. **Composer** na base da thread

- Textarea multiline com auto-resize.
- `Cmd+Enter` envia (consistente com outros apps shadcn no repo).
- Enter simples quebra linha.
- Chips de "Sugestões rápidas" acima do textarea: "Sugira metas", "Compare com mês passado", "Explique o desvio".

## Alternativas consideradas e rejeitadas

- **Modal overlay sobre dashboard** — força gestor a fechar pra ver dados; contra-intuitivo porque a IA responde a partir dos dados.
- **Drawer lateral** — em mobile vira modal mesmo, perdendo a vantagem.
- **Dashboard + chat na mesma tela** — em mobile cada um fica pequeno demais; em desktop desperdiça espaço para gestor que só quer conversar.
- **Chat sempre em `/dashboard`** (com tab "Conversar") — mistura entry-points, confunde gestor novo.

## Consequências

**Positivas:**
- Chat como cidadão de primeira classe na arquitetura de UI reforça diferenciador "IA-first".
- FAB permite invocar Copiloto sem sair da tela atual (mantém fluxo).
- Cards de proposta são visualmente comparáveis — gestor escolhe por dimensão visual, não por texto.
- Dashboard separado não carrega peso do chat (performance).

**Negativas/Custos:**
- Gestor acostumado com "dashboard primeiro" pode sentir estranho — mitigado por onboarding de 30s explicando o modelo.
- Duas páginas = dois bundles Inertia → lazy-loading importante.
- FAB global requer wrapper em todas Pages do módulo — ou embutir no `CopilotoLayout.tsx`.

## Componentes shadcn a adicionar (se não existirem)

- `Textarea` (composer)
- `Badge` (dificuldade, chips de sugestão rápida)
- `Card` (propostas)
- `ScrollArea` (thread)
- `Avatar` (role user vs assistant)
- `Sheet` (lista de conversas em mobile)
- `Command` (já usado no AppShell pra cmd+K — reaproveitar)

## Acessibilidade

- `role="log"` + `aria-live="polite"` na thread.
- Composer: `aria-label="Mensagem para o Copiloto"`.
- Cards de proposta: botões têm `aria-label` completo ("Escolher meta Faturamento 2026 de R$ 5 milhões, dificuldade ambiciosa").
- Dark mode obrigatório (já é DoD padrão do projeto).

## Telas stubs (ordem de prioridade)

1. `Chat.tsx` + `ChatList.tsx` + `MessageThread.tsx` + `Composer.tsx` + `SuggestionCards.tsx`
2. `Dashboard.tsx` + `MetaCard.tsx` + `Sparkline.tsx` + `FarolBadge.tsx`
3. `Metas/Index.tsx` + `Metas/Show.tsx`
4. `Metas/CreateWizard.tsx` (wizard 3 passos)
5. `Alertas/Index.tsx`

## Referências

- Auto-memória `preference_persistent_layouts.md` — Pages usam `Component.layout`.
- Auto-memória `project_shell_nav_architecture.md` — menu via `Resources/menus/topnav.php`.

---

**Última atualização:** 2026-04-24
