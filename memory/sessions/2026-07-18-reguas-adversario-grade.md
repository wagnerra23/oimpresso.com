---
date: "2026-07-18"
topic: "Passe adversarial formal (regra 15) sobre a grade completa 2026-07-18 — 25 achados (13 SUSTENTADOS / 12 PARCIAIS / 0 DERRUBADOS); 2 notas invalidadas → rodada parcial; 1 CRÍTICO (grep declarado-não-rodado); simetria com o precedente 07-12"
authors: [C]
prs: []
outcomes:
  - "Workflow 5-atacantes→defesa-por-achado→juiz (31 agentes, ~4,9M tokens, 22 min) sobre a grade byte-exata, na base MEDIDA (commit 811da8e759 recriado). Placar: 25 achados — 13 SUSTENTADOS, 12 PARCIAIS, 0 DERRUBADOS (~20 defeitos únicos após dedup)."
  - "Simetria quase exata com o precedente 2026-07-12: de novo 9/11 notas sobrevivem, de novo a classe que caiu é número-sem-recibo + bookkeeping de janela. Melhorou: zero mecanismo inventado. Piorou: regra 8 (same-day) reincidiu pela 2ª rodada consecutiva, e o único CRÍTICO é classe nova — grep DECLARADO como evidência que, rodado, refuta a própria célula."
  - "CRÍTICO (existência/ausência): 'captura documental confirmada AUSENTE' era FALSO — BoletoOcrService (OpenAI Vision gpt-4o + Textract) shipped end-to-end (rota /financeiro/unificado/ocr-boleto + FinOcrBoletoSheet + Onda23OcrBoletoTest; git grep ocr → 85 hits). A evidência 'grep Financeiro/NfeBrasil sem captura' nunca foi rodada — família das lápides 2026-07-15 (varredura contada) e 2026-07-17 (recibo)."
  - "2 notas INVALIDADAS → rodada parcial obrigatória (re-score em prosa = anti-padrão): erp-ia-produto (o 'maior gap de produto' era parcialmente shipped) e evals-outcome (row-teto removido pro dono custo-eficiência + nota fundida trocada entre fraquezas)."
  - "ALTA: '+18,5% vs Zep' é o resultado publicado PELO PRÓPRIO Zep (LongMemEval, arXiv 2501.13956) colado no BiTemporalResolver como se fosse medição local — número emprestado sem recibo, 2 ocorrências. Corrigido pra 'mesmo modelo; empate de DESENHO, não de medida'."
  - "US-COPI-137: 'NÃO feito' era stale — #4460 (merge 07-17 16:00) shipou JudgeTraceOnlineJob + shouldSample + PiiRedactor com 6 testes CT100, atrás de flag OFF (decisão LGPD [W]). Atacada 4×, defesa unificou: nota 4 se mantém (mede MEDIÇÃO EFETIVA, não existência do mecanismo), mas o degrau 'construir amostra' virou 'ligar flag + juiz local' — o chip spawned nesta sessão já apontava certo por sorte, o texto da grade não."
  - "Placar-manchete 23/23 condicionado: fiel ao journal (contado), mas inauditável sem ledger enumerado + REFUTADO_TB nunca disparou em ~63 vereditos históricos → disclosure de poder discriminativo obrigatório no placar (família foundation-ratchet). Ledger criado: 2026-07-18-reguas-grade-ledger.md."
  - "Fase Verificar cobriu 24 DE 76 fraquezas — slice(0,24) SEM log() em reguas-do-sistema.js:130, viés 100% pró-eixo-1 (as 24 são todas de CONSTRUIR-E-GOVERNAR): a truncagem silenciosa que o próprio incidente 2026-07-17 já tinha catalogado, agora na Fase 5. Fix de máquina pendente (log + Counter por veredito + estratificar por dimensão)."
  - "2 regras novas candidatas pra skill (16: composição fiel ao journal na Fase Grade; 17: disclosure de poder do placar de integração) — nenhuma regra 8-15 nem lápide cobre a COMPOSIÇÃO. Decisão [W]."
related_adrs:
  - 0330-mapa-dos-niveis-estado-real-2026-07-constituicao
  - 0333-emenda-0330-eixo-rodar-e-observar-submedido
  - 0290-fidelity-lock-v0-recusado
  - 0318-ragas-eval-real-mata-tautologia-ct100-staging
---

# Adversário da grade 2026-07-18 — o sistema imunológico mordendo a própria grade

Passe adversarial formal (regra 15 da skill `reguas-do-sistema`: "grade sem passe adversarial próprio = modo de falha") sobre a [grade completa emitida hoje](2026-07-18-reguas-grade-completa.md). Harness: **5 atacantes por vetor** (recon same-day · números de máquina · existência/ausência · coerência/escopo/GATED · placar/integração com acesso ao journal dos 83 agentes originais) → **defesa por achado** (só derruba com comando+output; empate epistêmico = PARCIAL) → **juiz** (compõe, não re-julga). 31 agentes, ~4,9M tokens. Base = commit exato medido pela grade (`811da8e759`, worktree recriado).

## Placar

| Veredito | Total | CRÍTICA | ALTA | MÉDIA | BAIXA |
|---|---|---|---|---|---|
| SUSTENTADO | **13** | 1 | 2 | 7 | 3 |
| PARCIAL | **12** | — | 6→rebaixadas | 4 | 2 |
| DERRUBADO | **0** | — | — | — | — |

~20 defeitos únicos após dedup (US-COPI-137 atacada 4×, +18,5% 2×, contagem de hooks 2×).

## Veredito global do juiz (literal)

> A grade sobrevive — 9/11 notas de pé, zero mecanismo inventado e zero achado derrubado — mas sobrevive como sobreviveu a de 07-12: pelo mérito das verificações e apesar dos seus números, porque tudo que ela afirmou como "medido" sem recibo re-executável (OCR, +18,5%, 19 .ps1, kappa, 23/23, "pós-verificação") caiu ou precisou de muleta, e duas dimensões (erp-ia-produto, evals-outcome) só voltam a ter nota depois de rodada parcial — não de prosa.

## Os achados que mudam o retrato

1. **[CRÍTICO] OCR "AUSENTE" era falso.** `BoletoOcrService` shipped end-to-end. A evidência da grade ("grep Financeiro/NfeBrasil sem captura") **nunca foi rodada** — rodada, ela refuta a célula. O "maior gap de produto" da grade era parcialmente shipped; gap real = NF genérica, canal WhatsApp, classificação→lançamento, lado AR. → nota erp-ia-produto INVALIDADA, rodada parcial.
2. **[ALTA ×2] +18,5% emprestado.** Resultado publicado pelo próprio Zep (arXiv 2501.13956) apresentado como benchmark local. Empate bi-temporal é de desenho, não de medida.
3. **[ALTA] US-COPI-137 stale.** #4460 (29h antes da emissão) shipou a amostra de 5% — a grade mandava construir o que já existia. Nota 4 se mantém (mede medição efetiva); degrau corrigido pra "ligar" (flip [W] + juiz local).
4. **[MÉDIA] §4 dupla-Δ.** 3 linhas creditavam como "desde o último retrato" trabalho de 09/10-jul já creditado no D1 como "rodada anterior" — regra 12. §4 dividido em 4a (Δ real 17/18-jul) e 4b (correção de retrato stale).
5. **[MÉDIA] Same-day não reconciliada — reincidência da regra 8** (2ª rodada consecutiva). 6 merges entre o corte da base (07:11) e a emissão (20:38) fora do retrato; entre eles **#4522 (DR-2a bite-log dos gates de design CONSTRUÍDA — atualiza o contexto da lápide #4511: a infra agora existe, o veredito não muda)** e #4519 (IngestLivenessChecker). Nenhuma nota muda.
6. **[MÉDIA] Placar 23/23 inauditável + [PARCIAL] poder discriminativo.** Composição fiel ao journal (contado: 23/23, zero suprimido), mas: (a) só 12 das 23 claims eram identificáveis no artefato → ledger enumerado criado ([grade-ledger](2026-07-18-reguas-grade-ledger.md) — re-contagem confirma 23 refutadores/23 integrações 1:1 com o placar); (b) REFUTADO_TB **nunca disparou** — re-contagem independente do ledger: **0 em 81 vereditos** (8 runs 07-10→07-18 = 58 anteriores + 23 desta; o "~63" do juiz re-contado em 58, diferença registrada sem reconciliação forçada) → o teste de integração pode ser carimbo; disclosure obrigatório junto ao placar. O prompt de integração embute o contexto BR na pergunta — reformular = emenda da lápide §5 2026-07-10, decisão [W]. **Colateral do ledger que reforça a regra 16:** o placar publicado do retrato 2026-07-10 ("0/2/3/3") também divergia do próprio journal (9 refutadores contados) — a classe composição≠journal não é desta rodada, é recorrente.
7. **[PARCIAL ×2] "Pós-verificação" era 24/76.** `fraquezas.slice(0, 24)` sem `log()` cortou a fase Verificar — e por ordem do DIMS, as 24 verificadas são 100% do eixo 1; os eixos 2-3 (justamente os "buracos reais") ficaram em baseline + spot-check. O "24/24 tinham algo existente" é propriedade da amostra enviesada, não do sistema. Fix de máquina pendente.
8. **[SUSTENTADO] Fusão de nota trocada.** "Enforcement só no merge — 8": título de uma fraqueza com a nota de outra (o verificador do título deu 6,5). Desfundido em 2 linhas.
9. **[SUSTENTADOS menores]** "23 .mjs + 19 .ps1" não reproduz em nenhum oráculo (vivo: 39+24 trackeados / 23+18 wired); "kappa>0,6" sem fonte-data (agora citada); dupla-contagem custo×outcome em 2 dimensões (dono único: custo-eficiência); chip 8 sem a trava 0105 na metade semantic-layer; session log com 2 afirmações não sustentadas ("só observabilidade-agente"; "evidência file:line").

## Regras novas candidatas (decisão [W] — skill edit via PR)

- **Regra 16 — Composição fiel ao journal:** cada fraqueza verificada = 1 linha com a nota do SEU verificador; fusão usa a MENOR nota e declara inline; linha vive na dimensão DONA do escopo; nota nunca migra entre fraquezas. (Análogo, na Fase Grade, do que a regra 12 é pra janela de Δ.)
- **Regra 17 — Disclosure de poder do placar:** placar adversarial (REFUTADO_TB etc.) só publicável com o histórico de negativos do branch; 0 negativos em N rodadas obriga disclaimer no próprio placar. (Distinto da lápide foundation-ratchet: aqui é apresentação obrigatória, não promoção.)

Cobertos por regra/lápide existente (NÃO criar): same-day (regra 8), dupla-Δ (regra 12), placar órfão (regra 9), "NÃO feito" vs construído-parado (regras 10+14), recibos (+18,5%/kappa/hooks/grep → lápides 07-15 e 07-17), slice silencioso (doutrina anti-truncagem do próprio workflow — bug de cobertura), chip 8 (ADR 0105).

## Encaminhamentos

| Item | Estado |
|---|---|
| Rodada parcial `erp-ia-produto` + `evals-outcome` (base fresca `0c80c51936`, escopos com correções embutidas) | **CONCLUÍDA** — erp-ia 5,2 · evals-outcome 5,4; bônus: 1ª vez que ACIMA_CONFIRMADO dispara (2 de 4 claims novas, com limite — ver grade-completa §Rodada parcial); 1ª tentativa caiu no limite de sessão e expôs crash anti-null (fixado no PR #4546); resume do cache recuperou os 9 agentes pagos |
| Ledger de proveniência `2026-07-18-reguas-grade-ledger.md` | criado nesta sessão |
| Correções textuais (18 itens do juiz) no session log da grade + Artifact | aplicadas nesta sessão |
| Fix de máquina no `reguas-do-sistema.js` | **cap estratificado JÁ EM MAIN via #4542** (sessão paralela do mesmo chip entregou primeiro — padrão sessões-paralelas conhecido); meu resgate #4546 ficou CONFLICTING e foi fechado superseded; a peça única remanescente (guarda anti-null — crash real do resume) seguiu no **PR #4547** (required todos verdes; único vermelho = `module-grades-gate` advisory ADR 0314 D-1, regressão de módulo pré-existente alheia ao diff de 3 linhas). Dry-test 76×11 do cap rodado e verde (nenhuma dimensão zerada) |
| Regras 16-17 na skill | pendente — decisão [W] |
| Branch órfã `claude/jana-online-eval-copi137` (chip online-eval, 5 commits/582 linhas: liga eval + juiz Ollama + testes lane sqlite) | **CONFLITO DE ROTA com PR #4496 pré-existente** (`copi-137-judge-local`, rota B zero-egress, aberto 07-18 00:24 UTC) — duas implementações independentes do mesmo juiz; decisão [W] qual rota segue; NÃO abri 2º PR pra não duplicar |
| Emenda da lápide §5 2026-07-10 (pergunta de integração com braço discriminativo) | pendente — decisão [W] |

## Método (reprodução)

Atacantes receberam as regras de evidência das lápides (saída-vazia≠ausência, varredura contada, required = `required-checks-baseline.json`, dono-de-schedule = runtime). Defensores: derrubar SÓ com reprodução; retórica proibida. Juiz: compõe sem re-julgar; contradição entre vereditos é apontada, não resolvida (caso real: #16×#18 sobre evals-outcome → resolvida pela rodada parcial, não por prosa). Journal do run auditado: `wf_e0a3d488-20c`; journal do adversário: `wf_70cacc02-920`.
