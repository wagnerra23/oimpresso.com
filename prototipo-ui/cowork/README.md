# prototipo-ui — Chat Cockpit (Oimpresso ERP)

<!-- HANDOFF-ENTRY -->
## 🤖 Claude Code ([CL]) — COMECE AQUI (não pule, antes de qualquer código)

> Você recebeu este projeto via **Handoff to Claude Code**. O arquivo "aberto" no comando do handoff (ex.: `Método 9.75 Financeiro.html`) é só o **ponto de entrada** — **NÃO é a sua tarefa**. Sua lista de tarefas vive em **`COWORK_NOTES.md` → seção "📥 Pendentes"**.

**Ordem de leitura obrigatória:**
1. **`COWORK_NOTES.md` → "📥 Pendentes"** — é a sua **LISTA DE TAREFAS**. Processe de cima pra baixo. Cada item diz: o que fazer · o destino no repo · a natureza (§10.4 / Tier 0).
2. **`STATUS.md`** — onde estamos + a REGRA DE OURO.
3. **`prototipo-ui/PROTOCOL.md`** no git `wagnerra23/oimpresso.com@main` — o protocolo (6 papéis × 7 fases · §10.4 · Tier 0). Você valida propostas contra o `main` **sozinho**.
4. **Charters** (`*.charter.md`) — memória-por-tela: o que cada tela precisa ter / aprovado / reprovado.

**Como agir em cada pendente:**
- **Valide contra o `main` (§10.4):** não duplicar ADR/canon, não cunhar número de ADR, não trazer rascunho local pro canon. Se a resposta está no git, decida e aja; só o subjetivo escala pra [W].
- **Tier 0** (ADR, lei, tooling/lint, dinheiro, segurança, decisão de produto) → abre PR e **espera [W]**. Aditivo / não-Tier-0 → loop autônomo (CI verde → merge `--admin`).
- **Ao terminar cada item:** marque `[PROCESSADO AAAA-MM-DD]` na `COWORK_NOTES.md` **e** escreva o retorno em **`CODE_NOTES.md`** (o que fez · onde caiu · o que espera de [W]).

**Tarefas abertas agora (2026-06-16 · sessão de design [CC]) — processe a `COWORK_NOTES.md` → "📥 Pendentes" DE CIMA PRA BAIXO. Os mais novos no topo:**
1. **Header + PageHeader (decidido [W])** — `PROMPT_PARA_CODE_HEADER-PAGEHEADER-CONVERGENCIA.md`: trocar headers inline `os-page-h`/`fin-page-h` pelo `<PageHeader>` que JÁ existe. **Auto-contido, em ondas (1 tela = 1 PR).** Começar por Financeiro (Unificado/Dashboard/Dre).
2. **DS v6 (aprovado [W])** — `PROMPT_PARA_CODE_DS-V6.md`: formalizar 11 componentes canônicos + landar showcase/receita. Reuse-first (Passo 0). Tier 0 (martelo do nº de ADR = [W]).
2. **Oficina dark + paleta de etapas + Fila** — `PROMPT_PARA_CODE_OFICINA-DARK-STAGE-DS.md`: tokens `--stage-*` + chroma (Parte 1, **pré-req do DS v6**), dark padrão+toggle, harmonização, view Fila. Amarra no (i) kanban-port.
3. **CRM Ficha da Frota** — tela 360 nova (aditiva, não toca o funil). Entra junto da migração CRM quando [W] liberar.
4. ...demais pendentes abaixo na `COWORK_NOTES.md` (CRM trio · fiscal real Martinho · Cobrança Recorrente · etc).

> ⚠️ O snapshot antigo "faça só estas 5" (2026-06-01) foi **substituído** por este — aquelas 5 já estão processadas/em `main` (ver `CODE_NOTES.md`). **Fonte da verdade = topo da `COWORK_NOTES.md`**, não esta lista (que é só atalho).

> ✅ **Já em `main` — NÃO refazer:** Jana Pro F3 (#2069) · prep dos 3 Tier 0 de IA (#2073) · G4 retorno automático (#2064) · charters de governança (#2061 + ADR 0242) · README handoff-entry (#2062) · health-check de charter (#2055) · Financeiro charter v10 (#2053) · G5 lint (#2054 + ESLint 0209). As 3 regras de sessão (no-dup L-21 + trilha L-22) → `CLAUDE_DESIGN_BRIEFING §7.1` (branch `docs/design-no-dup-trilha`, aguarda merge [W]); rename shell = N/A. "Vendas A+" é F1 design do [CC], **não** sua tarefa.

> ⚠️ Tudo que estiver **abaixo de "Otimizar as ROTINAS de design"** na `COWORK_NOTES.md` **já foi PROCESSADO** (ver `CODE_NOTES.md` no `main`) — **não reprocessar**. "Vendas A+" é F1 design do [CC], **não** sua tarefa.

> Os arquivos citados (`COWORK_NOTES.md`, `STATUS.md`, charters, `Método 9.75 Financeiro.html`) estão **na raiz deste bundle**. Não dependa de links externos/efêmeros — está tudo aqui.

---

> **O que é isto:** protótipo de alta fidelidade, em React+JSX direto no browser (sem build), do novo shell **Chat Cockpit** que vai substituir o `AppShell.tsx` atual quando a migração Blade→React (MWART) avançar.
>
> **De onde vem:** desenhado no Cowork (Claude.ai), exportado periodicamente para esta pasta do repo.
>
> **Para que serve:** referência visual e de interação. Quando uma tela do protótipo for promovida a produção, ela é portada para `resources/js/Pages/<Modulo>/<Tela>.tsx` seguindo [ADR 0039](../memory/decisions/0039-ui-chat-cockpit-padrao.md).

---

## Como abrir

Não precisa de build. É HTML + JSX (Babel in-browser).

```bash
# qualquer servidor estático na pasta
cd prototipo-ui
npx serve .
# abre http://localhost:3000/oimpresso.com.html
```

Ou abre direto pelo VS Code com a extensão **Live Server**.

---

## Estrutura

```
prototipo-ui/
├── oimpresso.com.html     ← entry point
├── app.jsx                       ← shell (sidebar + main + tweaks)
├── sidebar.jsx                   ← AppShell sidebar (espelho do AppShell.tsx real)
├── chat.jsx                      ← coluna lista de conversas + thread
├── tasks.jsx                     ← inbox unificada (TaskProvider mockado)
├── viewers.jsx                   ← componentes do painel direito
├── linked-apps.jsx               ← Apps Vinculados (coluna 320px)
├── laravel-panel.jsx             ← debug panel (dev only, oculto)
├── tweaks-panel.jsx              ← painel de tweaks expressivos
├── icons.jsx                     ← icon set (lucide-flavored)
├── styles.css                    ← tokens + componentes (vibe/density/accent)
│
├── data.jsx                      ← mock empresas, menu, conversas
├── data-os.jsx                   ← mock OS, clientes, catálogo, timeline
├── data-clientes.jsx             ← mock clientes (Fase 3)
├── data-orc-prod.jsx             ← mock orçamentos + produtos
│
├── os-page.jsx                   ← Listagem + detalhe + Nova OS + Aprovar arte
├── clientes-page.jsx             ← Listagem de clientes (Fase 3)
├── orc-page.jsx                  ← Listagem de orçamentos (Fase 3)
├── prod-page.jsx                 ← Catálogo de produtos (Fase 3)
├── producao-page.jsx             ← Fila de produção
│
├── Inventario - Migracao Blade React.html  ← inventário visual da migração
│
├── memory/
│   ├── HANDOFF.md                ← estado vivo da migração (escopo, fases, próximos passos)
│   ├── decisions/                ← ADRs do design (espelham `memory/decisions/` raiz)
│   └── sessions/                 ← logs de sessão de design
│
└── README.md                     ← este arquivo
```

---

## 📁 Índice — WhatsApp / Caixa Unificada (Atendimento)

> Handoff geral da página de atendimento. Mapa completo dos arquivos do módulo: **protótipo Cowork** (vivo, neste bundle) ↔ **produção** (`resources/js/Pages/Atendimento/CaixaUnificada/`, ✓lido `@main e2752b3b8ddb` 2026-06-18). O módulo está **LIVE em produção** — este índice é orientação/manutenção, não um port a fazer.

### Host
| Arquivo | Papel |
|---|---|
| `oimpresso.com.html` | **Único** HTML do ERP. Registra os 5 scripts inbox + `inbox-page.css`; rota `inbox` (sidebar "Caixa unificada"). Proibido `.html` novo por tela. |

### Protótipo Cowork (raiz deste bundle) — `window.*` exportados
| Arquivo | Exporta | Papel |
|---|---|---|
| `inbox-page.css` | — | Estilos `.om-*` (thread, bolhas, composer, drawer, banner saúde `om-health-banner`). |
| `inbox-page.jsx` | `InboxPage` | Tela-mestra: lista de conversas + thread + sidebar de contexto + topnav (Canais/Troubleshooters/Trilhas). |
| `inbox-extras.jsx` | `computeSLA` · `SLAPill` · `InboxPalette` (⌘K) · `InboxCheatSheet` (?) · `InboxMobileTabs` · `useInboxKeyboard` · `useInboxFavs` | SLA, paleta de comando, cheat-sheet, tabs mobile, atalhos, favoritos. |
| `inbox-ai.jsx` | `SummarizeThreadDialog` · `AskInboxDialog` · `suggestReplyAI` · `SuggestReplyButton` | IA: resumir thread, perguntar, sugerir resposta (humano revisa). |
| `inbox-cur.jsx` | `linkifyMessage` · `useMsgComments` · `MsgCommentWrap` · `INBOX_TROUBLES` · `INBOX_PATHS` · `InboxTroubleDialog` · `InboxPathsDialog` | Curadoria: links, comentários por mensagem, troubleshooters, trilhas de onboarding. |
| `inbox-out.jsx` | `resolveVars` · `ComposerVarPreview` · `VarMenu` · `InboxTranscriptDialog` · `InboxPresenterMode` · `InboxMediaLightbox` | Saída: variáveis `{{…}}`, transcript imprimível, modo apresentação, lightbox de mídia. |

### Produção — `resources/js/Pages/Atendimento/CaixaUnificada/` (✓lido `@main`)
| Arquivo | Papel |
|---|---|
| `Index.tsx` | Página Inertia. 7 tabs, power-filters (2 botões), passa `accounts`/`channels`/`unhealthyChannels` pros filhos. |
| `Index.charter.md` | Memória-por-tela (v10, status `live`, 12 testes Pest `R-WA-CAIXA-UNIF-*`). |
| `_components/helpers.ts` | Types + utils (status/tabs, `AccountItem`, `CaixaUnifThread`, `UnhealthyChannel`, SLA, health). |
| `_components/ConversationListV4.tsx` | Coluna esquerda: lista + busca + `ChannelHealthBanner` no topo. |
| `_components/ConversationThreadV4.tsx` | Coluna central: header + mensagens + SLA pill + IA + composer. |
| `_components/ComposerV4.tsx` | Rodapé: templates ⌘T, macros `/`, variáveis `{}`, mídia, voz, interactive, sugerir IA. |
| `_components/ContextSidebarV4.tsx` | Coluna direita: contexto do contato (CRM, tags, VoC; §OS/Saldo/Histórico = placeholder). |
| `_components/ChannelHealthBanner.tsx` | Banner "canal caiu — reconectar" (US-WA-308, cron `whatsmeow:health-probe`). |
| `_components/ChannelsDrawer.tsx` | Drawer "Canais e contas" (US-WA-304). |
| `_components/QueuesSheet.tsx` | CRUD de filas (US-WA-301, ADR 0267). |
| `_components/NewConversationDialog.tsx` | Iniciar conversa nova. |
| `_components/BroadcastSheet.tsx` | Disparo em massa (Fase 2, ADR 0268, gate [W]). |
| `_components/InboxAiDialog.tsx` | IA resumir/perguntar (Jana). |
| `_components/InboxCheatSheet.tsx` · `InboxMobileTabs.tsx` · `InboxPresenterMode.tsx` · `InboxTranscriptDialog.tsx` | Polish V2: cheat-sheet, tabs mobile, apresentação, transcript. |
| `_components/useInboxFavs.ts` | Favoritos per-user (localStorage). |

### Protótipo → produção
| Protótipo (Cowork) | Produção | Estado |
|---|---|---|
| `inbox-page.jsx` | `Index.tsx` + `ConversationListV4`/`ThreadV4`/`ContextSidebarV4` | 🟢 live |
| `inbox-extras.jsx` (SLA/⌘K/cheat/mobile/favs) | `helpers.ts` + `InboxCheatSheet`/`InboxMobileTabs`/`useInboxFavs` | 🟢 live (⌘K palette global = US futura) |
| `inbox-ai.jsx` | `InboxAiDialog.tsx` + Sugerir no `ComposerV4` | 🟢 live |
| `inbox-out.jsx` | vars no `ComposerV4` + `InboxTranscriptDialog`/`InboxPresenterMode` + `MediaFullscreenModal` | 🟢 live (`{{os}}/{{saldo}}` esperam Repair+Financeiro) |
| `inbox-cur.jsx` (troubleshooters/trilhas/comentários) | — | 🔴 só protótipo (curadoria, não portado) |

### Pendências (fila viva = `COWORK_NOTES.md`)
- **Saúde de canal O2/O3** — `prototipo-ui-patch/PROMPT_PARA_CODE_CHANNEL-HEALTH-BANNER.md` (composer pausa em canal caído + Reconectar no drawer).
- **Resíduos de domínio** (não-bugs): §OS/Saldo/Histórico da sidebar + vars `{{os}}/{{saldo}}` (esperam Repair+Financeiro) · broadcast em massa Fase 2 (gate [W]) · ⌘K palette global · cutover Inbox legado → V4 (canary 7d, gate [W]).

---

## Mapa: protótipo → produção

| Tela no protótipo | Vira em produção | Status |
|---|---|---|
| `app.jsx` (shell) | `resources/js/Layouts/AppShellV2.tsx` | 🟡 a portar |
| `sidebar.jsx` | `resources/js/Layouts/AppShell/Sidebar.tsx` | 🟡 a portar |
| `tasks.jsx` | `resources/js/Pages/Tarefas/Index.tsx` | 🟢 já parcial |
| `os-page.jsx` (lista+detalhe+novo+aprovar) | `resources/js/Pages/Os/{Index,Show,Edit,AprovarArte}.tsx` | 🔴 só protótipo |
| `clientes-page.jsx` | `resources/js/Pages/Clientes/Index.tsx` | 🔴 só protótipo |
| `orc-page.jsx` | `resources/js/Pages/Orcamentos/Index.tsx` | 🔴 só protótipo |
| `prod-page.jsx` | `resources/js/Pages/Produtos/Index.tsx` | 🔴 só protótipo |
| `producao-page.jsx` | `resources/js/Pages/Producao/Fila.tsx` | 🔴 só protótipo |
| `linked-apps.jsx` | `resources/js/Components/LinkedApps/*.tsx` | 🔴 só protótipo |
| `viewers.jsx` | `resources/js/Components/Viewers/*.tsx` | 🟢 já parcial |

---

## Convenções importadas pro código de produção

Ao portar uma tela, garantir:

1. **Tokens CSS** vêm do shell (`--accent`, `--row-h`, `--origin-OS-bg` etc) — definidos em `resources/css/app.css`. Não inventar cor.
2. **Persistência** em `localStorage` com prefixo `oimpresso.<modulo>.<chave>`.
3. **Atalhos** J/K/E/A em master/detail (ver `tasks.jsx` e `os-page.jsx`).
4. **Apps Vinculados** (coluna direita 320px) renderizada quando há contexto vinculado — cada bloco em `Components/LinkedApps/` separado.
5. **TaskProvider** para inbox de novo módulo — não criar tela de listagem própria, registrar provider e deixar `Tarefas/Index.tsx` agregar.
6. **PT-BR** em todo label/copy/comentário.

Detalhes completos em [`memory/HANDOFF.md`](memory/HANDOFF.md) e [ADR 0039](../memory/decisions/0039-ui-chat-cockpit-padrao.md).

---

## Sync com o Cowork

Este protótipo é **vivo** no Cowork. Toda vez que houver mudança significativa:

1. No Cowork, exportar o projeto como `.zip`
2. Em `D:\oimpresso.com\`, executar (ou pedir ao Claude Code):
   ```
   claude "Sincroniza prototipo-ui/ com o zip em D:\downloads\oimpresso-prototipo.zip,
   cria branch chore/prototipo-sync-$(date +%Y-%m-%d), commita diffs, abre PR."
   ```
3. Revisar PR no GitHub e mergear

---

## Status atual

Ver `memory/HANDOFF.md`. Última atualização: ver topo do arquivo.

**Fases concluídas:** 1 (shell), 2 (OS piloto), 3 (Clientes/Orçamentos/Produtos).
**Próxima:** Fase 4 — operacional de produção (fila, acabamento, expedição).
