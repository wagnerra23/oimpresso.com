# PLANO DE DESIGN — subir as 44 telas < 70 e alinhar ao sidebar

> **Origem:** [SCREEN-GRADE BOARD 2026-05-30](SCREEN-GRADE-BOARD-2026-05-30.md) — 222 telas, média **75/100**, método SCREEN-GRADE 9.75.
> **Escopo deste plano:** as **44 telas abaixo de 70** (42 Developing 50-69 + 2 Beginner <50).
> **Meta:** toda tela ≥ **70** (nível Advanced). **Ratchet ([ADR 0236](../../decisions/0236-screen-grade-ratchet.md)): nota só sobe.**
> **Fechamento por EVIDÊNCIA, não opinião** (sessão 2026-05-30): tela fecha com `ds:report` do módulo = 0 (ou alvo) + screenshot que bate o golden Cowork. Narrativa não fecha.
> **Loop:** Cowork (Claude Design) entrega visual-source/golden + charter → Claude Code implementa → fecha por evidência ([ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)).

---

## Parte 1 — O que o design tem que fazer (9 padrões = receitas reaproveitáveis)

**80% das 44 telas falham pelos MESMOS 9 motivos.** A alavancagem está em resolver o PADRÃO 1× (receita/codemod) e aplicar em lote — não tratar tela a tela do zero.

| # | Padrão (o que o design faz) | Telas atingidas | Esforço/tela | Como fecha (evidência) |
|--:|---|--:|---|---|
| **P1** | **Cor crua → token v4 roxo.** Remover `bg-sky/zinc/stone/amber/blue/emerald` crus, `#hex` e `oklch()` inline; trocar por tokens DS (primary roxo 295). | ~25 | S–M | `ds:report` cor-crua = 0 |
| **P2** | **Montar no AppShellV2.** Telas que renderizam fora do shell canon → embrulhar no AppShellV2 + PT-01. | 2 (Manufacturing, ComunicacaoVisual) | L | render no shell + screenshot |
| **P3** | **PageHeader canon.** Header legacy (`os-page-h`/`fin-page-h`) → `<PageHeader>` v3 + SubNav. | ~6 | M | PageHeader presente no diff |
| **P4** | **Charter ausente → criar `.charter.md`.** Mission/Goals/Non-Goals/Anti-hooks ao lado do `.tsx`. | ~12 | S | arquivo charter existe |
| **P5** | **Nativo → @/Components/ui.** `<select>/<input>/<textarea>/<table>` crus → Select/Input/Textarea/DataTable DS. | ~10 | M | zero tag nativa no diff |
| **P6** | **`confirm()`/`alert()`/`prompt()` → Dialog/AlertDialog DS.** | ~5 | S | zero `window.*` no diff |
| **P7** | **`Inertia::defer` + skeleton.** Props pesadas eager → defer com fallback. | ~4 | M | defer no controller + skeleton |
| **P8** | **A11y + PII (LGPD).** `role/aria`/foco teclado; mascarar doc/CPF exposto. | ~4 | S–M | mask aplicada + axe limpo |
| **P9** | **Stub → conteúdo real (Speed-to-task).** Placeholder "em construção" → feature mínima viável. | ~7 | L | tela entrega valor real |
| **P0-XSS** | **Sanitizar `dangerouslySetInnerHTML`** (Site/CMS) — risco stored-XSS. | 3 | M | sanitize no render |

---

## Parte 2 — A LISTA (44 telas em 5 ondas)

### 🚑 Onda 0 — Resgate (4 telas) — as 2 piores + as 2 únicas SEM AppShellV2
| Tela | Grupo | Nota→Alvo | Fix primário | Esf |
|---|---|--:|---|:--:|
| `NfeBrasil/Transactions/NfceStatus` | FISCAL | 38→70 | P1 — remover `style{}` + `oklch 240` azul; Card/Badge DS + ação reemitir | M |
| `Produto/StockHistory` | CADASTRO | 47→70 | P9 — timeline real via JSON+defer (hoje só linka Blade legacy) | L |
| `Manufacturing/Index` | CADASTRO | 50→70 | P2 — montar no AppShellV2 + @/ui + PT-01 Lista; habilitar CTA | L |
| `ComunicacaoVisual/Index` | PRODUÇÃO | 54→70 | P2 — montar no AppShellV2 + tokens; entregar calculadora m² (API já existe) | M |

### 🎨 Onda 1 — Pre-Flight em lote (13 telas) — perdem ESSENCIALMENTE por cor/token (P1). Maior alavancagem, menor esforço.
| Tela | Grupo | Nota→Alvo | Fix primário | Esf |
|---|---|--:|---|:--:|
| `Auditoria/Index` | SISTEMA | 57→70 | P1 + barra de filtros | M |
| `Auditoria/Detail` | SISTEMA | 58→70 | P1 (sky azul) + diff formatado (não JSON dump) | S |
| `ads/Admin/Graph` | SISTEMA | 60→70 | P1 — HEX cru inline no ReactFlow → CSS vars; responsivo | L |
| `Admin/FeatureFlags/Index` | SISTEMA | 64→70 | P1 (amber/red → Alert) + P4 charter | M |
| `Admin/FeatureFlags/Show` | SISTEMA | 66→70 | P1 + P5 (`<select>`→Select) + P4 | M |
| `governance/Policies` | SISTEMA | 66→70 | P1 (emerald) + Switch DS optimistic | S |
| `ads/Admin/Learning` | SISTEMA | 67→70 | P1 (colorMap 9 cores) + chart com eixo | M |
| `Ponto/Relatorios/Index` | RH | 68→70 | P1 (blue/violet PROIBIDO) + params de período | S |
| `Produto/SellingPrices` | CADASTRO | 68→70 | P1 (stone) + P3 PageHeader + atalho salvar | M |
| `Fiscal/Sped` | FISCAL | 68→70 | P1 (hex fallback) — núcleo já funciona | S |
| `Admin/RagQualityDashboard` | SISTEMA | 69→70 | P1 + P4 charter + tooltip sparkline | M |
| `ads/Admin/Confidence` | SISTEMA | 69→70 | P1 + a11y tabela + mobile card-stack | M |
| `governance/DriftAlerts` | SISTEMA | 69→70 | P1 (amber) + Card DS + CTA por item | M |

### 🏗️ Onda 2 — Stubs → conteúdo real (6 telas) — precisam decisão de produto + feature (P9)
| Tela | Grupo | Nota→Alvo | Fix primário | Esf |
|---|---|--:|---|:--:|
| `Financeiro/Unificado/Novo` | FINANÇAS | 52→70 | P9 — stub picker (2 cards) → form unificado real | L |
| `Jana/Brief/Index` | IA (atalho) | 52→70 | P9 — renderizar brief real inline (não redirect) | L |
| `Jana/Regras/Index` | IA (atalho) | 52→70 | P9 — listar policies PolicyEngine read-only | L |
| `Jana/Painel` | IA (atalho) | 55→70 | P9 + P1 — markup `.jc-*` → @/ui; unificar c/ Cockpit | L |
| `Repair/JobSheet/Index` | PRODUÇÃO | 52→70 | P9 — placeholder DataTables → TanStack real | L |
| `Ponto/Welcome` | RH | 58→70 | P9 — stub boas-vindas → dashboard de ponto (pendências) | M |

### 🧱 Onda 3 — Conformance estrutural (18 telas) — PageHeader + charter + @/ui + defer + Dialog + a11y
| Tela | Grupo | Nota→Alvo | Fix primário | Esf |
|---|---|--:|---|:--:|
| `Financeiro/Advisor/Login` | FINANÇAS | 50→70 | P5 + P1 + P4 (portal contador, fora do shell ok) | M |
| `Financeiro/Advisor/Dashboard` | FINANÇAS | 52→70 | P5 + P1 + P4 — tudo hand-roll → @/ui | L |
| `Produto/Unificado/Index` | CADASTRO | 56→70 | P5 (nativos) + P1 (sky/stone) + a11y | L |
| `Financeiro/AssinaturaAtualizar` | FINANÇAS | 58→70 | P3 PageHeader + P4 + preview de impacto | M |
| `Financeiro/Configuracoes/Contador` | FINANÇAS | 58→70 | P5 + P3 SubNav + P6 (confirm) + P1 (bg-blue) | M |
| `superadmin/Usuario360/Index` | SISTEMA | 58→70 | P4 + Button DS + debounce busca | S |
| `superadmin/Usuario360/Show` | SISTEMA | 64→70 | P6 (confirm→Dialog) + P1 + P4 | M |
| `OficinaAuto/Vehicles/Edit` | COMERCIAL | 62→70 | restaurar paridade de campos c/ Create + P5 | M |
| `OficinaAuto/Vehicles/Create` | COMERCIAL | 64→70 | P4 + P5 (Select/Textarea) + paridade Edit | M |
| `OficinaAuto/Vehicles/Show` | COMERCIAL | 68→70 | P4 + badge canon + KPI/ação FSM no topo | M |
| `OficinaAuto/ServiceOrders/Create` | COMERCIAL | 66→70 | P5 (nativos) + erros completos + combobox placa | M |
| `Repair/JobSheet/AddParts` | PRODUÇÃO | 61→70 | autocomplete produto (não Variation ID) + totais | L |
| `Repair/JobSheet/Create` | PRODUÇÃO | 68→70 | busca cliente (não contact_id) + erros inline + P5 | L |
| `Repair/Dashboard/Index` | PRODUÇÃO | 62→70 | P7 defer + gráficos reais (não listas) + KPIs | M |
| `Admin/Index` | SISTEMA | 68→70 | P7 — defer nos 10 widgets + StatusBadge tokenizado | L |
| `Financeiro/Extrato/Index` | FINANÇAS | 67→70 | P8 PII mask doc + P3 PageHeader/SubNav | S |
| `Settings/PaymentGateways/CnabRetorno` | SISTEMA | 58→70 | P4 + P1 (stone) + dropzone | M |
| `MemCofre/Modulo` | SISTEMA | 69→70 | markdown render (não `<pre>` dump) + tabs overflow | M |

### 🌐 Público (3 telas) — fora do app shell, mas com fix de SEGURANÇA obrigatório
| Tela | Grupo | Nota→Alvo | Fix primário | Esf |
|---|---|--:|---|:--:|
| `Site/BlogPost` | Público | 55→70 | **P0-XSS** sanitizar HTML + lazy img + meta autor/data | M |
| `Site/Page` | Público | 58→70 | **P0-XSS** sanitizar + fallback null/404 | M |
| `Site/Blogs` | Público | 68→70 | paginação + busca/tags + data pt-BR | M |

---

## Parte 3 — Onda 4: alinhamento estrutural do sidebar (4 fixes, não-tela)

Cruzando o board (agrupado por **módulo**) com o `SIDEBAR_GROUPS` canon ([ADR 0180](../../decisions/0180-sidebar-v3-href-direto-ghosts-pageheader.md), 8 grupos):

1. **SISTEMA está inchado (15 das 44 telas fracas).** Maioria é **ferramenta interna** (`ads/*`, `governance/*`, `MemCofre`, `Usuario360`, `RagQuality`) — não tela de cliente. **Fix:** separar "interno/superadmin" do SISTEMA do tenant (cliente não deve ver governança no menu dele). Várias já deviam viver só no footer Superadmin cascade.
2. **Módulos órfãos caindo em MAIS:** `ads`, `MemCofre`, `kb`, `ProjectMgmt` **não declaram `group`** no DataController → caem no fallback MAIS (fim, fechado). **Fix:** dar group canon OU marcar explicitamente como interno.
3. **Site/Auth não pertencem ao sidebar** (público/login) — correto estarem fora, mas o board mistura na média. **Fix:** bucket "Público" separado no board.
4. **`OficinaAuto` duplicado** em COMERCIAL e PRODUÇÃO no `SIDEBAR_GROUPS.items[]`. Resolve pra COMERCIAL (vem antes), mas é dívida. **Fix:** remover da lista PRODUÇÃO.

### Mapa correto módulo → grupo (ordem canon do sidebar)
| Grupo (ordem) | Módulos |
|---|---|
| _Atalhos topo_ | IA `Jana` · Equipe `team-mcp` · Atendimento `Whatsapp` |
| 1 CADASTRO | Cliente · Produto · Manufacturing |
| 2 COMERCIAL | Sells · OficinaAuto |
| 3 FINANÇAS | Financeiro · RecurringBilling · TransactionPayment |
| 4 FISCAL | Fiscal · NfeBrasil · Nfse |
| 5 PRODUÇÃO | Repair · ComunicacaoVisual |
| 6 ESTOQUE | Compras/Purchase · StockAdjustment · StockTransfer |
| 7 RH | Ponto · Essentials |
| 8 SISTEMA | Admin · Auditoria · governance · Modules · Settings |
| _Footer Superadmin_ | superadmin · Backup/CMS/Conector/Office Impresso |
| _Público_ | Site · Auth |
| _MAIS / interno_ | ads · MemCofre · kb · ProjectMgmt |

---

## Parte 4 — Sequência de execução + custo (IA-pair, [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md))

| Onda | Telas | Foco | Esforço estimado (IA-pair) | Move o quê |
|---|--:|---|---|---|
| **0 Resgate** | 4 | piores + sem-shell | ~1 dia | tira o projeto do "vermelho" (0 Beginner, 0 sem-shell) |
| **1 Pre-Flight lote** | 13 | P1 cor→token (receita 1×) | ~1-2 dias | +13 telas pra Advanced com baixo esforço |
| **2 Stubs** | 6 | P9 feature real | ~3-4 dias | precisa decisão de produto antes |
| **3 Estrutural** | 18 | PageHeader/charter/@ui/defer | ~3-4 dias | o grosso da conformance |
| **Público** | 3 | XSS + discoverability | ~0,5 dia | fecha risco de segurança |
| **4 Sidebar** | — | 4 fixes de grupo | ~0,5 dia | alinha navegação ↔ board |

**Critério de pronto (toda tela):** `ds:report --module=<X>` = 0 (ou alvo declarado) **+** screenshot aprovado pelo Wagner que bate o golden Cowork. Sem isso, não fecha (anti-gaming, sessão 2026-05-30).

---

_Gerado 2026-05-31 a partir do board 2026-05-30. Companheiro de [SCREEN-GRADE-BOARD-2026-05-30.md](SCREEN-GRADE-BOARD-2026-05-30.md)._
