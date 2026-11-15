---
id: requisitos-comunicacao-visual-briefing
module: ComunicacaoVisual
status: parcial
updated_at: "2026-11-11"
---

# BRIEFING — ComunicacaoVisual

ERP vertical para gráfica rápida / comunicação visual BR (CNAE 1813-0/01: lona, banner, adesivo, fachada, plotter). **O que roda hoje:** o orçamento por m² com o servidor como fonte de verdade, do cálculo até a OS. A operadora calcula, salva, anexa a arte e manda o link ao cliente; o cliente aceita pelo celular, sem login; a OS nasce com as peças e o total do orçamento e entra no quadro do PCP do `Modules/Producao`; a instalação é agendada com aviso ao cliente por WhatsApp; o pós-cálculo compara orçado com realizado. **Faturamento não existe:** nenhum código liga a OS a venda, NF-e ou NFS-e. O fluxo passo a passo está no [SDD §5.5](SDD-tela-orcamento-m2-v1.0.md).

**Estado:** **parcial** — 3 telas próprias (`Index`, `Agenda`, `AceitePublico`), mais a produção no `Modules/Producao`. Nenhum piloto registrado: a previsão de Q3/2026 passou, e a nova data é decisão [W] (ADR 0105).

> 📐 **Contrato desta capacidade:** [`SDD-tela-orcamento-m2-v1.0.md`](SDD-tela-orcamento-m2-v1.0.md) (fluxo em §5.5, CU em §6) +
> os `.casos.md` das 3 telas em `resources/js/Pages/ComunicacaoVisual/`.
> Números (US por estado, casos por veredito, arquivos de teste) não se repetem aqui: `node scripts/governance/requisitos-status.mjs ComunicacaoVisual`, `npm run casos:report` e [`SUPERFICIE.md`](SUPERFICIE.md).

**Capacidades REAIS (existem no código):**
- **Calculadora de orçamento por m²** (`Index.tsx`): catálogo do business no seletor de material (desde 2026-09-24, #7870), mínimo por peça, perda, emenda e acabamentos no cálculo do servidor (#9852, 2026-10-26), salvar e reabrir, anexar arquivo, custo e margem por peça para quem gerencia materiais.
- **Mandar ao cliente** (US-COMVIS-023, #9860): "Enviar no WhatsApp" gera o link de aceite (7 dias, uma resposta) e abre o WhatsApp do próprio vendedor com a mensagem pronta. O sistema não envia nada. PDF do orçamento não existe.
- **Aceite pelo link** (`AceitePublico.tsx`): o cliente vê itens e totais gravados, sem custo nem margem, e aceita ou pede ajuste. Aceitar cria a OS; ajuste volta o orçamento a rascunho com o comentário.
- **OS sem redigitar** (US-COMVIS-020): por "Aprovar e criar OS" ou pelo aceite do cliente, `OrcamentoViraOs` cria `comvis_os` com itens, arquivos e o total do orçamento, sem recalcular.
- **OS no quadro do PCP** (US-COMVIS-003, #9885, 2026-10-26): com o roteiro padrão da empresa escolhido no hub, a OS ganha as etapas em `pcp_programacoes` e aparece em `/producao/quadro`. Sem roteiro padrão, ou sem o `Modules/Producao` ligado, nasce fora do quadro. Trocar o roteiro de uma OS à mão ainda não existe.
- **Produção no `Modules/Producao`** (ADR 0431; ADR 0436 ainda `proposto`): quadro, romaneio, simulador de bobina e apontamento por QR tratam `comvis_os`. O operador informa o m² impresso ao concluir a etapa.
- **Agenda da instalação** (US-COMVIS-007, #9721): tela semana × equipe, agendar equipe e veículo na OS com bloqueio de conflito, e aviso ao cliente por WhatsApp ao agendar (#9723, listener no `Modules/Whatsapp`). Faltam arrastar na grade, checklist de EPI, foto/comprovante e comissão.
- **Pós-cálculo da OS** (US-COMVIS-005, #9994, 2026-10-28): custo orçado × real e as duas margens, em JSON, para quem vê custo. Atrás de `COMVIS_POS_CALCULO_ENABLED`, desligada por padrão. Sem tela.
- **API de apontamento do módulo** (spool de plotter, US-COMVIS-004): iniciar/finalizar/cancelar com drift de m². **Sem tela**, e o pós-cálculo não a usa.
- **Orçamento rápido do app das lojas** (`/api/app/orcamentos`): grava pelo mesmo `OrcamentoGravador` da web.
- **Multi-tenant Tier 0**: toda Entity com escopo de `business_id`; os serviços que rodam fora de sessão (link público, app, fila) passam a empresa explícita. Em que lane cada teste roda: `node scripts/governance/test-lane-coverage.mjs`.

**Costuras abertas (decisões do [W], descritas como estão):**
- **Faturamento:** nada liga `comvis_os` a venda, NF-e ou NFS-e (US-COMVIS-008/009 pendentes).
- **Etapa da OS:** `status_etapa` nasce `arte` e nenhum código a avança; qual OS é a canônica (`comvis_os` ou `cv_ordens_producao`, nunca gravada) está aberto.
- **Aviso de instalação:** opt-out por contato (`null` permite); o charter do módulo promete opt-in por OS.

**PLANEJADAS (só SPEC/schema, NÃO construídas):**
- **Cadastro de materiais e de acabamentos** (US-COMVIS-002): seed existe (`MaterialSeeder`); sem tela de gestão.
- **Entities sem fluxo:** `Substrato`, `InstalacaoCatalogo` e `OrdemProducao` (`cv_ordens_producao`).
- **Aprovação de arte da OS da CV** (US-COMVIS-NEW-004): o link de prova existe no Producao para `pcp_ordens` (UC-FLX-02), não para `comvis_os`.
- Comissão por OS, DAM, CT-e/MDF-e, Jana vertical, loja whitelabel, importador do legado.

**Dependências reais:** UltimatePOS core (auth, `business_id`, permissões `comvis.*`, install 1-click, sidebar); `Modules/Producao` pelos contratos `App\Contracts\Producao\*` (roteiro, custo da hora, custo real); `Modules/Whatsapp` (aviso da instalação); `App\Support\Privacy\PiiRedactor`; `App\Util\OtelHelper`. FSM canon (`app/Domain/Fsm/`) referenciado no schema e num seeder, **não consumido** em runtime.

**SPEC:** [SPEC.md](SPEC.md) · **PII/LGPD:** [PII-LGPD.md](PII-LGPD.md)

---
**Tipo:** BRIEFING destilado (KL-E3). **Estado:** parcial. **Fonte:** código real `Modules/ComunicacaoVisual/` + `resources/js/Pages/ComunicacaoVisual/`, verificado 2026-06-15; redestilações parciais em 2026-07-28 e 2026-10-26; **revisão de 2026-11-11** (auditoria de 2026-11-05, eixo 3) tirou 5 afirmações que já eram falsas: OS fora do quadro do PCP, acabamento à espera do #9852, "2 telas", aceite pelo link como planejado e "telas de PCP" atribuídas à US-COMVIS-005.

> ℹ️ **Por que esta porta segue SEM `distilled_at:`** — decisão técnica, com a fonte medida:
> `measureDistillerFreshness` ([`sdd-scorecard.mjs`](../../../scripts/governance/sdd-scorecard.mjs))
> **não penaliza** porta sem carimbo (só `stamped && stale` conta).
> Carimbar põe o módulo no conjunto *stale-able* de uma métrica **ARMADA** (GT-G3) para sempre:
> qualquer doc do módulo tocado >7 dias depois, por qualquer pessoa, vira `stale +1`. Como este é
> um módulo **em construção sem piloto** (docs mexem em rajada e param), o custo esperado recai
> sobre terceiros e o ganho é nulo. Reavaliar quando houver cutover e cadência de destilação real.
