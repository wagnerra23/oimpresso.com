# CAPTERRA-INVENTÁRIO — Fiscal

> Gerado por skill `comparativo-do-modulo` (`/comparativo Fiscal`) em **2026-07-03**.
> Fontes: [`CAPTERRA-FICHA.md`](CAPTERRA-FICHA.md) (21 capacidades) + [`SPEC.md`](SPEC.md) (US-FISCAL-001..021) + [`AUDIT-SENIOR-2026-05-25.md`](AUDIT-SENIOR-2026-05-25.md) + `Modules/Fiscal/` + `Modules/NfeBrasil/Services/{MotorTributario,Tributacao,Manifestacao}` + MCP `tasks-list module:Fiscal`.
> ADR de governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md). Programa de Ondas Passo 2.
> Base code-verified: `origin/main` (Fiscal module maduro pós-Waves 1-9).

## Resumo

| Bucket | Quantidade | % |
|---|---|---|
| ✅ APROVADO | 12 | 57% |
| 🟡 PARCIAL | 4 | 19% |
| ❌ AUSENTE | 5 | 24% |
| **Total** | 21 | 100% |

**Por score:**

| Score | ✅ | 🟡 | ❌ | Total |
|---|---|---|---|---|
| **P0** (bloqueador) | 7 | 0 | 1 | 8 |
| **P1** | 2 | 4 | 1 | 7 |
| **P2** | 2 | 0 | 2 | 4 |
| **P3** | 1 | 0 | 1 | 2 |

**Diagnóstico em 1 linha:** módulo **MADURO** (57% aprovado — inverso do NfeBrasil que era scaffold) — cockpit + config + DF-e + eventos + multi-tenant sólidos, com diferenciais que o mercado não tem (FSM cascade, cSit warning, ⌘K, Jana sugere). **Único P0 vivo = IBS/CBS cálculo** (schema pronto, motor sem lógica). Gaps concentrados em **obrigações acessórias** (SPED completo, EFD-Contribuições) + **refinamento do motor** (ICMS-ST/DIFAL) + **automações** (health-check cert, cache).

## Inventário detalhado

| # | Capacidade | Score | Status | Evidência | Falta |
|---|---|---|---|---|---|
| 1 | Motor tributário automático (cascade NCM/CFOP/UF/regime) | P0 | ✅ | `MotorTributarioService` cascade-4 + OTel + memoization; `SpedMotorTributarioIntegrationTest` | — |
| 2 | Regras ICMS configuráveis + import CSV/templates | P0 | ✅ | `TributacaoController` + `Tributacao/{TributacaoTemplate,ImportRegrasCsv}Service`; `nfe_fiscal_rules` + `tax_rate_links` | — |
| 3 | Distribuição DF-e automática + Manifestação (4 ações) | P0 | ✅ | `DistribuicaoDfeService` + `BuscarDfesRecebidosJob` + `ManifestacaoService`; `DfeController` (ADR 0116) | — |
| 4 | Eventos fiscais (CC-e / Cancelamento / Inutilização / Retransmissão) | P0 | ✅ | `AcoesController` → NfeBrasil Services; preservation contract CONFAZ Art. 14 | — |
| 5 | Config certificado A1 + regime + tributação default | P0 | ✅ | `ConfigController` (RO) + `TributacaoController` (edit) | — |
| 6 | Multi-tenant isolamento cert/emissões por `business_id` (Tier 0) | P0 | ✅ | HasBusinessScope + guard Service; `{NfeCockpit,Cockpit,Eventos}MultiTenantTest` | — |
| 7 | **Reforma Tributária IBS/CBS — cálculo/preenchimento** | P0 | ❌ | schema scaffold (colunas `cClassTrib`/`cst_ibs`/`cst_cbs`/`aliquota_ibs`/`aliquota_cbs`); `OndaIbsCbsScaffoldTest` — **0 lógica de cálculo** no motor | Cálculo IBS/CBS no `MotorTributarioService` + preenche grupo UB no XML + valida NT 2025.002 |
| 8 | Cockpit fiscal unificado (KPIs + alertas + sparklines) | P0 | ✅ | `CockpitController`; cache Redis 60s ✅ **US-FISCAL-019** (GAP-FISCAL-002 fechado) | — |
| 9 | ICMS-ST / DIFAL / FCP cálculo automático | P1 | 🟡 | `TributoCalculado` tem campo `mva`; cascade não computa ST/DIFAL/partilha completos | ST/DIFAL/FCP interestadual contribuinte (CFOP 6102) |
| 10 | ISS / NFS-e config (alíquota por município) | P1 | 🟡 | `NfseCockpitController` lê `NfseEmissao`; config municipal em `Modules/NFSe` parcial | adapters de alíquota por município |
| 11 | SPED EFD-ICMS/IPI (geração TXT PVA-validável) | P1 | 🟡 | `SpedIcmsIpiGeneratorService` 23 registros (Blocos 0+C+E + H esqueleto) | Bloco H com inventário real + smoke PVA-EFD homologação |
| 12 | Eventos timeline auditável append-only | P1 | ✅ | `EventosController` (gate único audit) | — |
| 13 | Health-check certificado A1 (alerta vencimento) | P1 | 🟡 | Config exibe validade | cron proativo dias-a-vencer → `mcp_alertas` |
| 14 | Busca cross-fiscal / ⌘K palette | P1 | ✅ | `PaletteSearchController` + `CmdKPalette.tsx`; anti-DOS/índice ✅ **US-FISCAL-019** | — |
| 15 | Entrada via DF-e manifestada → escrituração (Bloco C inputs) | P1 | ❌ | DF-e recebidas não viram entradas | reconciliação fornecedor (Crm) + Bloco C0 inputs |
| 16 | SPED EFD-Contribuições (PIS/COFINS) | P2 | ❌ | não gerado | arquivo separado PIS/COFINS |
| 17 | MDF-e (Manifesto de Documento Fiscal) | P2 | ❌ | sem emissão | MdfeService + integração SEFAZ (mercado tem; piloto sem transporte) |
| 18 | Sugestão determinística por cStat rejeitado ("Jana sugere") | P2 | ✅ | mapa cstat → receita no `NotaDrawer.tsx` | — |
| 19 | FSM cancel cascade (estorno financeiro + notif cliente) | P2 | ✅ | `CancelarVendaCascade` (ADR 0143) — **diferencial único** | — |
| 20 | Webhook de eventos fiscais p/ sistemas externos | P3 | ❌ | sem callback | WebhookDispatcher + HMAC + retry |
| 21 | Aviso antecipado de `cSit` no cadastro cliente | P3 | ✅ | ADR 0186 — **diferencial único** | — |

## Cruzamento com backlog MCP existente (dedup — evitar duplicação)

| Gap desta auditoria | Task MCP já existente? | Ação |
|---|---|---|
| IBS/CBS **cálculo** (cap #7) | ❌ não existe (só scaffold US-FISCAL-021 já mergeado) | **CRIAR** (P0 — proposta #1) |
| Integrar Motor no SPED (GAP-FISCAL-003) | ✅ **US-FISCAL-020** (marcada "✅ Onda CONSOLIDAR" — código feito, `SpedMotorTributarioIntegrationTest`) | **NÃO duplicar** — sinalizar drift: MCP status `todo` mas código done → mover pra `done` |
| Habilitar biz=4 Larissa + canary (GAP-FISCAL-001) | ✅ **US-FISCAL-018** [p0] | **NÃO duplicar** — já no backlog |
| EFD-Contribuições PIS/COFINS (cap #16) | ✅ **US-FISCAL-011** (backlog PR #10) | **NÃO duplicar** |
| Cache KPIs cockpit + anti-DOS palette (caps #8/#14) | ✅ **US-FISCAL-019** (done, GAP-FISCAL-002 fechado) | **NÃO duplicar** (dedup inicial falhou — `tasks-list` só lista ativas) |
| ICMS-ST/DIFAL, health-check cert, entrada DF-e, MDF-e, webhook | ❌ não existem | **CRIAR** (propostas #2-#6) |

## Tasks propostas (aguardando aprovação Wagner)

> Módulo maduro → backlog **curto e cirúrgico** (7 novas tasks). Recomendação: aprovar **Onda 6 IBS/CBS (1 task P0)** primeiro (prazo regulatório 03/08/2026) + Onda de refinamento P1 depois. Não re-propõe o que já está no MCP (US-FISCAL-011/018/020).

### ✅ APROVADAS por Wagner 2026-07-03 (criadas no MCP)

1. **[P0] IBS/CBS cálculo no MotorTributarioService** → **US-FISCAL-021** (criada) — sair do scaffold: motor calcula `cClassTrib` + CST IBS/CBS + alíquotas IBS/CBS a partir das colunas já existentes em `nfe_fiscal_rules`; preenche grupo UB (IBSUF/IBSMun/CBS) no XML; valida NT 2025.002; dependência de `nfephp-org/sped-nfe` com IBS/CBS (hoje só `dev-master`, issue #1274). ~3-4 dev-days IA-pair. **GAP-FISCAL-004. Prazo produção obrigatória 03/08/2026.** (cap #7)
4. **[P1] Health-check certificado A1 (cron alerta vencimento)** → **US-FISCAL-022** (criada) — schedule diário dias-a-vencer ≤30 → `mcp_alertas`. ~0.5 dev-day. (cap #13)

> **~~5. Cache Redis KPIs cockpit~~ — DESCARTADA** (já entregue em US-FISCAL-019 ✅ done). US-FISCAL-023 gerada por engano, não persistida (dedup falhou: `tasks-list` default só lista ativas).

### 🕒 Não-criadas nesta rodada (próxima onda — aguardam OK [W] futuro)

2. **[P1] ICMS-ST / DIFAL / FCP no cascade tributário** — completar cálculo interestadual contribuinte (CFOP 6102 c/ ST); usa campo `mva` existente. Nota: US-FISCAL-020 já resolveu seleção CFOP interno/interestadual, falta o cálculo ST/DIFAL/FCP. ~2 dev-days. (cap #9)
3. **[P1] SPED Bloco H inventário real + smoke PVA-EFD homologação** — Bloco H com dados reais de Stock (declaração 31/12) + validar TXT no PVA-EFD CONFAZ. ~2 dev-days. (cap #11)
5. **[P1] Entrada via DF-e manifestada → Bloco C0 inputs** — DF-e recebidas viram entradas escrituráveis; depende de reconciliação cadastro fornecedor (`Modules/Crm`). ~2 dev-days. (cap #15)
6. **[P2] MDF-e (Manifesto de Documento Fiscal)** — emissão MDF-e + integração SEFAZ. **Defer** — piloto sem transporte próprio; mercado (middlewares) tem. ~1 semana. (cap #17)

### Backlog baixo (não propor agora)

- **[P3] Webhook de eventos fiscais (HMAC + retry)** — cap #20. Sem sinal de cliente (ADR 0105). Registrar como feature-wish.

---

## Histórico

- `2026-07-03` — Claude Code (`/comparativo Fiscal`) — **criação**. Programa de Ondas Passo 2. 21 capacidades classificadas (12✅/4🟡/5❌). Wagner OK [W] → **2 tasks criadas no MCP**: US-FISCAL-021 (P0 IBS/CBS) + US-FISCAL-022 (P1 health-check cert), apensas ao SPEC. 1 descartada (US-FISCAL-023 cache = duplicata de US-FISCAL-019 done — dedup falhou pq `tasks-list` só lista ativas; corrigido). 4 tasks seguram pra próxima onda (ICMS-ST/DIFAL, SPED Bloco H, entrada DF-e, MDF-e). Base `origin/main` fresca.
