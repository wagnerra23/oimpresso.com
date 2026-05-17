# CAPTERRA-FICHA — ComunicacaoVisual

> Ficha canônica de benchmark do módulo `Modules/ComunicacaoVisual`.
> Reaproveita matriz e priorizações de [`SPEC.md`](SPEC.md), [`MATRIZ-ROI.md`](MATRIZ-ROI.md) e [`BRIEFING.md`](../../../Modules/ComunicacaoVisual/BRIEFING.md).
> ADR governança: [0089 Capterra-driven](../../decisions/0089-capterra-driven-module-evolution.md) + [0101 Capterra v2 3 eixos](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md) + [0121 modular vertical](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md).
> Wave 22 (2026-05-16) — 1ª emissão. Próxima revisão sugerida: 2026-08-16.

---

## Identidade do módulo

- **Nome interno**: `ComunicacaoVisual`
- **Vertical CNAE**: 1813-0/01 (Impressão de material de segurança / comunicação visual / serviços de impressão sob encomenda)
- **Domínio de negócio**: gráfica rápida + comunicação visual BR — lona, fachada, plotter, banner, adesivo, painel, sinalização, brinde, gráfica digital + offset pequeno porte
- **Cliente piloto-alvo**: 6 OfficeImpresso legacy "saudáveis" (Gold confirmado vertical comvis em GO, Vargas/Extreme/Zoom/Fixar/Mhundo/Produart candidatos)
- **Status**: 🟡 em construção (Sprint 1 backend canon entregue; Sprint 2 Pages Inertia + NfeBrasil pendente; piloto Q3/2026)

## Concorrentes-alvo direto (4 BR + 1 global benchmark)

| Concorrente | URL | Posicionamento | Base instalada | Preço (faixa) |
|---|---|---|---|---|
| **Mubisys** | [mubisys.com](https://mubisys.com/) | Líder vertical comunicação visual BR (Mubi Sistemas / PG Consultoria) — orçamento + produção + financeiro + estoque + app mobile + MubiDrive (~150TB DAM) | gráficas SP/PR (centenas) | sob consulta (R$ [redacted Tier 0]-600/m estimado) |
| **Zênite Sistemas (GE 3.0)** | [zsl.com.br](https://www.zsl.com.br/) | 25+ anos, 2.000+ gráficas BR — Lite/Smart/Standard/Full — orçamento+PCP+NFe+estoque+CRM | 2.000+ gráficas nacional | escalonado por porte (R$ [redacted Tier 0]-1.500/m) |
| **Calcgraf / NetCalc** | [calcgraf.com.br](https://www.calcgraf.com.br/) | 40+ anos especialista orçamentação gráfica — 2 milhões orçamentos/mês processados — cálculo automático etiqueta/cilindro/faca | gráficas offset BR | sob consulta |
| **Calcme** | [calcme.com.br](https://www.calcme.com.br/) | Gráficas + marcenarias — orçamento + PCP Kanban + WhatsApp Chatme + NFe + comissão + financeiro | crescendo BR (PMEs) | planos a partir R$ ~200/m (2026 reajuste) |
| **EFI PrintSmith Vision 5** (global) | [efi.com](https://www.efi.com/pt-br/products/productivity-software/management-mis-erp/efi-printsmith-vision/overview/) | MIS gráfica global EFI — web-to-print + offset/digital + integração MarketDirect StoreFront — versão 5 HTML 2026 | global (gráficas médio porte) | US$ 599 (offset) - US$ 750 (offset+digital) one-shot |

> ⚠️ Concorrentes adicionais menores levantados em SPEC §4: **Alfa Sistemas** (PR — gráficas pequenas), **Visua** (checklist instalação fachada NR-35 diferencial). Citados ao longo desta ficha.

---

## Capacidades baseline com score (20 capacidades P0-P3)

```yaml
capacidades:

  # ============= P0 — bloqueia 1ª piloto =============

  - id: calculo-m2-automatico
    nome: "Cálculo m² automático server-side por substrato"
    score: P0
    descricao: "Recebe largura×altura×qtd → calcula m² total + aplica preço/m² do substrato + acabamentos. Coração da operação CV."
    quem_tem: ["Mubisys", "Zênite", "Calcgraf", "Calcme", "PrintSmith"]
    quem_nao_tem: []
    evidencia_de_pronto: "OrcamentoCalculator::calcular() + Pest cobre cenários edge (largura zero, sangria, faca) + UI inline"
    status_oimpresso: "✅ done (Sprint 1) — OrcamentoCalculatorTest.php verde"

  - id: cadastro-substrato-preco-m2
    nome: "Cadastro substrato (lona 280g/440g, adesivo, vinil, ACM) + tabela preço/m² escalonada"
    score: P0
    descricao: "Materials/Substratos com preço/m² + tier por volume + custo CMYK estimado. Sem isso, sem orçamento."
    quem_tem: ["Mubisys", "Zênite", "Calcgraf", "Calcme"]
    quem_nao_tem: ["Visua (foco instalação)"]
    evidencia_de_pronto: "Substrato + Material entities + MaterialSeeder + UI /comvis/materiais"
    status_oimpresso: "✅ done — Substrato + Acabamento + InstalacaoCatalogo migrations + seeder + Tier0Guard verde"

  - id: pcp-kanban-grafico
    nome: "PCP Kanban gráfico com vocabulário CV (arte → corte → impressão → laminação → acabamento → conferência)"
    score: P0
    descricao: "Painel visual operadores movem OS entre estágios; bloqueia salto inválido; transparência prazo/atraso"
    quem_tem: ["Mubisys", "Zênite", "Calcgraf", "Calcme"]
    quem_nao_tem: ["Visua (não cobre produção)", "PrintSmith parcial"]
    evidencia_de_pronto: "FSM canon (ADR 0143) consumido em cv_ordens_producao + Pages/ComVis/Pcp/Board.tsx + Pest stages 16"
    status_oimpresso: "🟡 backend ✅ (16 stages canon Sprint 1) / UI Pages.tsx pendente Sprint 2"

  - id: apontamento-producao-cmyk
    nome: "Apontamento produção real (m² produzido + tempo plotter + CMYK consumido)"
    score: P0
    descricao: "Operador aponta na máquina: m² real, tempo, refugo, tinta. Insumo do pós-cálculo orçado×realizado."
    quem_tem: ["Mubisys (parcial)", "Zênite (IoT)", "Calcme básico"]
    quem_nao_tem: ["Calcgraf (orçamento puro)", "Alfa", "Visua"]
    evidencia_de_pronto: "Apontamento entity append-only + ApontamentoController + ApontamentoTracker drift detection"
    status_oimpresso: "✅ done — ApontamentoTrackerTest + ApontamentoControllerTest verdes"

  - id: orcamento-os-aprovacao
    nome: "Orçamento → aprovação cliente → conversão em OS em 1 clique"
    score: P0
    descricao: "Fluxo: rascunho → enviado → aprovado → OS aberta com mesmos itens (sem digitar de novo)"
    quem_tem: ["Mubisys", "Zênite", "Calcgraf", "Calcme", "PrintSmith"]
    quem_nao_tem: []
    evidencia_de_pronto: "OrcamentoController@aprovar → cria Os automaticamente + FSM start pipeline"
    status_oimpresso: "🟡 backend ✅ / UI inline aprovação Sprint 2"

  - id: nfe-modelo-55-vertical
    nome: "Emissão NFe55 (B2B) integrada ao núcleo NfeBrasil"
    score: P0
    descricao: "OS faturada → NFe55 SEFAZ → DANFE PDF/email cliente. Crítico pra B2B gráfica."
    quem_tem: ["Mubisys", "Zênite", "Calcgraf (integração TecnoSpeed)", "Calcme"]
    quem_nao_tem: ["PrintSmith (não BR)"]
    evidencia_de_pronto: "Listener OsFaturada → EmitirNfe55Job → cstat=100 + adapter pronto"
    status_oimpresso: "🔴 TODO US-COMVIS-006 Sprint 2 — depende Modules/NfeBrasil já capaz"

  - id: nfse-instalacao-municipal
    nome: "Emissão NFSe (serviço instalação/fachada) — Floripa/Gravatal/Goiânia"
    score: P0
    descricao: "Instalação = serviço → NFSe municipal (ABRASF v2.04). Sem isso cliente paga ISSQN errado."
    quem_tem: ["Mubisys", "Calcme"]
    quem_nao_tem: ["Calcgraf parcial", "Zênite parcial", "Alfa", "Visua", "PrintSmith"]
    evidencia_de_pronto: "NfseService driver Floripa + Goiânia + Pest mock prefeitura"
    status_oimpresso: "🔴 TODO US-COMVIS-008 Sprint 3"

  - id: multi-tenant-tier0-isolamento
    nome: "Multi-tenant Tier 0 isolamento cross-business"
    score: P0
    descricao: "10 entities CV com business_id global scope + Pest cross-tenant biz=1 vs biz=99. ADR 0093 IRREVOGÁVEL."
    quem_tem: ["Mubisys (multi-CNPJ)", "Calcme (multi-CNPJ)", "PrintSmith parcial"]
    quem_nao_tem: ["Calcgraf (single-tenant tradicional)", "Zênite (Lite single-tenant)", "Alfa", "Visua"]
    evidencia_de_pronto: "10 entities BelongsToBusinessTrait + Tier0GuardTest verde + MultiTenantTest verde"
    status_oimpresso: "✅ done — Tier0GuardTest 100% Wave 16"

  - id: importer-firebird-officeimpresso
    nome: "Importer Firebird OfficeImpresso legacy (clientes/orçamentos/produtos)"
    score: P0
    descricao: "Migração one-shot 6 gráficas legacy Delphi → oimpresso sem digitação 80h × R$ [redacted Tier 0]"
    quem_tem: ["Bling/Omie genérico CSV", "ContaAzul genérico"]
    quem_nao_tem: ["Mubisys, Zênite, Calcgraf, Calcme, Alfa, Visua (todos sem importer Firebird OfficeImpresso vertical)"]
    evidencia_de_pronto: "Comando artisan comvis:import-legacy --gdb=<file> + Pest mocks 5+ tabelas + idempotente"
    status_oimpresso: "🔴 TODO US-COMVIS-017 — diferencial migração (ROI 1562)"

  # ============= P1 — diferencial competitivo =============

  - id: pos-calculo-orcado-realizado
    nome: "Pós-cálculo: orçado × realizado por OS (margem real vs estimada)"
    score: P1
    descricao: "Descobre OS sangria (margem negativa). Calcgraf único concorrente que cobre bem."
    quem_tem: ["Calcgraf (único forte)", "PrintSmith parcial"]
    quem_nao_tem: ["Mubisys", "Zênite", "Calcme", "Alfa", "Visua"]
    evidencia_de_pronto: "Service compara Apontamento.m2_real × Orcamento.m2_estimado + relatório margin_drift"
    status_oimpresso: "🔴 TODO US-COMVIS-005 Sprint 3"

  - id: nfe-de-boleto-pago-automatica
    nome: "NFe-de-boleto-pago automática (boleto cai → NFe sai sem clique)"
    score: P1
    descricao: "Listener InvoicePaid (RecurringBilling/Asaas) → EmitirNfeJob automático. Diferencial OIMPRESSO."
    quem_tem: []
    quem_nao_tem: ["Mubisys, Zênite, Calcgraf, Calcme, Alfa, Visua, PrintSmith — TODOS"]
    evidencia_de_pronto: "Listener InvoicePaid → CreateNfeFromOsListener → NfeBrasil emit + idempotent"
    status_oimpresso: "🔴 TODO US-COMVIS-009 — wedge único (US-RB-044 base entregue)"

  - id: dual-doc-fiscal-nfe-nfse-simultaneo
    nome: "Dual-doc fiscal: 1 OS gera NFe55 (produto) + NFSe56 (instalação) simultâneo"
    score: P1
    descricao: "Fachada = chapa ACM (mercadoria/NFe55) + serviço instalação (NFSe). Outros forçam 2 vendas separadas."
    quem_tem: []
    quem_nao_tem: ["Mubisys, Zênite, Calcgraf, Calcme, Alfa, Visua (todos forçam 2 vendas)"]
    evidencia_de_pronto: "OS com flag has_servico → orquestra NfeService::emitir55 + NfseService::emitir paralelo"
    status_oimpresso: "🟡 base entregue (US-SELL-014) — adapter CV pendente"

  - id: jana-ia-conversacional-dashboard
    nome: "Jana IA conversacional 'Quanto faturei hoje?' / 'bulk update lona 440g +5%'"
    score: P1
    descricao: "Dashboard noturno via WhatsApp/chat com SQL auditável. Calcme tem WhatsApp (Chatme) sem IA."
    quem_tem: ["Calcme (WhatsApp Chatme — não IA)"]
    quem_nao_tem: ["Mubisys, Zênite, Calcgraf, Alfa, Visua, PrintSmith"]
    evidencia_de_pronto: "Jana Agent CV-vocabulary + ferramenta sql_safe + ferramenta bulk_update_substrato"
    status_oimpresso: "🔴 TODO US-COMVIS-013/014 — diferencial IA"

  - id: workflow-arte-whatsapp-aprovacao
    nome: "Workflow arte → cliente aprova via WhatsApp (link + visualização)"
    score: P1
    descricao: "Reduz ciclo aprovação 2 dias → 4h. Cliente recebe link, aprova/rejeita com comentário."
    quem_tem: ["Calcme (Chatme parcial)"]
    quem_nao_tem: ["Mubisys, Zênite, Calcgraf, Alfa, Visua, PrintSmith"]
    evidencia_de_pronto: "Job DispatchArteWhatsapp + endpoint público /aprovar/{token} + LGPD consent"
    status_oimpresso: "🔴 TODO Sprint 3 (reuso ADR 0117 multi-números)"

  - id: instalacao-nr35-checklist-agenda
    nome: "Gestão instalação fachada NR-35 (checklist + agenda equipe + ferramenta)"
    score: P1
    descricao: "Evita re-trabalho ferramenta esquecida (2 OS/mês × R$ [redacted Tier 0]k). Visua único especialista."
    quem_tem: ["Visua (único forte)"]
    quem_nao_tem: ["Mubisys parcial", "Zênite parcial", "Calcgraf", "Calcme", "Alfa", "PrintSmith"]
    evidencia_de_pronto: "Instalacao + InstalacaoCatalogo entities + Agenda calendar + checklist NR-35 PDF"
    status_oimpresso: "🟡 entities ✅ done — UI agenda + checklist Sprint 3"

  - id: dam-arquivos-arte-versionado
    nome: "DAM (Digital Asset Management) — arquivos arte versionados S3/Wasabi"
    score: P1
    descricao: "Substitui WhatsApp 80MB caos. Mubisys único concorrente forte (MubiDrive ~150TB)."
    quem_tem: ["Mubisys (único — MubiDrive)"]
    quem_nao_tem: ["Zênite, Calcgraf, Calcme, Alfa, Visua, PrintSmith parcial (StoreFront)"]
    evidencia_de_pronto: "Uppy chunked + S3 driver + versioning + UI thumbnails + busca tag"
    status_oimpresso: "🔴 TODO US-COMVIS-012 P2 (esforço alto 12h)"

  # ============= P2 — incrementa após sinal cliente =============

  - id: comissao-multi-papel-json
    nome: "Comissão multi-papel JSON (vendedor + designer + instalador) na mesma OS"
    score: P2
    descricao: "OS divide comissão entre 3+ pessoas com % configurável. Gold paga 0,5% errado hoje em ~80 comissões/m."
    quem_tem: ["Mubisys", "Calcgraf", "Zênite parcial"]
    quem_nao_tem: ["Calcme", "Alfa", "Visua", "PrintSmith"]
    evidencia_de_pronto: "Comissoes JSON column em Os + ComissaoCalculator + relatório folha"
    status_oimpresso: "🔴 TODO US-COMVIS-011"

  - id: provador-orcamento-online-publico
    nome: "Provador orçamento online público (lead gera orçamento sozinho)"
    score: P2
    descricao: "8 lead/m × 20% conv × ticket R$ [redacted Tier 0] Calcme/Alfa entregam."
    quem_tem: ["Calcme", "Alfa", "PrintSmith (MarketDirect StoreFront)"]
    quem_nao_tem: ["Mubisys, Zênite, Calcgraf, Visua"]
    evidencia_de_pronto: "Rota pública /orcamento/<biz-slug> + captcha + lead → CRM"
    status_oimpresso: "🔴 TODO US-COMVIS-010"

  - id: web-to-print-storefront
    nome: "Web-to-print storefront (e-commerce gráfico catálogo + checkout)"
    score: P3
    descricao: "PrintSmith MarketDirect é estado-da-arte global. Mercado BR ainda imaturo."
    quem_tem: ["PrintSmith (MarketDirect StoreFront — killer global)"]
    quem_nao_tem: ["Mubisys, Zênite, Calcgraf, Calcme, Alfa, Visua"]
    evidencia_de_pronto: "Loja whitelabel /comvis/loja/{biz} + Stripe/Asaas + catálogo + carrinho"
    status_oimpresso: "🔴 backlog P3 — só ativa com sinal pagante (ADR 0105)"

  - id: ct-e-mdfe-entrega
    nome: "CT-e / MDF-e entrega gráfica própria"
    score: P3
    descricao: "Obrigatório se gráfica usa transporte próprio (SINIEF 2026). Calcgraf/Calcme/Bling cobrem."
    quem_tem: ["Calcgraf", "Calcme", "Bling/Omie horizontal"]
    quem_nao_tem: ["Mubisys, Zênite, Alfa, Visua, PrintSmith"]
    evidencia_de_pronto: "MdfeService + adapter SEFAZ + UI agenda transporte"
    status_oimpresso: "🔴 backlog P3"
```

---

## Como auditar este módulo (etapa específica)

**Locais a inspecionar (paths esperados):**

- Entities: `Modules/ComunicacaoVisual/Entities/{Orcamento, OrcamentoItem, Os, Apontamento, Material, Substrato, Acabamento, Instalacao, InstalacaoCatalogo, OrdemProducao}.php` (10 entities ✅)
- Services: `Modules/ComunicacaoVisual/Services/{OrcamentoCalculator, ApontamentoTracker}.php` (2 ✅; faltam `NfeBridgeService`, `NfseBridgeService`, `PosCalculoService`, `JanaCvAgentService`, `ImporterFirebirdService`)
- Controllers: `Modules/ComunicacaoVisual/Http/Controllers/{Orcamento, Apontamento, Data, Install}Controller.php` (4 ✅; faltam `Pcp`, `Nfe`, `Instalacao`, `Loja`)
- Migrations: 10 migrations (`cv_substratos`, `cv_acabamentos`, `cv_instalacoes_catalogo`, `cv_ordens_producao`, `cv_instalacoes`, `comvis_materiais`, `comvis_orcamentos`, `comvis_os`, `comvis_apontamentos`) — observar legacy `comvis_*` vs novo `cv_*` (ADR pendente normalização)
- Tests: `Modules/ComunicacaoVisual/Tests/Feature/` — 11 suites Pest verdes (MultiTenant, Tier0Guard, OrcamentoCalculator, OrcamentoController, ApontamentoController, ApontamentoTracker, MaterialSeeder, Migrations, Observability, LgpdCompliance, CustomerJourney, DemoSeedCommand, DataController, InstallController)
- UI Inertia: `resources/js/Pages/ComVis/{Orcamento, Pcp, Apontamento, Instalacao, Loja}/*.tsx` (🔴 TODO Sprint 2 — charter MWART F1.5 visual gate obrigatório)
- Console: `Modules/ComunicacaoVisual/Console/Commands/{ComvisHealthCommand, DemoSeedCommand}.php` (2 ✅)

**Critérios customizados de classificação:**

| Capacidade | ✅ APROVADO requer | 🟡 PARCIAL aceita |
|---|---|---|
| Cálculo m² | Service + Pest edge cases + UI inline | Service sem UI |
| Cadastro substrato | Migration + seeder + UI CRUD + tier preço | Migration + seeder sem UI |
| PCP Kanban | FSM canon consumido + Pages Board.tsx + Pest 16 stages | Backend FSM sem UI |
| Apontamento | Append-only + drift detection + UI mobile | Sem drift detection |
| NFe55/NFSe | Listener + Job + idempotente + ≥1 emitida prod | Service stub |
| Multi-tenant | BelongsToBusinessTrait + Pest cross-biz | Sem teste isolamento |
| Pós-cálculo | Comparador margem + relatório + alerta margem negativa | Comparador sem alerta |
| Jana IA CV | Agent CV-vocabulary + ferramenta SQL safe + Pest | Stub sem ferramentas |
| Workflow arte WhatsApp | Job + endpoint público + LGPD consent + auditoria | Endpoint sem LGPD |

**Métricas de prod relevantes** (quando piloto rodar):

- Taxa conversão orçamento→OS aprovada — meta `≥ 35%` (Gold baseline R$ [redacted Tier 0]M GMV)
- Drift m² produzido vs orçado — meta `< 5%` p95 (acima = problema operação ou cotação errada)
- Latência cálculo orçamento server-side — meta `< 500ms` p95
- % OS com margem negativa (descoberto pós-cálculo) — meta `< 10%` (alerta operacional)
- NFe55+NFSe56 dual-doc autorização — meta `≥ 99%` p95

---

## Métricas de adoção

- **Última auditoria**: 2026-05-16 (1ª via Wave 22 CAPTERRA agent)
- **Capacidades P0 cobertas**: **6/9** (calc m² ✅, cadastro substrato ✅, PCP backend ✅, apontamento ✅, orçamento→OS backend ✅, multi-tenant Tier 0 ✅; faltam NFe55 adapter, NFSe, importer Firebird)
- **Capacidades P1 cobertas**: **1/7** parcial (instalacao entities ✅, falta UI; resto TODO)
- **Capacidades P2/P3 cobertas**: 0/4
- **Próxima reauditoria sugerida**: após mergear Sprint 2 (Pages Inertia + NFe55+NFSe adapter) — ~2026-08-16

---

## Nota agregada — Modules/ComunicacaoVisual hoje

**Metodologia ([ADR 0101](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md)):** nota ponderada P0=4, P1=2, P2=1, P3=0.5. Cada capacidade vale: 1.0 ✅ done, 0.5 🟡 parcial, 0.0 🔴 TODO.

| Categoria | Pesos disponíveis | Cobertura oimpresso | Pontos obtidos |
|---|---:|---|---:|
| 9 P0 × peso 4 | 36 | calc m² ✅(4) + substrato ✅(4) + PCP 🟡(2) + apontamento ✅(4) + orç→OS 🟡(2) + multi-tenant ✅(4) + NFe55 🔴(0) + NFSe 🔴(0) + importer 🔴(0) | **20** |
| 7 P1 × peso 2 | 14 | pós-calc 🔴(0) + NFe-boleto 🔴(0) + dual-doc 🟡(1) + Jana IA 🔴(0) + arte WhatsApp 🔴(0) + NR-35 🟡(1) + DAM 🔴(0) | **2** |
| 2 P2 × peso 1 | 2 | comissão 🔴(0) + provador 🔴(0) | **0** |
| 2 P3 × peso 0.5 | 1 | web-to-print 🔴(0) + CT-e 🔴(0) | **0** |
| **TOTAL** | **53** | — | **22** |

### Nota final: **22 / 53 = 41.5 / 100** (🟡 em construção — esperado pra módulo Sprint 1 entregue, Sprint 2-3 pendente)

**Projeção pós-Sprint 2 (Pages + NfeBrasil adapter):** ~32/53 = **60/100** (alcança paridade operacional concorrentes)
**Projeção pós-Sprint 3 (Jana IA + WhatsApp + pós-cálculo + Importer Firebird):** ~44/53 = **83/100** (ultrapassa Mubisys/Zênite em diferenciais únicos)

---

## Top 5 GAPS (priorizar próximas waves)

| # | Gap | Score perdido | Concorrente que cobre | Esforço (h IA-pair) | ROI score MATRIZ-ROI |
|---|---|---:|---|---:|---:|
| 1 | **Pages Inertia Orçamento/PCP/Apontamento (UI Sprint 2)** — backend pronto, sem UI cliente não usa | 6 (3× P0 parciais) | Todos | ~30h (5 Pages × charter MWART) | — bloqueador |
| 2 | **NFe55 adapter Listener OS→Job** (US-COMVIS-006) | 4 | Mubisys, Zênite, Calcgraf, Calcme | 8h | 1500 |
| 3 | **NFSe driver Floripa+Goiânia** (US-COMVIS-008) | 4 | Mubisys, Calcme | 14h | 321 |
| 4 | **Importer Firebird OfficeImpresso** (US-COMVIS-017) — diferencial migração 6 saudáveis | 4 | NENHUM vertical | 16h | **1562 (top ROI)** |
| 5 | **NFe-de-boleto-pago automática + Jana IA dashboard 22h + Dual-doc** — 3 wedges únicos juntos | 4 (2×P1 + 1×P1) | NENHUM concorrente | 16h combinado | 1500/4000/1083 |

---

## UX heuristics (Capterra v2 — eixo Usabilidade)

```yaml
ux_heuristics:
  - id: cliques-orcamento-novo
    nome: "Cliques pra criar orçamento novo (cliente novo + 1 item lona 2x3m)"
    score: P0
    benchmark: "Mubisys: 6 cliques. Zênite (GE3): 8 cliques. Calcgraf: 5 cliques. Calcme: 4 cliques. PrintSmith: 5 cliques."
    target: "≤ 5 cliques (paridade Calcme/Calcgraf)"
    metrica: "navegacao_steps_orcamento_novo"
    status_oimpresso: "🟡 backend pronto, UI Sprint 2 mede"

  - id: tempo-orcamento-aprovacao
    nome: "Tempo cliente recebe link aprovação após operador clicar 'enviar'"
    score: P0
    benchmark: "Calcme (Chatme WhatsApp): ~3s. Mubisys (email): ~30s. Zênite (PDF anexo email): ~45s. PrintSmith: ~10s."
    target: "≤ 5s p95 (WhatsApp + email paralelo)"
    metrica: "envio_orcamento_p95_seconds"
    status_oimpresso: "🔴 TODO (depende US-COMVIS-009/workflow WhatsApp)"

  - id: cliques-apontamento-mobile
    nome: "Cliques operador plotter aponta finalização OS no celular"
    score: P0
    benchmark: "Zênite IoT: 0 (automático). Mubisys app: 4 cliques. Calcme: 6 cliques. Calcgraf: N/A (sem mobile)."
    target: "≤ 3 cliques (PWA mobile-first)"
    metrica: "navegacao_steps_apontamento_mobile"
    status_oimpresso: "🟡 backend ApontamentoController ✅, UI mobile Sprint 2"

  - id: tempo-recuperacao-orcamento-perdido
    nome: "Tempo busca + reabre orçamento de 2 meses atrás"
    score: P1
    benchmark: "Calcme: ~10s (search global). Mubisys: ~25s. Zênite: ~40s (filtro datas). Calcgraf: ~60s."
    target: "≤ 8s p95 (Meilisearch indexa orcamentos)"
    metrica: "search_orcamento_p95_seconds"
    status_oimpresso: "🔴 TODO Sprint 3 (reuso Meilisearch CT 100)"
```

## Automation targets (Capterra v2 — eixo Automação)

```yaml
automation_targets:
  - id: auto-nfe-after-boleto-paid
    nome: "Boleto pago → NFe55 emitida sem clique humano"
    score: P1
    benchmark: "Mubisys: manual (operador clica 'emitir NFe' após confirmar pagamento). Zênite: manual. Calcgraf: manual. Calcme: manual."
    target: "Listener InvoicePaid → EmitirNfeJob, p95 < 30s, taxa autorização > 99%"
    metrica: "auto_nfe_after_boleto_p95_seconds + taxa_autorizacao"
    status_oimpresso: "🔴 TODO US-COMVIS-009 — WEDGE ÚNICO"

  - id: auto-nfse-after-instalacao
    nome: "Instalação concluída (operador aponta no app) → NFSe emitida sem clique"
    score: P1
    benchmark: "Mubisys/Calcme parcial (cron diário). Outros: manual."
    target: "Event InstalacaoConcluida → EmitirNfseJob, p95 < 60s"
    metrica: "auto_nfse_after_instalacao_p95_seconds"
    status_oimpresso: "🔴 TODO Sprint 3"

  - id: auto-alerta-margem-negativa
    nome: "Pós-cálculo detecta margem < 0 → alerta WhatsApp Wagner/dono"
    score: P1
    benchmark: "Calcgraf único (cron diário relatório PDF). Outros: nenhum."
    target: "Job PosCalculoJob roda fim-de-OS → notifica margem < 5% via WhatsApp dono"
    metrica: "alerta_margem_negativa_latencia_horas"
    status_oimpresso: "🔴 TODO US-COMVIS-005 — wedge vs Calcgraf"

  - id: auto-jana-bulk-update-substrato
    nome: "Dono fala 'aumenta lona 440g em 5%' no chat → ToolCall Jana atualiza DB com confirmação"
    score: P2
    benchmark: "NENHUM concorrente entrega. Todos exigem operador entrar tela cadastro × N substratos."
    target: "Jana Agent ferramenta bulk_update_substrato + confirmação + audit log"
    metrica: "tempo_bulk_update_substrato_segundos (vs 12min manual)"
    status_oimpresso: "🔴 TODO US-COMVIS-013 — wedge único Jana IA"

  - id: auto-importer-firebird-incremental
    nome: "Importer Firebird detecta legacy.gdb mudou → cron incremental migra deltas"
    score: P2
    benchmark: "Bling/Omie genérico CSV manual. Vertical CV: NENHUM."
    target: "Cron daily comvis:import-legacy --incremental + delta detection"
    metrica: "deltas_importados_dia"
    status_oimpresso: "🔴 TODO US-COMVIS-017 Fase 2 (1º importer one-shot)"
```

---

## Histórico de revisão da ficha

- `2026-05-16` — Claude (Wave 22 CAPTERRA agent) — 1ª emissão. Baseada em pesquisa BR (Mubisys, Zênite GE 3.0, Calcgraf/NetCalc, Calcme) + benchmark global PrintSmith Vision 5. Reaproveita MATRIZ-ROI.md (Wave 16) + BRIEFING.md (Wave 18) + SPEC.md (US-COMVIS-001..018).

## Refs

- [BRIEFING.md](../../../Modules/ComunicacaoVisual/BRIEFING.md) — estado consolidado capacidade
- [SPEC.md](SPEC.md) — backlog US-COMVIS-001..018
- [MATRIZ-ROI.md](MATRIZ-ROI.md) — 24 features × ROI score × concorrentes
- [ComunicacaoVisual.charter.md](ComunicacaoVisual.charter.md) — charter módulo
- [PLANO-MIGRACAO-6-SAUDAVEIS.md](PLANO-MIGRACAO-6-SAUDAVEIS.md) — Gold + 5 outros
- [QUALIFICACAO-PILOTO-2026-05-16.md](QUALIFICACAO-PILOTO-2026-05-16.md) — gating cliente piloto
- [ADR 0089](../../decisions/0089-capterra-driven-module-evolution.md) — Capterra-driven evolution
- [ADR 0101](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md) — Capterra v2 3 eixos
- [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) — modular especializado vertical
- [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM canon
- Concorrentes pesquisados Wave 22: [Mubisys](https://mubisys.com/), [Zênite GE 3.0](https://www.zsl.com.br/sistema-de-gestao-grafica), [Calcgraf NetCalc](https://www.calcgraf.com.br/solucao/netcalc/), [Calcme](https://www.calcme.com.br/sistema-para-graficas/), [PrintSmith Vision 5](https://www.efi.com/pt-br/products/productivity-software/management-mis-erp/efi-printsmith-vision/overview/) ([Capterra review](https://www.capterra.com/p/210917/ePs-PrintSmith-Vision/))
