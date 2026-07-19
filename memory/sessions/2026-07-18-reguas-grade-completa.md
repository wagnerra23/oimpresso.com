---
date: "2026-07-18"
topic: "Grade de réguas COMPLETA (11 dimensões, 3 eixos) vs acima-do-mercado — placar 0 acima-de-categoria / 23 à-frente-por-integração; buraco real = RODAR-E-OBSERVAR + SERVIR-O-NEGÓCIO; corrigida pós-adversário (25 achados; 2 notas re-medidas)"
authors: [C]
prs: []
outcomes:
  - "Rodada COMPLETA das 11 dimensões (3 eixos) via workflow reguas-do-sistema.js — 83 agentes, 11,4M tokens, 37 min, base _reguas-base-20260718 (HEAD 811da8e7). A novidade desta rodada é o retrato ÚNICO das 11 numa base fresca + placar integrado — NÃO o primeiro retrato da maioria: 07-17 teve 5 sessions de grade parcial (obs-agente · memória pós-C8/C12 · RODAR+SERVIR 5 dims · design→código · truncagem-silenciosa), ~8 das 11 dimensões já tinham retrato de ≤1 dia."
  - "Placar honesto (2 colunas, anti-'0 acima'): 0/23 acima-de-categoria · 23/23 à-frente-por-integração · 18 empatadas na peça · 5 refutadas na peça (viram integração no todo). CONDICIONADO pelo adversário: inauditável sem ledger enumerado (criado: 2026-07-18-reguas-grade-ledger.md, números CONTADOS do journal — 23 refutadores/23 integrações batem 100% com o placar) + disclosure de poder discriminativo: REFUTADO_TB nunca disparou — re-contagem independente = 0 em 81 vereditos (8 runs 07-10→07-18: 58 anteriores + 23 desta; o '~63' do juiz re-contado em 58, diferença registrada sem reconciliação forçada). ATUALIZAÇÃO pós-rodada-parcial: 4 claims novas → 2 ACIMA_CONFIRMADO com limite (1ª vez no histórico) + 2 DIFERENCIAL_SISTEMA — consolidado do dia: 27 claims → 2 acima-com-limite · 25 à-frente-por-integração · 0 refutadas inteiras."
  - "PASSE ADVERSARIAL same-day (regra 15) — 25 achados: 13 SUSTENTADOS / 12 PARCIAIS / 0 DERRUBADOS; 9/11 notas de pé; 1 CRÍTICO (grep declarado-não-rodado); 2 notas invalidadas re-medidas por rodada parcial. Detalhe: memory/sessions/2026-07-18-reguas-adversario-grade.md."
  - "Padrão 7/9 reincidiu em escala — mas com assimetria que o adversário expôs: a fase Verificar cobriu 24 DE 76 fraquezas (slice(0,24) sem log, bug do workflow), 100% do eixo CONSTRUIR-E-GOVERNAR; os eixos 2-3 ficaram em baseline (#4494/#4482/#4433/#4485) + spot-check do compositor. O '24/24 tinham algo existente' é propriedade da amostra, não do sistema."
  - "Eixo 1 CONSTRUIR-E-GOVERNAR forte: spec-gov 6,5 · design→código 7,5 · memória 7,5 · orquestração 6,5 · evals-outcome 5,4 re-medida (era 6,0 invalidada; rows dono-único). Flip mais barato do IA OS: [W] rotular 12 status destrava a calibração do juiz (#4507 selada)."
  - "Eixo 2 RODAR-E-OBSERVAR = o buraco real: qualidade-drift-IA-prod 4 (a resposta da Jana ao cliente segue operacionalmente não-medida — a MÁQUINA existe: US-COPI-137 construída-e-testada #4460, JudgeTraceOnlineJob + PiiRedactor, porém DESLIGADA por decisão LGPD [W]; nota 4 mede a medição EFETIVA) · segurança 5 · custo-eficiência 5 (dono único do row custo×outcome) · observabilidade 6,5."
  - "Eixo 3 SERVIR-O-NEGÓCIO: erp-ia-produto 5,2 re-medida (era 5,0 invalidada; 'captura AUSENTE' era FALSA — BoletoOcr + DFe SEFAZ automática shipped; PurchaseXmlController completo porém ÓRFÃO sem rota) · inteligência-de-negócio 5. IBS/CBS: SEM via de entrada nenhuma (nem manual — NAO_EXISTE 3,0; prazo 03/08 morde CRT 3; pilotos são Simples). Leitura: 5,2 por último-quilômetro, não por falta de motor."
  - "Nenhuma lápide §5 inédita da grade em si; o ADVERSÁRIO propôs 2 regras novas de skill (16 composição-fiel-ao-journal · 17 disclosure-de-poder-do-placar) — decisão [W]."
  - "Mapa 0330 STALE re-confirmado, com correção do adversário: hooks trackeados = 39 .mjs + 24 .ps1 (git ls-files); wired em settings.json = 23 .mjs + 18 .ps1 (medido @811da8e7) — não os '43 .ps1 + 12 .mjs' do 0330 NEM o '23+19' da 1ª emissão da grade. 'tier skill 4 fontes' fechado (#4032); 'custo sem número' falso (#4491). Indexar via emenda/sucessora — NÃO criar paralelo (§5)."
related_adrs:
  - 0330-mapa-dos-niveis-estado-real-2026-07-constituicao
  - 0333-emenda-0330-eixo-rodar-e-observar-submedido
  - 0329-doutrina-documentacao-executavel-fonte-unica
  - 0290-fidelity-lock-v0-recusado
  - 0318-ragas-eval-real-mata-tautologia-ct100-staging
  - 0105-cliente-como-sinal-guiar-sem-mandar
---

# Grade de réguas COMPLETA — IA OS oimpresso vs acima-do-mercado (2026-07-18)

Rodada COMPLETA das **11 dimensões** (3 eixos) da skill `reguas-do-sistema`, disparada por pedido do Wagner. Workflow — **83 agentes · 11,4M tokens · 37 min**, base `_reguas-base-20260718` (HEAD `811da8e7`). No mesmo dia, **passe adversarial formal** (regra 15; [session dedicada](2026-07-18-reguas-adversario-grade.md)) com 25 achados — este log incorpora as correções do juiz; a 1ª emissão (pré-correção) vive no output do workflow `wf_e0a3d488-20c`, auditada byte-exata pelo adversário.

Contraste com 07-17: **5 sessions de grade parcial** no dia anterior (~8 das 11 dimensões com retrato de ≤1 dia) — a novidade aqui é o retrato único integrado, não o primeiro retrato. Método: pesquisa → refutação slice-a-slice → integração → verificação no repo vivo. Nenhuma nota sem evidência de arquivo/PR verificada no repo vivo (pares arquivo:linha existem só no output bruto do workflow, efêmero, fora do git).

## Placar honesto (2 colunas — regra anti-"0 acima")

| Coluna | Contagem |
|---|---|
| Acima-de-categoria (ACIMA_CONFIRMADO) | **0 / 23** — nenhuma peça isolada é sem-par |
| À-frente-por-integração (DIFERENCIAL_SISTEMA) | **23 / 23** — o TODO recursivo auto-aplicado em ERP vertical BR em prod não tem par |
| Empatadas na peça | 18 |
| Refutadas na peça | 5 (todas viram integração no todo) |

**Proveniência e limites do placar (exigência do adversário):** enumeração claim-a-claim com veredito no [ledger](2026-07-18-reguas-grade-ledger.md) — números CONTADOS do journal: 23 refutadores (5 REFUTADO + 18 EMPATADO + 0 ACIMA) e 23 integrações pareadas 1:1, **batem 100% com o placar publicado**. O delta vs "26 claims de 07-10" é **impossível 1:1** (claims re-geradas por rodada, sem identidade persistente; o "26" é irreproduzível dos journals — delta entregue por famílias temáticas: 10 persistem, 4 saíram, 9 entraram). Disclosure: REFUTADO_TB **nunca disparou** — 0 em **81 vereditos contados** (8 runs com fase Integração, 07-10→07-18: 58 anteriores + 23 desta; o "~63" do juiz re-contado em 58, diferença registrada); o valor informativo está nas RAZÕES por claim, não no binário. Reformular a pergunta de integração com braço discriminativo = emenda da lápide §5 2026-07-10, decisão [W].

As 5 refutadas-na-peça desta rodada (não re-alegar como categoria): registro de refutações de processo (ProjectMem/GateMem — refutador da peça; Lore arXiv 2603.15566 é par direto citado na claim-irmã de memória), doutrina anti-presence-gate, gate-selftest, heartbeat lendo a API do destino, online-eval PII-redigida.

## Notas por dimensão (pós-adversário)

| Eixo | Dimensão | Nota |
|---|---|---|
| Construir-e-governar | spec-governança | 6,5 |
| Construir-e-governar | design→código | 7,5 |
| Construir-e-governar | memória-conhecimento | 7,5 |
| Construir-e-governar | orquestração-adversarial | 6,5 |
| Construir-e-governar | evals-outcome | **5,4** _(re-medida; era 6,0 invalidada)_ |
| **Rodar-e-observar** | observabilidade-agente | 6,5 |
| **Rodar-e-observar** | **qualidade-drift-IA-prod** | **4,0** |
| **Rodar-e-observar** | segurança-do-agente | 5,0 |
| **Rodar-e-observar** | custo-eficiência | 5,0 |
| **Servir-o-negócio** | ERP-IA-produto | **5,2** _(re-medida; era 5,0 invalidada)_ |
| **Servir-o-negócio** | inteligência-de-negocio | 5,0 |

_As 2 re-medições saíram da rodada parcial `args.dimensoes` (base fresca `0c80c51936`, escopos com as correções embutidas; 26 agentes, ~5,8M tokens somando a 1ª tentativa) — regra da skill: nota invalidada não volta por prosa._

## Rodada parcial pós-adversário — resultado (wf_36116d6d)

- **erp-ia-produto 5,2** (média de 10 fraquezas verificadas): o produto está em 5,2 **por último-quilômetro, não por falta de motor** — OCR, DFe SEFAZ automática (agendada daily 06:15), sugestão+aceite (ADR 0236), FSM e chassi decisório (DecisionRouter) existem; nenhum ciclo fecha ponta-a-ponta como Conta Azul/Omie/BC vendem. Destaques verificados: `PurchaseXmlController` **completo porém ÓRFÃO sem rota**; IBS/CBS **sem via de entrada nenhuma** (nem manual — NAO_EXISTE 3,0, prazo 03/08 morde CRT 3); WhatsApp-IA 3,0 (placeholder SPRINT 3, E4 adiada por ROI [W]); IA-em-form existe em ≥4 forms, falta no de PRODUTO.
- **evals-outcome 5,4** (6 rows, dono-único respeitado): juiz não-calibrado 5,0 (rodada 2 SELADA #4507 — **falta só [W] rotular 12 status, o flip HITL mais barato do IA OS**); trajectory 3,0 (`MetricasReflexivasCommand` pontua tool-calls porém SEM INVOCADOR — chokepoint fantasma §5); goal-based 6,0 (harness + smoke real 07-17; falta task-set 20-50 ratificado [W]); trend 6,5 (US-GOV-052 já existe — claim da pesquisa ~40% stale).
- **PLACAR ATUALIZADO — 1ª vez que ACIMA_CONFIRMADO dispara no histórico:** 4 claims novas submetidas → **2 ACIMA-de-categoria COM LIMITE** + 2 DIFERENCIAL_SISTEMA. (a) *Governança de IA embarcada em ERP PME BR* (o TODO AiUsageLog+PiiRedactor+health-check-PII+online-eval) — **correção obrigatória:** ERPFlex é par PARCIAL no pilar redaction; a sub-claim "categoria publica 0% governança" é **FALSA e não re-alegável** (fonte erpflex.com.br, 2026-07-18). (b) *Medidor de outcome com gaps machine-readable* (`--json.gaps` G1-G5 + `confianca` + selftest-armadilha) — limite: ausência-de-par em **4 buscas dirigidas, não prova exaustiva**; reabre se vendor publicar caveat-field per-metric. Consolidado do dia: **27 claims → 2 acima-com-limite · 25 à-frente-por-integração · 0 refutadas inteiras**.
- **3 candidatos a §5 desta rodada (decisão [W]):** (1) claim "PME BR publica 0% governança" morta como formulada; (2) não re-inflar "memória per-tenant" como categoria (EMPATADO — Omie ~1.000 pagantes; silêncio-público ≠ ausência); (3) não re-inflar "armamento por medição repetida" como categoria (EMPATADO — Buildkite).
- **Padrão dominante: 15/16 fraquezas eram "existia-mas-invisível"** — o chip mais barato da rodada é **indexar no mapa 0330-corrente** (payload `onde_indexar` anexo às verificações), senão a próxima grade re-descobre os mesmos gaps falsos pela 3ª vez.
- 15 chips (C1-C15) com ressalvas embutidas no output do workflow; top-3 por alavanca: [W] rotular 12 status (calibração) · decisão juiz local-vs-openai (flip US-COPI-137) · via de entrada `c_class_trib` (urgência fiscal CRT 3).

## Correções do adversário incorporadas (resumo — detalhe na session adversarial)

1. **[CRÍTICO] OCR:** "captura documental AUSENTE" era falso — `BoletoOcrService` (OpenAI Vision gpt-4o + Textract) shipped end-to-end (rota + UI + teste; git grep ocr → 85 hits). **Falha de medição registrada:** o grep declarado como evidência nunca foi rodado (família lápides 07-15/07-17). Gap real: NF genérica, WhatsApp, classificação→lançamento, AR.
2. **+18,5% vs Zep:** número publicado PELO Zep (arXiv 2501.13956), não medido aqui. Empate bi-temporal é de DESENHO; medição própria = `jana:recall-eval`.
3. **US-COPI-137:** construída-e-testada (#4460, 07-17) porém desligada (flag OFF, LGPD [W]) — "NÃO feito" era stale; degrau corrigido: ligar (flip [W] + juiz local Ollama), não construir.
4. **§4 "Já feito" dividido:** 4a = Δ real da janela (PRs 17/18-jul: #4491/#4488/#4495/#4500/#4492/#4489/#4511/#4479/#4474/#4512-14); 4b = pré-existente re-flagado pela pesquisa (correção de retrato stale, 09/10-jul: #4032/#4031/#4029/#4058 — já creditados na rodada anterior, NÃO é Δ desta janela).
5. **Same-day (regra 8, reincidência):** 6 merges entre corte da base (07:11) e emissão (20:38) fora do retrato — crédito: **#4522** (DR-2a bite-log dos gates de design CONSTRUÍDA — contexto da lápide #4511 atualizado: a infra existe; o veredito não muda, ledger com 0 mordidas) e #4519 (IngestLivenessChecker). Nenhuma nota muda.
6. **Dono único de escopo:** row custo×outcome pontua SÓ em custo-eficiência; fraqueza que caiba em 2 dims pontua no dono do escopo escrito, demais referenciam sem nota.
7. **Contagem de hooks:** trackeados 39 .mjs + 24 .ps1; wired 23 .mjs + 18 .ps1 (@811da8e7).
8. **kappa>0,6:** fonte agora citada (deepeval.com/guides/guides-llm-as-a-judge, acessado 2026-07-18); rodada 2 do C10 é flip HITL com prep 100% agent-executável (regra 14).
9. **IBS/CBS:** scaffold shipped (NT 2025.002 + flag #3774 + ADR 0321); classificação assistida ausente (0 hits); roubar #7 → alto ÷ médio-alto; chip 7 ganha "NÃO re-scaffoldar".
10. **Chip 8 / roubar #8:** trava ADR 0105 estendida às DUAS metades (semantic layer E sensor só com client_signal; CYCLE-BI-01 em planning, gate armado).

## Leitura fria (corrigida)

Zero diferenciais acima-de-categoria — toda régua isolada tem par 2026 —, mas 23/23 à-frente-por-integração: o TODO recursivo auto-aplicado num ERP vertical BR em produção não tem par publicado (placar condicionado ao ledger + disclosure). O eixo CONSTRUIR-E-GOVERNAR está forte; os eixos RODAR-E-OBSERVAR e SERVIR-O-NEGÓCIO seguem o buraco real — a resposta da Jana ao cliente segue **operacionalmente não-medida** (a máquina existe desligada, decisão LGPD [W]: o degrau é FLIP+juiz, barato), a captura documental é **parcial** (boleto AP shipped; falta NF genérica/WhatsApp/AR) e a IA fiscal tem scaffold sem assistência. As maiores alavancas continuam baratas ou legais — várias viraram flip em vez de construção.

## Top-8 o que roubar (pós-correção)

1. **Custo-por-outcome-verificado** (join `agent-cost-per-pr` × `outcome-metrics`) — SWE-bench — **alto ÷ baixo** (chip spawned, task em execução)
2. **LIGAR o online-eval da Jana** — flip `enabled` [W] + juiz local Ollama CT100 — Braintrust — **alto ÷ baixo (flip)** · médio pro juiz (chip spawned, task em execução)
3. **Verificador cross-model** nas grades Tier-0 — Amp Oracle — alto ÷ médio
4. **Calibração dos juízes (kappa)** — retomar rodada 2 pausada #4507 (flip HITL: faltam 12 rótulos [W]) — **alto ÷ baixo (flip)** · médio-alto pro programa completo
5. **Guardrail de custo pré-call** (cap por-tenant + REJECT/429) — MLflow Gateway — alto ÷ médio
6. **Captura documental — o DELTA** (NF genérica, WhatsApp, classificação→lançamento, AR; boleto AP já shipped) — Conta Azul/Odoo — alto ÷ **médio**
7. **IA fiscal IBS/CBS — classificação assistida** sobre o scaffold existente (NÃO re-scaffoldar) — Omie.IA — alto ÷ **médio-alto**
8. **Semantic layer da Jana** — Cube/Dataverse — alto ÷ alto — **gated ADR 0105** (não vira US sem client_signal)

## Rejeitados → proibições §5

Nenhuma lápide inédita da grade em si — becos já catalogados: "0 acima" slice-a-slice (07-10), peça-como-categoria (07-09/07-17), `component-registry-check` required (#4511 — contexto atualizado pelo #4522: infra DR-2a existe, veredito mantido), baseline do drift-sentinel (07-17), render-diff pareado CI (ADR 0290).

## Pendências de máquina e de decisão [W]

- **Fix do workflow** `reguas-do-sistema.js`: `slice(0,24)` sem `log()` na Fase Verificar (viés 100% pró-eixo-1) → log do descarte + Counter por veredito + estratificar por dimensão (ou cap 76). PR proposto.
- **Regras 16-17 da skill** (composição fiel ao journal · disclosure de poder do placar) — decisão [W].
- **Emenda da lápide §5 2026-07-10** (braço discriminativo na pergunta de integração) — decisão [W].
- **Mapa 0330 stale** — indexar correções via emenda/sucessora (ADR 0333 já proposto pro eixo).

## Artefatos

- **Artifact navegável** (dashboard corrigido pós-adversário) — publicado nesta sessão.
- [Session adversarial](2026-07-18-reguas-adversario-grade.md) (placar 13/12/0 + 18 correções + veredito do juiz) · [Ledger do placar](2026-07-18-reguas-grade-ledger.md).
- Outputs: grade original `wf_e0a3d488-20c` · adversário `wf_70cacc02-920` · rodada parcial `wf_36116d6d-76b`.
