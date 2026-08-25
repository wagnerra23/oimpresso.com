# Caixa Unificada — Cowork (protótipo) ↔ Produção (`@main`) · LISTA DE RECONCILIAÇÃO

> **Data:** 2026-06-20 · **[W]:** "compara com o git na real, põe no padrão; produção avançou muito, faça a lista"
> **Fontes lidas no git @main:** `Index.tsx` · `ContextSidebarV4.tsx` · `helpers.ts` · árvore `_components/` (23 arquivos).
> **Veredito:** **produção está À FRENTE do protótipo** em quase tudo. O protótipo Cowork é hoje a *fonte visual* de 4 mudanças recentes; o resto já existe (e melhor) no repo. Abaixo o que **alinhar** e em que **direção**.

---

## 0) Cor de fundo (o que o [W] apontou) — ✅ JÁ CORRIGIDO no protótipo
- **Sintoma:** o fundo do drawer/superfícies do inbox no **dark** estava **roxo-tingido** (hue 295, chroma ~0.015).
- **Padrão git:** `bg-card` = `--surface` = **neutro hue 282, chroma ~0.009** (`oklch(0.198 0.009 282)`).
- **Causa:** o recolor verde→roxo aplicou 295 também nas *superfícies* dark (`--omd-canvas/panel/raise/line`), não só no acento.
- **Fix aplicado:** superfícies dark do inbox agora apontam pros tokens canônicos (`var(--bg/--surface/--raised/--hairline)`) = **idêntico ao `bg-card` de produção**. Roxo 295 fica **só no acento** (seleção, bolha enviada, badge de canal).
- **Regra daqui pra frente:** acento = roxo; **superfície = neutro do ds-v6**. Nunca tingir card/drawer/canvas com a cor de marca.

---

## 1) Contexto: DRAWER (protótipo) vs COLUNA RECOLHÍVEL (produção) — ⚠️ DECISÃO [W]
- **Protótipo (Onda D, a teu pedido):** coluna some; botão "Contexto" na thread abre **drawer flutuante** (overlay + scrim).
- **Produção `@main`:** contexto é a **3ª coluna recolhível** — `lg:grid-cols-[320px_1fr_300px]` aberta ↔ `[320px_1fr_44px]` recolhida num **trilho de 44px** com botão vertical "Contexto" (`bg-card`, `border-l`). Sem scrim. Lembra a escolha em `localStorage` e auto-recolhe < 1440px.
- **Mesma intenção** ("sumir o contexto + botão pra reabrir") resolvida de 2 jeitos. Produção = flush, sem overlay; protótipo = overlay.
- **➡️ Recomendo:** adotar o **padrão de produção** (coluna recolhível com trilho 44px) — é o que já está no canary e não escurece a tela com scrim. Se preferires manter o drawer flutuante, é override visual e o Code precisa saber. **Decide [W].**

## 2) IA (Resumir / Perguntar): header da thread (protótipo) vs seção "Inteligência" no contexto (produção) — ⚠️
- **Produção (handoff T1 · 2026-06-19):** botões **Resumir conversa** / **Perguntar ao histórico** foram **movidos pro topo do ContextSidebar** (seção "Inteligência", `Sparkles` roxo).
- **Protótipo:** ainda referencia IA noutro lugar.
- **➡️ Alinhar protótipo** à posição de produção (IA dentro do contexto), pra não brigar com o que já mergeou.

## 3) Seções do Contexto — produção tem **9**, protótipo tem ~6 — ⚠️ produção à frente
Produção (`ContextSidebarV4.tsx`), de cima pra baixo:
1. **CustomerMemoryBlock** (perfil persistente do cliente · fetch lazy) — **não existe no protótipo**
2. **Inteligência** (IA Resumir/Perguntar) — ver item 2
3. **Fila** + popover "mover pra fila" (override manual · US-WA-305)
4. **Atribuído** + assignee picker (US-WA-302) — **não existe no protótipo**
5. **Canal · Conta**
6. **Tags** com editor inline via Popover (PATCH update_tags)
7. **OS vinculada** (placeholder TODO honesto)
8. **Saldo cliente** (de `customerContext` · a receber em aberto)
9. **Histórico** (pedidos + LTV) · **Último contato** · **Contato CRM** (vincular/criar do phone) · **Ações** (cobrança/arte/ligar/**bloquear**)
- **➡️** O protótipo não precisa replicar tudo — produção é a fonte aqui. **Não regredir** essas seções ao traduzir mudanças visuais.

## 4) Status no popover "Filtros" — ✅ protótipo ALINHADO com produção
- Produção já tirou a faixa de canais e pôs canal/conta no popover "Filtros" (Onda 1/2 · 2026-06-16).
- Minha **Onda C** (mover **Status** pra dentro do mesmo popover) segue exatamente essa direção. ✅ Compatível.

## 5) Avatar com foto de WhatsApp — 🆕 protótipo À FRENTE (precisa de backend)
- **Produção:** avatar = `initials()` + `avatarHue()` em `helpers.ts`. **Não há** campo de foto (`avatar_url`) em `CaixaUnifConversation`/`CaixaUnifThread`.
- **Protótipo (Onda A):** `<img>` da foto com fallback pras iniciais.
- **➡️** É feature nova: exige **backend** expor `avatar_url` (profile pic via whatsmeow/Baileys `GetProfilePictureInfo`, coluna nova) — ver Onda A do `PROMPT_PARA_CODE_CAIXA-FOTO-ROXO-FILTRO-CONTEXTO.md`. US separada.

## 6) Verde → roxo (canal WhatsApp) — 🆕 protótipo À FRENTE
- **Produção:** hue do canal vem do catálogo no Controller; WhatsApp ainda **verde 145**.
- **Protótipo (Onda B):** WhatsApp **roxo 295**; verdes semânticos (SLA-fresh, presença, sim/não) preservados; dark idem.
- **➡️** Trocar `hue 145→295` no catálogo de canais do `CaixaUnificadaController` (todos os `wa_*`). Ver Onda B do mesmo prompt.

---

## Resumo executivo (pro Code)
| # | Item | Estado | Ação |
|---|---|---|---|
| 0 | Fundo dark = `bg-card` neutro 282 | ✅ corrigido no protótipo | confirmar no repo que superfície ≠ acento |
| 1 | Contexto: coluna recolhível vs drawer | ⚠️ decisão [W] | **default: manter coluna de produção** |
| 2 | IA dentro do Contexto ("Inteligência") | ⚠️ produção à frente | alinhar protótipo |
| 3 | 9 seções do Contexto | ⚠️ produção à frente | não regredir |
| 4 | Status no popover Filtros | ✅ alinhado | Onda C → tradução simples |
| 5 | Avatar foto WhatsApp | 🆕 protótipo | Onda A (precisa backend `avatar_url`) |
| 6 | WhatsApp verde→roxo | 🆕 protótipo | Onda B (hue no Controller) |

**Direção geral:** produção é a base; o protótipo contribui **só** itens 0, 4, 5, 6 (e o 1/2 são *alinhar o protótipo à produção*, não o contrário). Nada aqui regride o que já mergeou no canary.
