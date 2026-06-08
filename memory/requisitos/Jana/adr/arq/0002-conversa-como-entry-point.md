# ADR ARQ-0002 — Conversa com IA é o entry-point, não o dashboard

**Data:** 2026-04-24
**Status:** Aceita
**Escopo:** Módulo Copiloto — fluxo principal e arquitetura de UI
**Autor/a:** Claude (aval de Wagner em 2026-04-24: "eu quero ir conversando com a ia ela me instruindo as melhores metas")

---

## Contexto

A proposta original do módulo (quando ainda chamava `MetasNegocio`) era centrada em **dashboard + CRUD de metas**. Wagner em 2026-04-24 pediu explicitamente:

> "eu quero ir conversando com a ia ela me instruindo as melhores metas. para cenarios apresentados. eu escolho a que eu quero. e começa a monitorar"

Isso inverte o fluxo:
- **Antes:** gestor abre dashboard → vê metas (que precisa ter criado antes) → edita CRUD se quiser.
- **Depois:** gestor abre Copiloto → **chat já está ali** → IA monta briefing com dados → gestor pede sugestões → escolhe → meta passa a existir → dashboard é consequência.

## Decisão

1. **Rota raiz `/copiloto` abre o chat**, não o dashboard.
   - `ChatController@index` é a home do módulo.
   - Dashboard fica em `/copiloto/dashboard` (acessível por link do chat, do menu, ou quando já há metas ativas).

2. **Se o gestor ainda não tem nenhuma meta ativa, o dashboard redireciona pro chat** com uma CTA "Deixe o Copiloto te sugerir suas primeiras metas".

3. **Se já tem metas ativas, o dashboard é acessível por padrão**, mas o chat permanece um clique de distância (botão flutuante "Conversar com Copiloto").

4. **CRUD tradicional de metas permanece disponível** (`/copiloto/metas/create`, wizard de 3 passos) — para quando o gestor sabe exatamente o que quer e não precisa de IA. Mas **não é o caminho promovido**.

5. **Todo fluxo conversacional converge em dados estruturados** — a IA retorna propostas com schema JSON validado (zod), não texto livre. O gestor escolhe por botão/card, não por "digite 'aceito #2'".

## Alternativas consideradas e rejeitadas

- **Chat como feature secundária** (botão "Perguntar ao Copiloto" no canto) — dilui o diferencial, desperdiça potencial.
- **Chat em drawer lateral** sobre o dashboard — esteticamente bonito, mas força o gestor a entender o dashboard antes de conversar. Contra o fluxo "descubra por conversa".
- **Modal overlay obrigatório no primeiro uso** — abusa de padrão de onboarding, deveria ser o estado natural, não uma prisão.

## Consequências

**Positivas:**
- UX alinhada com expectativa de "IA-first" (pitch de venda).
- Gestor não-técnico não precisa entender "KPI" antes de usar — o Copiloto explica.
- Onboarding zero: abre o módulo, já tem alguém conversando.
- Conversa é persistida — gestor retoma no ponto que parou.
- Captura passiva de preferências (quais propostas aceita/rejeita) alimenta prompt futuro.

**Negativas/Custos:**
- Se a IA estiver offline/quebrada, entry-point quebra — **precisa ter fallback gracioso** (modo "wizard manual" com mensagem "Copiloto indisponível, use o modo manual").
- Custo de tokens em todo primeiro uso, mesmo quem vai pular o chat — mitigado com `ContextSnapshotService` cacheado (10min).
- Teste E2E fica mais complexo (chat é stateful, stream, etc.).

## Implicações práticas

- `ChatController@index` **é uma Inertia Page React (`Chat.tsx`)** — não Blade.
- Use streaming SSE se latência do modelo passar de 3s.
- Snapshot de contexto montado **antes** da primeira resposta da IA — gestor não deve ver tela em branco.
- Histórico de conversa paginado (últimas 50 mensagens por default).
- Botão "Resumir esta conversa" em toda conversa com 20+ mensagens — ajuda o gestor a relembrar.

## Métricas de validação (pós-v1)

- % de metas criadas via chat vs wizard manual (target: 70%+ via chat na v1).
- Tempo médio até primeira meta ativa em uma nova conta (target: < 10 min).
- Taxa de abandono do chat sem escolher proposta (target: < 40%).

## Referências

- Auto-memória `ideia_chat_ia_contextual.md` — essa decisão materializa o conceito.
- Preferência `preference_persistent_layouts.md` — `Chat.tsx` usa `Component.layout` padrão Inertia, não envolve em `<AppShell>` manualmente.

---

**Última atualização:** 2026-04-24
