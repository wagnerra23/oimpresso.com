# ADR 0039 — Padrão de UI "Chat Cockpit" (3 colunas) para o ERP

**Status:** ✅ Aceita
**Data:** 2026-04-27
**Decisão por:** Wagner Rocha (cliente/owner) + Claude (designer)
**Substitui:** parcialmente ADR 0008 (sidebar única + tabs horizontais — continua válido para o módulo Ponto isolado, mas o ERP completo agora segue o padrão deste ADR)

---

## Contexto

O ERP Oimpresso tem 23 módulos nWidart, dos quais ~5 já estão em React (Inertia v3) e ~18 ainda em Blade. À medida que o cliente final usa o sistema durante o dia, o problema dominante deixou de ser "encontrar a tela" e passou a ser **"saber o que precisa ser feito agora"** — informação que hoje está espalhada em N módulos (OS no Officeimpresso, ligações no CRM, boletos no Financeiro, marcação faltante no Ponto, etc).

A forma tradicional do UltimatePOS — sidebar com lista de módulos + telas isoladas — força o usuário a abrir 5 a 8 telas pra descobrir suas pendências. Em paralelo, conversas (com cliente, com equipe, com fornecedor) ficam fora do ERP, no WhatsApp/e-mail, desconectadas das OS e dos dados.

Wagner pediu, em sessão de design 2026-04-27, um protótipo que:

1. Centralize tarefas em uma inbox única
2. Coloque o chat (interno + cliente) ao lado das tarefas, não em outra tela
3. Não reeduque o cliente — ele precisa reconhecer o sistema
4. Abra dentro de cada conversa os apps vinculados àquele contexto (a OS, o cliente CRM, a marcação de ponto, o boleto), sem trocar de tela

## Decisão

Adotar o padrão **"Chat Cockpit"** como layout-mãe do ERP em React, composto de **três colunas** + topbar de breadcrumb:

```
┌──── 260px ───┬────────── 1fr ──────────┬──── 320px ────┐
│  SIDEBAR     │  TOPBAR (breadcrumb)    │               │
│              ├─────────────────────────┤  APPS         │
│  • empresa   │                         │  VINCULADOS   │
│  • [Chat]    │  CONTEÚDO PRINCIPAL     │               │
│    [Menu]    │  (chat OU tarefa OU     │  (contextual  │
│  • lista     │   módulo legado)        │   à conversa  │
│  • usuário   │                         │   ou tarefa)  │
└──────────────┴─────────────────────────┴───────────────┘
```

### 1. Sidebar (260px) — dual-tab Chat ↔ Menu

**Topo (fixo, NUNCA reformatar):**
- Seletor de empresa com avatar + nome + chevron + dropdown listando empresas + "Adicionar empresa"
- Toggle Chat/Menu logo abaixo

**Aba Chat:**
- Atalhos: Nova conversa · Tarefas · Despachos · Personalizar
- Seções: Fixadas · Rotinas · Recentes
- Cada conversa é uma linha enxuta (bullet + título)

**Aba Menu** (espelho fiel do `AppShell.tsx`):
- Mesma ordem, labels e ícones do `sidebar.blade.php` atual
- Accordions agrupados por área de negócio
- O cliente final **não percebe diferença** ao alternar do Blade pro React (LegacyMenuAdapter já entrega `MenuItem[]` com flag `inertia`, ver protocolo MWART)

**Rodapé:**
- Avatar + nome do usuário + cargo + dropdown "Meu perfil / Disponível / Aparência / Atalhos / Ajuda / Sair"

### 2. Coluna principal (1fr) — conteúdo da rota ativa

Renderiza um de três modos:
- **chat:** thread de mensagens da conversa selecionada, com **abas internas Todos / OS / Equipe / Clientes** filtrando os tipos de conversa, composer ao final
- **tarefas:** master/detail. Lista de tarefas à esquerda + viewer específico do tipo à direita (`OsAprovarArte`, `CrmLigar`, `PntJustificar`, `FinAprovarBoleto`)
- **módulo legado:** stub `<ModuleStub/>` enquanto a tela não foi migrada (mostra que está em Blade ainda)

Atalhos obrigatórios (escopo: master/detail e thread):
- **J / K** — próxima/anterior tarefa ou conversa
- **E** — concluir tarefa em foco
- **A** — adiar tarefa em foco
- **⌘K** — busca global

### 3. Coluna direita (320px) — Apps Vinculados

Painel contextual que reflete os módulos vinculados à conversa ou tarefa em foco. Cada bloco é colapsável e mostra o resumo + uma ação primária:

- **OS** — número, cliente, prazo, estágio, [abrir]
- **Cliente (CRM)** — nome, telefone, último contato, [ligar]
- **Ponto** — marcações do colaborador no dia, [justificar]
- **Financeiro** — saldo do cliente, boletos abertos, [emitir cobrança]
- **Anexos** — arquivos enviados na conversa
- **Histórico** — eventos cronológicos (quem mexeu, quando, em quê)

Esta coluna é **a entrega chave do padrão.** É ela que tira o usuário da rotação entre módulos.

### 4. Persistência (localStorage)

Tudo sobrevive a F5:
- `oimpresso.company` — empresa ativa
- `oimpresso.sidebar.tab` — `chat` ou `menu`
- `oimpresso.route` — rota da coluna principal
- `oimpresso.conv` — conversa ativa
- `oimpresso.menu.expanded` — quais accordions do Menu estão abertos
- `oimpresso.tasks.filter` — filtro de tarefas

### 5. Tweaks expressivos (modo design)

Em ambiente de protótipo (e em prod via flag dev), o painel Tweaks expõe três controles que reescrevem a vibe do sistema sem virar customização do usuário:

- **Vibe** — `workspace` (denso/formal, padrão) · `daylight` (cores quentes, mais ar) · `focus` (alto contraste, monocromático)
- **Densidade** — `skim` ↔ `briefing` (slider 0–100; recalcula altura de linha, padding, conteúdo do card)
- **Tom do accent** — slider 0–360° hue, repinta accent + cores das origens (OS/CRM/FIN/PNT) harmonicamente

## Consequências

### Boas

- **Uma tela só pra trabalhar.** O usuário abre o ERP de manhã e tem chat + tarefas + apps vinculados na mesma viewport. Reduz cliques drasticamente.
- **Menu fica imperceptível.** Cliente migrando do Blade pro React não vê diferença na navegação geral — só percebe que algumas telas ficaram mais rápidas.
- **Cada módulo entrega sua própria UI vinculada** sem tocar na tela de Tarefas (TaskRegistry agrega via interface `TaskProvider`).
- **Atalhos de teclado** dão produtividade pra power users (PCP, atendimento) sem virar a UI dependente de mouse.
- **Tweaks** permitem provar variações de design pro cliente sem manter N protótipos paralelos.

### Ruins / mitigações

- **Coluna direita ocupa 320px** — em monitor 1366×768 sobra pouco pro chat. **Mitigação:** colapsar para 44px (apenas ícones) abaixo de 1280px de largura, e oferecer botão de toggle.
- **TaskProvider precisa ser implementado em cada módulo** — esforço de migração. **Mitigação:** começar por OS (Officeimpresso) como piloto, depois CRM, FIN, PNT; até lá, painel direito mostra "stub" pros módulos sem provider.
- **Aba Chat não substitui WhatsApp interno do cliente final** — é canal interno + atendimento. Decisão: integrar Chatwoot via webhook (já há fork do Chatwoot na conta GitHub). Fora do escopo deste ADR.

## Alternativas consideradas

- **A — Manter padrão clássico (sidebar única + tela cheia por módulo).** Rejeitada: não resolve o problema da rotação entre N módulos.
- **B — Tabs no topo + drawer de chat.** Rejeitada: chat fica de novo "à parte"; não vincula aos dados.
- **C — Layout 2 colunas (sidebar + main, sem direita).** Rejeitada: força usuário a abrir N telas pra ver dados vinculados; perde-se o ganho.

## Plano de migração

1. **Fase 0 (atual):** protótipo HTML+React validado em `Oimpresso ERP - Chat.html` (este projeto Cowork). Wagner valida UX; Claude itera.
2. **Fase 1:** portar protótipo pro repo (`resources/js/Layouts/AppShellV2.tsx` + `resources/js/Pages/Tarefas/Index.tsx`). Manter `AppShell.tsx` antigo em paralelo via flag `useV2Shell`.
3. **Fase 2:** Officeimpresso ganha `OsAprovarArteTask` provider; `TasksController@inbox` agrega.
4. **Fase 3:** CRM, FIN, PNT ganham providers.
5. **Fase 4:** flag `useV2Shell` vira default.
6. **Fase 5:** `AppShell.tsx` antigo é removido (decommission).

## Refs

- Protocolo MWART (`LegacyMenuAdapter` + `menu.php` por módulo) — flag `inertia: true/false`, ver session log 2026-04-25.
- ADR 0008 — sidebar 1-item (continua válido pro Ponto isolado fora do ERP completo).
- ADR 0011 — Padrão Jana (UltimatePOS-like) — base estrutural.
- ADR 0023 — Inertia v3 (base técnica).
- Protótipo de referência — `Oimpresso ERP - Chat.html` no projeto Cowork "Oimpresso ERP Comunicação Visual" (arquivos `.jsx` + `styles.css` extraídos do projeto, fora do repo).

## Designer

Padrão visual definido por **Claude** (Anthropic) em sessão de design 2026-04-27 com Wagner. Daqui pra frente, **alterações ou criações de tela no React seguem este ADR como contrato de UI**, e o agente que for executá-las consulta este documento + `CLAUDE.md §10` antes de codificar.
