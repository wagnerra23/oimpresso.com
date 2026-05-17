# BRIEFING — `NfeBrasil`

> **Tipo:** BRIEFING canônico do módulo — 1 página executiva atualizada por PR mergeado relevante
> **Refs:** [proibicoes.md §Sempre fazer](../../proibicoes.md) — regra Tier 0 "BRIEFING.md atualizado em todo PR mergeado"
> **Skill auto-trigger:** `brief-update` (Tier B)
> **Origem deste arquivo:** Wave M boost auditoria 2026-05-16 (NfeBrasil 71→meta 82 — gap D3.b sem BRIEFING)

---

## 1. O que é

**URL principal:** `https://oimpresso.com/nfe-brasil`
**Backend:** `Modules/NfeBrasil/`
**Frontend:** `resources/js/Pages/NfeBrasil/`

Emissor fiscal brasileiro completo: NFC-e (modelo 65 B2C), NF-e (modelo 55 B2B), NFS-e, cancelamento, carta de correção, manifestação destinatário, contingência, motor tributário (ICMS/ICMS-ST/MVA/DIFAL/FCP/IPI/PIS/COFINS) e SPED Fiscal. Compliance crítico: sem emissão fiscal, tenant não pode operar varejo BR (CF/88 art. 195 §3º — guarda XML 5 anos).

## 2. Estado consolidado (2026-05-16)

| Dimensão | % | Última medição |
|---|---|---|
| Operacional PME (P0+P1 core) | ~75% | 2026-05-16 |
| Capterra score vs top-mercado | 71/100 | 2026-05-13 (ref [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md)) |
| Diferencial competitivo | ~70% | 2026-05-16 |
| Cobertura SPEC formal (done/spec'ado) | ~60% | 2026-05-13 |
| Documentação canon (SPEC + AUDIT + CAPTERRA + BRIEFING) | 100% | 2026-05-16 |
| Deploy/ops (prod biz=1 + biz=4) | ✅ ativo | 2026-05-12 (FSM Pipeline LIVE) |

## 3. Capacidades hoje

- **Emissão**: NFC-e modelo 65 + NF-e modelo 55 via `sped-nfe` (eduardokum) — SEFAZ-SP homolog + prod
- **Configuração**: Certificado A1 upload encrypted + toggle ambiente (1 prod / 2 homolog) + painel fiscal consolidado
- **Manifestação destinatário**: cienciar/confirmar/desconhecer/não-realizada (eventos 210/220) + sync NSU + bulk-confirm
- **Tributação**: motor cascata defaults business → regra NCM → regra UF (CSOSN/CST/ICMS/PIS/COFINS) — importação CSV NCM
- **Cancelamento**: até 24h NFC-e / 168h NF-e — orquestrado por ADR 0143 FSM `CancelarVendaCascade` (NFe SEFAZ + Asaas/Inter refund + Whatsapp/email cliente)
- **Carta de Correção (CCe)**: correção não-monetária
- **Status real-time**: badge useNfceStatus polling 2s pós-venda (preparado pra broadcast Centrifugo CT 100)

## 4. Diferenciais únicos

1. **FSM Cancel Cascade** — único módulo no mercado que cancela NFe + refund gateway + notificação cliente em 1 ação atômica (ADR 0143 LIVE biz=1)
2. **Painel fiscal consolidado** — razão social / regime / NCM padrão / série / CFOP/CSOSN/CST em 1 tela (cert+config unificados — concorrentes separam em 3-4 abas)
3. **Manifestação Cockpit V2 list+detail** — atalhos J/K/C/D/R, bulk-confirm batch, KPIs prazo legal (180d SEFAZ) — referência Linear Inbox densidade alta
4. **Storage cert encrypted Tier 0** — `storage/app/nfe-certs/{business_id}/` (multi-tenant ADR 0093) — concorrentes BSP frequentemente compartilham vault
5. **Motor tributário cascata** — defaults business → regra NCM → regra UF (4 níveis) — vs concorrentes que param em 2 níveis

## 5. Gaps remanescentes (próxima onda)

| # | PR alvo | Esforço IA-pair | Score impact |
|---|---|---|---|
| 1 | Contingência EPEC + FS-DA (Fase 4 plan original) | 3-4d | +4pp |
| 2 | MDF-e + CT-e (Fase 6 logística) | 1 semana | +3pp |
| 3 | SPED Fiscal/EFD ICMS-IPI mensal (Fase 7) | 1 semana | +3pp |
| 4 | Charters faltantes 6/10 telas (D3.c boundary 30%→70%) | 1-2d | +2pp |
| 5 | Activity log mutações (US-NFE-062 P1) | 4h | +1pp |

## 6. Bloqueadores manuais Wagner

- Aprovação canary prod biz=1 → biz=4 (ROTA LIVRE) pra cada novo evento SEFAZ
- Re-emissão cert A1 anual (manual SEFAZ + upload)
- Aprovar Non-Goals + Anti-hooks de cada novo charter
- Decisão pricing tier (Starter R$99 / Pro R$299 / Enterprise R$599)

## 7. ROI defendido vs concorrentes

| Concorrente | Como ganhamos | Como perdemos |
|---|---|---|
| Bling/Tiny NFe | ERP nativo integrado (não plugin), FSM cascade, multi-tenant Tier 0 | Onboarding fiscal (eles têm contador-by-default) |
| Tecnospeed/Migrate (BSP) | Subscription previsível R$99-599, sem por-doc | API B2B robusta deles, multi-cliente revenda |
| Nuvem Fiscal | Cancel cascade automático, manifestação Cockpit V2 | Volume CT-e/MDF-e (Fase 6 pendente) |
| Focus NFe / WebmaniaBR | Stack moderna React 19 + Inertia v3, charter governance | API legada deles tem catálogo |

## 8. Risks ativos

- 🟡 **Reforma Tributária 2026-2033** — schema CBS/IBS preparado mas evolução incerta (CONFAZ ajusta layout LayoutVersion 4.x semestralmente)
- 🟡 **Cert A1 vencimento sem alerta auto** — UI mostra badge ≤30d mas não envia email/WA (mitigação: charter Certificado expansion)
- 🔴 **Contingência ausente** — se SEFAZ-SP cai, biz=1+biz=4 PARAM venda (mitigação Fase 4 EPEC+FS-DA prioritária)
- 🟡 **Boundary D3.c charters 30%** — 7/10 telas críticas sem `*.charter.md` (mitigação: este Wave M cria 2-3)

## 9. Métricas-chave (last 7d — biz=1 prod)

- Emissão NFC-e: ~150/dia médio biz=1
- Manifestação DFes pendentes: ~12 abertas
- Cert A1 status: 6m até vencimento (alerta amber ≤30d ativo)
- Cancelamentos cascade (FSM): 3 desde 2026-05-12 (100% sucesso end-to-end)
- Rejeição SEFAZ avg: <2% (cStat 100 = autorizado)

## 10. Cliente piloto / canary

- **Atual:** `biz=1` (matriz oimpresso) — emite ativamente desde 2026-04-24
- **Próximo canary:** `biz=4` (ROTA LIVRE Modules/Vestuario) — já emitindo NFC-e SP Simples Nacional CSOSN 102
- **Próximos avaliados:** candidatos ComunicacaoVisual (6 ex-OfficeImpresso) quando Modules/ComunicacaoVisual maduro

## 11. ADRs centrais do módulo

- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL (cert A1 por business_id)
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (SoC + transparência)
- [ADR 0116](../../decisions/0116-caso-gold-manifestacao.md) — caso Gold → manifestação destinatário
- [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM Pipeline LIVE prod biz=1 (`CancelarVendaCascade` orquestra cancel NFe + refund + notif)
- [ADR 0029](../../decisions/0029-inertia-upos.md) — Inertia + UltimatePOS (stack base)
- ADRs satélites: `Modules/NfeBrasil/adr/arq/0003` (cert storage encrypted), `arq/0006` (cascade defaults NCM)

## 12. Sessões e handoffs relevantes (últimos 30d)

- 2026-05-13 CAPTERRA-FICHA score 71/100 + 15-20 capacidades P0-P3
- 2026-05-12 ADR 0143 FSM Pipeline LIVE — `CancelarVendaCascade` ativo biz=1
- 2026-05-10 US-NFE-061 charter-write em Certificado + Manifestacao + Tributacao
- 2026-05-09 manifestacao-visual-comparison.md approved (15 dimensões)

---

## 13. Último update

**Atualizado:** 2026-05-16 BRT pelo Wave 18 saturation (D1/D2 NfeEvento cross-tenant + D7 LogsActivity NfeEvento)
**Próximo update esperado:** quando próximo PR relevante mergear (auto-trigger `brief-update` skill)
**Mantenedor:** Claude (auto) + Wagner (review)

### Wave 18 deltas (2026-05-16)
- D7: `NfeEvento` ganha `LogsActivity` — accountability LGPD Art. 37 (loga tipo/status/cstat_evento sem payload_json completo)
- D1/D2: novo Pest `NfeEventoMultiTenantIsolationTest.php` (5 testes — scope herdado + append-only + count cross-tenant)
- CHANGELOG.md criado pelo módulo (rastreio observable de wave a wave)
