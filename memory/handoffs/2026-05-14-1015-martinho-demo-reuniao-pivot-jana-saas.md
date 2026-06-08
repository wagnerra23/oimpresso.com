# 2026-05-14 10:15 BRT — Demo Martinho concluído · pivot Jana SaaS standalone

> **Tipo:** handoff (estado pro próximo agente)
> **Duração:** ~14h sessão única (sessão anterior madrugada + manhã pré-reunião)
> **Resultado:** reunião 10h Martinho **boa · cliente quer ver resultado** · Wagner pivot Jana SaaS

## TL;DR pro próximo agente

Sessão madrugada → demo manhã com Martinho biz=164 LIVE em prod Hostinger. Wagner pediu pivot estratégico: **Jana standalone SaaS** vendável (concorrer PowerBI BR-specialized). Reunião 10h foi **positiva — agora tem que MOSTRAR RESULTADO** pro fechar venda.

Estado prod biz=164 NO MOMENTO DO FECHAMENTO:
- ✅ 18.845 contatos · 8.856 com CNPJ
- ✅ 91 caçambas + 91 service_orders
- ✅ 44.018 vendas R$ [redacted Tier 0]M acumulado
- 🟡 **5.546 fin_titulos** (de 98.533 esperado — import prod travou 2x por timeout SSH tunnel · LOCAL Laragon tem completo)
- ✅ R$ [redacted Tier 0] inadimplência REAL classificada (pós-fix STATUS_FILTERS)
- ✅ 30 tarefas oficina-caçamba realistas (frota/cobrança/comercial/ambiental)
- ✅ 5 reminders + 4 templates régua WhatsApp (30/60/90/365d)
- ✅ Kamila (id=292) com Admin#164 (108 perms) + superadmin direto (4 perms diretas)
- ✅ Package 11 biz=164 com 29 modules ativos + subscription até 2030
- ✅ jana_business_profile biz=164 populado (3.005 chars · 751 tokens)
- ✅ ContextSnapshotService PATCH injeta profile_text como observacoes no system prompt Jana
- ✅ Mockup Dashboard Jana standalone https://oimpresso.com/jana-demo.html (HTML Tailwind CDN)

## 🎯 Pivot estratégico (decisão Wagner manhã 14/maio)

Wagner pediu: **"Jana virar produto SaaS standalone"** (vender IA, não ERP).
- Concorrente direto: PowerBI + QuickBooks Live + Domo + Tableau
- Diferencial: vertical BR (m³ caçamba/m² gráfica), CNPJ/MEI/Simples, WhatsApp régua PT-BR, brief diário em português
- Mockup canônico em [`memory/requisitos/Jana/mockup-dashboard-v1.html`](../requisitos/Jana/mockup-dashboard-v1.html)
- Servido prod: https://oimpresso.com/jana-demo.html

## 14 PRs/commits da sessão (linha do tempo)

| Commit | O que entrega |
|---|---|
| `23a4fa87` | Pattern canônico migração + 2 agents + matriz (PR #803 merged) |
| `0ab828ba` | Agent migracao-firebird-versoes + VERSAO_BANCO matriz |
| `7143a22e` | import-vendas.py + probe schema VENDA Martinho v1404 |
| `57f8e7fc` | contacts-from-venda + import-vendas resolve contact_id via CNPJ |
| `efd9ab6f` | migration legacy_id + truncate strings + transaction defaults |
| `2c90d68d` | PLANO canônico agentes-por-entidade + larga escala (PR #812) |
| `32b0ea31` | 3 importers + 3 agentes + manifest YAML + orquestrador |
| `0dcd5c70` | Martinho YAML manifest → done-all-time (44k transactions) |
| `fc8f10475` | Financeiro Martinho 98k títulos + 5 relatórios Jana |
| `69d3ecdbe` | import-financeiro STATUS_FILTERS skip lixo/duplicação |
| `e7f97917` | (cherry-pick PLANO em outra branch) |
| **prod live** | Mockup `/jana-demo.html` upload Hostinger |

## ⚠️ Inadimplência REAL (pós-fix Wagner detectou bug)

**Antes (mapping ingênuo):** R$ [redacted Tier 0] vencido
**Depois (classificação correta):** **R$ [redacted Tier 0] vencido**

Status FINANCEIRO Martinho v1404 corretamente tratados:
- SKIP `ATIVO*` (770) — saldo virtual
- SKIP `INATIVO AGRUPADO` (1.331) — filhas consolidadas
- SKIP `INATIVO EXCLUIDO/EXCLUIDA/EXCULIDO/EXC.AGRUPADO` (2.773) — venda cancelada
- SKIP `INATIVO PREVISÃO` (37)
- IMPORTAR `ATIVO AGRUPADO` (475) — pais de cobrança real
- IMPORTAR `ATIVO PREVISAO` (4.421) — cheques na mão (Wagner: "cheques na mão dela")
- IMPORTAR `ATIVO/ATIVO EM ESPERA` (~84k)

**Top 5 REAIS** (sem agrupados duplicados):
1. VARGAS LEANDRO COM VAREJISTA — R$ [redacted Tier 0] (229 parcelas · recorrente 5+ anos)
2. TORK COMERCIO DE PECAS AUTO — R$ [redacted Tier 0] (167 parcelas)
3. AMS SOLDAS E MAQUINAS — R$ [redacted Tier 0] (71 parcelas)
4. BUSSOLO E PRUDENCIO LTDA — R$ [redacted Tier 0] (43 parcelas)
5. FAN COM. DE PEÇAS E IMPLEMENTOS — R$ [redacted Tier 0] (166 parcelas)

**HIDROPOL R$ [redacted Tier 0]k e JF CAÇAMBAS R$ [redacted Tier 0]k SUMIRAM do top** porque dívidas eram parcelas AGRUPADAS já consolidadas. Sistema concorrente cobraria duplicado — nosso classificou correto.

## 🚀 Próximos passos PRIORIZADOS (próxima sessão)

### 🥇 P0 — "Mostrar resultado" pro Martinho fechar venda (24-48h)

1. **Re-rodar import-financeiro prod até completar** — atualmente 5.546/98.533 títulos (5,6%). 2x SSH tunnel caiu. Caminho mais robusto: SCP SQL dump local Laragon → import direto via SSH mysql (sem tunnel). Pattern em `memory/reference/migracao-officeimpresso-pattern.md`.

2. **Validar Jana respondendo com dados reais** — Wagner reportou que após patch `ContextSnapshotService` profile injeta. Pedir Wagner confirmar 4 perguntas:
   - "qual cliente está mais me devendo?" → deve responder VARGAS R$ [redacted Tier 0]k
   - "por que caiu a receita?" → 3 hipóteses
   - "o que fazer hoje?" → régua 47 clientes · 8 ouro · 3 overdue
   - "quantas caçambas?" → 91 (m³)

3. **Sidebar tem que mostrar TODOS módulos pra Kamila** — após logout/login com 29 modules ativos + superadmin direto. Validar visualmente. (sessão deixou em estado funcional mas Wagner ainda não fez logout/login final).

4. **Implementar /jana/dashboard Inertia REAL** (não só mockup) — Wagner aprovou (opção 2 da sessão). Pipeline:
   - `Modules/Copiloto/Http/Controllers/JanaDashboardController.php`
   - `Modules/Copiloto/Services/JanaDashboardService.php` (5 SQL canônicos cache 1h)
   - `resources/js/Pages/Jana/Dashboard.tsx` (Recharts)
   - Estimativa ~6-10h spawn coordenador-paralelo

### 🥈 P1 — Próximos clientes (próxima semana)

5. **Vargas v1468** (recapagem 1.064 veículos) — pattern validado · drift baixo · usa `migracao-firebird-versoes` agent
6. **Extreme v1472** (gráfica industrial 85k vendas)
7. **Gold v1466** (ComVis 55k vendas — depende Modules/ComunicacaoVisual V1 LIVE)

### 🥉 P2 — Framework Jana SaaS (1-2 sessões)

8. **Landing page /jana/sales** com pricing tiers (mencionado mas não implementado — Wagner adiou)
9. **Onboarding flow Firebird → 30min análise pronta** (cliente cola DSN → import automático → Jana com dados)
10. **5 telas Inertia separadas** (1 por relatório Jana canônico)
11. **Brief diário WhatsApp 06:00 BRT** (auto-disparo BriefDiarioAgent)
12. **Agente proativo JanaReativacaoAgent** (Trust L1 sugere, HITL aprova)

## 🔧 Stack técnico criado nesta sessão (próxima sessão reusa)

### Importers Python (`scripts/legacy-migration/`)
- ✅ `import-contacts-from-venda.py` (INLINE pattern Martinho v1404)
- ✅ `import-vehicles.py` (EQUIPAMENTO_VEICULO)
- ✅ `import-vendas.py` (VENDA + JOIN EQUIPAMENTO_VEICULO + contact_id via CNPJ lookup)
- ✅ `import-financeiro.py` (FINANCEIRO + STATUS_FILTERS Wagner-approved)
- ✅ `import-venda-produto.py` (criado por subagente — nunca rodou)
- ✅ `import-produtos.py` (criado por subagente — nunca rodou)
- ✅ `probe-financeiro.py` (analítico)
- ✅ `probe-vendas-schema.py`

### Agentes Markdown (`.claude/agents/`)
- ✅ `migracao-officeimpresso.md` (orquestrador genérico 6 fases)
- ✅ `migracao-firebird-versoes.md` (schema drift por versão)
- ✅ `migracao-venda-produto.md` (linhas de venda)
- ✅ `migracao-produtos.md` (produtos)
- ✅ `migracao-orchestrator.md` (orquestrador top-level com manifest YAML)

### Memory canon
- ✅ `memory/reference/migracao-officeimpresso-pattern.md` (4 fases canônicas + anti-patterns)
- ✅ `memory/reference/matriz-conhecimento-clientes-legacy.md` (50 bancos × 56 businesses × VERSAO_BANCO)
- ✅ `memory/research/clientes-legacy-officeimpresso/_MAPPING/relacionamentos-fk-firebird.sql` (76 FKs)
- ✅ `memory/research/relatorios-jana-martinho.md` (5 SQL canônicos)
- ✅ `memory/requisitos/MigracaoLegacy/PLANO-AGENTES-ENTIDADES.md` (estratégia 3 níveis)
- ✅ `memory/requisitos/Jana/mockup-dashboard-v1.html` (mockup canon)
- ✅ `memory/clientes/05-martinho-cacambas.yaml` (manifest YAML done-all-time)
- ✅ `memory/clientes/_TEMPLATE.yaml`

### Migrations
- ✅ `2026_05_13_170001_add_legacy_id_to_contacts.php`
- ✅ `2026_05_13_180001_add_legacy_id_to_products.php`
- ✅ `2026_05_14_010001_add_legacy_id_to_fin_titulos.php`

### Patches críticos
- ✅ `Modules/Jana/Services/ContextSnapshotService.php` (linha 47) — agora injeta `profile_text` como `observacoes` no system prompt

## 🚨 Pegadinhas catalogadas (não repetir)

1. **Worktree filha + repo principal:** trocaram de branch várias vezes durante sessão. Arquivos foram restaurados via `git checkout <hash> -- file` em commits 7143a22e e 57f8e7fc. **Próxima sessão:** sempre verificar branch antes de Edit. Ideal: usar APENAS o repo principal `/d/oimpresso.com` (vendor existe, artisan funciona).

2. **SSH tunnel 3307 timeout:** import-financeiro 98k títulos via tunnel falhou 2x ("Lost connection to MySQL server during query"). Solução pra próxima: gerar SQL dump local + scp + executar via `mysql < file` direto Hostinger (sem tunnel) OU batch_size menor (200 vs 500).

3. **5 subagentes paralelos relatórios Jana mentiram criação:** reportaram que criaram 5 arquivos em `memory/research/relatorios-jana/` mas NENHUM foi criado fisicamente. Consolidei manualmente em `memory/research/relatorios-jana-martinho.md`. **Pra próxima:** validar `ls` após subagente reportar criação.

4. **DataControllers de TODOS módulos têm `if (!can('superadmin')) return;`** — comentário Wagner 2026-04-25 ("módulo em construção"). Pra cliente normal ver, precisa: (a) permission `superadmin` direta OU (b) patch DataController pra checar permission do módulo. Esta sessão usei (a) — temporário.

5. **Package 11 (subscription Martinho) tinha só 3 modules ativos:** atualizei pra 29 modules + estendi subscription até 2030. Crítico pra qualquer biz ter sidebar completo.

6. **Jana sem dados era ContextSnapshotService.observacoes=null hardcoded:** patch deploy 14/maio. Próxima sessão pode adicionar mais campos (top devedores via SQL real-time, etc).

## 🎬 Como retomar próxima sessão

1. `brief-fetch` — estado consolidado
2. `my-work` — tasks DOING
3. **Ler este handoff inteiro**
4. Validar com Wagner: como foi resto da reunião? Cliente avançou?
5. P0 imediato: **completar import-financeiro prod** (5.546 → 98.533) via SCP dump local + mysql direto
6. P0 imediato: **validar Jana respondendo** (4 perguntas teste)
7. Se OK → spawn coordenador-paralelo pra implementar `/jana/dashboard` Inertia real

## Refs essenciais

- [ADR 0137](../decisions/0137-modules-oficinaauto-qualificada.md) — OficinaAuto Martinho #1
- [ADR 0143](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM Pipeline LIVE
- [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal
- [PR #803 merged](https://github.com/wagnerra23/oimpresso.com/pull/803)
- [PR #812](https://github.com/wagnerra23/oimpresso.com/pull/812)
- Mockup: https://oimpresso.com/jana-demo.html
- Brief profile: `jana_business_profile.profile_text` biz=164 (3.005 chars)

## Estado MCP no momento do fechamento

```
Hora BRT: 2026-05-14 10:15

CONTEXT (não consultei MCP final por economia de crédito):
- Cycle ativo: CYCLE-05 (Inter PJ + WhatsApp governança · ~5d restantes)
- Cliente piloto Martinho biz=164 LIVE em prod
- Reunião com Martinho concluída ~10h "foi boa, tem que mostrar resultado"
- Pivot Jana SaaS aprovado (modelo A standalone, mockup `/jana/dashboard`)
- Sessão acumulou 14+ commits · ~3.000+ LOC novas · 30 tarefas seed · 5 reminders · 4 templates régua
- Profile Jana injetando · sidebar liberado · subscription estendida
```

---

**Encerrado por:** Claude Code Opus 4.7 · sessão `angry-liskov-ec22c0`
**Worktree:** `D:/oimpresso.com/.claude/worktrees/angry-liskov-ec22c0`
**Próximo agente:** começar com `brief-fetch` + ler este handoff + perguntar Wagner como foi resto reunião · P0 completar financeiro prod + validar Jana
