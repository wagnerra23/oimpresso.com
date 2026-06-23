# [CC]→[CL] · Caixa Unificada — fechar TODOS os gaps protótipo→produção

> Mandato [W] 2026-06-10: **"vamos aplicar todas"** — os 7 US do roadmap do charter
> (US-WA-301..307) + pacote polish V2 + pacote IA + cutover §6.
> Diff feito por [CC] com leitura @main de: `Index.tsx`, `ComposerV4.tsx`,
> `ContextSidebarV4.tsx`, `ConversationThreadV4.tsx`, `Index.charter.md`
> (Atendimento/CaixaUnificada). `ConversationListV4.tsx` NÃO foi lido — valide.
>
> **Regra §10.4:** isto é PROPOSTA. Valide TUDO contra o `main` antes de codar.
> Numeração de US/ADR é sua, não minha. Se algo aqui contradiz o repo, o repo vence.

## Fontes do protótipo (Cowork — fonte visual canônica do charter)

Baixe via curl (URLs valem ~1h; se expirarem, pedir ao [W] que peça regeneração ao [CC]):

```bash
mkdir -p /tmp/cowork-caixa && cd /tmp/cowork-caixa
curl -sL -o inbox-page.jsx   'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/inbox-page.jsx?t=141366c15d0d755c4181ad28ed957ff2836b8a2c848333f4f2ab58f4e628863f.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781114870.fp&direct=1'
curl -sL -o inbox-extras.jsx 'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/inbox-extras.jsx?t=a2b1b1a11548a4a5b82615296507b684e67a1d9ff71cd29f2f0cb4fa19477870.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781114871.fp&direct=1'
curl -sL -o inbox-ai.jsx     'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/inbox-ai.jsx?t=506a516cf0aa1ef3b8413ff3647eec264548624e3e8930760543c6cf00905aa9.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781114872.fp&direct=1'
curl -sL -o inbox-cur.jsx    'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/inbox-cur.jsx?t=0cb392e443170875991f335c0c8a3a48571b8448ddbb987ae8c87f1952ec7b9f.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781114872.fp&direct=1'
curl -sL -o inbox-out.jsx    'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/inbox-out.jsx?t=bbe9e244bc35ca747dd50b25ac163412c432edb2226a605bde0608a0c37918c6.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781114873.fp&direct=1'
curl -sL -o inbox-page.css   'https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/inbox-page.css?t=70a3b72414c2f864140a0e9a9fbfc8b489d2b8603b8023595988518076c9748b.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781114874.fp&direct=1'
```

Espelhe-as também em `prototipo-ui/prototipos/caixa-unificada/` (a versão lá está
na geração PR-D 05-15; estas são V2 com refinos) — commit separado "sync protótipo".

## Regras transversais (TODOS os PRs)

- **Tier 0 IRREVOGÁVEL:** multi-tenant `business_id` global scope · ACL canal=fila
  (US-WA-069) · `Inertia::defer` em props caras · `preserveScroll+preserveState`
  em todo reload (US-WA-068) · polling 5s paralelo ao WSS (US-WA-066).
- **Gates:** `ui:lint` R1 (nenhuma família Tailwind `-NNN` nova) · `conformance-gate`
  (cor crua só-desce) · `foundation-guard` (zero token novo fora de allowlist) ·
  ESLint `ds/*` (sem controle nativo, sem `rounded-xl+`). Padrão de cor: tokens
  semânticos + inline `oklch(...)` style objects como o código V4 existente já faz.
- **Tabela DB nova = ADR per-schema ANTES da migration** (padrão charter §1).
- **Sem emoji em UI · PT-BR · TODOs honestos** (M-AP-1/M-AP-2): se um item não
  couber no PR, commit title diz `WIP`/`scaffold`, nunca tom de completude.
- **Pest:** cada PR estende `CaixaUnificadaControllerTest` (padrão R-WA-CAIXA-UNIF-00X)
  + atualiza o charter (`Métricas vivas` + `Histórico`).
- Cada PR: branch própria, merge na ordem. Se um quebrar gate, conserta antes de seguir.

## Ordem de execução

### PR-1 · US-WA-302 — Assignee picker (P1, ~2-3h)
`ContextSidebarV4` section 2 hoje é placeholder "— sem atribuição".
- Dropdown (Popover, mesmo pattern do editor de tags) listando users ativos do
  business com acesso `whatsapp.access`; opção "remover atribuição".
- PATCH novo `atendimento.inbox.assign` → `assigned_user_id` (coluna já existe).
- Avatar com iniciais + hue determinístico (`avatarHue` de helpers.ts).
- Tab "Minhas" (`assigned`) já filtra por isso — ganha utilidade real.

### PR-2 · US-WA-303 — Templates ⌘T + Macros `/` no composer (P1, ~4h)
Os 2 botões do `ComposerV4` hoje são "em breve". Referência: `inbox-page.jsx`
(L760-840 — dropdowns `om-tpl-pop`/`om-macro-pop` + autocomplete `om-slash-pop`).
- **Templates:** reusar `Whatsapp/_components/TemplatePicker.tsx` (legacy, já existe).
  Filtrar por canal da thread. Inserir corpo no input.
- **Macros:** verificar como o legacy Inbox implementa o dropdown `/macros` e reusar.
  Se macros hoje são estáticas/inexistentes: tabela `whatsapp_macros`
  (business_id, slash, title, body, actions json) + ADR + seeder com as 7 do
  protótipo. Autocomplete inline: digitar `/` no input abre pop com matches;
  Enter aplica a 1ª. Ações de macro (`tag:`, `assign:`, `queue:`) aplicam via
  endpoints existentes (update_tags / assign do PR-1).
- **Variáveis `{{nome}}`/`{{empresa}}`/`{{os}}`/`{{saldo}}`** (de `inbox-out.jsx`:
  `resolveVars` + `VarMenu` + `ComposerVarPreview`): botão `{}` no composer +
  preview resolvido acima do input + substituição no send.

### PR-3 · US-WA-301 — Filas DB + painel config (P1, ~4-6h)
- ADR per-schema + migration `whatsapp_queues` (business_id, slug, label, hue,
  sla_minutes, dist enum round-robin/sticky/manual, trigger_tags json, members json).
- Seeder a partir de `config('whatsapp.queues')` (migração sem quebra).
- Painel: drawer/página em Atendimento (CRUD) — botão "Filas" do topnav deixa de
  ser disabled. Visual: pattern Cockpit V2, chips hue como `QUEUES` do protótipo.
- Controller passa a ler do DB com fallback config.

### PR-4 · US-WA-305 — Mover conversa entre filas (P2, ~2h)
Depende do PR-3. Override manual: coluna `queue_override` nullable na conversa
(ou equivalente que você validar) vencendo a heurística tag→fila. Select na
section Fila da sidebar.

### PR-5 · US-WA-304 — Drawer "Canais e contas" (P2, ~2-3h)
Topnav "Canais" hoje navega pra `/atendimento/canais`. Virar drawer lateral
in-place (Sheet) com lista agrupada por type + contas com status ativo/em-breve +
link "gerenciar" pra página completa. Referência visual: `inbox-page.jsx`
(drawer `om-drawer` de canais).

### PR-6 · US-WA-307 — + Nova conversa (P2, ~2-3h)
Botão topnav disabled → dialog: `ContactPickerModal` (já existe) + select de
conta ativa + mensagem inicial (freeform ou template). POST cria/encontra
conversa e abre thread.

### PR-7 · US-WA-306 — Broadcast cross-canal (P2, ~6-8h)
Referência: dialog `om-bcast` no `inbox-page.jsx`. Pre-flight obrigatório:
seleção de contatos → contagem → preview → dry-run → disparo. Respeitar janela
24h Meta (fora da janela = só HSM) + opt-in LGPD (campo no Contact; sem opt-in,
fora da lista). Job em fila com rate-limit. Se o escopo estourar, entregar
scaffold honesto (modelo + pre-flight sem disparo) e marcar WIP.

### PR-8 · Pacote polish V2 (~4-6h) — do protótipo, fora do charter atual
Fontes: `inbox-extras.jsx` + `inbox-out.jsx` + `inbox-cur.jsx`.
1. **SLA pill** — `computeSLA(conv, queue)` (sla_minutes do PR-3; antes dele,
   config). Pill âmbar/vermelho na lista + header da thread quando estourando.
2. **Command palette ⌘K** — busca convs + contatos + ação "ir pra busca";
   incluir OS/KB só se houver índice barato (senão TODO honesto).
3. **Cheat-sheet `?`** — overlay com os atalhos reais já existentes (J/K///E/A/⌘⇧N/⌘K).
4. **Lightbox in-app** — imagem da bubble abre `MediaFullscreenModal` (já existe
   em `Whatsapp/_components/`) em vez de `window.open`.
5. **Mobile tabs <1100px** — 3 tabs Conversas/Thread/Contexto (`InboxMobileTabs`).
6. **Favoritos** — estrela na conversa, localStorage per-user (`useInboxFavs`),
   ordenam no topo da lista. Sem DB.
7. **Transcript PDF** — `InboxTranscriptDialog`: print-friendly com header
   Oimpresso (esconder notas internas por default).
8. **Modo apresentação** — `InboxPresenterMode`: overlay limpo sem IDs internos, Esc fecha.
Atualizar charter: estas 8 entram em Goals.

### PR-9 · Pacote IA — Resumir · Perguntar · Sugerir resposta (~4-6h, depende de Jana)
Fontes: `inbox-ai.jsx`. **Primeiro valide o que o módulo Jana já expõe** de
endpoint de completion server-side. Se existir: 3 endpoints finos
(`POST .../thread/{id}/summarize`, `.../ask`, `.../suggest-reply`) com prompts
do protótipo (convToText/convCtxBlock) + botões no header da thread
("Resumir", "Perguntar") e no composer ("✦ Sugerir"). Se NÃO existir
infra: PR vira proposta de ADR + scaffold, sem fingir IA com mock — anti M-AP-2.

### PR-10 · §6 Cutover Inbox legacy → Caixa Unificada (P0 — GATE [W])
Preparar e deixar em PR ABERTO (não mergear sem OK explícito do [W] no PR):
1. Redirect 301 `/atendimento/inbox` → `/atendimento/caixa-unificada`
2. Substituir entry na sidebar/topnav
3. Charter Inbox legacy → `status: historical`
4. Remoção de `Pages/Atendimento/Inbox/` fica pra PR seguinte ao OK.

## Fechamento
- Ao final, append em `prototipo-ui/COWORK_NOTES.md` (seção respostas) com placar
  PR-1..10 (merged/aberto/WIP) e atualizar `Index.charter.md` (versão + histórico).
- Commits/push/merge: você executa (`git add/commit/push`, PR por item). O [CC]
  não escreve no git — este arquivo é a ponte.
