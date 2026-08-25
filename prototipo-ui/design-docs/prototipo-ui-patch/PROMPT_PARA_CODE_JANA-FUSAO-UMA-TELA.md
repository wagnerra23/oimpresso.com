# PROMPT PARA CODE — Jana: fusão das 4 telas numa só (abas de área)

> **Cole isto UMA vez no Claude Code.** Repo `wagnerra23/oimpresso.com` · branch base `main`.
> Origem do design (F1, Cowork): protótipo **Jana fundida** — URLs abaixo (~1h; pedir regenerar se expirar).
> **Soberania:** não editar constituição (ADR/PROTOCOL/BRIEFING). ADR só como `_PROPOSTA`. Merge só [W2].
> Tier 0 intocável: `business_id` scope, HITL, PiiRedactor, sem `withoutGlobalScopes` sem comentário.

## 0) Estado lido em `main` NESTE turno (2026-08-07)

`resources/js/Pages/Jana/` tem **11 arquivos** — o que interessa:

| Arquivo @main | Charter | Status no charter |
|---|---|---|
| `Chat.tsx` (15.9k) | `Chat.charter.md` v2 | `live` — 2-col, multi-conversa, tabs `Dashboard \| Chat` via `JanaAreaHeader` |
| `Dashboard.tsx` (13.4k) | `Dashboard.charter.md` v2 | `live` — metas + farol + série 12 janelas + projeção |
| `Cockpit.tsx` (38.4k) | `Cockpit.charter.md` v1 | `draft` / `spec-ahead-of-impl` — anti-pattern WhatsApp; `absorbs_when_live: Dashboard.tsx` |
| `Memoria.tsx` (7.5k) | `Memoria.charter.md` v1 | `draft` no front-matter, corpo diz `live` desde 2026-04 — **incoerência a resolver** |
| `Pro.tsx` (19k) | `Pro.charter.md` + `Pro.casos.md` | `live` — página de decisão, **fica fora da fusão** (modo foco) |

Também em `main`: `_components/JanaCockpit.tsx` (25.8k), `components/JanaCockpitV2.tsx` (24.6k), `components/JanaAreaHeader.tsx`, `_shared/JanaSubNav.tsx`, `_components/AssistantUiChat.tsx`.

⚠️ **`Painel.tsx` / `Painel.charter.md` NÃO existem em `main`** (só no espelho local). **Decisão:** são fantasma — não referenciar, não portar. O PR-0 confirma que nenhuma rota aponta pra eles e o espelho local é limpo.

⚠️ **Rotas divergem entre charters** (`/jana`, `/jana/dashboard`, `/jana/cockpit`, `/copiloto/dashboard`, `/copiloto/memoria`). **Decisão:** canon é `/jana/*`; tudo em `/copiloto/*` vira **redirect 301**. As permissões continuam `copiloto.*` (renomear quebra usuário — task futura). O PR-0 só confirma o inventário em `Modules/Jana/Http/routes.php` antes de mexer.

**Diagnóstico:** hoje são **4 telas disputando o mesmo trabalho** (brief/KPI, metas, conversa, memória) + 2 cockpits duplicados em `_components/` e `components/`. O charter do Cockpit já previa a fusão (F5 “Folding Dashboard.tsx como tab”) e nunca foi executada.

## 1) Destino (F1 [CC] — aprovado visualmente no Cowork)

**Uma tela** com **abas de área** dentro do módulo Jana:

```
[ Painel ] [ Conversa ] [ Memória ]          (Metas = SEÇÃO do Painel, canon)
```

- **Painel** = brief diário + 4 KPIs + **seção Metas** (absorve `Dashboard.tsx`) + 6 análises + ações HITL.
- **Conversa** = `Chat.tsx` preservado: histórico à esquerda (filtros todas/minhas/compartilhadas/arquivadas) + thread + composer.
- **Memória** = `Memoria.tsx` preservado (LGPD Art. 18).
- **`/jana/cockpit` morre** — `Cockpit.tsx` não é substituído em-place, é **removido**; o que sobrevive dele é o layout do Painel.
- **`Pro.tsx` continua tela própria** (modo foco, sem abas). Criar meta / editar meta também: `/…/metas/nova` e `/…/metas/{id}` em modo foco.
- Rotas canônicas: **`/jana`** (Painel, default) · **`/jana/conversa`** · **`/jana/memoria`**. Deep-link + back do browser funcionam. Legados → 301: `/jana/dashboard` e `/copiloto/dashboard` → `/jana` · `/copiloto/memoria` → `/jana/memoria` · `/jana/cockpit` → `/jana`. Persistência em `localStorage` prefixo `oimpresso.jana.*` (chave da aba: `oimpresso.jana.tab`; migrar valores legados `dashboard→painel`, `ia→conversa`).

**Design source (ler como gramática visual — NÃO copiar CSS cru):**
- `jana-merge.jsx` — https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/jana-merge.jsx?t=c9d0272a6dde165eb70077548eacc80fd2913085ef96148a58ba1769bbe75df6.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1786106890.fp&direct=1
- `jana-merge.css` — https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/jana-merge.css?t=180acfec41bb54987c8362fa44f1160a899bf174465a6f76e2078f5d96f4eb78.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1786106891.fp&direct=1
- `chat-jana.jsx` — https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/chat-jana.jsx?t=e2b8743eeb2d47d501310d573068e8d0381713ed1a1fc75522198256c84a1d8b.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1786106892.fp&direct=1
- `chat-jana.css` — https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/chat-jana.css?t=fed603788cb5153d455be56f47d5b924d7f00226fe072e342b18147c73eff5ab.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1786106892.fp&direct=1

## 1.5) Fidelidade — o protótipo é especificação, não inspiração

Regra deste pacote: **o F3 tem que ficar fiel ao protótipo, ou o mais próximo possível.** Divergência não é bug de gosto, é falha de gate. O que muda é a *tecnologia* (TSX + Tailwind 4 + `Components/ui` + Inertia), nunca a *tela*.

**Fiel = literal (sem margem):**
- **Copy.** Toda string visível sai do protótipo **verbatim** — títulos, labels, subtítulos de drawer, empty states, avisos, textos de botão. Ex.: “Configurar a Jana” / “O que ela observa, quando ela fala e até onde ela pode agir” · “Aprovação obrigatória (HITL)” / “Toda ação passa por você — não pode ser desligado” · “A Jana ainda não aprendeu nada sobre o seu negócio” · “clique num card pra ver de onde vem o número” · “Memória da Jana — LGPD Art. 18”. Reescrever copy = rejeitar o PR.
- **Estrutura.** Ordem e hierarquia das seções de cada aba, ordem das abas, ordem dos campos em cada drawer, ordem dos botões no rodapé (ghost à esquerda, primário à direita).
- **Estados.** Os cinco estados de cada aba (carregando/vazio/erro/sem-permissão/filtro-vazio) com o **mesmo texto** e a mesma ação.
- **Interação.** Detalhe abre em **drawer lateral** (nunca modal), confirmação em modal, apagar fato confirma **inline** na própria linha, ação HITL tem prévia literal da mensagem e as fases `idle → enviando → feito`.
- **Atalhos.** `J/K` · `⌘⇧H` · `/` · `Esc` · `⌘Enter`, com as mesmas dicas visíveis (`J K anda · ⌘⇧H recolhe`).
- **Tokens.** Roxo canon `oklch(0.55 0.15 295)`, type ramp `--fs-*`, raios do DS. Nada de cor crua nem valor solto: se o protótipo usa um valor que não existe em token, **propor o token** (`_PROPOSTA`), não hard-codar.

**Pode divergir (e deve):**
- Dados mock → serviço real com `business_id`; farol/projeção **server-side**.
- Componentes bespoke do protótipo → equivalentes do DS (`Drawer`, `Modal`, `Alert`, `Toast`, `EmptyState`, `Skeleton`, `DropdownMenu`, `KpiCard`, `TabBar`) — o DS ganha do CSS do protótipo quando os dois discordam em 1–2px.
- CSS de `jana-merge.css`/`chat-jana.css` é **leitura de gramática** (densidade, ritmo, hierarquia), não arquivo a copiar.

**Como se prova (gate de cada PR, além do UI-Judge):**
1. `prototipo-ui/contrato/jana-index.contract.json` declara **seções + copy literal + estados** — CI falha se a string mudar (ADR 0286).
2. Screenshot lado-a-lado protótipo × implementação, 1280px, por aba e por estado, anexado no PR → aprovação [W2].
3. Checklist de paridade no corpo do PR: cada item do §2 daquela onda com ✓ e o print correspondente.

## 2) Ondas (PRs) — ordem e critério

Cada PR para no gate visual (UI-Judge + `ui:lint` R1 + `conformance-gate` + `foundation-guard`). DS v6: roxo canon `oklch(0.55 0.15 295)`, sem cor crua, sem `rounded-xl+`, sem select/checkbox/radio nativo (usar `Components/ui`), drawer lateral pra detalhe (PT-02), modal só pra confirmação (PT-04).

**PR-0 · Inventário (sem UI)** — ler `Modules/Jana/Http/routes.php` + `config/core_topnavs.php` + sidebar e registrar num `_PROPOSTA`: rotas Jana vivas hoje, quem aponta pra `Cockpit.tsx`, e confirmação de que nada aponta pra `Painel.tsx`. As decisões já estão tomadas (§0) — este PR é a evidência, não a deliberação. Também: **`components/JanaCockpitV2.tsx` é o que sobrevive** como base do Painel; `_components/JanaCockpit.tsx` morre no PR-7. **Nada é apagado antes deste PR.**

**PR-1 · Shell da área + abas** — `JanaAreaHeader` vira o header único (avatar mono “J”, nome, chip `{biz} · biz=N`, “Atualizado HH:MM”, Configurar / Exportar / Atualizar, selo de plano) + `JanaSubNav` vira a `TabBar` `Painel | Conversa | Memória` com contadores. Sem conteúdo novo: as telas atuais passam a render dentro do shell.

**PR-2 · Painel absorve Dashboard (metas)** — `Dashboard.tsx` vira **seção “Metas ativas”** do Painel: cards com farol (verde ≥95% / amarelo ≥50% / vermelho), seletor de período (mês corrente + 2 anteriores), progresso, projeção. **Farol e projeção continuam server-side** (`MetricasApurador::farol`) — frontend não calcula (Anti-hook do `Dashboard.charter.md`). Click no card → **Drawer** (situação, delta vs janela anterior, série 12 janelas, “origem do número”, CTA “Conversar com a Jana”). Redirect 301 da rota antiga → aba Painel. `Dashboard.charter.md` → `status: historical`.

**PR-3 · Painel completo + drill-down** — brief diário (chips de ação rápida → abrem conversa nova com a pergunta), 4 KPIs, 6 análises, ações HITL. Novo e obrigatório: **todo número clica e mostra de onde vem** — Drawer “Fonte” com tabela/serviço de origem (`AnaliseInadimplenciaService`, `AnaliseFaturamentoService`, …) + hora da apuração + escopo `business_id`. KPI só é clicável quando existe análise **do mesmo dado** (ticket médio, p.ex., não abre faturamento). Ação HITL → **Modal com prévia literal da mensagem** + fases `idle → enviando → feito`; nada sai sem aprovação por mensagem. `Inertia::defer` em brief/kpis/analises/acoes/metas.

**PR-4 · Conversa dentro da aba** — `Chat.tsx` preservado (streaming, kinds `markdown`/`tool_use`/`data_table`/`action_card`, citations `[1]`, PII detector, `⌘Enter`). Acrescenta: histórico **recolhível** (`⌘⇧H`, estado em `oimpresso.jana.hist`), `J/K` navegando **conversas** no histórico — **decisão:** o `Chat.charter.md` dava `J/K` pra mensagens, e isso muda: trocar de conversa é o que o Wagner/Larissa fazem o dia todo, rolar mensagem é `↑/↓` na thread. Emendar o `Chat.charter.md` (Goal “atalhos”) no mesmo PR, com nota no Histórico, overlay do histórico abaixo de 1100px, header da thread com escopo (só sua / da equipe / arquivada), `aria-live` no troca-de-conversa.

**PR-5 · Memória dentro da aba** — `Memoria.tsx` preservado + `Alert` LGPD Art. 18 no topo, busca + categorias, edição inline com **motivo obrigatório** (vai pro `activitylog`), apagar com confirmação inline (não modal), relevância 1–5, origem e “desde”. Export de fatos só com audit log (Non-Goal do charter).

**PR-6 · Configurar a Jana (drawer)** — brief on/off + hora, áudio (Pro), 6 análises liga/desliga (persistem por usuário), **HITL travado em on** (não desligável), retenção de fatos + atalho “Ver fatos”, selo de plano. Gating do plano: Grátis = conversa + memória + metas; Pro = brief + análises + ações. Upsell dentro da aba usa `EmptyState`, e a ativação leva pra `/ia/pro` (tela própria).

**PR-7 · Cutover** — remover `Cockpit.tsx` + o cockpit duplicado + rotas mortas; `Cockpit.charter.md` → `status: historical` com nota “fundido, não substituído em-place”; charter novo `Jana/Index.charter.md` (+ `.casos.md` com UCs) descrevendo a tela única e herdando os Non-Goals dos 4 charters; entrada no `prototipo-ui/contrato/*.contract.json`.

## 3) Estados obrigatórios (todas as abas)
Carregando (skeleton no ritmo do `Inertia::defer`, não spinner) · vazio (“ainda não tem histórico pra analisar” + CTA Conversa) · **erro** (“não consegui ler os dados agora” + Tentar de novo, e o retry que falha diz o que acontece: brief sai atrasado) · sem permissão · filtro sem resultado. Copy sempre **por quê + o quê fazer** — nunca “erro 500”.

## 4) Pest GUARD (mínimo pra fechar)
`renders 3 tabs (painel/conversa/memoria) e nenhuma rota /jana/cockpit` · `farol vem do servidor — frontend não recalcula` · `Non-Goal: sem multi-channel, sem fila/assignee/SLA` · `cross-tenant biz=99 invisível` · `action HITL exige confirm por mensagem` · `PII redigido no audit log` · `localStorage prefixo oimpresso.jana.* (nunca sessionStorage)` · `1280px sem scroll horizontal` · `edição de fato sem motivo é rejeitada (LGPD)`.

## 5) O que NÃO fazer
- ❌ Não criar tela nova nem módulo novo — é fusão do que já existe.
- ❌ Não substituir `Cockpit.tsx` em-place (o charter dizia isso; a decisão nova é **remover**).
- ❌ Não trazer `Pro.tsx`, criação/edição de meta pra dentro das abas (modo foco).
- ❌ Não calcular farol/projeção no frontend. ❌ Não `dangerouslySetInnerHTML`. ❌ Não emoji no app.
- ❌ Não renomear permissões `copiloto.*` neste PR. ❌ Não apagar `Painel.tsx` antes do PR-0.
