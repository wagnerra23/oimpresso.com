# CAPTERRA-FICHA — Officeimpresso (bridge legacy WR Sistemas)

> **Ficha canônica de benchmark do módulo Officeimpresso** — bridge de migração entre ERP Delphi histórico (WR Comercial / OfficeImpresso) e oimpresso Laravel moderno. Avaliação NÃO compara feature-a-feature com ERPs horizontais, e sim com **alternativas reais que o cliente legacy enxerga** quando decide o que fazer com 26 anos de dados Firebird na mesa.
> ADR de governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md) · [0153](../../decisions/0153-module-grade-rubrica-v1.md) · [0154](../../decisions/0154-module-grade-v2-na-justificado.md).
> ADRs canônicas do módulo: [0017](../../decisions/0017-officeimpresso-restaurado-superadmin-exclusivo.md) · [0018](../../decisions/0018-officeimpresso-log-acesso-passivo.md) · [0019](../../decisions/0019-officeimpresso-delphi-nao-autentica.md) · [0020](../../decisions/0020-officeimpresso-grupo-economico.md) · [0021](../../decisions/0021-officeimpresso-contrato-api-delphi.md) · 0136 (bridge legacy) · 0137 (pareado Connector).

---

## 1. Identidade do módulo

- **Nome interno**: `Modules/Officeimpresso`
- **Domínio de negócio**: bridge de migração + licenciamento desktop Delphi WR Comercial → oimpresso Laravel (transição assistida por business)
- **Persona alvo**: Wagner (superadmin único) hoje; Felipe (suporte) com `officeimpresso.access` no futuro
- **Cliente principal alvo**: 7 clientes legacy WR Sistemas saudáveis em produção on-prem Delphi — **Vargas, Extreme, Gold, Zoom, Fixar, Mhundo, Produart**
- **Status**: bridge-legacy (será **descomissionado** quando último cliente Delphi sair — sem ETA, depende sinal qualificado por business ADR 0105)

## 2. Concorrentes-alvo (sob ângulo "o que o cliente legacy gráfico faz?")

Officeimpresso NÃO é módulo de produto. Compara com **rotas alternativas** que o cliente legacy considera:

| Concorrente | Tipo | Proposta ao cliente legacy |
|---|---|---|
| **Bling ERP** (LWSA) | SaaS BR horizontal | Migra tudo pro Bling, abandona Delphi, NF-e nativa, sem importer próprio (cliente recadastra) |
| **Tiny ERP** (Olist) | SaaS BR horizontal | Idem Bling — migração manual, foco e-commerce; Olist canal |
| **Omie** | SaaS BR horizontal | Idem — força contábil + bancos, fraca em chão-fábrica gráfico |
| **PrintIQ** (NZ/Global) | SaaS vertical print MIS | Migra pra MIS global referência mundial; preço enterprise (USD), curva longa, sem PT-BR nativo |
| **Continuar Delphi indefinidamente** | Status quo | Custo zero curto prazo + risco fiscal/Win11/NFe legado crescente |

Comparativo geral disponível em `memory/comparativos/oimpresso_vs_concorrentes_capterra_2026_04_25.md`.

## 3. Capacidades baseline com score

```yaml
capacidades:
  - nome: "Bridge REST autenticado Firebird → Laravel (read-only legacy)"
    score: P0
    descricao: "7 Controllers expõem clientes/vendas/NFe/financeiro/licenças do Firebird legacy via endpoints Passport-auth; Connector consome durante migração assistida"
    quem_tem: ["—"]  # nenhum SaaS BR fornece bridge dedicada Delphi WR
    evidencia_de_pronto: "Modules/Officeimpresso/Http/Controllers/{Client,Data,Audit,Licenca*}Controller.php + 3 Pest smoke + pareado Modules/Connector (ADR 0137)"

  - nome: "Licenciamento computador desktop Delphi (Lei 9.609/98)"
    score: P0
    descricao: "Controle de máquinas autorizadas a rodar WR Comercial Desktop por cliente legacy; revogação por superadmin; retention 5y obrigatória"
    quem_tem: ["Bling: N/A", "Tiny: N/A", "Omie: N/A", "PrintIQ: SaaS sem licença máquina"]
    referencias: ["Lei 9.609/98 (Software)", "ADR 0017"]
    evidencia_de_pronto: "LicencaComputadorController + LicencaService + LicencaAuditService + Entities/Licenca_Computador + LicencaLog (append-only) + view computadores.blade"

  - nome: "Schema Firebird documentado (mapeamento legacy → Laravel)"
    score: P0
    descricao: "Mapa explícito tabelas Firebird WR Comercial → modelos Laravel; permite escrever importer reproduzível por cliente"
    quem_tem: ["—"]
    evidencia_de_pronto: "memory/requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md + skill officeimpresso-source-analysis"

  - nome: "Cleanup tools migração legacy (auditoria + reset)"
    score: P1
    descricao: "Comandos artisan pra inspecionar Delphi API + parsear logs de licença + revogar máquina órfã pós-migração"
    quem_tem: ["—"]
    evidencia_de_pronto: "Console/Commands/InspectDelphiApiCommand + ParseLicencaLogCommand + AuditController"

  - nome: "Coexistência multi-cliente legacy (7 businesses simultâneos)"
    score: P0
    descricao: "Cada cliente legacy é business_id isolado; bridge respeita Tier 0 ADR 0093; menu superadmin lista todos"
    quem_tem: ["Bling: SaaS multi-tenant nativo mas sem bridge", "Tiny/Omie: idem", "PrintIQ: enterprise multi-tenant"]
    evidencia_de_pronto: "Pest isolation business_id biz=1 vs biz=99 (Wave A 2026-05-12) + LicencaComputador escopo per-business"

  - nome: "Snapshot financeiro pré-migração (proposta comercial)"
    score: P1
    descricao: "Extrai receita histórica Firebird pra calcular ROI/payback do cliente migrar; alimenta proposta vs Mubsys/Bling"
    quem_tem: ["—"]
    referencias: ["RUNBOOK-financial-snapshot-cliente.md"]
    evidencia_de_pronto: "Skill officeimpresso-financial-snapshot + RUNBOOK-financial-snapshot-cliente.md + PROPOSTA-COMERCIAL-vs-mubsys.md template"

  - nome: "Recuperação on-prem cliente (alternativa à migração)"
    score: P1
    descricao: "Runbook pra reativar cliente saindo (Gold caso primário ADR 0115) sem forçar migração full; mantém Delphi rodando + adiciona oimpresso módulos NFe/Boleto"
    quem_tem: ["—"]
    evidencia_de_pronto: "RUNBOOK-recuperacao-on-prem.md + ADR 0115 Gold + bundle pricing"

  - nome: "Migração UI Blade → Inertia/React (modernização interna)"
    score: P2
    descricao: "14 telas Blade legacy → Pages React/Inertia via MWART; melhora UX superadmin sem quebrar workflow"
    quem_tem: ["—"]
    evidencia_de_pronto: "RUNBOOK-migracao-react.md (MWART aplicado) + skill sidebar-menu-arch + DataController::modifyAdminMenu()"

  - nome: "Log acesso passivo + grupo econômico"
    score: P2
    descricao: "Audit append-only de quem acessou bridge + agregação por grupo econômico (Vargas+Extreme mesmo dono etc)"
    quem_tem: ["—"]
    evidencia_de_pronto: "ADR 0018 + ADR 0020 + AuditController + LicencaLog append-only"

  - nome: "Delphi NÃO autentica (one-way bridge)"
    score: P0
    descricao: "Decisão arquitetural: Delphi consome bridge mas NÃO recebe credenciais Laravel; reduz superfície ataque legacy"
    quem_tem: ["—"]
    referencias: ["ADR 0019"]
    evidencia_de_pronto: "Contrato API documentado ADR 0021 + Passport tokens scoped pra Connector, nunca pro Delphi"

  - nome: "Importer Delphi → MySQL idempotente (capacidade ainda parcial)"
    score: P0
    descricao: "Ler Firebird + idempotente upsert no MySQL Laravel preservando ID legacy + dedupe + dry-run; hoje é manual por cliente"
    quem_tem: ["Bling: importador genérico CSV/XML, sem mapa Delphi", "Tiny: idem", "PrintIQ: profissional services migration"]
    evidencia_de_pronto_target: "Comando artisan officeimpresso:import {business_id} --dry-run idempotente; HOJE: parcial via Connector caso-a-caso, sem comando unificado"
    gap_atual: "Não existe comando unificado idempotente; cada migração é semi-artesanal usando InspectDelphiApiCommand + Connector"

  - nome: "Migração assistida por wizard (UI guiada cliente-a-cliente)"
    score: P1
    descricao: "Wizard superadmin com checklist por cliente: snapshot financeiro → backup Firebird → import dry-run → import real → reconciliação → cutover"
    quem_tem: ["PrintIQ: professional services humano"]
    gap_atual: "Não existe; hoje Wagner conduz manual usando runbooks isolados"

  - nome: "Telemetria migração (% cliente migrado, drift Delphi vs Laravel)"
    score: P2
    descricao: "Métricas por cliente: N produtos migrados / N total Firebird, drift vendas D+1, alerta se Delphi escreveu após cutover"
    quem_tem: ["—"]
    gap_atual: "Não existe; cutover hoje é Wagner observando manual"
```

## 4. Score capacidades (P0 + P1 ponderado, P2 informativo)

| Capacidade | Peso | oimpresso (hoje) | Bling | Tiny | Omie | PrintIQ |
|---|---|---|---|---|---|---|
| Bridge REST autenticado Firebird→Laravel | P0×4 | **4** | 0 | 0 | 0 | 0 |
| Licenciamento computador Lei 9.609/98 | P0×4 | **4** | N/A | N/A | N/A | N/A |
| Schema Firebird documentado | P0×4 | **4** | 0 | 0 | 0 | 0 |
| Coexistência multi-cliente legacy | P0×4 | **4** | 0 | 0 | 0 | 2 |
| Delphi NÃO autentica (one-way) | P0×4 | **4** | N/A | N/A | N/A | N/A |
| **Importer idempotente unificado** | P0×4 | **1.5** | 1 (CSV) | 1 | 1 | 3 (prof svc) |
| Cleanup tools migração | P1×2 | **2** | 0 | 0 | 0 | 1 |
| Snapshot financeiro pré-migração | P1×2 | **2** | 0 | 0 | 0 | 1 |
| Recuperação on-prem (bundle) | P1×2 | **2** | 0 | 0 | 0 | 0 |
| **Wizard migração assistida** | P1×2 | **0** | 0 | 0 | 0 | 2 (humano) |
| MWART UI modernização | P2×1 | 1 | — | — | — | — |
| Log + grupo econômico | P2×1 | 1 | — | — | — | — |
| Telemetria migração | P2×1 | 0 | — | — | — | — |

**Soma ponderada P0+P1 (max=46):**
- **oimpresso: 27.5 / 46 = 59.7** → nota **6.0**
- Bling/Tiny/Omie/PrintIQ: N/A (não competem nesta categoria — produto é diferente)

**Conversão Capterra-style (escala 1-10):**
- **Officeimpresso bridge: 6.0** — capacidades P0 bridge/licença/schema/multi-cliente sólidas (notas 4/4); P0 importer idempotente unificado é o gap dominante (1.5/4); wizard P1 ausente.

## 5. Diferenciais únicos oimpresso (nicho que ninguém atende)

1. **Bridge dedicada WR Comercial/OfficeImpresso Delphi**: zero alternativa de mercado — é nicho 26 anos histórico Wagner. Bling/Tiny/Omie ignoram este universo; PrintIQ não fala PT-BR e ignora ERPs locais
2. **Coexistência sem cutover forçado**: cliente migra **gradual** módulo a módulo (NFe primeiro via bundle, depois financeiro, depois full) — concorrente exige big-bang
3. **Licenciamento desktop preservado** (Lei 9.609/98 retention 5y): cliente legacy continua faturando enquanto migra; nenhum SaaS oferece isto
4. **Snapshot financeiro pré-proposta** (RUNBOOK + skill): proposta comercial baseada em dado real Firebird do cliente, não estimativa

## 6. Gaps top-3 (oportunidades de evolução)

1. **(P0)** Comando artisan unificado `officeimpresso:import {biz} --dry-run` idempotente — hoje cada migração é semi-artesanal; risco erro humano cresce com N de clientes simultâneos. Esforço: ~2 semanas; impacto: destrava migração paralela 3+ clientes
2. **(P1)** Wizard React migração assistida — checklist visual por cliente (snapshot → backup → dry-run → real → reconcile → cutover) reduz superadmin-bottleneck (Wagner) e prepara delegação Felipe. Esforço: ~3 semanas pós-importer; impacto: 1 superadmin → 1 suporte (`officeimpresso.access`)
3. **(P2)** Telemetria migração + drift detector — alerta automático se Delphi escrever após cutover de cliente; protege contra perda de dados pós-migração. Esforço: ~1 semana; impacto: confiança Wagner pra fazer cutover mais cedo

## 7. Restrições Tier 0 preservadas

- ✅ **Bridge legacy Delphi**: preservada conforme ADR 0136 + 0137 + 0019 (Delphi NÃO autentica) + 0021 (contrato API)
- ✅ **Lei 9.609/98 (Software) — retention 5 anos** licença computador: `LicencaLog` append-only + revogação registrada, nunca delete físico
- ✅ **Superadmin-only**: ADR 0017 — todo Controller guard `auth()->user()->can('superadmin')` + Spatie permission `superadmin`
- ✅ **Multi-tenant Tier 0**: ADR 0093 — bridge respeita `business_id` global scope; licenças escopadas per-business

## 8. Decisões deliberadas (NÃO são gaps)

- **Sem app mobile próprio**: bridge é backend; superadmin acessa via web
- **Sem documentação pública**: superadmin-only, ADR 0017
- **Sem importer cliente-self-service**: migração é assistida por Wagner — risco fiscal/dado muito alto pra automação cega
- **MDF-e / NF-e direto no Officeimpresso**: NÃO — fica em `Modules/NfeBrasil` (SoC brutal Constituição v2 §5); Officeimpresso só expõe dado legacy

## 9. Referências canônicas

- `Modules/Officeimpresso/SCOPE.md` — escopo + trust L3
- `memory/requisitos/Officeimpresso/SPEC.md` — especificação atual
- `memory/requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md` — mapa Firebird → Laravel
- `memory/requisitos/Officeimpresso/RUNBOOK-migracao-react.md` — MWART aplicado
- `memory/requisitos/Officeimpresso/RUNBOOK-recuperacao-on-prem.md` — bundle recuperação (Gold caso primário ADR 0115)
- `memory/requisitos/Officeimpresso/RUNBOOK-financial-snapshot-cliente.md` — extração receita Firebird
- `memory/requisitos/Officeimpresso/PROPOSTA-COMERCIAL-vs-mubsys.md` — template proposta
- ADRs: 0017 / 0018 / 0019 / 0020 / 0021 / 0115 / 0136 / 0137
- Skills: `officeimpresso-source-analysis`, `officeimpresso-financial-snapshot`, `sidebar-menu-arch`

## 10. Nota final

**6.0 / 10** — bridge sólida em P0 estruturais (REST + licença Lei 9.609/98 + schema + multi-cliente + segurança one-way Delphi); penalizada pelo gap P0 do importer idempotente unificado (hoje semi-artesanal) e ausência do wizard assistido P1. Considerando que é **módulo bridge transitório** (será descomissionado), o nível atual é suficiente pra atender os 7 clientes saudáveis em ritmo Wagner-conduzido; investir nos top-3 gaps só se sinal qualificado de 3+ clientes simultâneos chegando (ADR 0105) ou se Wagner quiser delegar a Felipe.

---

**Última atualização**: 2026-05-16 — Wave 22 governança CAPTERRA-FICHA inicial. Comparação contextualizada (bridge legacy ≠ ERP horizontal) seguindo recomendação ADR 0153/0154 N/A justificado.
