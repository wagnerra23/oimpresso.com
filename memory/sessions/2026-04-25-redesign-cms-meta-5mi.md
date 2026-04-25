# Sessão 2026-04-25 — Redesign do site público + estratégia R$5mi/ano

> **Worktree:** `D:\oimpresso.com\.claude\worktrees\cms-redesign` (branch `claude/cms-react-redesign`, base `6.7-bootstrap`)
> **Início:** ~18h BRT
> **Foco:** descontinuar template UltimatePOS em inglês na home → migrar pra Inertia/React PT-BR + fix WP /ajuda + research competitivo + decisão estratégica de posicionamento.
> **Status final:** PR1+PR2 commitados, ADR 0026 gravado, aguardando validação visual local pra push+deploy.

---

## 1. O que foi entregue

### 1.1 — Fix do WordPress `/ajuda/` (50min)
**Problema:** HTTP 500 desde upgrade pra PHP 8.4. Plugin `ht-knowledge-base` usava `create_function()` (removido no PHP 8.0).
**Fix:** patch via PHP CLI no servidor — substitui 6 ocorrências por closures equivalentes. Backup completo em `wp-content/plugins/ht-knowledge-base.bak.20260425-195657`.
**Resultado:** [`oimpresso.com/ajuda/`](https://oimpresso.com/ajuda/) HTTP 200, conteúdo "Controle de Vendas / Gestão Financeira / NFe / Gestão da Produção" carregando.
**Memória:** [`reference_wp_ajuda_fix.md`](C:\Users\wagne\.claude\projects\D--oimpresso-com\memory\reference_wp_ajuda_fix.md) (auto-memória).
**Armadilha gravada:** se Wagner clicar "Atualizar plugin" no wp-admin, sobrescreve o patch e site quebra de novo.

### 1.2 — Crítica de design da landing atual (oimpresso.com/ + /pricing)
Crítica estruturada via `/design-critique` skill. Findings principais:
- Template UltimatePOS em inglês ("Automate your business management at very-Low cost")
- Erros gramaticais: "What They **Says** About Us"
- Pricing roxo "estilo 2010" travando em loading state
- Logos placeholder, ilustração SVG genérica de varejo (Storyset), sem identidade visual
- Hero focado em "low cost" em vez de valor real

### 1.3 — Redesign Cms em Inertia/React (PR1, commit `aabe142d`)
12 arquivos novos, +584 linhas.

**Arquivos:**
- `Layouts/SiteLayout.tsx` (público, sem AppShell)
- `Pages/Site/Home.tsx` com Component.layout
- 6 componentes em `Components/Site/`: SiteHeader, SiteFooter, Hero, DashboardMockup, SocialProof, FeatureGrid
- `Modules/Cms/Tests/Feature/SiteHomeTest.php` (4 testes Pest)
- Modificações em `CmsController` (Inertia::render) + Routes (rota `/old` fallback) + phpunit.xml

### 1.4 — Redesign /pricing em Inertia (commit `3fd21e6b`)
- `Pages/Site/Pricing.tsx` com 3 tiers (Essencial/Profissional/Enterprise) + toggle mensal/anual + FAQ
- Componentes `PricingTiers.tsx` + `PricingFaq.tsx`
- `PricingController` modificado pra Inertia + rota `/pricing/old` legado
- 2 testes Pest adicionais
- ADR 0025 + atualização SPEC `memory/requisitos/Cms.md`

### 1.5 — Research de concorrentes (commit `73945fc2`)
[`memory/comparativos/site_marketing_concorrentes_comunicacao_visual_2026_04_25.md`](../comparativos/site_marketing_concorrentes_comunicacao_visual_2026_04_25.md)

Top 6 do nicho mapeados: **Zênite, Mubisys, Alfa Networks, Visua, Calcgraf, Calcme**. Benchmarks: Bling, Omie, Tiny, Printi.

5 priority recommendations identificadas.

### 1.6 — Aplicação do research no PR2 (commit `9ffa56c2`)
Incorporadas 5 recomendações em 4 componentes:
- **Hero:** copy verbo-de-ação ("orça, imprime, monta e entrega") + métrica m² na subhead
- **SocialProof:** trocou "Cliente A/B/C" placeholders por banda de SETORES atendidos
- **FeatureGrid:** 6 → 8 features (adicionados Orçamento por m² + Ordem de Produção)
- **PricingTiers:** tier Profissional menciona m² + OP explicitamente
- **Hero:** entry point "Não sabe qual plano? Me ajuda a escolher →" (estilo Zênite)

### 1.7 — Matriz Capterra/G2 cruzada
[`memory/comparativos/oimpresso_vs_concorrentes_capterra_2026_04_25.md`](../comparativos/oimpresso_vs_concorrentes_capterra_2026_04_25.md)

Feature-by-feature: oimpresso vs 8 concorrentes em 6 categorias (Nicho gráfico, Fiscal BR, Operacional, Financeiro, Diferencial moderno, Operação SaaS). Notas G2 1-5 estimadas. Top 3 GAPS, Top 3 VANTAGENS, posicionamento sugerido (3 caminhos).

### 1.8 — ADR 0026 — Posicionamento estratégico
[`memory/decisions/0026-posicionamento-erp-grafico-com-ia.md`](../decisions/0026-posicionamento-erp-grafico-com-ia.md)

**Posicionamento adotado:** "**O ERP de comunicação visual com IA que substitui seu Mubisys/Zênite — e nunca esquece um cliente.**"

3 features prioritárias pra próximos 6 meses:
1. **PricingFpv** (cálculo m² + FPV gráfica) — 3-4 sprints
2. **Copiloto v1 production-ready** — 2-3 sprints
3. **CT-e + MDF-e + conciliação OFX** — 3 sprints

### 1.9 — Template Capterra próprio
[`memory/comparativos/_TEMPLATE_capterra_oimpresso.md`](../comparativos/_TEMPLATE_capterra_oimpresso.md)

Padrão reutilizável pra próximos comparativos: 10 seções (TL;DR, concorrentes, matriz 30+ features, notas G2, top 3 gaps, top 3 vantagens, 3 caminhos de posicionamento, math da meta, recomendação 3 features, sources). Checklist obrigatório antes de commitar.

---

## 2. Commits da sessão (em ordem)

| Commit | O quê |
|---|---|
| `aabe142d` | feat(cms): redesign landing publica em Inertia/React (PT-BR) |
| `3fd21e6b` | feat(cms,superadmin): redesign /pricing em Inertia + ADR 0025 |
| `73945fc2` | docs(comparativos): research de sites de marketing — concorrentes BR |
| `9ffa56c2` | feat(cms): aplica research de concorrentes — copy do nicho graf./com.visual |
| _(pendente)_ | docs(strategy): ADR 0026 + matriz Capterra + template + resumo sessão |

---

## 3. Pendências bloqueantes (Wagner precisa decidir/fazer)

### Pra hoje/amanhã
- [ ] **Validar visualmente PR1+PR2 local** (`composer install && npm install && npm run dev:inertia` no worktree, abrir `oimpresso.test`)
- [ ] **Push pra GitHub** + deploy SSH se OK
- [ ] **Confirmar prazo da meta R$5mi** (12/24/36 meses) — ADR 0022 ainda tem `pendente`

### Pra próxima sprint
- [ ] **Levantar faturamento real dos últimos 12 meses** (query Hostinger via SSH+MySQL — `reference_hostinger_analise.md` tem receita)
- [ ] **Levantar matriz clientes × módulos** (quem tem o quê dos 7 ativos) → alimenta Trilha 3 do 11-metas
- [ ] **Validar PricingFpv** com 5 orçamentos antigos da ROTA LIVRE antes de codar
- [ ] **Credenciais demo** pra eu tirar screenshot real do produto (substitui o DashboardMockup wireframe)

### Futuro próximo
- [ ] PR3: hidratar Hero/FeatureGrid com dados de `cms_pages` (Wagner edita pelo `/cms/cms-page` superadmin)
- [ ] Criar SPEC do `Modules/PricingFpv` em `memory/requisitos/PricingFpv/`
- [ ] PR4: consolidar `/ajuda/` (WP) no `Modules/Knowledgebase` Inertia + redirects 301

---

## 4. Decisões fixadas nesta sessão

| Decisão | Onde está gravada |
|---|---|
| Redesign Cms migra de Blade pra Inertia/React (mesmo stack do app) | ADR 0025 |
| `/` e `/pricing` rodam Inertia agora; Blade legado em `/old` e `/pricing/old` | ADR 0025 |
| Posicionamento "ERP de comunicação visual com IA" (Caminho B) | ADR 0026 |
| 3 features prioritárias 6m: PricingFpv, Copiloto v1, CT-e/MDF-e/conciliação | ADR 0026 |
| Padrão de comparativos competitivos = template `_TEMPLATE_capterra_oimpresso.md` | template no repo |
| Métrica de fé 90d: PricingFpv + Copiloto v1 em prod + 5 indicações ROTA LIVRE | ADR 0026 |
| WP `/ajuda/` patch é fragil (update via wp-admin reverte) | reference_wp_ajuda_fix.md |

---

## 5. Aprendizados desta sessão (pra próximas)

1. **Worktree atual estava muito atrás de produção** — perdi 30min descobrindo que `Modules/Cms` só existe na produção. Branch `producao` no servidor tem 90k+ arquivos mas não está no Git remote. Solução: criei nova worktree de `6.7-bootstrap` (branch ativa de deploy) — 25 módulos em vez dos 18 da branch antiga.
2. **SSH na Hostinger é flaky** — frequente timeout, resolvido com curl warm + retry. Memória `reference_hostinger_server.md` já avisava.
3. **Worktree git não tem `vendor/` nem `node_modules/`** — não dá pra rodar `php artisan` ou `npm run` direto. Solução pra validação local: Wagner roda `composer install && npm install` antes.
4. **`perl` não existe no Hostinger** — pra patches em massa em produção, usar `php -r '...'` ou `python` ao invés.
5. **`PricingController` está no `Modules/Superadmin`, não no Cms** — `/pricing` é rota pública mas o controller mora no Superadmin. Acoplamento cross-módulo aceitável (PR1 não refatora isso).
6. **Template Inertia bem estabelecido**: `Pages/{Modulo}/*.tsx` + `Component.layout = (page) => <Layout>{page}</Layout>`. Memória `preference_persistent_layouts.md` já avisava: "nunca envolver em <AppShell>".
7. **Padrão de testes** = PHPUnit-style class extends TestCase, NÃO Pest puro (apesar do projeto ter Pest configurado em `tests/Pest.php`). Adicionar `Modules/X/Tests/Feature` no `phpunit.xml`.
8. **Conteúdo rico no WP officeimpresso.com.br** — DB tem 40+ posts + páginas pitch ("O ERP", "Você tem interesse em reduzir custos") com 5-16k chars cada. Material bom pra importer futuro pro `cms_blogs`.

---

## 6. Próxima sessão deveria começar por...

1. **Verificar que Wagner validou PR1+PR2 visualmente** — se não, debugar erros de build local
2. **Push + deploy SSH** se OK
3. **Iniciar SPEC do Modules/PricingFpv** em `memory/requisitos/PricingFpv/` (segue template padrão de SPEC do projeto)
4. **Levantar dados de faturamento real** (SSH+MySQL) pra calibrar números do ADR 0026
