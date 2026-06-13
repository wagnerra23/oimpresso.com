---
module: ComunicacaoVisual
doc_type: qualificacao_piloto
status: draft
last_review: 2026-05-16
owner: [W]
related_adrs: [0105, 0119, 0121, 0143]
related_docs:
  - PLANO-MIGRACAO-6-SAUDAVEIS.md
  - MATRIZ-ROI.md
  - ../../reference/matriz-conhecimento-clientes-legacy.md
  - ../../reference/concorrentes-com-visual.md
---

# Qualificação piloto CYCLE-06 — candidatos ComVis (research documental)

> **Missão:** decidir QUAL dos 6 candidatos saudáveis OfficeImpresso entra como **piloto Modules/ComunicacaoVisual** quando V1 LIVE. Pseudonimização obrigatória — receita/CNPJ NÃO em arquivo commitado ([proibicoes.md](../../proibicoes.md) §LGPD/PII).
>
> **Universo:** 6 candidatos (Vargas REMOVIDO 2026-05-10 — vertical autopecas confirmado). Lista canon: **Extreme, Gold, Zoom, Fixar, Mhundo, Produart** ([matriz-conhecimento-clientes-legacy.md](../../reference/matriz-conhecimento-clientes-legacy.md#tier-b--sampled-versao_banco-4-candidatos-comvis-pendentes)).
>
> **Critério ADR 0105:** dor reportada por cliente pagante > backlog hipótese. **Sem sinal qualificado → não migrar especulativo.**

---

## Universo e pseudonimização

| Pseudônimo | Alias real (registry) | Localização | Justificativa pseudônimo |
|---|---|---|---|
| **Cliente A** | Extreme | a confirmar | maior GMV restante + vertical encaixe perfeito |
| **Cliente B** | Gold | Três Lagoas/MS (provável) | ambiguidade identidade vs Mubisys |
| **Cliente C** | Zoom | a confirmar | versão Delphi mais nova do parque |
| **Cliente D** | Fixar | a confirmar | mid-tier dono-único |
| **Cliente E** | Mhundo | a confirmar | pequeno dono-único |
| **Cliente F** | Produart | a confirmar | banco antigo / fim-de-vida |

> 🔒 GMV exato + CNPJ ficam em `memory/research/clientes-legacy-officeimpresso/0N-<slug>/01-perfil.md` (gitignored fora deste worktree). Aqui só faixa relativa.

---

## Matriz 6 × 5 dimensões (score 1-5)

> Escala: **5** = excepcional/forte sinal · **4** = bom · **3** = neutro · **2** = fraco/cuidado · **1** = bloqueador · **TBD** = pesquisar (sem dado disponível)

| Dimensão | Cliente A | Cliente B | Cliente C | Cliente D | Cliente E | Cliente F |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| **D1. Receita estimada (GMV faixa)** | 5 (R$ [redacted Tier 0]M+) | 4 (R$ [redacted Tier 0]M+ se identidade ≠ Mubisys) | 3 (R$ [redacted Tier 0]M faixa) | 2 (R$ [redacted Tier 0]M faixa) | 2 (sub-R$ [redacted Tier 0]M) | 1 (sub-R$ [redacted Tier 0]M) |
| **D2. Tickets gráfica/mês (atividade FB)** | 5 (~85k vendas FB) | 4 (~55k vendas FB) | 5 (~52k vendas FB) | 3 (~4,5k vendas FB) | 4 (~18k vendas FB) | TBD (não sampled) |
| **D3. Distância geográfica (Wagner em SC)** | TBD | 4 (MS — Brasil central, viagem viável) | TBD | TBD | TBD | TBD |
| **D4. Perfil técnico (versão Delphi = receptividade upgrade)** | 5 (v1472 atualizado, chama Connector) | 3 (v1466 mid-tier) | 5 (v1474 mais nova do parque, paga upgrades) | 3 (v1421 sem update recente) | 3 (v1429) | 1 ("Banco antigo" marcado registry — sinal de churn latente) |
| **D5. Abertura migração (sinais)** | 4 (build atualizado = aberto a novidade) | 1 (provável já em Mubisys 24m contrato) | 3 (top-tier OfficeImpresso, talvez satisfeito demais) | 4 (versão sem update = pode estar avaliando) | 3 (dono-único, ciclo curto) | 4 (sabe que precisa trocar, oimpresso pode chegar primeiro) |
| **Soma (max 25)** | **19** | **16** | **16+TBD** | **15** | **15+TBD** | **9+TBD** |

### Notas por dimensão

**D1 (Receita estimada)** — faixas relativas baseadas em [PLANO-MIGRACAO-6-SAUDAVEIS.md](PLANO-MIGRACAO-6-SAUDAVEIS.md). Sem snapshot financeiro real ([RUNBOOK-financial-snapshot-cliente.md](../Officeimpresso/RUNBOOK-financial-snapshot-cliente.md) skill `officeimpresso-financial-snapshot`), são estimativas.

**D2 (Atividade FB)** — vendas Firebird histórico cumulativo (não mensal). Sample em [matriz-conhecimento-clientes-legacy.md Tier B](../../reference/matriz-conhecimento-clientes-legacy.md#tier-b--sampled-versao_banco-4-candidatos-comvis-pendentes). Sinal de operação ativa e volumosa.

**D3 (Geografia)** — Wagner em SC. Pesquisar cidade/UF via Firebird `SELECT * FROM EMPRESA` em discovery. Score aplica viabilidade de visita presencial pra cutover crítico (D-Day).

**D4 (Perfil técnico)** — `VERSAO_BANCO` Firebird como proxy de "cliente paga upgrades". v1474 = mais nova do parque, v1404 = mais antiga (Martinho). Detalhes em [legacy-delphi-firebird.md](../../reference/legacy-delphi-firebird.md).

**D5 (Abertura migração)** — sinais qualitativos (post-mortem Gold, registry observations). ADR 0105 exige sinal CONCRETO antes de classificar como Tier A.

---

## Ranking — Top 3 candidatos recomendados

### 🥇 1. Cliente A (Extreme) — score 19/25

**Por que:**
- Maior GMV restante (Vargas removido pra Autopecas)
- Vertical encaixe nominal: nome registry sugere LED/comunicação visual
- Build Delphi atualizado (v1472, chama backend Connector) = receptivo a novidade
- Volume FB alto (~85k vendas histórico) = operação madura testa Modules/ComunicacaoVisual com carga real

**Plano discovery:** Wagner [W] + Felipe [F] técnico — call 45min após Sprint 1 Modules/ComunicacaoVisual verde.

**Risco:** concorrência Mubisys/Zênite pode ter mapeado paralelo (AFACOM+ centro-oeste). Acelerar Q3/26.

**Pacote recomendado:** Enterprise R$ [redacted Tier 0]/m grandfathered 24m + setup R$ [redacted Tier 0] + 30% off 6m + Modules/ComunicacaoVisual completo (cálculo m² + spool plotter + PCP + NFe-de-boleto) + Jana ilimitada. Compromisso: depoimento escrito.

### 🥈 2. Cliente C (Zoom) — score 16/25 (+TBD geo)

**Por que:**
- Versão Delphi v1474 = MAIS NOVA observada no parque (Tier A receptividade)
- Volume FB alto (~52k vendas histórico)
- Cliente top-tier OfficeImpresso = paga upgrades, retém bem
- Decisão potencialmente comitê → ciclo venda mais longo, mas decisão firme quando vier

**Plano discovery:** Wagner [W] + Felipe [F] técnico — demo 60min focada em Jana IA conversacional ("pergunte ao seu negócio") + NFe automática + cálculo m².

**Risco:** pode estar SATISFEITO DEMAIS com OfficeImpresso atual. Diferencial precisa ser CONCRETO (não preço — defender pelo wedge competitivo NFe-de-boleto automática + Jana 22h).

**Pacote recomendado:** Enterprise R$ [redacted Tier 0]/m grandfathered 18m + setup R$ [redacted Tier 0] (50% off Enterprise) + Modules/ComunicacaoVisual completo. Q4/26 outubro outreach.

### 🥉 3. Cliente E (Mhundo) — score 15/25 (+TBD)

**Por que:**
- Volume FB médio-alto (~18k vendas) pra GMV pequeno = ticket médio baixo possivelmente, alta frequência
- Decisão dono-único → ciclo curto
- Tier mid permite testar Pro R$ [redacted Tier 0]/m (vs Enterprise) — calibra modelo comercial sem queimar relacionamento Enterprise

**Por que NÃO Cliente D (Fixar):** score similar mas Mhundo tem +4x vendas FB histórico = operação mais ativa. Fixar fica como backup Q1/27.

**Plano discovery:** Maiara [M] outreach + Felipe [F] técnico se avançar — cold email + WhatsApp follow-up.

**Risco:** GMV pequeno pode não justificar Pro R$ [redacted Tier 0] (margem cliente apertada). Avaliar tier R$ [redacted Tier 0] em discovery.

**Pacote recomendado:** Pro R$ [redacted Tier 0]/m grandfathered 12m + setup R$ [redacted Tier 0] (entry-level) + Modules/ComunicacaoVisual lite (essenciais) + NFe + Jana 200 perguntas/m. Q1/27 março outreach.

---

## Plano discovery call (30min — perguntas-chave)

**Estrutura padrão pros 3 candidatos:** introdução 5min · operação atual 10min · dor 10min · próximo passo 5min.

### 1. Introdução (5min)

- "Wagner aqui, da WR Sistemas. Você é cliente OfficeImpresso há X anos. Tô construindo um sistema novo, oimpresso.com, que mantém TUDO seu histórico Delphi e adiciona NFe automática + IA conversacional. Quer ouvir 25 minutos?"

### 2. Operação atual (10min) — entender o JOB-TO-BE-DONE

- **Q1.** "Como vocês orçam hoje? Planilha Excel? Calculadora? Direto no Delphi?"
- **Q2.** "Quem decide preço m² substrato — você? Vendedor? Tabela fixa?"
- **Q3.** "Quantas OS/mês em média? Quantos plotters? Quantos operadores?"
- **Q4.** "Como acompanha produção — quadro físico? WhatsApp? Sistema?"
- **Q5.** "NFe emite manual ou automatizado? Quanto tempo gasta financeiro/dia?"

### 3. Dor (10min) — ADR 0105 sinal qualificado

- **Q6.** "Última semana — qual operação te custou mais tempo que devia? Por quê?"
- **Q7.** "Se você pudesse magicamente apertar um botão e resolver UMA coisa amanhã, qual seria?"
- **Q8.** "Você considerou trocar de sistema nos últimos 12 meses? Por quê SIM/NÃO?"
- **Q9.** "Já viu Mubisys/Zênite/Calcgraf? Que vibe deu?"
- **Q10.** "Quanto pagaria/mês por um sistema que resolve 80% do que te incomoda HOJE?"

### 4. Próximo passo (5min)

- "Vou mandar um vídeo 5min mostrando como ROTA LIVRE (cliente piloto vestuário) opera Jana IA + NFe automática. Se fizer sentido, marcamos demo 45min com Felipe."
- "Sem pressão. Você fica no OfficeImpresso até decidir. Migration Factory recupera 100% histórico."

---

## Critério qualificação ADR 0105 (sinal > hipótese)

Cliente vira **Tier A** (top prioridade piloto) APENAS se:

- ✅ **(a)** Cliente RESPONDE call discovery 30min E **reporta dor concreta** (Q6/Q7/Q8 com resposta tangível) OU
- ✅ **(b)** Cliente JÁ PAGOU plano oimpresso E reporta problema operacional OU
- ✅ **(c)** Métrica detecta drift (latência > 5s legacy, erros > 1%, churn risk)

**Sem sinal → cliente fica Tier B (não migrar especulativo).** Não fechar contrato baseado em chute "ele é grande, deve querer".

### Sinais de NÃO-qualificação (skip imediato)

- ❌ Resposta "tá tudo bem, não tenho nenhuma dor" → cliente satisfeito, não vende
- ❌ "Já fechei com Mubisys/Zênite contrato 24m" → respeitar contrato; outreach D+90 só se Mubisys deteriorar
- ❌ Cliente menciona preço como ÚNICO critério → não fit (oimpresso vende confiança + diferencial, não preço)
- ❌ Cliente exige feature que NÃO está no roadmap Modules/ComunicacaoVisual Sprint 1-4 → não forçar; backlog ADR feature-wish

---

## Pré-requisitos antes de qualquer outreach (Wagner aprova)

1. ⏳ **Snapshot financeiro** dos 3 top candidatos via skill [officeimpresso-financial-snapshot](../../../.claude/skills/officeimpresso-financial-snapshot/SKILL.md) — confirma receita real + ticket pago histórico + recência updates Delphi
2. ⏳ **Sprint 1 Modules/ComunicacaoVisual verde** (4 features P0: cálculo m² + cadastro substrato + NFe-de-boleto + PCP Kanban CV-vocabulário) — sem isso, demo frustra
3. ⏳ **Validar identidade Cliente B (Gold)** — registry vs Mubisys post-mortem 2026-05-09 ([04-gold-comvis](../../research/clientes-legacy-officeimpresso/04-gold-comvis/01-perfil.md))
4. ⏳ **Confirmar vertical real** de cada candidato via Firebird `SELECT * FROM EMPRESA` (cidade/UF/CNAE inferido) — alguns aliases ambíguos
5. ⏳ **Battle card atualizado** (Mubisys/Zênite/Calcgraf) — defender pelo wedge competitivo, não pelo preço

---

## TBD — items pra pesquisar antes de discovery

| TBD | Como obter | Skill/ferramenta |
|---|---|---|
| Cidade/UF/CNAE real cada cliente | Firebird `SELECT RAZAO_SOCIAL, CIDADE, UF, CNAE FROM EMPRESA` | skill `officeimpresso-source-analysis` ou direto via DSN |
| Faturamento últimos 12m | `SELECT SUM(VALOR_TOTAL) FROM VENDAS WHERE DATA > DATEADD(-12 MONTH, CURRENT_DATE)` | skill `officeimpresso-financial-snapshot` |
| Identidade Cliente B (Gold) — mesmo do Mubisys post-mortem? | Wagner cruza CNPJ registry vs CNPJ post-mortem | Wagner [W] manual |
| Tier comercial atual (Starter/Pro/Enterprise OfficeImpresso pago) | Extrato banco Wagner cruzado com cliente | Wagner [W] manual |
| Versão Delphi Cliente F (Produart) | Sample `VERSAO_BANCO` Firebird ([matriz Tier B](../../reference/matriz-conhecimento-clientes-legacy.md#tier-b--sampled-versao_banco-4-candidatos-comvis-pendentes)) | skill `officeimpresso-source-analysis` |

---

## Próximo passo imediato

**Wagner valida este ranking + critério ADR 0105.** Se aprovado:

1. Rodar snapshot financeiro Cliente A (Extreme) primeiro — desbloqueia outreach Q3/26 se Sprint 1 verde
2. Criar task MCP `tasks-create` por candidato top 3: discovery call + decisão + (se sinal) migração ([ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md))
3. Postergar Clientes D/E/F pra Q1/27+ — sem urgência, sem queimar relacionamento

---

**Última atualização:** 2026-05-16 · **Próximo review:** após Wagner aprovar ranking + 1º snapshot financeiro Cliente A executado
