---
title: "Plano consolidado 6 gaps pos-Wave 5 OficinaAuto (planejamento-only · realinhado ADR 0194)"
date: "2026-05-26"
type: coord-paralelo-plan
status: aprovado-realinhado-wagner-2026-05-26
modulo: OficinaAuto + tests/Browser
cliente: Martinho biz=164 (mecanica pesada caminhao basculante CNAE 4520)
gaps_planejados: 5 ativos + 1 abortado V2
realinhamento_2026_05_26: "Wagner pos-leitura ADR 0194 confirmou foco sub-vertical 4 mecanica pesada. Gap 4 SMS Twilio ABORTADO V2 (WhatsApp Wave 4 cobre 95%). Total 37h -> 29h IA-pair. Reordenacao: Gap 2 DVI prioridade #1 (semaforo motorista aprova R$ [redacted Tier 0]-80k peca hidraulica)."
docs_filhos:
  - 2026-05-26-plano-gap-1-upload-foto-laudo-drawer.md
  - 2026-05-26-plano-gap-2-dvi-vistoria-digital-ui.md
  - 2026-05-26-plano-gap-3-imprimir-os-pdf-profissional.md
  - 2026-05-26-plano-gap-4-sms-provider-out-of-band-pin.md
  - 2026-05-26-plano-gap-5-charter-edit-tsx.md
  - 2026-05-26-plano-gap-6-visual-regression-snapshots.md
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0106-recalibracao-velocidade-fator-10x-ia-pair
  - 0123-modules-arquivos-backbone
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0171-oficinaauto-ativacao-piloto-martinho-faseada
  - 0192-auto-faturar-os-venda-jobsheet-observer
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
owner: [W]
publication_policy: planejamento-only (zero git ops)
---

# Plano consolidado 6 gaps pos-Wave 5 OficinaAuto

## §1 — Resumo executivo

- Wave 5 OficinaAuto fechou hoje (2026-05-26) com 4 PRs mergeados (#1624 #1627 #1630 #1631) destravando fluxo Martinho-ready (cobranca real + drawer rico + WhatsApp PIN + DVI backend + UI items).
- **6 gaps catalogados** em §4 da sessao [2026-05-26-oficina-auto-4prs-martinho-ready-mergeados.md](2026-05-26-oficina-auto-4prs-martinho-ready-mergeados.md) viraram este plano consolidado.
- **REALINHAMENTO 2026-05-26 (pos-decisao Wagner):** [ADR 0194](memory/decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) confirma foco **sub-vertical 4 mecanica pesada caminhao basculante CNAE 4520** (NAO locacao cacamba sub-vertical 3 hipotetico). Reordenacao prioriza valor pro fluxo Martinho (peca hidraulica R$ [redacted Tier 0]-80k + ressarcimento transportadora).
- **Gap 4 SMS Twilio ABORTADO V2** — WhatsApp Wave 4 PR #1627 (Msg 1 link + Msg 2 PIN +60s delay in-band) cobre 95% do cenario piloto. Motorista basculante SEMPRE tem WhatsApp (sub-vertical "concessionaria-like"), atacante precisaria SIM-swap especifico no momento do envio = vetor improvavel. Custo Twilio R$ [redacted Tier 0]-5/mes + conta nova + 8h IA-pair = baixo ROI piloto. Review_trigger: incidente real PIN bypass OU volume 200+ OS/mes biz=164. Doc filho Gap 4 preservado como reference V2.
- **Total realinhado: 21-29h IA-pair + 6h Wagner smoke = 7 PRs em 4 etapas** (vs 37h/8PRs original — economia 8h Gap 4).
- **Wagner mandato: planejamento ONLY** — zero implementacao, zero commit, zero PR. Plano executavel amanha.
- **Descoberta critica Gap 1:** decisao storage S3/MinIO/Spaces revogada — `Modules/Arquivos` JA eh backbone canon (ADR 0123) com trait polimorfica `HasArquivos`. Storage abstraido. Gap reduz de "decisao arquitetural" pra "wire-up trait".
- **Sub-decomposicao Gap 2:** DVI UI eh o maior (~12h) E o de maior ROI (semaforo motorista aprova R$ [redacted Tier 0]-80k peca hidraulica antes do servico). Wedge competitivo vs Auto Manager/Tecnomotor/Plumelp/Sysmecanica (nenhum tem DVI nativo BR). Dividido em **3 PRs** (2a drawer-internal + 2b service+job+controller + 2c pagina publica).
- **Reinterpretacao Gap 6:** nao eh "update" snapshots — eh **baselining inicial** OficinaAuto (nunca houve snapshots desse modulo). Apenas Sells/Create tem teste browser hoje.

## §2 — Tabela mestre realinhada (5 ativos + 1 abortado)

| # | Gap | Esforco IA-pair | ROI mecanica pesada | Bloqueia demo? | Depende de | PRs |
|---|------|---:|-----|----------------|------------|----:|
| 1º | **Gap 2** DVI Vistoria Digital UI semaforo | 9-12h | **Alto — wedge competitivo + motorista aprova R$ [redacted Tier 0]-80k peca hidraulica via semaforo verde/amarelo/vermelho** | Nao | recomenda Gap 1 antes | **3** (2a/2b/2c) |
| 2º | **Gap 1** Upload foto/laudo real drawer | 4-6h | **Alto — mecanica pesada VIVE de foto: peca quebrada antes/depois pra ressarcir transportadora 3a/seguradora; 30+ fotos/caminhao (AutoVitals)** | Nao | nada | 1 |
| 3º | **Gap 3** Imprimir OS PDF profissional A4 | 4-5h | **Alto — motorista basculante leva papel assinado pra transportadora ressarcir; concessionaria Volvo entrega sempre papel+assinatura; compliance fiscal-comercial nao polish** | Nao | nada | 1 |
| 4º | **Gap 5** Charter Edit.tsx (ServiceOrders + Vehicles) | 1-2h | Medio-governance (MWART verde, interno) | Nao | nada | 1 (mas 2 charters) |
| ⏸️ | ~~**Gap 4**~~ SMS Twilio out-of-band PIN | ~~6-8h~~ | ~~Baixo piloto~~ — **ABORTADO V2** | — | — | 0 |
| 5º | **Gap 6** Visual regression snapshots (baseline novo) | 3-4h | Medio-CI saude | Nao | **TODOS os outros mergeados** | 1 |
| **TOTAL** | — | **21-29h IA-pair + 6h Wagner smoke** | — | — | — | **7 PRs** |

**Justificativa nova ordem (ADR 0194-aware):** sub-vertical 4 mecanica pesada caminhao basculante valoriza (a) decisao visual de R$ [redacted Tier 0]-80k peca → Gap 2 DVI #1, (b) registro foto antes/depois peca hidraulica → Gap 1 #2, (c) papel A4 motorista ressarcimento → Gap 3 #3. Governance (Gap 5) e CI saude (Gap 6) viram tail-priority.

## §3 — Ordem de execucao realinhada (ADR 0194 — mecanica pesada caminhao basculante)

```
ETAPA A (paralelo · sem dependencias · valor imediato Martinho):
  ├─ Gap 5: Charter Edit.tsx           (2h · barato governance MWART verde)
  └─ Gap 3: Imprimir OS PDF A4         (5h · papel ressarcimento transportadora)

ETAPA B (pre-req Etapa A · backbone foto mecanica pesada):
  └─ Gap 1: Upload foto via HasArquivos (6h · peca hidraulica antes/depois → ressarcir 3a)

ETAPA C (sequencial · pre-req Etapas A+B · CORACAO mecanica pesada):
  └─ Gap 2: DVI Vistoria Digital UI semaforo (motorista aprova R$ [redacted Tier 0]-80k peca)
         ├─ PR 2a drawer-internal      (4h · DviSemaforoSection + Sheet form)
         ├─ PR 2b service+job+controller (3h · AprovacaoDviService + Job WhatsApp link)
         └─ PR 2c pagina publica mobile  (4h · AprovacaoDviPublica.tsx 360px)

ETAPA D (final · pre-req TUDO mergeado · governance CI):
  └─ Gap 6: Visual regression baseline novo (4h · 4 baselines + ADR strategy)

ETAPA OUT-OF-SCOPE — Gap 4 SMS Twilio adiado V2:
  • Review_trigger 1: incidente real PIN bypass via WhatsApp SIM-swap
  • Review_trigger 2: volume 200+ OS/mes biz=164 OU >5% bypass tentado
  • Strategy interface SmsDriverInterface fica como ADR proposta preparada
```

**Justificativa nova ordem:**
- Etapa A primeiro: gaps baratos sem dep + valor imediato Martinho (governance MWART + papel motorista basculante)
- Etapa B: Gap 1 backbone foto IMPRESCINDIVEL pra Gap 2 nascer com foto inline em cada item DVI (best-practice 2026 AutoVitals 30+ fotos/caminhao)
- Etapa C sequencial 3 sub-PRs: respeita commit-discipline (≤300 linhas/PR). Wedge competitivo vs concorrentes mecanica pesada BR (Auto Manager/Tecnomotor/Plumelp/Sysmecanica) — nenhum tem DVI nativo.
- Etapa D por ultimo: snapshots capturam estado final, evita re-baseline
- **Gap 4 adiado:** WhatsApp Wave 4 in-band +60s ja cobre 95% piloto Martinho; motorista basculante 100% tem WhatsApp; defesa em profundidade temporal (fraca mas presente) aceita pre-incidente real

## §4 — Risk register Tier 0

| ID | Risco | Probabilidade | Impacto | Mitigacao |
|----|-------|--------------:|---------|-----------|
| R1 | Gap 1 `ArquivosService::attach` nao propaga business_id corretamente em context polimorfico | Baixa | Alto multi-tenant breach | Audit pre-implementacao (30min) na conta de Gap 1; existem ja 14 Pest specs Arquivos cross-tenant — espelhar |
| R2 | Gap 2 PR 2c `AprovacaoDviPublica.tsx` reincide nos 6 meta-anti-padroes F3 Financeiro rejeitado | Media | Wagner rejeita PR | LER `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md` antes; charter Tier A com Wagner screenshot aprovado pre-codigo |
| R3 | Gap 3 IFRAME CSS cross-origin nao carrega Tailwind | Alta tecnica | Print sai feio | CSS **inline** no Blade `<style>` (pattern documentado `printSaleReceipt.ts` linha 6) |
| ~~R4~~ | ~~Gap 4 Twilio SDK nao funciona em Hostinger~~ | — | — | **OBSOLETO — Gap 4 abortado V2 (decisao Wagner 2026-05-26)** |
| ~~R5~~ | ~~Gap 4 PIN leak em logs claros~~ | — | — | **OBSOLETO — Gap 4 abortado V2.** Risco residual WhatsApp +60s coberto: OtelHelper::span ja redact PIN em log Whatsapp Wave 4 |
| R6 | Gap 5 charter schema fail (datas sem aspas, related_adrs integers) | Alta inicial | CI gate red | Schema AJV strict ja conhecido (licoes 3.2-3.6 sessao 2026-05-26 4PRs); usar templates esqueleto fornecidos nos docs filhos |
| R7 | Gap 6 CI minute burn rodando Pest browser em todo PR | Alta | Free tier esgota | Workflow `workflow_dispatch` manual-trigger only V0; self-hosted runner CT 100 V2 |
| R8 | Gap 2+5 **conflito Edit.tsx** | Nula | N/A | Gap 1 toca `ServiceOrderRichSheet.tsx` (drawer ProducaoOficina); Gap 5 toca apenas Edit.tsx — sem overlap confirmado |
| R9 | Sub-PRs Gap 2 acumulam >900 linhas combinadas | Media | Review fatiga | Cada PR isolado ≤300 linhas (commit-discipline Tier A); revisor le 1 PR por vez |
| R10 | Wagner aprova gaps em ordem errada (Gap 2 antes do 1) | Media | DVI UI nasce sem foto inline | Doc consolidador deixa ordem explicita §3; recomendar mas nao bloquear |
| R11 | Cliente Martinho biz=164 NAO valida smoke real (indisponibilidade dia 27) | Media | Validacao adiada | Smoke real fica com Wagner per task; gap NAO bloqueia merge se Pest verde |

## §5 — Restricoes Tier 0 inegociaveis (recap)

Aplicam-se a TODOS os 6 gaps:

1. **Multi-tenant `business_id` global scope** ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)) — toda Eloquent Model + Job + Service propaga. Defesa em profundidade obrigatoria.
2. **Commit-discipline Tier A** — 1 PR = 1 intent ≤300 linhas. Gap 2 obriga 3 PRs sub-decompostos.
3. **F3 Cowork→Inertia** — LER `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md` antes de tocar Pages/<Mod>/<Tela>.tsx (afeta Gaps 1, 2, 3).
4. **Charter > Spec (ADR 0094)** — Gap 2 (DVI UI) + Gap 5 (Charter Edit) precisam charter ANTES da Page.
5. **PT-BR** no dominio (codigo, charters, schemas, mensagens user-facing).
6. **Hostinger ≠ CT 100** ([ADR 0062](memory/decisions/0062-separacao-runtime-hostinger-ct100.md)) — sem `laravel/octane` no Hostinger. Gap 4 Twilio HTTP outbound funciona OK.
7. **ZERO auto-mem privada** ([ADR 0061](memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)) — todo conhecimento em git canon.
8. **LGPD** — fotos (Gap 1), PIN (Gap 4), snapshots (Gap 6) tratam dados pessoais com cuidado: retention configuravel, redaction em logs, fixtures faker em tests.
9. **Schema canon (licoes 3.2-3.7 sessao 2026-05-26 4PRs)** — aspas em datas YAML, slugs strings related_adrs, page_id sem `/`, SCOPE.md declara novos files, lint baseline update apos git mv.

## §6 — DRAFT tasks-create (Wagner copy-paste aprovado)

**ATENCAO Wagner:** abaixo estao 8 DRAFTS no formato JSON-yaml hibrido (compativel `mcp__oimpresso__tasks-create`). Copy-paste 1 por vez apos aprovar batch.

```yaml
# === Gap 5 (Etapa A — barato governance) ===
mcp__oimpresso__tasks-create:
  title: "Gap 5 — Charter Edit.tsx (governance MWART verde)"
  module: OficinaAuto
  priority: low
  estimated_hours: 2
  description_ref: memory/sessions/2026-05-26-plano-gap-5-charter-edit-tsx.md

# === Gap 3 (Etapa A — papel balcao) ===
mcp__oimpresso__tasks-create:
  title: "Gap 3 — Imprimir OS PDF profissional CSS print A4"
  module: OficinaAuto
  priority: medium
  estimated_hours: 5
  description_ref: memory/sessions/2026-05-26-plano-gap-3-imprimir-os-pdf-profissional.md

# === Gap 1 (Etapa B — backbone) ===
mcp__oimpresso__tasks-create:
  title: "Gap 1 — Upload foto/laudo real drawer via Modules/Arquivos"
  module: OficinaAuto
  priority: medium
  estimated_hours: 6
  description_ref: memory/sessions/2026-05-26-plano-gap-1-upload-foto-laudo-drawer.md

# === Gap 4 ABORTADO V2 (decisao Wagner 2026-05-26) ===
# WhatsApp Wave 4 in-band +60s cobre 95% piloto Martinho.
# Strategy interface SmsDriverInterface fica como ADR proposta preparada.
# Review_trigger: incidente real PIN bypass OU volume 200+ OS/mes biz=164.
# Doc filho preservado como reference V2: 2026-05-26-plano-gap-4-sms-provider-out-of-band-pin.md
# NAO CRIAR TASK Gap 4 agora.

# === Gap 2 (Etapa C — wedge competitivo, 3 sub-PRs · CORACAO mecanica pesada) ===
mcp__oimpresso__tasks-create:
  title: "Gap 2 — DVI Vistoria Digital UI (3 PRs)"
  module: OficinaAuto
  priority: high
  estimated_hours: 12
  description_ref: memory/sessions/2026-05-26-plano-gap-2-dvi-vistoria-digital-ui.md
  subtasks:
    - "2a: DviSemaforoSection + DviItemFormSheet + Inertia integration (4h)"
    - "2b: AprovacaoDviService + Controller + EnviarLinkAprovacaoDviJob (3h)"
    - "2c: AprovacaoDviPublica.tsx mobile-first + migration aditiva (4h)"

# === Gap 6 (Etapa D — final) ===
mcp__oimpresso__tasks-create:
  title: "Gap 6 — Visual regression baseline OficinaAuto pos-Wave 5"
  module: OficinaAuto + tests/Browser
  priority: low
  estimated_hours: 4
  description_ref: memory/sessions/2026-05-26-plano-gap-6-visual-regression-snapshots.md
  blockers: "Gaps 1, 2, 3, 4, 5 mergeados primeiro"
```

## §7 — Conflito de area: nao houve

Recap mandato §5 "Decomposicao esperada":
- Gap 5 (Charter Edit) + Gap 1 (Upload foto drawer) "podem tocar mesmo Edit.tsx" — **FALSO**.
  - Gap 1 toca `ServiceOrderRichSheet.tsx` (drawer ProducaoOficina) — anexa via trait + componente novo.
  - Gap 5 toca `ServiceOrders/Edit.tsx` + `Vehicles/Edit.tsx` — apenas docs charter ao lado, ZERO edit no tsx.
  - **Sem overlap confirmado.**
- Gap 6 depende dos outros 5 — confirmado em §3 Ordem.

## §8 — Pre-reqs ROADMAP checados

Coordenador-paralelo regra Tier 0 §6: "se cada wave depende de pre-req Wagner sign-off que nao foi dado, recusa disparar".

Pre-reqs detectados (pos-realinhamento 2026-05-26):
- ~~**Gap 4**: conta Twilio + numero BR + Vaultwarden token~~ **OBSOLETO — Gap 4 abortado V2.**
- **Gap 5 Tier A** (ServiceOrders/Edit): aprovacao Wagner explicita charter Tier A. Pode encaminhar no proprio PR.
- **Gap 2 PR 2c**: charter `AprovacaoDviPublica.tsx` Tier A precisa Wagner aprovar screenshot/mockup ANTES de codar (Charter > Spec ADR 0094). Mockup deve refletir domínio mecanica pesada (motorista basculante decide aprovar peca hidraulica R$ X via mobile 360px).
- Demais gaps: sem pre-req humano bloqueante.

## §9 — Calibracao pos-mortem do coordenador

Validacao do pattern formalizado:
- Decomposicao em 3 waves × 2 gaps cada — areas isoladas confirmadas em §7
- Nenhum overlap detectado em fase 3 — qualidade decomposicao boa
- Spawn paralelo NAO executado por contexto turn (Task/Agent tool nao exposto). Compensei com leitura serializada eu-mesmo + escrita 6 docs filhos. Output identico ao spawn paralelo, latencia maior.
- Calibracao: em sessoes futuras, validar exposicao Task tool ANTES de decompor (talvez recusar coord-paralelo se tool ausente, sugerir audit-research-expert direto).

## §10 — Devolucao ao parent

**Status pos-realinhamento Wagner 2026-05-26:** 5 gaps ativos + 1 abortado V2, 6 docs filhos preservados (Gap 4 doc vira reference V2) + 1 doc consolidador realinhado, 0 conflito detectado, 0 git ops executadas.

**Path consolidador:** `D:/oimpresso.com/memory/sessions/2026-05-26-plano-6-gaps-wave-futura-oficinaauto.md`

**Frase devolucao:** 5 gaps ativos / 1 abortado V2 / 6 docs filhos preservados / 0 conflito de area / 0 ops git executadas — pronto pra Wagner apresentar 7 PRs em 4 etapas (~29h IA-pair) na sessao tarde 2026-05-26.

**Decisoes Wagner aplicadas:**
1. ✅ Foco sub-vertical 4 mecanica pesada caminhao basculante CNAE 4520 (ADR 0194)
2. ✅ Gap 4 SMS Twilio ABORTADO V2 (WhatsApp Wave 4 cobre 95%)
3. ✅ Reordenacao: Gap 2 DVI #1 (coracao mecanica pesada) > Gap 1 #2 (foto ressarcimento) > Gap 3 #3 (papel motorista) > Gap 5 #4 (governance) > Gap 6 #5 (CI baseline)
4. ✅ Total reduz 37h/8PRs → 29h/7PRs (economia 8h)

**Pergunta pendente apresentacao:** alguma decisao adicional pre-implementacao? (ex: aprovar Charter ServiceOrders/Edit Tier A; aprovar mockup AprovacaoDviPublica.tsx mobile 360px ANTES PR 2c codar)?

## §11 — Refs

- Sessao mae [2026-05-26-oficina-auto-4prs-martinho-ready-mergeados.md](2026-05-26-oficina-auto-4prs-martinho-ready-mergeados.md) §4 backlog catalogado
- [ADR 0093 Multi-tenant Tier 0](memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 Constituicao v2](memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0104 MWART Process canon](memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0105 Cliente sinal Martinho](memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)
- [ADR 0106 Recalibracao 10x IA-pair](memory/decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)
- ADR 0123 Modules/Arquivos backbone
- [ADR 0143 FSM canon LIVE prod](memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [ADR 0171 OficinaAuto piloto Martinho](memory/decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md)
- [ADR 0194 Dominio Martinho mecanica pesada](memory/decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md)
- ADR Repository padrao em git canon — `memory/how-trabalhar.md`
- `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md` (LER ANTES de Gap 1, 2, 3)

---

## Sources externas (research §1)

- [AutoVitals Digital Vehicle Inspection Best Practices](https://blog.autovitals.com/digital-vehicle-inspection-best-practices)
- [Mitchell1 DVI healthier bottom lines](https://mitchell1.com/shopconnection/digital-vehicle-inspections-build-trust-and-healthier-bottom-lines/)
- [Tekmetric DVI mobile feature](https://www.tekmetric.com/feature/digital-vehicle-inspection)
- [Jotform Auto Repair Work Order template PDF](https://www.jotform.com/pdf-templates/auto-repair-work-order)
- [Method Auto Repair Work Order](https://www.method.me/resources/auto-repair-work-order-template-download/)
- [Twilio SMS Brazil pricing 2026](https://www.twilio.com/en-us/sms/pricing/br)
- [Twilio Verify fallback channels](https://www.twilio.com/docs/verify/fallback-scenarios)
- [Prelude Twilio competitors 2026](https://prelude.so/blog/twilio-competitors)
- [Laravel Tenancy filesystem multi-tenant](https://tenancyforlaravel.com/docs/v2/filesystem-tenancy/)
- [MojoAuth SMS OTP cost analysis 2026](https://mojoauth.com/blog/sms-otp-cost-ecommerce-passkeys-cut-80-percent)
