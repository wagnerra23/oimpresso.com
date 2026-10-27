---
id: requisitos-comunicacao-visual-briefing
module: ComunicacaoVisual
status: parcial
updated_at: "2026-10-26"
---

# BRIEFING — ComunicacaoVisual

ERP vertical para gráfica rápida / comunicação visual BR (CNAE 1813-0/01: lona, banner, adesivo, fachada, plotter). **O que de fato roda hoje:** uma calculadora de orçamento por m² — única tela Inertia entregue (`Index.tsx`, hub + calculadora) — apoiada por uma API JSON authoritative server-side (`POST /comunicacao-visual/api/calcular` + persistência em `/orcamentos`) e por uma API de apontamento de produção (spool plotter: iniciar/finalizar/cancelar/em-andamento, com duração e drift m² produzido × orçado). O cálculo de preço (área × preço/m², resolução override→catálogo→erro), o multi-tenant Tier 0 e a observabilidade (spans OTel + PII redaction) são reais e cobertos por ~19 suítes Pest. A agenda da instalação também roda (tela semana × equipe, bloqueio de conflito, aviso ao cliente por WhatsApp). A produção da OS da gráfica mora no `Modules/Producao` (quadro, romaneio, simulador de bobina, apontamento por QR), que lê `comvis_os`. Cadastro de materiais e acabamentos, NF-e/NFS-e e PDF/WhatsApp do orçamento ainda não existem.

**Estado:** **parcial** — orçamento (calculadora, salvar, aprovar e virar OS), agenda da instalação e apontamento; 2 telas próprias (`Index`, `Agenda`) + a produção no `Modules/Producao`. Zero cliente em produção (piloto Q3/2026, sem sinal qualificado — ADR 0105).

> 📐 **Contrato desta capacidade:** [`SDD-tela-orcamento-m2-v1.0.md`](SDD-tela-orcamento-m2-v1.0.md) (§5 fluxos F1-F6 · §6 10 CU) +
> [`Index.casos.md`](../../../resources/js/Pages/ComunicacaoVisual/Index.casos.md) (12 UC).
> Estado derivado, não escrito à mão: `node scripts/governance/requisitos-status.mjs ComunicacaoVisual`.

**Capacidades REAIS (existem no código):**
- **Calculadora de orçamento por m²** (`Index.tsx`): preview client-side + "Conferir no servidor"; o seletor de material recebe o catálogo do business desde 2026-09-23. **Desde 2026-10-21 (PRs #9722 · #9724 · #9726 · onda D):** salvar e reabrir (`?orcamento={id}`, lista dos salvos), "Aprovar e criar OS" (UC-FLX-01), anexar arquivo antes da OS, e custo/margem por peça ao conferir para quem gerencia materiais (UC-FLX-03). PDF/WhatsApp ainda não. _(Registro de 2026-07-28: a tela passava só `bizName` e o seletor ficava em "Sem catálogo".)_
- **API de orçamento authoritative** (`OrcamentoController` + `OrcamentoCalculator`): `calcular` (preview; com custo/margem via `MargemDoOrcamento` para quem vê custo), `index`/`store`/`show`/`update` (numeração `ORC-YYYY-NNNNN` por business), `aprovar` (vira OS) e arquivos (`OrcamentoArquivoController`). A tela consome todos.
- **API de apontamento de produção** (`ApontamentoController` + `ApontamentoTracker`): iniciar/finalizar/cancelar/listar; 1 spool ativo por operador, drift/duração server-side, append-only. **Sem UI** — só endpoints.
- **Multi-tenant Tier 0**: `business_id` global scope em todas as 10 entities. ⚠️ **Correção 2026-07-28:** *"`Tier0GuardTest` cross-tenant verde"* era leitura otimista — o arquivo **aborta inteiro na lane sqlite do PR** (`markTestSkipped`), junto com `MultiTenantTest`, `OrcamentoControllerTest`, `MaterialSeederTest`, `MigrationsTest` e `CustomerJourneyTest` (**6 de 21**). O verde do PR prova que foram *pulados*; o veredito real vem da full-suite noturna (MySQL). [SDD §9 D-7](SDD-tela-orcamento-m2-v1.0.md).
- **Observabilidade + LGPD**: spans OTel nos Services, `PiiRedactor` em observações, `retention.php`, `comvis:health`.
- **Agenda da instalação** (US-COMVIS-007, UC-FLX-21): agendar equipe e veículo na OS com bloqueio de conflito (`AgendaInstalacao` + `InstalacaoController`), tela semana × equipe (`Agenda.tsx` + `AgendaController`) e aviso ao cliente por WhatsApp com opt-in LGPD (evento `InstalacaoAgendada`, listener no `Modules/Whatsapp`). Faltam arrastar na grade, checklist de EPI, foto/comprovante e comissão.
- **Produção da OS no `Modules/Producao`** (ADR 0431/0436): o quadro `/producao/quadro` (`QuadroDaProducao`), o romaneio de impressão, o simulador de bobina e o apontamento por QR (`AppApontamentoController`) já tratam `comvis_os`. ⚠️ **Lacuna medida em 2026-10-26:** a OS que nasce do orçamento aprovado (`OrcamentoViraOs`) **não ganha etapas em `pcp_programacoes`**, e o quadro só mostra OS com etapa aberta — então ela não aparece lá. Falta decidir com que roteiro ela é programada (pergunta aberta ao [W]).
- **Hub sidebar** single-entry `/comunicacao-visual` (dropdown legacy 404 removido em 2026-05-26).

**PLANEJADAS (só SPEC/schema, NÃO construídas):**
- **Entities sem fluxo:** `OrdemProducao` (`cv_ordens_producao`, só testes a criam — a OS por item da venda é a `pcp_ordens` do Producao, ADR 0436), `Substrato` e `InstalacaoCatalogo` seguem sem controller nem tela. `Acabamento` passa a entrar no preço quando o PR #9852 for mergeado (mínimo por peça + acabamentos no cálculo, US-COMVIS-001).
- **Cadastro de materiais e de acabamentos**: seed existe (`MaterialSeeder`); sem tela de gestão de nenhum dos dois.
- Telas próprias de PCP/materiais/apontamento (US-COMVIS-005): TODO.
- **NF-e da peça + NFS-e da instalação** (US-COMVIS-008/009, NEW-003): os módulos fiscais existem no núcleo, mas nada liga a OS da gráfica a eles. Custo real da OS (pós-cálculo, US-COMVIS-005): desde 2026-10-27 a OS da CV tem a leitura JSON orçado × real e as duas margens (`PosCalculoDaOs`, flag `COMVIS_POS_CALCULO_ENABLED` desligada); faltam terceiros e a tela.
- **PDF/WhatsApp do orçamento com aceite pelo link** e **aprovação de arte da OS da CV** (a aprovação de arte por link existe no Producao para `pcp_ordens`, UC-FLX-02).

**Dependências reais:** UltimatePOS core (auth, `business_id`, permissões `comvis.*`, install 1-click, sidebar); `Modules/Producao` (produção da OS); `Modules/Whatsapp` (aviso da instalação); `App\Support\Privacy\PiiRedactor`; `App\Util\OtelHelper`. FSM canon (`app/Domain/Fsm/`) referenciado no schema mas **não consumido** em runtime.

**SPEC:** [SPEC.md](SPEC.md)

---
**Tipo:** BRIEFING destilado (KL-E3). **Estado:** parcial. **Fonte:** código real `Modules/ComunicacaoVisual/` + `Pages/ComunicacaoVisual/Index.tsx`, verificado 2026-06-15; **redestilação parcial 2026-07-28** e **2026-10-26** (entraram a agenda da instalação e a produção no Producao; saiu "instalação planejada") (chip da Onda 4 do passo 5) corrigiu 2 afirmações otimistas — o catálogo na tela e o "cross-tenant verde".

> ℹ️ **Por que esta porta segue SEM `distilled_at:`** — decisão técnica, com a fonte medida:
> `measureDistillerFreshness` ([`sdd-scorecard.mjs`](../../../scripts/governance/sdd-scorecard.mjs))
> **não penaliza** porta sem carimbo (`sem_carimbo` = 67 de 78 hoje; só `stamped && stale` conta).
> Carimbar põe o módulo no conjunto *stale-able* de uma métrica **ARMADA** (GT-G3) para sempre:
> qualquer doc do módulo tocado >7 dias depois, por qualquer pessoa, vira `stale +1`. Como este é
> um módulo **em construção sem piloto** (docs mexem em rajada e param), o custo esperado recai
> sobre terceiros e o ganho é nulo. Reavaliar quando houver cutover e cadência de destilação real.
