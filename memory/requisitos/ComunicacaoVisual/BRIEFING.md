---
id: requisitos-comunicacao-visual-briefing
---

# BRIEFING — ComunicacaoVisual

ERP vertical para gráfica rápida / comunicação visual BR (CNAE 1813-0/01: lona, banner, adesivo, fachada, plotter). **O que de fato roda hoje:** uma calculadora de orçamento por m² — única tela Inertia entregue (`Index.tsx`, hub + calculadora) — apoiada por uma API JSON authoritative server-side (`POST /comunicacao-visual/api/calcular` + persistência em `/orcamentos`) e por uma API de apontamento de produção (spool plotter: iniciar/finalizar/cancelar/em-andamento, com duração e drift m² produzido × orçado). O cálculo de preço (área × preço/m², resolução override→catálogo→erro), o multi-tenant Tier 0 e a observabilidade (spans OTel + PII redaction) são reais e cobertos por ~19 suítes Pest. O resto do escopo (PCP/FSM, materiais, instalação, NFe) é schema e contrato, não fluxo navegável.

**Estado:** **parcial** — backend de 2 capacidades (orçamento + apontamento) + 1 tela; restante planejado. Zero cliente em produção (piloto Q3/2026, sem sinal qualificado — ADR 0105).

> 📐 **Contrato desta capacidade:** [`SDD-tela-orcamento-m2-v1.0.md`](SDD-tela-orcamento-m2-v1.0.md) (§5 fluxos F1-F6 · §6 10 CU) +
> [`Index.casos.md`](../../../resources/js/Pages/ComunicacaoVisual/Index.casos.md) (12 UC).
> Estado derivado, não escrito à mão: `node scripts/governance/requisitos-status.mjs ComunicacaoVisual`.

**Capacidades REAIS (existem no código):**
- **Calculadora de orçamento por m²** (`Index.tsx`): preview client-side + botão "Conferir no servidor". Salvar/PDF/WhatsApp ainda **não** existe (UI diz "em breve"). ⚠️ **Correção 2026-07-28:** a frase *"o seletor de material puxa o catálogo do business"* (abaixo e no docblock do `.tsx`) **é promessa, não fato** — a única rota que renderiza a tela passa só `bizName`, então o seletor fica desabilitado ("Sem catálogo") e o preço/m² é digitado à mão. [SDD §5.4.1](SDD-tela-orcamento-m2-v1.0.md) / `CU-CV-09`.
- **API de orçamento authoritative** (`OrcamentoController` + `OrcamentoCalculator`): `calcular` (preview), `store` (persiste, numeração `ORC-YYYY-NNNNN` por business), `show`. Frontend só consome `calcular`.
- **API de apontamento de produção** (`ApontamentoController` + `ApontamentoTracker`): iniciar/finalizar/cancelar/listar; 1 spool ativo por operador, drift/duração server-side, append-only. **Sem UI** — só endpoints.
- **Multi-tenant Tier 0**: `business_id` global scope em todas as 10 entities. ⚠️ **Correção 2026-07-28:** *"`Tier0GuardTest` cross-tenant verde"* era leitura otimista — o arquivo **aborta inteiro na lane sqlite do PR** (`markTestSkipped`), junto com `MultiTenantTest`, `OrcamentoControllerTest`, `MaterialSeederTest`, `MigrationsTest` e `CustomerJourneyTest` (**6 de 21**). O verde do PR prova que foram *pulados*; o veredito real vem da full-suite noturna (MySQL). [SDD §9 D-7](SDD-tela-orcamento-m2-v1.0.md).
- **Observabilidade + LGPD**: spans OTel nos Services, `PiiRedactor` em observações, `retention.php`, `comvis:health`.
- **Hub sidebar** single-entry `/comunicacao-visual` (dropdown legacy 404 removido em 2026-05-26).

**PLANEJADAS (só SPEC/schema, NÃO construídas):**
- **PCP gráfico / FSM**: entities (`OrdemProducao`, `Instalacao`, `Substrato`, `Acabamento`, `InstalacaoCatalogo`) + migrations `cv_*` existem, mas **sem controller, rota ou tela** — schema órfão.
- **CRUD de Materiais**: seed existe (`MaterialSeeder`); sem tela de gestão.
- Telas próprias de PCP/materiais/apontamento (US-COMVIS-005): TODO.
- **Integração NfeBrasil, dual-doc NFe55+NFSe56, IA Jana**: narrativa de roadmap, **sem código** fiscal/IA neste módulo.

**Dependências reais:** UltimatePOS core (auth, `business_id`, permissões `comvis.*`, install 1-click, sidebar); `Modules\Jana\Services\Privacy\PiiRedactor`; `App\Util\OtelHelper`. FSM canon (`app/Domain/Fsm/`) referenciado no schema mas **não consumido** em runtime.

**SPEC:** [SPEC.md](SPEC.md)

---
**Tipo:** BRIEFING destilado (KL-E3). **Estado:** parcial. **Fonte:** código real `Modules/ComunicacaoVisual/` + `Pages/ComunicacaoVisual/Index.tsx`, verificado 2026-06-15; **redestilação parcial 2026-07-28** (chip da Onda 4 do passo 5) corrigiu 2 afirmações otimistas — o catálogo na tela e o "cross-tenant verde".

> ℹ️ **Por que esta porta segue SEM `distilled_at:`** — decisão técnica, com a fonte medida:
> `measureDistillerFreshness` ([`sdd-scorecard.mjs`](../../../scripts/governance/sdd-scorecard.mjs))
> **não penaliza** porta sem carimbo (`sem_carimbo` = 67 de 78 hoje; só `stamped && stale` conta).
> Carimbar põe o módulo no conjunto *stale-able* de uma métrica **ARMADA** (GT-G3) para sempre:
> qualquer doc do módulo tocado >7 dias depois, por qualquer pessoa, vira `stale +1`. Como este é
> um módulo **em construção sem piloto** (docs mexem em rajada e param), o custo esperado recai
> sobre terceiros e o ganho é nulo. Reavaliar quando houver cutover e cadência de destilação real.
