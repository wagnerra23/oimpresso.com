---
name: Deal Agrosys — R$ [redacted Tier 0]M/ano + R$ [redacted Tier 0]k upfront (em negociação 2026-05-12)
description: Maior oportunidade comercial do oimpresso até hoje — Agrosys (ERP agro, adquirida pela Aliare) quer revender WhatsApp+NFe pra 4000 clientes. Wagner = Tech Provider Meta. Vendedor Artur (sobrinho).
type: project
---
# Deal Agrosys — pipeline R$ [redacted Tier 0]M ano 1

## Estado inicial 2026-05-12

**Cliente potencial**: **Agrosys Sistemas** (não plug.co, não Pluga.co — confusão inicial corrigida)
- Criciúma/SC, ~R$ [redacted Tier 0]M receita 2025, ~160 funcionários
- Adquirida pela **Aliare** em 2026 (R$ [redacted Tier 0]M, big tech agro)
- Líder em ERP de avicultura (~30% dos frangos abatidos BR)
- LinkedIn: `linkedin.com/company/agrosystecnologia`

## Modelo de negócio proposto

| Item | Valor |
|---|---|
| **Taxa de implantação** (one-shot) | **R$ [redacted Tier 0]** |
| **MRR Agrosys → Wagner** (4000 × R$ [redacted Tier 0]) | **R$ [redacted Tier 0]/mês** |
| **MRR líquido Wagner** (4000 × R$ [redacted Tier 0]) | **R$ [redacted Tier 0]/mês** = **R$ [redacted Tier 0]M/ano** |
| **MRR Agrosys cobra cliente final** (4000 × R$ [redacted Tier 0]) | R$ [redacted Tier 0]/mês (margem deles R$ [redacted Tier 0]k) |
| **Total ano 1 Wagner** | **~R$ [redacted Tier 0] milhões** |

Resolve metade da meta ADR 0022 (R$ [redacted Tier 0]M/ano) em 1 deal.

## Setup técnico

**Use case**: Agrosys emite NFe → POST webhook XML+PDF pro oimpresso → oimpresso notifica produtor rural via WhatsApp Business.

Volume: 4000 produtores × ~10 NFe/mês = **40k msgs/mês** (utility, baixo risco ban).

## Equipe deal

- **Wagner [W]**: dono técnico + comercial final
- **Artur (sobrinho)**: vendedor — proposta "metade da comissão" (RED FLAG — ver feedback-comissao-recurring-vendedor.md)
- **Eliana [E]** (esposa advogada+financeiro): ASSET CRÍTICO pra revisar MSA/SLA + modelar fiscal Meta-Irlanda → BR (ISS/IRRF/CIDE/PIS/COFINS, ~15-25% taxa adicional)
- **Counsel LGPD externo**: provavelmente necessário (4000 clientes finais = Wagner é OPERADOR, cooperativa é CONTROLADORA)

## Arquitetura técnica recomendada

### Wagner = Tech Provider Meta DIRETO (não BSP intermediário)

**Por que NÃO usar BSP** (360dialog/Take Blip/Twilio):
- 360dialog €49/número × 4000 = **€196k/mês de fee fixo** → modelo INVIÁVEL
- Take Blip = concorrente direto (vende ERP agro)
- Twilio: markup $0,005/msg reduz margem

**Tech Provider direto** = zero fee Meta. Custo = só mensagens.

### Onboarding via Embedded Signup (5-7 cliques cliente final)

- Cliente cooperativa clica botão "Conectar WhatsApp" no oimpresso/Agrosys
- Popup Meta abre (login Facebook deles)
- Cria Business Portfolio + WABA + phone → confirma OTP
- Meta retorna `phone_number_id` + `waba_id` + `access_token` via callback
- Wagner gerencia via API sem cadastros manuais

**Cliente final = WABA própria** (não Wagner concentrando 4000 no mesmo BM — limite 20 phones/WABA).

### Custo Meta real (40k utility + 4k marketing/mês)

- Utility BR 2026: $0,0068/msg × 40k = $272
- Marketing BR 2026: $0,0625/msg × 4k = $250
- Service (cliente responde): GRÁTIS
- **Total: ~$522/mês ≈ R$ [redacted Tier 0]/mês**
- **Com fiscal BR (+20%): R$ [redacted Tier 0]/mês**
- **Margem Wagner: 98,4%** (R$ [redacted Tier 0]k receita – R$ [redacted Tier 0]k custo)

## Cronograma realista

| Semana | Marco |
|---|---|
| 1-2 | Verificar Meta Business Manager Wagner verified + 2FA + criar App novo |
| 3-4 | Implementar Embedded Signup no oimpresso (React + Laravel callback + webhook) |
| 5-6 | Review Meta aprova (3-8 semanas range; geralmente 1-2 iterações) |
| 7 | Smoke test 2-3 cooperativas piloto |
| 8-20 | Agrosys empurra adoção 4000 (~200-500/semana) |
| 21+ | Steady-state |

**4-6 meses pra 4000 ativos.** Velocidade limitada pela Agrosys orquestrar adoção, NÃO por código Wagner.

## Riscos não-óbvios

1. **Ban-em-cascata** — 1 cooperativa spammer afeta quality score de TODA rede via App Wagner. Mitigação: revisor central de templates.
2. **Misclassification utility/marketing** — "NFe emitida" = utility ($0,034). "Aproveite promo" = marketing ($0,31 = 9× mais caro). Revisar templates Agrosys antes submit.
3. **Fiscal Meta-Irlanda** — invoice estrangeiro triggera ISS+IRRF+CIDE+PIS+COFINS, ~20% adicional. Eliana modela ANTES de fechar pricing.
4. **LGPD operador/controlador** — Wagner=operador, cooperativa=controladora. Contrato Agrosys precisa cláusula.
5. **Phone migration** — cooperativas já com WhatsApp Business app (não API) → "coexistence flow" complexo (360dialog tem doc específica).
6. **Quality score**: Meta pode rebaixar phone → tier "low" = 1k conv/dia, "high" = ilimitado. Monitorar via Meta Business Suite.
7. **Embedded Signup popup bloqueado** — ad-blockers + corp firewalls cooperativas grandes. Fallback "abrir em nova aba".

## 5 perguntas críticas pra próxima call com Agrosys

1. **Spec do XML** (formato campos, frequência, autenticação webhook)
2. **Spec PDF** (anexo embed via URL ou base64? máx tamanho?)
3. **Modelo de mensagem** (template HSM já aprovado Meta? quais variáveis?)
4. **SLA esperado** (uptime % + tempo resposta máx + penalidade tetada)
5. **Modelo billing** — Agrosys MoR (cobra clientes finais, repassa Wagner) OU Wagner MoR? Quem emite NF dos R$ [redacted Tier 0]?

## Diferencial competitivo amplificável

**Jana IA com memória persistente** aplicada ao agro:
- "Pergunta pro produtor: sua safra desse mês foi maior que ano passado?"
- "Quando vence a NFe Z?"
- "Quanto faturou em julho vs ano anterior?"

Agrosys NÃO tem IA conversacional. **Esse é o diferencial não-replicável vs concorrentes.**

## Defensive técnica (esta semana, mesmo sem deal fechado)

- **Provisionar CT 101 backup** no Proxmox empresa (125GB RAM, 2TB sobra) — pra escalar quando virar 4000 instâncias

## Próximas ações (ordem)

1. **Conversa Artur** (família-difícil) — renegociar comissão antes assinar (50% MRR perpétuo = suicídio)
2. **Email Agrosys via Artur** pedindo as 5 specs acima
3. **Eliana revisa** qualquer doc/proposta Agrosys mandar
4. **Calcular margem real** incluindo Meta + infra + Brain B + Eliana + Artur (sem isso pode estourar 100% custos em 12-24m)
5. **Carta de intenção assinada** antes de codar UMA LINHA do Embedded Signup
