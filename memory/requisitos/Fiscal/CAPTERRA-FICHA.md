# CAPTERRA-FICHA — Fiscal

> Ficha canônica de benchmark do módulo **Fiscal** (cockpit / **configuração / orquestração fiscal**).
> Fonte da skill `comparativo-do-modulo`. Programa de Ondas (Onda `<N>`-fiscal) — [template-onda-modulo.md](../_Governanca/programa-ondas/template-onda-modulo.md) Passo 1.
> ADR de governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md) (Capterra-driven) + [0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) (Capterra v2 eixos).
> **Gerada:** 2026-07-03 · via agente `capterra-senior` · Wagner OK [W] 2026-07-03 (camada fiscal).

---

## ⚠️ Fronteira com NfeBrasil (não sobrepor)

Este módulo **NÃO é o emissor**. A emissão de XML + webservice SEFAZ + serialização vive em `Modules/NfeBrasil` (lib `nfephp-org/sped-nfe`) e tem [ficha própria](../NfeBrasil/CAPTERRA-FICHA.md). **Fiscal é a camada de CONFIGURAÇÃO/ORQUESTRAÇÃO** que orquestra por cima:

| Domínio desta ficha | O que Fiscal faz | Onde o backend vive |
|---|---|---|
| **Motor tributário / regras** | Consome `MotorTributarioService` (cascade-4) + exibe config default read-only | `Modules/NfeBrasil/Services/MotorTributarioService` + `Tributacao/*` |
| **Config / Certificado A1 / regime** | Sub-página Config (read-only) + cadastro editável de regras | Fiscal `ConfigController` (RO) + NfeBrasil `TributacaoController` (edit) |
| **Distribuição DF-e / Manifestação** | Sub-página DF-e + 4 ações manifestação | NfeBrasil `DistribuicaoDfeService` + `ManifestacaoService` |
| **Eventos (CC-e / Cancel / Inut / Retransm)** | Botões drawer + timeline append-only | Fiscal `AcoesController` → Services NfeBrasil |
| **SPED / obrigações acessórias** | Gerador EFD-ICMS/IPI TXT (código próprio) | Fiscal `SpedIcmsIpiGeneratorService` |
| **Cockpit unificado** | KPIs + alertas + ⌘K palette cross-fiscal | Fiscal (próprio) |

> A ficha do NfeBrasil mede **emitir/cancelar/DANFE**. Esta mede **configurar/orquestrar/escriturar** (motor, regras ICMS-ISS, DF-e, eventos, SPED, cockpit).

---

## Seção 1 — Identidade do módulo

- **Nome interno**: `Fiscal`
- **Domínio de negócio**: cockpit fiscal unificado + camada de **configuração e orquestração fiscal** — motor tributário (config de regras ICMS/CST/CFOP por NCM/UF), distribuição DF-e + manifestação do destinatário, eventos fiscais (CC-e/cancelamento/inutilização/retransmissão), certificado A1 + regime + tributação default, e obrigações acessórias (SPED EFD-ICMS/IPI)
- **Padrão arquitetural**: thin agregador (espelho de `Modules/Financeiro/Unificado`) — lê Models + chama Services de `NfeBrasil`/`NFSe` via `HasBusinessScope`; **não duplica backend de emissão**
- **Cliente principal alvo**: biz=1 (Wagner, operador fiscal) + **Eliana (contadora — leitura, conferência, SPED mensal)**; **Larissa @ ROTA LIVRE biz=4 em pre-canary** ([config/governance/module_clients.yaml] — `piloto_reportando_dor`)
- **Trust level**: L3 · **Permissão prefix**: `fiscal.*` · **Score module-grade-v3 interno**: 66/100 (rubrica ADR 0155 — governança interna, ≠ nota Capterra abaixo)

---

## Seção 2 — Concorrentes-alvo (natureza, preço, camada)

Dois grupos ocupam camadas **diferentes**. A comparação relevante muda conforme o eixo:

### Grupo A — Middleware / API fiscal (transmissores — camada NfeBrasil-like)

| Player | Natureza | Motor tributário? | SPED? | Preço/público |
|---|---|---|---|---|
| **TecnoSpeed (PlugDFe)** | Componente/API p/ software houses | ❌ Não (ERP decide a regra) | ✅ Produto SPED Fiscal **separado** (EFD-ICMS/IPI + Contribuições) | Sob contrato · software house |
| **PlugNotas** (TecnoSpeed) | API SaaS "plugou, emitiu" | 🟡 **Calculadora Automática** (forte em IBS/CBS) | ❓ Não na API (SPED é produto à parte) | Por documento · dev/API-first |
| **Nuvem Fiscal** | API REST developer-first | 🟡 Calcula valores de params (sem regras por produto) | ❌ Não | Preço público (centenas/mês) · dev self-service |
| **Focus NFe** | API fiscal REST | 🟡 Parcial (payload + IBPT aprox.) | ❌ Não | Por doc (dezenas–centenas/mês) · dev/ERP |

> **Insight-chave:** nenhum middleware é um motor tributário completo para ICMS/ICMS-ST/IPI/PIS/COFINS/DIFAL clássicos — **o ERP decide a regra, o middleware transmite/distribui**. É exatamente o papel que `Modules/NfeBrasil` cumpre para o oimpresso. A exceção emergente é a **Calculadora IBS/CBS do PlugNotas** (apoiada na calculadora oficial da Receita).

### Grupo B — ERP com camada fiscal própria (peers diretos do oimpresso)

| Player | Motor tributário | ICMS-ST/DIFAL | SPED nativo | IBS/CBS | Preço/público |
|---|---|---|---|---|---|
| **Bling** | ✅ Natureza de Operação + Regras por NCM/regime | ✅ (MVA/CEST) | ✅ EFD-ICMS/IPI + Contribuições (tier Titanium) | ✅ Preenchimento automático (Regime Normal) | PME/varejo (dezenas/mês) |
| **Tiny (Olist)** | ✅ Regras por NCM/UF/natureza | ✅ (FCP/ST/DIFAL por item) | ❌ Não nativo (fica com contador) | ✅ Anunciado (motor híbrido base única) | PME/varejo |
| **Omie** | ✅ Cenário Fiscal + **IA Fiscal** (sugere CST/alíq.) | ✅ DIFAL/GNRe forte | ✅ **Diferencial**: EFD-ICMS/IPI + Contribuições + ECD + PGMEI/DAS/REINF/… | ✅ Cronograma datado granular | PME→médio (escalado) |

> **Posição do oimpresso:** peer direto de **Bling/Tiny** (ERP com motor fiscal próprio), atrás de **Omie** em obrigações acessórias (SPED nativo completo). Ganha em UX de cadastro fiscal (aviso antecipado de `cSit` — [ADR 0186](../../decisions/0186-chain-certificado-sefaz-consulta-cadastro.md)), cockpit unificado e integração ERP-nativa; **perde em IBS/CBS calculado e SPED completo**.

---

## Seção 3 — Matriz comparativa P0-P3 + Nota 0-100

> Ponderação canônica capterra-senior: **P0=4 · P1=2 · P2=1 · P3=0.5**. Score por capacidade: ✅ SIM=1.0 · 🟡 PARCIAL=0.5 · ❌ NÃO=0.
> Nota = Σ(peso × score) ÷ Σ(peso × 1) × 100. **Fonte oimpresso = código em `origin/main` @ `7442c27c43`** (verificado 2026-07-03).

| # | Capacidade | Peso | oimpresso | Bling | Tiny | Omie | TecnoSpeed | PlugNotas | NuvemF | Focus |
|---|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| 1 | Motor tributário automático (cascade regras por NCM/CFOP/UF/regime) | P0 | ✅ | ✅ | ✅ | ✅ | ❌ | 🟡 | 🟡 | 🟡 |
| 2 | Regras ICMS configuráveis (CST/CSOSN/CFOP/alíq. por NCM/UF) + import | P0 | ✅ | ✅ | ✅ | ✅ | 🟡 | 🟡 | 🟡 | 🟡 |
| 3 | Distribuição DF-e automática + Manifestação (4 ações) | P0 | ✅ | ✅ | 🟡 | 🟡 | ✅ | ✅ | ✅ | ✅ |
| 4 | Eventos fiscais (CC-e / Cancelamento / Inutilização) | P0 | ✅ | ✅ | ✅ | ❓ | ✅ | ✅ | ✅ | ✅ |
| 5 | Config certificado A1 + regime + tributação default | P0 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 6 | Multi-tenant: isolamento cert/emissões por `business_id` (Tier 0) | P0 | ✅ | 🟡 | 🟡 | ✅ | ✅ | ✅ | ✅ | ✅ |
| 7 | **Reforma Tributária IBS/CBS — cálculo/preenchimento** | P0 | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ❓ | 🟡 |
| 8 | Cockpit fiscal unificado (visão consolidada + alertas) | P0 | ✅ | 🟡 | 🟡 | ✅ | ❌ | ❌ | ❌ | ❌ |
| 9 | ICMS-ST / DIFAL / FCP cálculo automático | P1 | 🟡 | ✅ | ✅ | ✅ | 🟡 | 🟡 | 🟡 | 🟡 |
| 10 | ISS / NFS-e config (alíquota por município) | P1 | 🟡 | ✅ | ✅ | ✅ | 🟡 | ✅ | 🟡 | ✅ |
| 11 | SPED EFD-ICMS/IPI (geração TXT PVA-validável) | P1 | 🟡 | ✅ | ❌ | ✅ | ✅ | ❓ | ❌ | ❌ |
| 12 | Eventos timeline auditável append-only | P1 | ✅ | 🟡 | 🟡 | ❓ | 🟡 | 🟡 | 🟡 | 🟡 |
| 13 | Health-check certificado A1 (alerta vencimento) | P1 | 🟡 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 14 | Busca cross-fiscal / ⌘K palette | P1 | ✅ | 🟡 | 🟡 | 🟡 | ❌ | ❌ | ❌ | ❌ |
| 15 | Entrada via DF-e manifestada → escrituração (Bloco C inputs) | P1 | ❌ | ✅ | 🟡 | ✅ | 🟡 | 🟡 | ❌ | 🟡 |
| 16 | SPED EFD-Contribuições (PIS/COFINS) | P2 | ❌ | ✅ | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| 17 | MDF-e (Manifesto de Documento Fiscal) | P2 | ❌ | 🟡 | ❓ | ❓ | ✅ | ✅ | ✅ | ✅ |
| 18 | Sugestão determinística por cStat rejeitado ("Jana sugere") | P2 | ✅ | ❌ | ❌ | 🟡 | ❌ | ❌ | ❌ | ❌ |
| 19 | FSM cancel cascade (estorno financeiro + notif cliente) | P2 | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| 20 | Webhook de eventos fiscais p/ sistemas externos | P3 | ❌ | 🟡 | 🟡 | 🟡 | ✅ | ✅ | 🟡 | 🟡 |
| 21 | Aviso antecipado de `cSit` no cadastro cliente (ADR 0186) | P3 | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

### Cálculo da nota oimpresso

| Bucket | Capacidades (peso) | Score obtido | Máx |
|---|---|---|---|
| **P0** (×4) | 8 caps | 7×✅ + 1×❌ = 7.0 → **28.0** | 32 |
| **P1** (×2) | 7 caps | 2×✅ + 4×🟡 + 1×❌ = 4.0 → **8.0** | 14 |
| **P2** (×1) | 4 caps | 2×✅ + 2×❌ = 2.0 → **2.0** | 4 |
| **P3** (×0.5) | 2 caps | 1×✅ + 1×❌ = 1.0 → **0.5** | 1 |
| **Total** | | **38.5** | **51** |

> ## 🎯 Nota Capterra Fiscal = **38.5 ÷ 51 = 75/100**

**Leitura:** módulo **competitivo** (75) — forte em config/DF-e/eventos/cockpit/multi-tenant e com diferenciais que ninguém do mercado tem (FSM cascade, cSit warning, Jana sugere). O que segura a nota abaixo de 85: **1 P0 zerado** (IBS/CBS não calcula — capacidade #7) + **P1 em meia-luz** (ICMS-ST/DIFAL, ISS municipal, SPED, health-check cert todos 🟡) + **P2/P3 de escrituração** (EFD-Contribuições, MDF-e = ❌).

---

## Seção 4 — Capacidades baseline com score (detalhe YAML)

```yaml
capacidades:
  - nome: "Motor tributário automático (cascade regras por NCM/CFOP/UF/regime)"
    score: P0
    estado: SIM
    descricao: "MotorTributarioService cascade-4 níveis (override → regra exata biz+ncm+ufO+ufD → default NCM → business default), OTel span + memoization, cobre ICMS/PIS/COFINS/IPI + CST/CSOSN/CFOP"
    onde: "Modules/NfeBrasil/Services/MotorTributarioService.php + Tributacao/{ProdutoFiscalContext,TributoCalculado}.php"
    quem_tem_no_mercado: ["Bling", "Tiny", "Omie"]
    gap: "SPED agora DI-integra o motor com constantes FALLBACK_* Simples (GAP-FISCAL-003 fechado na Onda CONSOLIDAR); antes usava 6 hardcodes"

  - nome: "Regras ICMS configuráveis + import CSV/templates"
    score: P0
    estado: SIM
    descricao: "Cadastro de regras NCM/UF (nfe_fiscal_rules + tax_rate_links) via UI + templates (fixtures) + import CSV; Fiscal Config sub-página é espelho read-only"
    onde: "Modules/NfeBrasil/Http/Controllers/TributacaoController.php + Services/Tributacao/{TributacaoTemplateService,ImportRegrasCsvService}.php ; Modules/Fiscal/Http/Controllers/ConfigController.php (RO)"
    quem_tem_no_mercado: ["Bling (Natureza de Operação)", "Tiny", "Omie (Cenário Fiscal)"]

  - nome: "Distribuição DF-e automática + Manifestação do Destinatário (4 ações)"
    score: P0
    estado: SIM
    descricao: "Download automático de NF-e emitidas contra o CNPJ (DistribuicaoDfeService + BuscarDfesRecebidosJob) + Ciência/Confirmação/Desconhecimento/Não Realizada; pílula prazo 90d (NT 2014.002)"
    onde: "Modules/NfeBrasil/Services/Manifestacao/{DistribuicaoDfeService,ManifestacaoService}.php + Jobs/BuscarDfesRecebidosJob.php ; Modules/Fiscal/Http/Controllers/DfeController.php (ADR 0116)"
    quem_tem_no_mercado: ["TecnoSpeed", "PlugNotas", "Nuvem Fiscal", "Focus NFe", "Bling"]

  - nome: "Eventos fiscais (CC-e 110110 / Cancelamento / Inutilização / Retransmissão)"
    score: P0
    estado: SIM
    descricao: "CC-e (seq 1-20), cancelamento (24h NFC-e / 168h NF-e), inutilização de faixa, retransmissão de rejeitada/denegada (UPDATE preservation contract CONFAZ Art. 14 — nunca forceDelete)"
    onde: "Modules/Fiscal/Http/Controllers/AcoesController.php → NfeBrasil Services {NfeCartaCorrecao,NfeInutilizacao}Service + NfeService::{cancelar,retransmitir}"
    quem_tem_no_mercado: ["todos os middlewares", "Bling", "Tiny"]

  - nome: "Config certificado A1 + regime + tributação default"
    score: P0
    estado: SIM
    descricao: "Sub-página Config consolidada (cert A1 + regime + numeração NFe + tributação default CFOP/CSOSN/CST); read-only no Fiscal, editável no NfeBrasil"
    onde: "Modules/Fiscal/Http/Controllers/ConfigController.php"
    quem_tem_no_mercado: ["todos"]

  - nome: "Multi-tenant isolamento cert/emissões por business_id (Tier 0)"
    score: P0
    estado: SIM
    descricao: "HasBusinessScope global em todos os Models lidos + guard cross-tenant explícito nos Services (defesa em profundidade) + Pest biz=1 vs biz=99"
    onde: "ADR 0093 ; Modules/Fiscal/Tests/Feature/{NfeCockpit,Cockpit,Eventos}MultiTenantTest.php"
    quem_tem_no_mercado: ["Omie (multi-empresa forte)", "middlewares multi-CNPJ"]
    diferencial: "isolamento Tier 0 IRREVOGÁVEL testado — mais rígido que multi-CNPJ de conveniência"

  - nome: "Reforma Tributária IBS/CBS — cálculo/preenchimento"
    score: P0
    estado: NAO
    descricao: "SCHEMA scaffold pronto (colunas cClassTrib + cst_ibs + cst_cbs + aliquota_ibs + aliquota_cbs em nfe_fiscal_rules) mas MotorTributarioService NÃO calcula IBS/CBS — zero lógica de cálculo"
    onde: "Modules/NfeBrasil/Database/Migrations/2026_05_26_000001_add_ibs_cbs_to_nfe_fiscal_rules.php (scaffold) + OndaIbsCbsScaffoldTest.php"
    quem_tem_no_mercado: ["Bling (auto-fill)", "Tiny", "Omie (datado)", "TecnoSpeed", "PlugNotas (calculadora)"]
    gap: "⚠️ P0 REGULATÓRIO com prazo duro — ver Seção 7"

  - nome: "Cockpit fiscal unificado (KPIs + alertas + sparklines)"
    score: P0
    estado: SIM
    descricao: "Visão consolidada NF-e/NFC-e/NFS-e/DF-e/eventos + alertas determinísticos + quick links; substitui telas fragmentadas"
    onde: "Modules/Fiscal/Http/Controllers/CockpitController.php"
    quem_tem_no_mercado: ["Omie (parcial)"]
    diferencial: "raison d'être do módulo — mercado middleware não tem cockpit"
    gap: "✅ resolvido — cache Redis 60s em US-FISCAL-019 (GAP-FISCAL-002 fechado, verificado@176f9bc 2026-07-01)"

  - nome: "ICMS-ST / DIFAL / FCP cálculo automático"
    score: P1
    estado: PARCIAL
    descricao: "TributoCalculado tem campo mva mas cascade não computa ST/DIFAL/partilha interestadual completos (2 menções no motor)"
    onde: "Modules/NfeBrasil/Services/Tributacao/TributoCalculado.php"
    quem_tem_no_mercado: ["Bling", "Tiny", "Omie (GNRe/DIFAL forte)"]
    gap: "risco P1: venda interestadual contribuinte (CFOP 6102 c/ ICMS-ST) gera CST/CFOP incompleto"

  - nome: "ISS / NFS-e config (alíquota por município)"
    score: P1
    estado: PARCIAL
    descricao: "Fiscal lê NfseEmissao (modelo 56 nacional NT 2024-001) mas config de alíquota municipal + adapters por cidade vivem em Modules/NFSe (parcial)"
    onde: "Modules/Fiscal/Http/Controllers/NfseCockpitController.php → Modules/NFSe"
    quem_tem_no_mercado: ["Focus NFe (3000+ municípios)", "Bling", "Omie", "PlugNotas (NFS-e Nacional)"]

  - nome: "SPED EFD-ICMS/IPI (geração TXT PVA-validável)"
    score: P1
    estado: PARCIAL
    descricao: "Gerador próprio 23 registros (Blocos 0+C+E apuração ICMS + H esqueleto) v3.1.1 perfil A saídas; SEM Bloco H com inventário real; TXT manual via helper linha() (não usa nfephp-org/sped-efd-icms-ipi)"
    onde: "Modules/Fiscal/Services/SpedIcmsIpiGeneratorService.php"
    quem_tem_no_mercado: ["Bling", "Omie", "TecnoSpeed (produto SPED)"]
    gap: "biz=1/biz=4 Simples OK; muda regime → PVA-EFD rejeita; smoke PVA homologação pendente"

  - nome: "Eventos timeline auditável append-only"
    score: P1
    estado: SIM
    descricao: "Timeline CC-e + Cancelamento + EPEC + Manifestação append-only, eager with('emissao') cross-página, gate único fiscal.access (audit)"
    onde: "Modules/Fiscal/Http/Controllers/EventosController.php"
    quem_tem_no_mercado: ["parcial em todos — poucos expõem timeline unificada"]
    diferencial: "audit append-only nativo"

  - nome: "Health-check certificado A1 (alerta vencimento cron)"
    score: P1
    estado: PARCIAL
    descricao: "Config exibe validade do cert mas sem cron proativo de alerta antecipado de vencimento"
    onde: "Modules/Fiscal/Http/Controllers/ConfigController.php"
    quem_tem_no_mercado: ["todos os middlewares", "Bling", "Omie"]
    gap: "GAP-P1 — automatizar via schedule (dias-a-vencer → mcp_alertas)"

  - nome: "Busca cross-fiscal / ⌘K palette"
    score: P1
    estado: SIM
    descricao: "Palette global Cmd/Ctrl+K busca notas + DF-e cross-fiscal, endpoint validado 2-50 chars + throttle 60/min + permission gate"
    onde: "Modules/Fiscal/Http/Controllers/PaletteSearchController.php + resources/js/Pages/Fiscal/_components/CmdKPalette.tsx"
    quem_tem_no_mercado: ["nenhum concorrente fiscal tem ⌘K"]
    diferencial: "UX estado-da-arte (Linear/Notion-like) inédito no vertical fiscal BR"
    gap: "✅ resolvido — anti-DOS/índice palette em US-FISCAL-019 (GAP-FISCAL-002 fechado)"

  - nome: "Entrada via DF-e manifestada → escrituração (Bloco C inputs)"
    score: P1
    estado: NAO
    descricao: "DF-e recebidas não viram entradas de estoque/escrituração automaticamente (exige reconciliação cadastro fornecedor Modules/Crm)"
    onde: "backlog"
    quem_tem_no_mercado: ["Bling (import automático)", "Omie"]

  - nome: "SPED EFD-Contribuições (PIS/COFINS)"
    score: P2
    estado: NAO
    descricao: "Arquivo separado PIS/COFINS não gerado (backlog PR #10)"
    quem_tem_no_mercado: ["Bling", "Omie", "TecnoSpeed"]

  - nome: "MDF-e (Manifesto de Documento Fiscal)"
    score: P2
    estado: NAO
    descricao: "Sem emissão de MDF-e (não há transporte próprio no piloto)"
    quem_tem_no_mercado: ["TecnoSpeed", "PlugNotas", "Nuvem Fiscal", "Focus NFe"]

  - nome: "Sugestão determinística por cStat rejeitado (Jana sugere)"
    score: P2
    estado: SIM
    descricao: "Mapa determinístico cstat rejeitado → receita de correção no drawer (substitui IA real per R#2 KB-9.75)"
    onde: "Modules/Fiscal drawer NotaDrawer.tsx"
    quem_tem_no_mercado: ["Omie (IA Fiscal parcial)"]
    diferencial: "orientação de correção inline no cockpit"

  - nome: "FSM cancel cascade (estorno financeiro + notificação cliente)"
    score: P2
    estado: SIM
    descricao: "Cancelar NFe dispara CancelarVendaCascade (cancel NFe SEFAZ + refund Asaas/Inter + WhatsApp/email cliente) via FSM ADR 0143"
    onde: "app/Domain/Fsm/CancelarVendaCascade → AcoesController::cancelarNfe"
    quem_tem_no_mercado: []
    diferencial: "ÚNICO — nenhum concorrente fiscal orquestra estorno financeiro + notificação no cancelamento"

  - nome: "Webhook de eventos fiscais p/ sistemas externos"
    score: P3
    estado: NAO
    descricao: "Sem callback HMAC de mudança de estado fiscal p/ terceiros"
    quem_tem_no_mercado: ["Focus NFe", "PlugNotas", "TecnoSpeed"]

  - nome: "Aviso antecipado de cSit no cadastro cliente (ADR 0186)"
    score: P3
    estado: SIM
    descricao: "Consulta cadastro SEFAZ + warning de situação cadastral do destinatário ANTES da emissão"
    onde: "ADR 0186 (chain certificado→SEFAZ→consulta cadastro)"
    quem_tem_no_mercado: []
    diferencial: "ÚNICO — Bling/Tiny não exibem cSit no cadastro (audit sênior confirmou)"
```

---

## Seção 5 — Diferenciais oimpresso (o que ninguém do mercado tem)

| # | Diferencial | Por que importa | Concorrente que tem |
|---|---|---|---|
| 1 | **FSM cancel cascade** (estorno financeiro + notif cliente no cancelamento) | Cancelar nota ≠ só evento SEFAZ: refund Asaas/Inter + WhatsApp cliente automáticos | **nenhum** |
| 2 | **Aviso antecipado `cSit`** no cadastro cliente (ADR 0186) | Evita rejeição SEFAZ por destinatário irregular ANTES de emitir | **nenhum** |
| 3 | **Cockpit fiscal unificado + ⌘K palette** | Visão única cross-fiscal com UX Linear/Notion — inédito no vertical fiscal BR | Omie (cockpit parcial, sem ⌘K) |
| 4 | **"Jana sugere" determinístico por cStat** | Receita de correção inline no drawer de rejeição | Omie (IA Fiscal parcial) |
| 5 | **Multi-tenant Tier 0 IRREVOGÁVEL** (não multi-CNPJ de conveniência) | Isolamento de cert/emissões testado biz=1 vs biz=99 | Omie (multi-empresa, sem garantia Tier 0) |
| 6 | **ERP-nativo unificado** (config fiscal + emissão + financeiro + venda numa tela) | Middlewares obrigam alt-tab p/ portal externo; ERPs peer não têm FSM+financeiro integrados | parcial (Bling/Omie) |

> A tese competitiva do oimpresso **não é ser o melhor motor fiscal** (Omie ganha em obrigações) — é ser o ERP-vertical onde o fiscal está **costurado ao financeiro + venda + IA** com governança Tier 0.

---

## Seção 6 — Gaps priorizados (impacto × esforço)

| Rank | Gap | Cap# | Impacto | Esforço (IA-pair) | Prazo |
|---|---|:---:|:---:|:---:|---|
| **1** | **IBS/CBS cálculo no MotorTributarioService** (sair do scaffold) | 7 | 🔴 P0 regulatório | ~3-4 dev-days | **produção obrig. 03/08/2026** — ver §7 |
| 2 | ICMS-ST / DIFAL / FCP no cascade (interestadual contribuinte) | 9 | 🟡 P1 | ~2 dev-days | quando Larissa fizer venda revenda interestadual |
| 3 | SPED Bloco H inventário real + smoke PVA-EFD homologação | 11 | 🟡 P1 | ~2 dev-days | entrega contábil Eliana dia 15 |
| 4 | Health-check cert A1 (cron alerta vencimento) | 13 | 🟡 P1 | ~0.5 dev-day | contínuo |
| ~~5~~ | ~~Cache Redis KPIs cockpit + índice busca palette~~ ✅ **feito (US-FISCAL-019)** | 8/14 | — | — | GAP-FISCAL-002 fechado |
| 6 | EFD-Contribuições PIS/COFINS (arquivo separado) | 16 | 🟢 P2 | ~1 semana | backlog PR #10 |
| 7 | Entrada DF-e manifestada → Bloco C inputs | 15 | 🟢 P2 | ~2 dev-days (dep. Crm) | backlog |

> Cruza com [AUDIT-SENIOR-2026-05-25.md](AUDIT-SENIOR-2026-05-25.md) (GAP-FISCAL-001..005). **GAP-FISCAL-002 (cache/perf) e GAP-FISCAL-003 (hardcodes SPED) já fechados** (US-FISCAL-019 e US-FISCAL-020, Ondas ESTABILIZAR/CONSOLIDAR). O gap #1 desta ficha = **GAP-FISCAL-004** e é o único P0 vivo. _(Correção 2026-07-03 Passo 2: gap #5 cache marcado como fechado — o audit de 25/mai listava GAP-FISCAL-002 aberto, mas foi resolvido depois.)_

---

## Seção 7 — Reforma Tributária IBS/CBS (estado regulatório + posição oimpresso)

### Timeline oficial (fontes: Câmara/Senado/Receita + leitura vendor TecnoSpeed da NT 2025.002-RTC)

| Data | Marco | Impacto Fiscal oimpresso |
|---|---|---|
| **2026-01-01** | Fase teste — IBS 0,1% / CBS 0,9% (produção restrita, pedagógico) | Já passou |
| **2026-04-01** | Validação dos fields IBS/CBS pela Receita | Já passou |
| **2026-07-01** | **Homologação obrigatória** (leitura vendor) | ⚠️ **passou há 2 dias** (hoje 03/07) |
| **2026-08-03** | **Produção obrigatória** — trava emissão sem IBS/CBS (CRT 3 Normal) | 🔴 **~1 mês** — hard deadline se biz mudar p/ Lucro Presumido/Real |
| **2027-01-01** | CBS substitui PIS+COFINS integral; IS entra; IBS transição | Sistema precisa estar 100% |
| **2027-01-04** | Simples Nacional/MEI passa a destacar IBS/CBS | **prazo Larissa biz=4** (Simples) |
| **2029-2032** | IBS suplanta ICMS+ISS gradual | Roadmap longo |
| **2033** | Sistema final = CBS + IBS + IS | Fim da transição |

### Campos novos no XML (NT 2025.002-RTC)

Grupo **UB** e correlatos: `IBSUF` (IBS estadual), `IBSMun` (IBS municipal), `CBS`, **`cClassTrib`** (vinculado a artigo específico da LC 214/2025), `gTribCompraGov`, `gIBSCBSMono` (monofásica), `gCredPresOper` (crédito presumido). Validações rígidas (LA01-30, N12-110; rejeições 1106/960).

### Posição oimpresso (code-verified @ `7442c27c43`)

- ✅ **Schema pronto** — migration `add_ibs_cbs_to_nfe_fiscal_rules` já tem `cClassTrib` + `cst_ibs` + `cst_cbs` + `aliquota_ibs` + `aliquota_cbs` (US-FISCAL-021 scaffold)
- ❌ **Cálculo ausente** — `MotorTributarioService` tem **0 lógica IBS/CBS**; não preenche nem valida os grupos
- ⚠️ **Dependência de lib** — `nfephp-org/sped-nfe` tem IBS/CBS na branch `master` + `TraitTagDetIBSCBS`, mas **tag estável Composer = v5.1.34 SEM reforma** (issue [#1274](https://github.com/nfephp-org/sped-nfe/issues/1274) pede release; sem data). Produção dependeria de `dev-master` até lá.

### Como o mercado se posiciona

- **PlugNotas** — líder: Calculadora da Reforma calcula CBS/IBS item-a-item (apoiada na calculadora oficial da Receita); "manda o mínimo, eu calculo"
- **Bling** — auto-fill IBS/CBS desde 01/01/2026 (Regime Normal; CST 000 + cClassTrib 000001 default customizável); trava emissão sem grupos a partir de 03/08/2026 (⚠️ reclamação pública de gap p/ produtos imunes)
- **Omie** — cronograma datado granular (homologação 21/08/2025 → IA Fiscal monitora IBS/CBS 18/12/2025 → ZFM 12/03/2026)
- **Tiny** — motor híbrido base única IBS+CBS anunciado

> **Veredito:** o oimpresso está **atrás do pelotão ERP** nesta dimensão — schema pronto mas sem cálculo. Como biz=1 (Wagner) e biz=4 (Larissa) são **Simples Nacional** (não destacam IBS/CBS até 2027-01), o risco imediato é **contido**, mas o gap vira **crítico** se qualquer piloto mudar para Regime Normal antes de 2027. **Recomendação: abrir Onda 6 IBS/CBS (GAP-FISCAL-004) como próximo P0 pós-estabilização.**

---

## Seção 8 — Como auditar este módulo

**Locais a inspecionar (paths em `origin/main`):**
- Controllers: `Modules/Fiscal/Http/Controllers/{Cockpit,Nfe,Nfse,Dfe,Eventos,Config,Sped,Acoes,PaletteSearch}Controller.php`
- Service próprio: `Modules/Fiscal/Services/SpedIcmsIpiGeneratorService.php`
- Motor consumido: `Modules/NfeBrasil/Services/MotorTributarioService.php` + `Services/Tributacao/*` + `Services/Manifestacao/*`
- Config editável: `Modules/NfeBrasil/Http/Controllers/TributacaoController.php`
- Schema regras: `Modules/NfeBrasil/Database/Migrations/*fiscal_rules*` (incl. IBS/CBS scaffold)
- Tests: `Modules/Fiscal/Tests/Feature/*` (multi-tenant + cockpit + SpedMotorTributarioIntegrationTest) + `Modules/NfeBrasil/Tests/Feature/{MotorTributarioService,DistribuicaoDfeService,Manifestacao}*`
- UI Inertia: `resources/js/Pages/Fiscal/{Cockpit,Nfe,Nfse,Dfe,Eventos,Config,Sped}.tsx` + `_components/{FxShell,NotaDrawer,InutilizacaoModal,CmdKPalette}.tsx`
- Doc: `memory/requisitos/Fiscal/{SPEC,BRIEFING,AUDIT-SENIOR-2026-05-25}.md` + 7 RUNBOOKs

**Critérios de classificação:**

| Capacidade | ✅ APROVADO requer | 🟡 PARCIAL aceita |
|---|---|---|
| Motor tributário | cascade ≥3 níveis + cache + teste 5+ cenários regime | Motor existe mas SPED não consome |
| IBS/CBS | `MotorTributarioService` calcula + preenche grupo UB + valida cClassTrib + teste | schema scaffold sem cálculo |
| DF-e/Manifestação | download automático + 4 ações + teste isolamento | ações sem download automático |
| SPED | Blocos 0+C+E+H com dados reais + smoke PVA homologação | MVP saídas + Bloco H esqueleto |
| Multi-tenant | HasBusinessScope + guard Service + Pest biz=1 vs biz=99 | scope sem teste isolamento |

**Métricas de prod relevantes:**
- Taxa autorização SEFAZ p95 `>99%` · latência motor tributário `<50ms` (cache warm) · % emissões com IBS/CBS preenchido (rumo a 2027)

---

## Seção 9 — UX heuristics + Automation targets (Capterra v2)

```yaml
ux_heuristics:
  - id: cliques-cancelar-com-estorno
    nome: "Cliques para cancelar nota COM estorno financeiro + notificação"
    score: P0
    benchmark: "Bling/Tiny: cancelar nota é evento isolado — estorno e aviso ao cliente são passos manuais separados. Middlewares: só o evento SEFAZ."
    target: "1 ação no drawer FSM dispara cascade (SEFAZ + refund + WhatsApp)"
    metrica: "fiscal_cancel_cascade_steps"

  - id: orientacao-rejeicao-inline
    nome: "Tempo até saber COMO corrigir uma rejeição cStat"
    score: P0
    benchmark: "Concorrentes: código cStat cru → operador pesquisa manual. oimpresso: mapa 'Jana sugere' inline no drawer."
    target: "receita de correção visível sem sair da tela"
    metrica: "fiscal_cstat_hint_coverage_pct"

  - id: busca-cross-fiscal
    nome: "Cliques para achar uma nota/DF-e por número/chave"
    score: P1
    benchmark: "ERPs: navegar por menu+filtro (3-5 cliques). oimpresso: ⌘K de qualquer tela."
    target: "<= 2 interações (Cmd+K → digitar)"
    metrica: "fiscal_palette_navegacao_steps"

  - id: config-fiscal-antecipa-erro
    nome: "Erro fiscal evitado ANTES da emissão (cSit destinatário)"
    score: P1
    benchmark: "Bling/Tiny não exibem cSit no cadastro cliente (ADR 0186). oimpresso avisa antes."
    target: "warning cadastral pré-emissão"
    metrica: "fiscal_csit_warning_hits"
```

```yaml
automation_targets:
  - id: distribuicao-dfe-automatica
    nome: "Baixar NF-e emitidas contra o CNPJ sem humano"
    score: P0
    benchmark: "TecnoSpeed/PlugNotas/Nuvem/Focus/Bling: SIM. oimpresso: SIM (BuscarDfesRecebidosJob)."
    target: "cron distribuição DF-e + persistência NfeDfeRecebido, p95 < 60s"
    metrica: "dfe_auto_download_p95_seconds"

  - id: cancel-cascade-financeiro
    nome: "Estorno financeiro + notificação cliente ao cancelar nota"
    score: P0
    benchmark: "Nenhum concorrente automatiza — diferencial único oimpresso (FSM ADR 0143)."
    target: "CancelarVendaCascade dispara refund + WhatsApp/email, idempotente"
    metrica: "fiscal_cancel_cascade_success_pct"

  - id: health-check-cert-a1
    nome: "Alerta proativo de vencimento de certificado A1"
    score: P1
    benchmark: "Middlewares e Bling/Omie alertam. oimpresso: PARCIAL (sem cron)."
    target: "schedule diário: dias-a-vencer <= 30 → mcp_alertas"
    metrica: "cert_a1_dias_a_vencer_min"

  - id: ibs-cbs-preenchimento-auto
    nome: "Preencher grupos IBS/CBS automaticamente na emissão"
    score: P0
    benchmark: "Bling auto-fill desde 01/01/2026; PlugNotas calcula item-a-item. oimpresso: NÃO (scaffold)."
    target: "MotorTributarioService calcula cClassTrib + CST + alíquotas IBS/CBS"
    metrica: "emissoes_com_ibs_cbs_preenchido_pct"
```

---

## Seção 10 — Métricas de adoção + Histórico de revisão

### Adoção

- **Última auditoria**: 2026-07-03 (1ª CAPTERRA-FICHA — via `capterra-senior`, programa de ondas)
- **Auditoria sênior anterior**: [AUDIT-SENIOR-2026-05-25.md](AUDIT-SENIOR-2026-05-25.md) (module-grade-v3 = 66/100)
- **Nota Capterra (esta ficha)**: **75/100** (ponderada P0-P3, market-competitiveness)
- **Capacidades P0 cobertas**: 7/8 (só IBS/CBS cálculo = ❌)
- **Cliente em produção**: biz=1 (Wagner) piloto; biz=4 (Larissa) pre-canary
- **Próxima reauditoria sugerida**: após Onda 6 IBS/CBS (GAP-FISCAL-004) mergear, OU 2026-10 (revisão trimestral pós produção obrig. 03/08)

### Comparativos de referência

- [AUDIT-SENIOR-2026-05-25.md](AUDIT-SENIOR-2026-05-25.md) — auditoria sênior interna (12 gaps + estado-da-arte)
- [NfeBrasil/CAPTERRA-FICHA.md](../NfeBrasil/CAPTERRA-FICHA.md) — ficha do emissor (fronteira — não sobrepor)
- Session log: [`memory/sessions/2026-07-03-capterra-fiscal.md`](../../sessions/2026-07-03-capterra-fiscal.md)

### Histórico de revisão da ficha

- `2026-07-03` — Claude Code (`capterra-senior`) — **criação**. Programa de Ondas Passo 1. Nota 75/100. Pesquisa: TecnoSpeed/PlugNotas/Nuvem Fiscal + Focus NFe/Bling + Tiny/Omie (3 agentes paralelos, 24 WebSearch, ~45 fontes citadas). Wagner OK [W] 2026-07-03 (camada fiscal). Base: `origin/main` @ `7442c27c43`.
- `2026-07-03` — Claude Code (`/comparativo Fiscal`, Passo 2) — **correção de staleness**: gap #5 (cache KPIs/palette) marcado como fechado (US-FISCAL-019, GAP-FISCAL-002) — o audit de 25/mai que alimentou a ficha listava aberto, mas foi resolvido depois. Nota 75/100 inalterada (cache era sub-nota de perf, não capacidade pontuada). Ver [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md).

---

> **Nota de honestidade (fontes):** vereditos de concorrente marcados ❓ = DESCONHECIDO por não-achado em fonte primária (não afirmação negativa). Preços de concorrente descritos qualitativamente (convenção NfeBrasil ficha — sem dígitos R$). Vereditos oimpresso = code-verified contra `origin/main` @ `7442c27c43` em 2026-07-03. Datas de obrigatoriedade IBS/CBS de homologação/produção são leitura vendor da NT 2025.002 — sujeitas a redefinição SEFIN/SEFAZ.
