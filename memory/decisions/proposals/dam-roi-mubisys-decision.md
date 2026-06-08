# Decisão proposta — DAM nativo: construir agora, adiar, híbrido ou waiting-list?

**Status:** proposed (Wagner valida)
**Data:** 2026-05-09
**Decisor:** Wagner [W]
**Refs:** [ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md) (cliente como sinal qualificado), [playbook Mubisys](../../sales/2026-05/10-playbook-migracao-mubisys.md), [pricing tiers](../../sales/2026-05/06-pricing-tiers.md), [concorrentes Zenite/Mubisys](../../research/2026-05-prospeccao/02-concorrentes-zenite-mubisys.md)

---

## Contexto

Mubisys publiciza **MubiDrive com 150+ TB armazenados** como diferencial forte do produto deles — cliente médio Mubisys tem **10-150GB de arte** depositado lá (PDFs print-ready, plotagens, projetos vetoriais pesados). O playbook de migração identificou que "DAM nativo equiv MubiDrive" é **gap crítico** que pode travar contratos Enterprise (R$ 1.499/m + R$ 5.000 setup). Time tem 5 pessoas (Wagner + 4) e [ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md) é claro: **backlog só recebe item se cliente paga + reporta**, não hipótese interna. Hoje **zero cliente Mubisys assinou** — sinal qualificado ainda **não existe**. Pergunta: construir DAM agora especulando sinal futuro, adiar, fazer híbrido low-cost, ou usar waiting-list pra forçar sinal antes do build?

## Tabela ROI 4 cenários

> **Premissas globais** (todas marcadas `[validar]`):
> - **Custo dev interno:** `R$ 80/h` `[validar — placeholder; range BR mid-market PJ pleno R$ 60-120/h, Wagner pareado IA-pair]`
> - **Wallclock fator IA-pair:** 10x (ADR 0106), conservador 5x → MVP 80h spec → 16h wallclock real
> - **Storage S3 BR (Wasabi/Cloudflare R2):** ~R$ 50/TB/mês `[validar — Cloudflare R2 sem egress fee, Wasabi R$ 30-60/TB]`
> - **GB/cliente Mubisys médio:** **50GB** (range public 10-150GB, mediana provável 30-80GB) `[validar com 1º discovery]`
> - **Receita Enterprise:** R$ 1.499/m + R$ 5.000 setup ([pricing-tiers](../../sales/2026-05/06-pricing-tiers.md))
> - **Conversão cold→pago em prospect Mubisys com gap DAM aberto:** `30% bloqueia` se >100GB; `60% aceita híbrido` se <50GB `[validar]`
> - **Pipeline qualificado disponível:** estimo 5-10 prospects em 90 dias (universo Mubisys 1.800 empresas, ~5% reclamação pública = 90 leads de cold) `[validar]`

| Métrica | A — Construir agora | B — Adiar (princípio 0105) | C — Híbrido S3/Drive externo | D — Waiting-list 3 pagos primeiro |
|---|---|---|---|---|
| Investimento up-front (h dev spec) | 80h | 0h | 24h | 0h imediato; 80h após 3 contratos |
| Wallclock real (IA-pair 5x conservador) | ~16h (~2 dias [F]+[W]) | 0h | ~5h (~1 dia [F]) | 0h até gatilho; ~16h depois |
| **Custo dinheiro up-front (R$)** | R$ 6.400 (80h × R$ 80/h) | R$ 0 | R$ 1.920 (24h × R$ 80/h) | R$ 0 até 3 contratos |
| **Custo recorrente/mês (storage + manutenção)** | R$ 250 storage `[5 clientes × 50GB × R$ 50/TB ÷ 1024 × 1000]` + R$ 400 manutenção (5h × R$ 80) = **R$ 650** | R$ 0 | R$ 0 storage (cliente paga próprio Drive) + R$ 200 manutenção 2.5h = **R$ 200** | R$ 0 até gatilho |
| Risco churn por gap DAM (3m) | Baixo (entregue) | Alto (perde >100GB clients) | Médio (sem versionamento próprio) | Médio-baixo (cláusula 30% desconto se atrasar) |
| **Receita esperada 3m (cenário base 5 prospects qualificados/mês)** | 3 contratos fechados Mubisys × (R$ 5k setup + 3m × R$ 1.499) = **R$ 28.491** | 1 contrato (perde 60% prospects DAM-blockers) × (R$ 5k + 3m × R$ 1.499) = **R$ 9.497** | 2 contratos (atende clients <50GB) × (R$ 5k + 3m × R$ 1.499) = **R$ 18.994** | 3 contratos com cláusula desconto 30% após 90d × (R$ 5k + 3m × R$ 1.499 × 0.85 médio) = **R$ 26.467** |
| **Receita esperada 12m (recorrente full)** | 5 contratos cumulativos × (R$ 5k + 12m × R$ 1.499) = **R$ 114.940** | 2 contratos × (R$ 5k + 12m × R$ 1.499) = **R$ 45.976** | 3-4 contratos × (R$ 5k + 12m × R$ 1.499) = **R$ 68.964 - R$ 91.952** | 5 contratos × (R$ 5k + 12m × R$ 1.499 × ~0.92 blended) = **R$ 107.730** |
| **Custo total 12m (dev + storage/manutenção)** | R$ 6.400 + 12 × R$ 650 = **R$ 14.200** | R$ 0 | R$ 1.920 + 12 × R$ 200 = **R$ 4.320** | R$ 6.400 + 9m × R$ 650 = **R$ 12.250** (build mês 4) |
| **ROI 12m** = (Receita − Custo) / Custo | (114.940 − 14.200) / 14.200 = **709%** | infinito (custo zero) mas receita perdida | (75k − 4.320) / 4.320 = **1.636%** | (107.730 − 12.250) / 12.250 = **779%** |
| **Lucro líquido 12m** | **R$ 100.740** | **R$ 45.976** | **R$ 71.000-87.632** | **R$ 95.480** |
| Payback period (até cobrir custo dev) | 1 contrato Enterprise (R$ 5k setup já paga 78% do dev). **~30 dias** | n/a | <1 contrato. **~15 dias** | 0d até gatilho; ~30d após build |
| Risco (1-5, 5=alto) | **3** (especulação se prospect virá) | **4** (perde mercado pra Mubisys) | **2** (low-cost, fácil reverter) | **2** (sinal qualificado garantido) |
| Alinhamento ADR 0105 (cliente como sinal) | ❌ **Viola** — build sem sinal qualificado, especula 3 contratos | ✅ Total — espera sinal explícito | 🟡 Parcial — investimento mínimo defensável como "preparação de demo" | ✅ Total — só constrói após cliente pagar + cláusula contratual |

## Premissas que sustentam cada cenário (sensibilidade)

**Cenário A quebra se:**
- Conversão real <2 contratos em 3m → ROI 12m cai pra ~250% (ainda positivo, mas dev parado)
- Storage médio sobe pra 150GB/cliente → manutenção dobra (R$ 1.300/m) → ainda positivo mas margem cai
- Mubisys cancela contrato cliente pra reter → perde 1-2 leads e build vira museu
- **Threshold morto:** se zero contrato em 90d com DAM pronto, ADR 0105 estava certa e foi capex queimado

**Cenário B quebra se:**
- Mubisys lança feature comparativa (API aberta) e fecha gap de defesa → janela competitiva fecha em 6m
- Discovery honesto "ainda não tem DAM" funciona em <30% dos casos (cliente vê como deal-breaker, não negocia)
- Concorrente novo (Zenite GE 4.0 web maduro) preenche o gap de modernização e nos passa

**Cenário C quebra se:**
- Cliente >100GB MubiDrive não aceita "trazer próprio storage" (vê como degradação)
- OAuth Google Drive/Dropbox quebra com freq (sem versionamento próprio = recovery manual)
- Tempo dev híbrido estoura pra 40-60h (integração OAuth multi-provider tem armadilhas)

**Cenário D quebra se:**
- Cláusula "DAM em 90d" é vista como red flag pelos prospects → 0 fechamentos
- 3 clientes pagam, build atrasa pra 120d, gatilho 30% desconto vira churn
- Wagner trata cláusula como compromisso fraco e procrastina build → trava receita

## Análise qualitativa

**Reputacional**
- A: prospect vê DAM, mas se zero cliente está usando real, vira "feature de marketing". Risco "build it and they wont come".
- B: prospect Mubisys vê falta de DAM e classifica oimpresso como "imaturo". Posicionamento Enterprise sofre.
- C: posicionamento honesto ("você traz seu storage, a gente integra") funciona pra prospect com TI maduro; ruim pra dono não-técnico.
- D: cláusula contratual com SLA explícito é **diferencial enterprise** — Mubisys não publica nada disso.

**Time** (5 pessoas, WIP máx 2/pessoa)
- A: Felipe [F] e Wagner [W] em sprint dedicado de 2 dias wallclock — **tira ambos do MWART/Repair em curso**. Cycle atual já tem trabalho.
- B: zero impacto, time segue MWART/MCP/NfeBrasil.
- C: 1 dia [F] sozinho — encaixa entre tasks como "vitória rápida" sem trocar prioridade.
- D: zero impacto até 3 contratos. Quando dispara, 2 dias [F]+[W] com sinal forte (cliente pagando) — motivação alta.

**Concorrência**
- Mubisys tem 150TB MubiDrive como "moat" público. Replicar feature completa = R$ 80k+ dev (playbook estima). MVP S3-wrap não é paridade — é **mínimo viável pra não travar venda**.
- Zenite ainda migra desktop→web — janela de modernização aberta 12-18m.
- **Janela competitiva real:** 12 meses até Mubisys reagir (eles não publicam roadmap; ciclo deles é lento — RA fev/2023 ainda não foi resolvido em mai/2026 = velocidade baixa).

## Recomendação

**Cenário D — Waiting-list com cláusula contratual "DAM em 90d"**, fallback **Cenário C** (híbrido) pra prospects <50GB.

### 3 razões fortes

1. **Alinha 100% com ADR 0105** (cliente como sinal qualificado). Build só dispara quando 3 clientes pagaram = sinal forte qualificado por R$ 15k+ em receita comprometida. Não viola princípio do projeto. Defensável em qualquer review interno.

2. **Lucro líquido 12m R$ 95.480 vs R$ 100.740 do Cenário A** — diferença de apenas R$ 5.260 (5%) **mas elimina o risco de capex queimado**. Se zero contrato fechar em 90d, A perde R$ 14.200 + 2 dias time alocado errado; D perde R$ 0 e mantém time em MWART/Repair.

3. **Cláusula contratual vira selling point**, não desvantagem. Mubisys não publica SLA, oimpresso publica "DAM em 90d ou desconto 30% até entregar". Isso é **governança formal materializada em contrato** — diferencial Constituição v2 (ADR 0094). Conforto enterprise > risco.

**Por que NÃO o C puro:** híbrido sozinho perde prospects >50GB que querem solução nativa Enterprise. C como **opção paralela** dentro do D cobre <50GB sem build (3 dias dev cobrem 60% do mercado Mubisys mid).

**Por que NÃO o A:** especula sinal. ROI parece bom no papel mas é hipótese sem evidência. ADR 0105 explicitamente rejeita.

**Por que NÃO o B puro:** perde 60% dos prospects DAM-blocker irrecuperavelmente nos 90d. Mubisys segue ganhando reputação MubiDrive enquanto a gente espera.

### Maior risco da recomendação

**Cláusula "DAM em 90d ou desconto 30%" vista como red flag pelos prospects** → 0 contratos fechados → não dispara build → cenário vira "B disfarçado". Mitigação: testar pitch da cláusula em 2 discoveries antes de oficializar; se prospect rejeita explicitamente, mudar pra C híbrido + roadmap público.

### Payback esperado

- **Setup R$ 5.000 do 1º contrato Enterprise** já paga 78% do dev (R$ 6.400). Após **3º contrato Enterprise (estimado mês 2-3)**, custo dev coberto + R$ 8.600 lucro antes do build começar.
- **Build dispara mês 3** com 3 clientes pagando (R$ 4.497 MRR + R$ 15k setup já em caixa).
- **Payback total dev:** ~30 dias após contrato 1, com sinal forte pra disparar.

## Se decidir "construir agora" (caso Wagner override D em A)

**Quem:** Felipe [F] backend (S3 SDK + signed URLs + CRUD metadata) pareado com Wagner [W] (review + UI Inertia upload/preview). Maiara [M] não — segue suporte. Luiz [L] não — iniciante, escopo grande demais.

**Quando:** Cycle 03 (próximo, ~2 semanas após este ADR). NÃO interromper cycle atual.

**Escopo MVP (NÃO fazer Mubidrive completo):**
- ✅ Upload arquivo via signed URL pré-assinada (S3-compatible: Wasabi ou Cloudflare R2)
- ✅ Listagem por venda/projeto com filtro `business_id` (multi-tenant Tier 0 - ADR 0093)
- ✅ Preview básico (image/pdf via tag HTML5; outros tipos = download direto)
- ✅ Soft-delete + lixeira 30 dias
- ✅ Tagging livre (1 campo string CSV) — sem taxonomia formal
- ✅ Limite quota por business (config: 50GB padrão, ampliável)
- ❌ **NÃO fazer:** versionamento, fluxo aprovação, busca FTS no conteúdo, reconhecimento OCR, integração direta CorelDraw/Illustrator
- ❌ **NÃO fazer:** app mobile dedicado pra DAM (PWA cobre)
- ❌ **NÃO fazer:** colaboração multi-user em arquivo (lock pessimista)

**Regra de ouro:** se feature requer >4h dev pra MVP, vai pra roadmap pós-MVP. Disciplina de scope mata Mubidrive completo.

## Métricas de validação pós-decisão

**Em 30 dias** (cenário D — pré-build):
- ✅ Pelo menos 5 prospects Mubisys abordados via outreach (LinkedIn DM + cold email)
- ✅ ≥2 discoveries 60-90min realizadas
- ✅ ≥1 proposta com cláusula "DAM em 90d" enviada
- ⚠️ Se 0 propostas em 30d → revisar pitch cláusula ou pivot pra C

**Em 90 dias** (gatilho build):
- ✅ 3 contratos Enterprise pagos (setup R$ 15k em caixa)
- ✅ Build MVP iniciado mês 3
- ✅ MRR Enterprise ≥ R$ 4.497/m (3 × R$ 1.499)
- ⚠️ Se <2 contratos em 90d → cláusula é red flag → reescrever oferta sem promessa DAM

**Em 180 dias** (pós-build):
- ✅ DAM MVP em produção, 3 clientes usando
- ✅ Storage médio real medido (vs premissa 50GB) → recalibrar pricing se >100GB
- ✅ Churn rate ≤10% nos 3 primeiros contratos (validação produto-mercado)
- ✅ 1 case escrito público (cliente migrado Mubisys → oimpresso) com autorização

**Em 360 dias:**
- ✅ Receita Enterprise cumulativa ≥ R$ 100k (~5 contratos médios)
- ✅ Pelo menos 1 reclamação DAM resolvida em <5 dias úteis (SLA validado)
- ⚠️ Se cliente reclama "queria versionamento" 2+ vezes → US no backlog (sinal qualificado, ADR 0105)

---

**Última atualização:** 2026-05-09 — proposta inicial. Aguarda validação Wagner antes de virar ADR canon.
