# CAPTERRA-DESIGN-FICHA — TeamMcp/Team (UX/UI de governança de tokens MCP)

> **Cruzamento gerado:** 2026-05-25
> **Skill aplicada:** `design-arte` (input pra CAPTERRA-DESIGN-INVENTARIO.md futuro)
> **Alvo:** `resources/js/Pages/team-mcp/Team/Index.tsx` (~521 LOC, live em prod, biz=1 superadmin Wagner — único cliente real desta tela)
> **Controller:** `Modules/TeamMcp/Http/Controllers/TeamController.php` (Inertia::defer 2 props pesadas, OTel spans em token.issue/revoke)
> **Persona:** Wagner [W] @ biz=1 (superadmin, dev sênior, monitor 1920px, **técnico**, opera várias vezes/dia onboarding de Felipe/Maiara/Eliana/Luiz no MCP server). **NÃO é Larissa** — esta tela é superadmin-only com `can: copiloto.mcp.usage.all`.
> **Charter:** ❌ ausente (`Team/Index.charter.md` não existe — gerar quando entrar em ciclo de refactor)
> **Visual-comparison prévio:** ❌ ausente
> **RUNBOOK:** ❌ ausente
> **SPEC:** ❌ ausente (ADR 0057 cumpre função de spec governança)
> **ADR mãe:** [ADR 0057 — Tela `/team-mcp/team`: regras de governança de tokens MCP e distribuição via `.dxt`](../../decisions/0057-tela-team-admin-regras-governanca-tokens-mcp.md)
> **ADRs relacionadas:** [0053](../../decisions/0053-mcp-server-governanca-como-produto.md) · [0055](../../decisions/0055-self-host-team-plan-equivalente-anthropic.md) · [0064](../../decisions/0064-modularizacao-split-teammcp-kb-superadmin360.md) · [0065](../../decisions/0065-permission-registry-contract.md) · [0072](../../decisions/0072-maturacao-memoria-team-mcp-openclaw-soa-2026.md) · [0073](../../decisions/0073-team-mcp-skills-policies-entidades-governadas.md) · [0075](../../decisions/0075-team-mcp-skills-ui-prompt-management-style.md) · [0076](../../decisions/0076-skills-db-primary-git-destino-drift-alert.md)

> ⚠️ **Nota mãe:** este é o **1º artefato canônico de UX** desta tela. Diferente da ficha Sells (cliente PME não-técnico Larissa) ou Compras (protótipo Cowork F1 PME varejo), aqui o sujeito é **superadmin/devops governando segredos sensíveis Tier 0**. O eixo crítico não é "agradar leigo", e sim **velocidade operacional (Wagner emite tokens diariamente) × fricção de segurança (1 token vazado = acesso a 107 docs de memória + 56 ADRs + chat Copiloto)**. Persona-alvo é Wagner [W] solo — Felipe/Maiara/Eliana/Luiz NÃO usam a tela (consomem MCP, não geram). Charter explícito ausente: este FICHA é o primeiro contrato de UX. Próximo passo: Wagner aprovar top gaps Tier 0 → parent agent codifica → criar `Index.charter.md` no PR.

> 🚨 **Premissa Tier 0 imutável:** esta tela é o **único ponto de criação/revogação** de credenciais MCP. Toda crítica de UX **subordina-se** a 3 invariantes:
> 1. Token raw mostrado **1× só** após criação (ADR 0057 §2 + §10) — Stripe-pattern canon.
> 2. Hash SHA256 gravado, **raw descartado** (não há "ver novamente") — incentivo correto à rotação.
> 3. Cross-tenant isolation Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — token de `business_id=A` só vê dados de A.
> Nenhum gap UX proposto pode atenuar essas invariantes.

---

## 1. Players UX avaliados (referência 2026)

### 1.1 Governança de tokens / API keys — leaders globais

| # | Player | Tipo | Padrão UX característico observável |
|---|---|---|---|
| 1 | **Stripe Dashboard — API keys** | Pgto B2B, gold-standard reveal-once | **Secret API key visível 1× no momento da criação**, depois `••••` permanente. **Restricted API keys (RAK)** com permissões granulares por endpoint. **Delayed expiration até 7 dias** ao rotacionar (janela de revert seguro). UI mostra "Last 4" + created_at + permissions resumidas + "Roll" (rotate) action |
| 2 | **GitHub — Fine-grained PATs** | Dev tools (1M+ devs) | Lista mostra **name + expiration + last used timestamp** (mas NÃO IP/UA por padrão). **Token value never revealed após criação** (apenas metadados). Org owners têm view consolidada com filtros de "expiring soon" + bulk revoke. Credential revocation API (2026-03) permite revogação programática |
| 3 | **Vercel — Access tokens** | DevOps deploy (após breach Abr/2026) | Pós-incidente Abr/2026: redesign de gestão de env vars sensíveis + recomendação de **2FA obrigatório** + rotação assistida + audit log destacado. UI Vercel canon: token list com "Last used" relativo ("3 hours ago") + scope tags + 1-click revoke com confirm modal |
| 4 | **Cloudflare — API tokens** | Infra (40M sites) | **Fine-grained scoping por permissão+zona+account** + **IP allowlist por token** + **expiration obrigatória** (não permite "never expires" em produção). Dashboard tem filtro "Active / Expired / Revoked" + warning amarelo se expira em <7 dias |
| 5 | **Linear — API personal keys** | SaaS B2B issue tracker | Cmd+K-first, telas internas seguem mesmo grid; tokens em Settings > API. Lista enxuta (name + last used + revoke) — minimalismo estratégico, sem KPIs financeiros (não cobra por uso) |
| 6 | **Doppler / Infisical** | Secrets management nativo 2026 | **Zero-downtime rotation** com 2 tokens idênticos válidos durante janela (Doppler Cloudflare-pattern). **Universal Secrets** dashboard com filtro por idade ("not used in 30d → suggest revoke") |
| 7 | **Auth0 / Okta** | Identity provider enterprise | Apps & APIs > Clients — **client_secret reveal-once** + rotation com janela de coexistência + audit log com IP+UA por request + alertas em tempo real (Slack/email) em uso anômalo |

### 1.2 Team management / cotas — referências auxiliares

| # | Player | Padrão observável |
|---|---|---|
| 8 | **Vercel Teams / GitHub Organizations** | Lista membros com role-pill colorida + "last active" + bulk-remove com 2-step confirm. **Pending invites** seção separada |
| 9 | **Notion Workspace Members** | Filter chips (Admin / Member / Guest) + search + sort por last active. Convite por email com role pré-seleciondao |
| 10 | **AWS IAM — Access keys** | Tela mais densa do mercado: até **2 keys ativas por user simultaneamente** pra permitir rotation seamless. Status "Active / Inactive / Disabled" + "Last used" + service usado + região |

### 1.3 Pré-requisitos UX-de-governança não-negociáveis (canon 2026)

Cruzando os 10 players, **8 padrões emergem como mandatórios** em telas de governança de credenciais sensíveis 2026:

1. **Reveal-once** com aviso explícito ("copy now, won't show again") — Stripe canon ✅ oimpresso tem
2. **Last used timestamp** (mínimo) + **IP da última request** (idealmente) — GitHub/Cloudflare/Auth0 canon 🟡 oimpresso tem só timestamp
3. **Confirmação destrutiva forte** em revoke/regenerate (não `confirm()` nativo — modal com nome digitado) — Stripe/AWS/GitHub canon ❌ oimpresso usa `window.confirm`
4. **Expiration policy obrigatória** (não "never expires") — Cloudflare canon ❌ oimpresso permite null = forever
5. **Audit log link inline** por usuário (clique no row → audit log filtrado) — Auth0/AWS canon ❌ oimpresso tem só Export CSV global
6. **Status pill semântico** (Active/Expiring/Expired/Revoked) — universal canon 🟡 oimpresso só conta ativos
7. **Bulk revoke** com filtro "not used in N days" — Doppler/Infisical canon ❌ oimpresso ausente
8. **2FA / step-up auth** em ações destrutivas (revoke all, regenerate) — Vercel pós-breach 2026 canon ❌ oimpresso ausente

---

## 2. Tabela de capacidades — 30 linhas (a peça central)

Legenda: ✅ tem completo · 🟡 parcial / divergente · ❌ ausente · ⚪ N/A nesse cenário

| # | Capacidade / Feature | **Stripe/GitHub/Cloudflare best-of-class** | **oimpresso /team-mcp/team hoje** | Severidade gap |
|---|---|:-:|:-:|:-:|
| **G-01** | Listagem de devs (lista paginável) | ✅ | ✅ tabela 9 colunas + Deferred skeleton 5 rows | OK |
| **G-02** | KPIs globais no topo (custo hoje/mês/calls/usuários ativos) | 🟡 (Stripe sim, GitHub não) | ✅ 4 KpiCard com tone semântico (info/default/warning/success) | OK |
| **G-03** | Permission gate `copiloto.mcp.usage.all` enforced | ✅ | ✅ middleware + DataController gates | OK |
| **G-04** | PageHeader canon roxo 295 aplicado (ADR 0180/0182/0189/0190) | ⚪ N/A | ✅ icon="users" + title + description + action slot | OK |
| **G-05** | Inertia::defer em props caras (`team`, `stats_globais` — ~30 queries) | ⚪ N/A | ✅ closure dupla + fallback skeleton coerente | OK |
| **G-06** | Token raw exibido 1× só no momento da criação | ✅ Stripe canon | ✅ Dialog com `<Input readOnly>` + aviso "COPIE AGORA" + onClick select | OK |
| **G-07** | Copy-to-clipboard com feedback visual (toast/checkmark) | ✅ Stripe checkmark inline 2s | 🟡 toast `sonner` "Copiado pro clipboard" — funciona mas sem checkmark inline no botão (UX 2026 prefere inline-feedback) | Tier 2 polimento |
| **G-08** | Setup snippet inline copiável (Bearer/JSON config) | ✅ GitHub mostra `git clone` com token; Stripe mostra `curl` | ✅ `<code>` block com JSON `.claude/settings.local.json` colado — bom, mas NÃO é botão "copy snippet" (só copia token raw) | Tier 1 UX |
| **G-09** | Confirmação destrutiva FORTE (modal com texto digitado, não `confirm()` nativo) | ✅ Stripe/AWS/GitHub canon | ❌ usa `window.confirm()` nativo do browser em `gerarToken` + `gerarDxt` (linhas 101, 125) — design inconsistente com resto da app (que usa shadcn `<Dialog>`) | **Tier 0 segurança** |
| **G-10** | Mostrar last_used_at por token (não só do user agregado) | ✅ GitHub canon | 🟡 mostra "ultimo_uso_mcp" agregado por user (último audit_log), mas se user tem 3 tokens (laptop/desktop/CI) NÃO mostra qual foi usado | **Tier 0 governança** |
| **G-11** | Mostrar IP da última request (anti-vazamento) | ✅ Cloudflare/Auth0 canon — `mcp_tokens` schema TEM `last_used_ip` (ADR 0057 §2) | ❌ schema TEM, UI NÃO MOSTRA. Wagner não consegue ver "este token foi usado de IP estranho" sem export CSV + filtro | **Tier 0 segurança** |
| **G-12** | Status pill por token (Active / Expiring / Expired / Revoked) | ✅ Cloudflare canon | 🟡 mostra contagem agregada "N ativos" — não mostra estado individual. Não há "expiring soon" warning. ADR 0057 §6 prevê soft-delete (`revoked_at` + `expires_at`), schema tem, UI esconde | **Tier 0 governança** |
| **G-13** | Lista de tokens individuais por dev (drill-down) | ✅ canon universal | ❌ tela mostra só "tokens_ativos: int" — clique no contador NÃO abre lista. ADR 0057 §6 menciona "botão lixeira ao lado do contador" mas não está implementado | **Tier 0 funcional** |
| **G-14** | Revoke individual de token específico | ✅ canon universal — backend tem `revogarToken(tokenId)` em TeamController:324 | ❌ UI NÃO tem botão pra revogar. Backend existe (`POST /team-mcp/team/token/{id}/revoke`), mas frontend não chama. Wagner hoje precisa SQL manual ou tinker | **Tier 0 funcional** |
| **G-15** | Bulk revoke (filtrar "não usado em 30d" → revogar massa) | ✅ Doppler/Infisical canon | ❌ ausente | Tier 2 enhancement |
| **G-16** | Expiration policy obrigatória + warning antes de expirar | ✅ Cloudflare canon | ❌ `expires_at` permite NULL = forever. Schema McpToken não força expiration. Sem warning "expira em 7d" | **Tier 0 segurança** |
| **G-17** | Audit log link inline por user (clica row → filtra log) | ✅ AWS IAM/Auth0 canon | 🟡 tem Export CSV global com filtro `de,ate`, mas: (a) UX terrível — `prompt()` nativo do browser pra escolher período (linha 155); (b) sem visão inline (precisa baixar CSV + abrir Excel) | **Tier 0 governança** |
| **G-18** | Quota visual com badge progressivo (verde<50% / amarelo 50-79% / laranja 80-99% / vermelho 100%) | ✅ Stripe/Vercel canon | ✅ `quotaBadge()` helper bem feito — 4 tiers + emoji 🚫/⚠️ + classes Tailwind. Pareio SOTA | OK |
| **G-19** | Quota editável inline ou em modal shadcn | ✅ canon | ✅ `<Dialog>` + `<QuotaForm>` com period toggle + limit number input + block checkbox — bem feito | OK |
| **G-20** | Microcopy PT-BR consistente | ⚪ EN nativo SOTA | ✅ "Devs ativos hoje", "Calls MCP hoje", "Quota dia/mês", "Top tools", "Último uso" — PT-BR técnico consistente, persona dev OK | OK |
| **G-21** | Top tools usadas por dev (visibilidade de comportamento) | 🟡 (GitHub Org Insights tem, Stripe não) | ✅ coluna `top_tools` mostra top 3 do mês com count `(N)` — útil pra Wagner detectar anomalia | OK |
| **G-22** | Filtros / busca / saved views | ✅ Linear/Stripe canon | ❌ tabela é estática — sem filtro por (custo > X / não usado há 30d / quota excedida / sem quota definida) | Tier 1 UX |
| **G-23** | Bulk actions (multi-select rows) | ✅ Shopify/GitHub canon | ❌ ausente | Tier 2 enhancement |
| **G-24** | Atalhos teclado (Cmd+K / `/` busca / `?` cheat-sheet) | ✅ Linear gold-standard | ❌ ausente. Wagner usa Cmd+K global (sidebar) mas nesta tela específica não há atalho | Tier 2 polimento |
| **G-25** | 2FA / step-up auth em ações destrutivas | ✅ Vercel pós-breach 2026 | ❌ ausente. Wagner gera/revoga token sem re-prompt de senha. Se sessão Wagner vazar → atacante regenera tokens em massa | **Tier 0 segurança** |
| **G-26** | A11y WCAG AA (aria-labels, focus visible, contraste) | ✅ Stripe/GitHub canon | 🟡 `<Input readOnly>` no modal token sem `aria-describedby` pra aviso "copie agora"; emoji-only botão (`📦 + DXT`, `⚙️`) sem `aria-label` — screen reader fica perdido | **Tier 1 a11y dever** |
| **G-27** | Loading skeleton em initial render | ✅ canon 2026 | ✅ `<Deferred fallback>` com 5 rows `animate-pulse` + KpiGrid placeholders | OK |
| **G-28** | Error UX (regenerate falhou, rede caiu) | ✅ inline recovery | 🟡 `.catch(() => toast.error('Erro de rede'))` — toast genérico sem ação "tentar de novo" inline. Se 401 expirou, joga toast em vez de redirect login | Tier 2 polimento |
| **G-29** | Empty state (primeiro acesso, business sem devs ainda) | ✅ canon | ❌ se `team.length === 0`, tabela renderiza vazia sem `<EmptyState>` CTA "Convidar primeiro dev" | Tier 2 enhancement |
| **G-30** | Trust signals / changelog inline ("novo: regenerate token") | 🟡 Linear announcements inline | ❌ ausente | Tier 3 (sem sinal Wagner) |

**Síntese:** dos 30 itens, oimpresso bate canon em **12** (G-01..06, G-18..21, G-27 + G-04/05 infra), pareia parcial em **8** (G-07, G-08, G-10, G-12, G-17, G-26, G-28 + G-02), e falha em **10** — sendo **6 deles Tier 0** (G-09, G-11, G-13, G-14, G-16, G-25) com impacto direto em **segurança ou governança crítica**.

A leitura honesta: a tela cumpre o **happy path de Wagner** (emitir token → entregar via Vaultwarden), mas **falha em casos de fricção real** (Wagner quer revogar 1 token específico de 3 do mesmo dev; Wagner quer auditar IP da última request; Wagner regenerou token errado e precisa undo).

---

## 3. Dimensões UX 15 pontos — tabela ponderada

Legenda: ✅ pareia SOTA · 🟡 parcial · ❌ ausente · ⚪ N/A

| ID | Dimensão | Peso | Stripe/GitHub canon | oimpresso /team-mcp/team | Distância | Nota /10 |
|---|---|:-:|:-:|:-:|:-:|:-:|
| **D-01 (P0)** | Hierarquia visual | 3 | ✅ h1+description+ação direita | ✅ PageHeader canon roxo 295 + 4 KPIs + Card "Time" + tabela | curta | **8** |
| **D-02 (P0)** | Densidade informacional | 3 | ✅ Stripe denso mas legível | 🟡 tabela 9 colunas em 1280px aperta — Wagner usa 1920px OK, mas "Top tools" coluna texto livre causa wrap; ações 3-botões inline ficam apertadas | curta | **7** |
| **D-03 (P0)** | Navegação primária | 3 | ✅ sidebar fina + breadcrumb | ✅ AppShellV2 + breadcrumb "Copiloto > Team Admin" + ghosts sub-views (Team/Tasks/CC Sessions + 6 ghosts ProjectMgmt absorvidos) | curta | **8** |
| **D-04 (P1)** | Sistema de design | 2 | ✅ Polaris/Primer | ✅ Tailwind 4 + shadcn (`Card`, `Button`, `Dialog`, `Input`, `Label`, `KpiGrid`, `PageHeader`); cores semantic (green/orange/yellow/red) consistentes | curta | **8** |
| **D-05 (P1)** | Microcopy PT-BR | 2 | ⚪ EN | ✅ PT-BR técnico ("Devs ativos hoje", "Quota dia", "Top tools", "Bloquear ao exceder") — persona dev BR Wagner certo. Aviso "COPIE AGORA — não será mostrado de novo" forte | curta | **9** |
| **D-06 (P1)** | Empty states | 2 | ✅ ícone+CTA | 🟡 nenhum empty state declarado — se `team.length===0`, tabela renderiza vazia sem CTA | média | **5** |
| **D-07 (P1)** | Loading + skeleton | 2 | ✅ Linear skeleton inteligente | ✅ `<Deferred>` dupla (team + stats_globais) com fallback skeleton coerente — pareia SOTA, melhor que muito player BR | curta | **9** |
| **D-08 (P1)** | Error UX | 2 | ✅ inline + recovery | 🟡 `.catch(toast.error('Erro de rede'))` genérico, sem ação retry inline, sem distinção 401/403/5xx | média | **5** |
| **D-09 (P2)** | Atalhos teclado | 1 | ✅ Cmd+K + tudo | ❌ nenhum atalho local. Cmd+K global existe mas não tem comando "gerar token Felipe" registrado | longa | **3** |
| **D-10 (P2)** | Mobile/touch 1280px | 1 | ✅ responsive | 🟡 tela é superadmin, mobile não é prioridade. 1280px tabela aperta (9 col + 3 botões ação). Wagner em 1920px OK | média | **6** |
| **D-11 (P2)** | A11y WCAG 2.1 AA | 1 | ✅ certificado | 🟡 botão `📦 + DXT` e `⚙️` sem `aria-label`; readOnly Input do token sem `aria-describedby`; modal de quota não declara `aria-modal` (shadcn Dialog já faz, mas confirmar focus trap); confirmações via `window.confirm()` quebram focus management | média | **5** |
| **D-12 (P2)** | Feedback ações | 1 | ✅ otimistic + undo | 🟡 toast pós-DXT sucesso OK; pós-gerar-token abre modal (bom); MAS revogar inexistente (G-14); undo de "regenerate" inexistente | longa | **5** |
| **D-13 (P2)** | Formulários (QuotaForm) | 1 | ✅ inline validation | ✅ period toggle Diário/Mensal + numeric min=0 + checkbox bloquear + helper "Reset diário 00:00 BRT" — bem feito, pareia SOTA | curta | **8** |
| **D-14 (P2)** | Dataviz | 1 | ✅ rich charts | 🟡 4 KPIs sim, MAS sem chart de evolução (custo últimos 7d / 30d / picos). Wagner não vê tendência sem export CSV | média | **5** |
| **D-15 (P3)** | Onboarding / docs inline | 1 | ✅ tooltips contextuais | 🟡 títulos `title="Gera token raw (Claude Code CLI / setup manual)"` em alguns botões; snippet de config no modal token MUITO BOM (`<code>` com JSON pronto); MAS sem link inline pra ADR 0057 / docs Vaultwarden | curta | **7** |

---

## 4. Cálculo da nota ponderada

```
Σ (nota_i × peso_i):
  D-01 (8×3) + D-02 (7×3) + D-03 (8×3) = 24 + 21 + 24 = 69
  D-04 (8×2) + D-05 (9×2) + D-06 (5×2) + D-07 (9×2) + D-08 (5×2) = 16+18+10+18+10 = 72
  D-09..D-15 (peso 1): 3 + 6 + 5 + 5 + 8 + 5 + 7 = 39

  Total: 69 + 72 + 39 = 180

Σ pesos: (3×3) + (5×2) + (7×1) = 9 + 10 + 7 = 26

nota_final = 180 / 26 × 10 = 69.23 → arredondado 69
```

```
NOTA OIMPRESSO ATUAL (/team-mcp/team): 69/100
NOTA REFERÊNCIA TOP (Stripe Dashboard hipotético se replicasse aqui): 92/100
NOTA REFERÊNCIA SOTA-PROXY (GitHub fine-grained PATs Org admin): 86/100
NOTA REFERÊNCIA BAIXO (UPOS legacy / qualquer admin Bootstrap PT-BR): ~40/100

Gap pro topo: -23 pts. Causa principal:
  ausência de drill-down por token individual (G-13/14) +
  audit/IP por linha invisível (G-11) +
  confirmações destrutivas via window.confirm() nativo (G-09) +
  sem 2FA step-up em ações Tier 0 (G-25).
```

**Leitura honesta:**

- **A tela cumpre o happy path de Wagner.** Emitir token + gerar `.dxt` + ver custo agregado funciona bem. Reveal-once (Stripe canon) está correto. PageHeader canon aplicado. Inertia::defer correto. Microcopy técnico PT-BR consistente.
- **A tela falha quando há fricção real.** Os 6 gaps Tier 0 são todos cenários de "algo deu errado": (a) revogar 1 token específico de 3, (b) auditar IP suspeito, (c) detectar token expirando, (d) regeneração com 2FA, (e) destructive confirm forte, (f) ver lista individual de tokens. Hoje Wagner cai em `tinker` ou SQL manual nesses casos.
- **A tela é melhor que UPOS legacy (~40) e tem 17 pontos de gap pro SOTA Stripe — gap é todo concentrado em peso 1 e em features de drill-down que NÃO existem (não é polish, é capacidade ausente).** Não é "feio", é "incompleto pra ação destrutiva".
- **O fato de não ter charter explícito** (`Index.charter.md` ausente) é por si só um Tier 1 gap de processo. Próximo PR deve criar charter declarando intent + non-goals + invariantes.

---

## 5. Top 10 gaps priorizados (impacto × esforço × tier)

| # | Gap | Tier | Impacto | Esforço | Sinal qualificado? | Prioridade final |
|---|---|---|---|---|---|---|
| **G-DESIGN-01** | Drill-down `tokens_ativos > 0` → lista individual de tokens do dev (name, last_used, last_ip, status, revoke) | **Tier 0** funcional | **Crítico** — sem isso Wagner não opera ADR 0057 §6 ("revogar em 1 clique") | M (~6h: novo Dialog `<TokensListDialog>` + endpoint `/team-mcp/team/{user}/tokens` + revoke action) | ✅ ADR 0057 §6 explicita expectativa, parent agent já viu Wagner reportar "tinker pra revogar token Felipe laptop" | **P0** |
| **G-DESIGN-02** | Botão revoke individual por token (UI faltando — backend `revogarToken` já existe em TeamController:324) | **Tier 0** funcional | **Crítico** — backend pronto, só falta UI | XS (~1h, depende do G-DESIGN-01) | ✅ backend existe = sinal canônico | **P0** |
| **G-DESIGN-03** | Substituir `window.confirm()` em `gerarToken` + `gerarDxt` por shadcn `<AlertDialog>` com **descrição explícita do efeito** ("Vai criar token novo com acesso a 107 docs de memória + revogar implícito o anterior? Esta ação não pode ser desfeita.") | **Tier 0** segurança/UX | **Alto** — `confirm()` nativo quebra focus management, é inconsistente com resto da app, e tom pobre pra ação Tier 0 | XS (~45min) | ✅ Wagner já reclamou de confirm nativo em outras telas; sinal Constituição UI v2 (shadcn canon) | **P0** |
| **G-DESIGN-04** | Mostrar `last_used_ip` + `last_used_at` por token (schema TEM, UI não expõe) | **Tier 0** segurança | **Alto** — anti-vazamento. Wagner detecta "token usado de IP fora do BR" em 1 olhada | S (~2h, junto com G-01 — adicionar 2 colunas na lista de tokens) | ✅ ADR 0057 §2 declara schema, ADR 0057 §10 (PII/LGPD) cita auditoria diária — sinal canônico | **P0** |
| **G-DESIGN-05** | Status pill por token (Ativo / Expira em N dias / Expirado / Revogado) com cor semântica (verde / amarelo / cinza / vermelho) — substituir contagem agregada por estado individual | **Tier 0** governança | **Alto** — invisibilidade de tokens "esquecidos" expirando é como Vercel foi breached em Abr/2026 | S (~1.5h, junto com G-01) | ✅ ADR 0057 §6 cita soft-delete, schema suporta; sinal SOTA Cloudflare/Stripe | **P0** |
| **G-DESIGN-06** | Forçar expiration policy: default 90 dias na criação (UI sugere) + warning "expira em <7d" no row | **Tier 0** segurança | **Médio-alto** — Cloudflare canon "never expires" é anti-pattern. Tokens MCP hoje permitem null = forever | S (~2h: ajustar `gerarToken` request com `expires_at` default + warning UI) | 🟡 não-explícito em ADR mas Vercel breach é sinal externo de mercado | **P1** |
| **G-DESIGN-07** | Substituir `prompt()` nativo de Export CSV (linha 155) por `<Dialog>` shadcn com date-range picker | Tier 1 UX/consistência | Médio — `prompt()` é UX de 1995, não combina com PageHeader canon roxo 295 | S (~1.5h, usar `<Calendar>` shadcn) | ✅ Constituição UI v2 (shadcn canon) | **P1** |
| **G-DESIGN-08** | Audit log inline por user (botão "Ver atividade" no row → Dialog com últimas N calls MCP do user filtradas, com link "Export CSV deste user") | Tier 1 UX | Médio — hoje Wagner exporta TUDO e filtra Excel | M (~4h: novo endpoint `/team-mcp/team/{user}/audit-recent?limit=50` + Dialog + componente lista) | 🟡 ADR 0057 §8 menciona auditoria — sinal indireto | **P1** |
| **G-DESIGN-09** | `aria-label` em botões emoji-only (`📦 + DXT`, `+ Token`, `⚙️`) + `aria-describedby` no Input readOnly do token apontando pra aviso "copie agora" + `<FormError>` no QuotaForm | Tier 1 a11y (dever) | Médio — WCAG 2.1 AA obrigatório (Constituição). Wagner usa screen reader ocasionalmente? Provavelmente não, mas dever de a11y é blanket | XS (~45min) | ⚪ a11y é dever, não wishlist (não precisa sinal Larissa) | **P1** |
| **G-DESIGN-10** | 2FA / step-up auth (re-prompt senha) em ações destrutivas (revoke all tokens de user / regenerate em massa) | **Tier 0** segurança | Médio — Vercel pós-breach Abr/2026 ensina que admin session hijack = catástrofe | L (~8h: novo endpoint `/team-mcp/verify-password` + Dialog 2-step + cache JWT 5min) | 🟡 Vercel 2026 é sinal externo; oimpresso ainda não foi attacked, mas Wagner conta como sinal qualificado pelo ADR 0105 (mercado SaaS é cliente indireto) | **P2** |

---

## 6. Top 5 gaps Tier 0 implementáveis em 1 PR único ≤300 linhas (recomendado pra parent agent)

> **Princípio:** commit-discipline ADR 0095 — 1 PR = 1 intent. **Mas** os gaps G-DESIGN-01..05 formam **1 intent coeso**: "expor estado individual de tokens MCP pra governança Tier 0". Tudo gira em torno de criar `<TokensListDialog>` (Dialog que abre ao clicar no contador `tokens_ativos`). Estimativa unificada: **~280-300 linhas** TSX + ~60 linhas PHP no controller.

### PR proposto: `feat(team-mcp): drill-down tokens individuais + revoke por token + audit IP/last-used`

**Escopo (5 itens em 1 PR coeso):**

1. **`G-DESIGN-01` Drill-down lista de tokens** — novo `<TokensListDialog>` component dentro de `Index.tsx`. Abre ao clicar na pill `N ativos` ou no nome do dev. Lista `mcp_tokens.where(user_id, $userId)` com colunas: `name | created_at | expires_at | last_used_at | last_used_ip | status_pill | actions`.

2. **`G-DESIGN-02` Botão revoke individual** — em cada row do TokensListDialog, botão `🗑 Revogar` que chama `POST /team-mcp/team/token/{tokenId}/revoke` (já existe em TeamController:324, só adicionar route). Após sucesso: `router.reload({ only: ['team'] })`.

3. **`G-DESIGN-03` Substituir `window.confirm()` por shadcn `<AlertDialog>`** — criar wrapper component `<DestructiveConfirmDialog>` reusável com:
   - Título tipo "Gerar novo token MCP pra {nome}?"
   - Descrição com efeito explícito (acesso a 107 docs / não pode desfazer)
   - 2 botões: Cancelar (ghost) + Confirmar (variant destructive vermelho)
   - Usar em `gerarToken`, `gerarDxt` e novo revoke.

4. **`G-DESIGN-04` Expor `last_used_ip` + `last_used_at` por token** — adicionar campos no payload `montarRow` (TeamController.php — novo método `buildTokensListPayload(userId)` retornado pela nova rota). Frontend exibe IP em mono `text-xs text-muted-foreground` ao lado de last_used.

5. **`G-DESIGN-05` Status pill por token** — helper `tokenStatusBadge(token)` retorna `{color, label}` para 4 estados:
   - `revoked_at != null` → cinza "Revogado"
   - `expires_at < now()` → cinza "Expirado"
   - `expires_at < now()+7d` → amarelo "Expira em N dias"
   - else → verde "Ativo"

**Diff aproximado:**
- `resources/js/Pages/team-mcp/Team/Index.tsx`: +180 linhas (novo Dialog + helper + integração no `<td>` tokens_ativos)
- `Modules/TeamMcp/Http/Controllers/TeamController.php`: +60 linhas (`buildTokensListPayload` + ajuste retorno em revoke)
- `Modules/TeamMcp/Routes/web.php` ou similar: +2 linhas (nova route `GET /team-mcp/team/{user}/tokens`)
- `resources/js/Components/shared/DestructiveConfirmDialog.tsx` (novo): +50 linhas
- Total: ~290 linhas (dentro do ≤300 ADR 0095 ✅)

**Test plan:**
- Pest feature test em `Modules/TeamMcp/Tests/Feature/TokensDrillDownTest.php` cobrindo: lista tokens biz=1, revoke individual, multi-tenant isolation (token biz=A NÃO aparece pra superadmin biz=B), audit log entry em revoke.
- Manual smoke biz=1: criar 2 tokens pra Felipe, abrir drill-down, revogar 1, confirmar que `tokens_ativos` cai de 2 → 1.

**Restrições Tier 0 que o PR respeita:**
- ✅ Multi-tenant ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)): query de tokens filtra `where('user_id', $userId)` E user pertence ao mesmo `business_id`. Audit OTel span `teammcp.tokens.list` propagado.
- ✅ Reveal-once invariante: lista NUNCA mostra `sha256_token` nem reconstrói raw. Apenas metadados.
- ✅ commit-discipline ADR 0095: 1 intent, ≤300 linhas.
- ✅ PageHeader canon ([ADR 0180/0182/0189/0190](../../decisions/)): não toca PageHeader existente.
- ✅ Constituição UI v2: usa shadcn `<Dialog>` + tokens Tailwind, sem cor crua.

---

## 7. O que NÃO mudar (pontos OK do design atual)

| Item | Por quê preservar |
|---|---|
| Reveal-once modal com `<Input readOnly>` + onClick select + aviso "COPIE AGORA" | Stripe canon — pareia SOTA 2026. Mudança aqui é regressão |
| Snippet `<code>` inline com JSON `.claude/settings.local.json` pré-colado | Time-to-onboard <30s — eliminou ticket "como configuro?" |
| PageHeader canon roxo 295 (icon="users" + title + description + action) | ADR 0180/0182/0189/0190 — universal app-wide |
| Inertia::defer dupla (team + stats_globais) com fallback skeleton | Wave 11 D6.a — 300-800ms → ~50ms first paint |
| `quotaBadge()` helper 4 tiers (verde/amarelo/laranja/vermelho) com emoji 🚫/⚠️ | Pareio Stripe canon, microcopy PT-BR forte |
| QuotaForm (period toggle Diário/Mensal + numeric + checkbox bloquear + helper reset) | Bem feito, pareia SOTA |
| OTel spans `teammcp.token.issue` / `teammcp.token.revoke` | ADR 0057 §10 + ADR 0073 governança |
| `McpToken::gerar()` helper canônico (não chamar `create()` direto — bug UNIQUE de 2026-04-30) | Pegadinha operacional documentada |
| `<code>` block com JSON config pré-formatado | Reduz fricção setup dev drasticamente |
| Toast `sonner` PT-BR ("Copiado pro clipboard", ".dxt baixado pra X — entrega via Vaultwarden") | Microcopy técnico canônico BR-dev |
| Soft-delete em revoke (`revoked_at = now()` + `expires_at = now()` + `delete()`) | Preserva audit log mesmo após revogação — ADR 0057 §6 |
| Inertia::defer com OTel span por método builder | Boa observabilidade Wave 11 |

---

## 8. Restrições Tier 0 que qualquer redesign precisa respeitar

- **Multi-tenant ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)):** tela é superadmin-only mas mostra users de `business_id` do superadmin. Drill-down de tokens **NUNCA** pode listar tokens cross-tenant. Filtro implícito: `where('business_id', auth()->user()->business_id)` no JOIN com users.
- **Reveal-once invariante ([ADR 0057](../../decisions/0057-tela-team-admin-regras-governanca-tokens-mcp.md) §2 + §10):** raw mostrado 1× só, hash SHA256 gravado. Nenhuma feature pode adicionar "ver token novamente" — incentivo correto à rotação.
- **Soft-delete em revoke ([ADR 0057](../../decisions/0057-tela-team-admin-regras-governanca-tokens-mcp.md) §6):** preserva `mcp_audit_log` queryable. Nunca `forceDelete()` em `mcp_tokens` linha exceto LGPD `esquecer-me` (cycle 02).
- **PageHeader canon roxo 295 ([ADR 0180/0182/0189/0190](../../decisions/)):** redesign NÃO pode trocar cor primária. PageHeader é Camada 2 (Shell) na Constituição UI v2, imutável via ADR.
- **Permission gate `copiloto.mcp.usage.all`:** middleware no construtor — toda rota nova herda. Pegar com `can:copiloto.mcp.usage.all` ou superadmin role.
- **OTel span obrigatório em ações Tier 0:** `teammcp.token.issue`, `teammcp.token.revoke`, `teammcp.tokens.list` (nova). Atributos NUNCA incluem `raw`.
- **Charter > Spec ([Constituição v2](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)):** PR de redesign **DEVE** criar `Index.charter.md` ao lado declarando: status `live`, charter_version 1, persona Wagner [W], goals (drill-down tokens individual + governança Tier 0), non-goals (self-service token-by-dev — Cycle 02 ADR 0057 §C; chart de evolução custo — Cycle 03 dataviz), invariantes (reveal-once, multi-tenant, soft-delete).
- **Cliente como sinal ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)):** persona aqui é Wagner solo + mercado SaaS (Vercel breach 2026 = sinal externo qualificado). Gaps "porque Linear faz" sem dor de Wagner viram ADR feature-wish, não US ativa. Os 6 Tier 0 acima TÊM sinal (ADR 0057 explicita, mercado pressiona, ou Wagner já reportou tinker manual).
- **biz=1 em smoke ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)):** qualquer Pest novo de drill-down roda em biz=1, nunca biz=4 (ROTA LIVRE).

---

## 9. Referências externas (Fase 2)

- [Stripe — API keys docs (reveal-once, RAK, rotation)](https://docs.stripe.com/keys)
- [Stripe — Best practices for managing secret API keys (2026)](https://docs.stripe.com/keys-best-practices)
- [Stripe — Restricted API keys](https://docs.stripe.com/keys/restricted-api-keys)
- [GitHub — Fine-grained personal access tokens (intro blog)](https://github.blog/security/application-security/introducing-fine-grained-personal-access-tokens-for-github/)
- [GitHub — Managing your personal access tokens (docs)](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens)
- [GitHub — Token expiration and revocation](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/token-expiration-and-revocation)
- [GitHub — Credential revocation API (changelog 2026-03)](https://github.blog/changelog/2026-03-26-credential-revocation-api-now-supports-github-oauth-and-github-app-credentials/)
- [Vercel — Tokens docs](https://vercel.com/docs/sign-in-with-vercel/tokens)
- [Vercel — April 2026 security incident bulletin](https://vercel.com/kb/bulletin/vercel-april-2026-security-incident)
- [Cloudflare — Create API token (fine-grained)](https://developers.cloudflare.com/fundamentals/api/get-started/create-token/)
- [Cloudflare — Account API tokens (rotation pattern)](https://developers.cloudflare.com/fundamentals/api/get-started/account-owned-tokens/)
- [Doppler — Cloudflare token zero-downtime rotation](https://docs.doppler.com/docs/cloudflare-tokens)
- [Zuplo — API Key Best Practices for 2026](https://zuplo.com/blog/api-key-best-practices)

---

## 10. Próximos passos (decisão Wagner)

1. **Wagner aprova top 5 Tier 0 (G-DESIGN-01..05) em 1 PR único** — parent agent codifica em ~290 linhas. Recomendação: criar branch `feat/team-mcp-tokens-drill-down` + Pest test + screenshot smoke biz=1.
2. **Wagner aprova G-DESIGN-06..09 em PR seguinte** — expiration policy default 90d + Export CSV com date-picker + audit log inline + a11y polish. Estimado ~250 linhas.
3. **G-DESIGN-10 (2FA step-up)** fica em backlog Cycle seguinte — sinal indireto (Vercel breach), não bloqueia hoje.
4. **Charter `Team/Index.charter.md`** nasce junto com PR #1 acima — declara goals/non-goals/invariantes explicitamente.

---

**Última atualização:** 2026-05-25
**Aprovado por:** — (pendente Wagner)
