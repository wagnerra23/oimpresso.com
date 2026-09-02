# Jana — Ledger de Lições de Operação (Reflexion runtime)

> **O que é:** registro **append-only** dos erros de **operação/comportamento** da própria
> Jana — *não* dos erros de **saída** do LLM (alucinação/relevância), que o golden 30Q +
> RAGAS gate + drift sentinel já cobrem. É o padrão **Reflexion** (auto-reflexão verbal em
> memória persistente) aplicado ao *runtime* da Jana: cada erro vira lição, cada lição vira
> **check** (mecanizável) ou **regra sempre-lida** (julgamento).
>
> **Por que existe:** lacuna #1 da Jana no placar [CC]×Jana×Champion (Aprendizado-com-erro
> Jana ~6.5 vs [CC] ~9.0). O [CC] já tem um ledger das próprias lições; a Jana pegava só erro
> de saída. Este doc fecha o gêmeo *runtime* do ledger de design do [CC].
>
> **Natureza:** working doc **proposto** (§10.4) — vira canon quando [W] aprovar o PR.
> NÃO duplica `proibicoes.md` (proibições globais Tier 0) nem o `LICOES_CC` do design ([CC]).
> Escopo: erros de operação **da Jana/Modules/Jana**.

---

## Formato canônico (1 lição = 1 entrada `### L-OP-NNN`)

Cada lição nasce com `Graduação:` — esse é o coração do loop. O parser do
`jana:health-check` (check `jana_lesson_ledger_graduation`) lê estas entradas e falha
(advisory) se alguma estiver **malformada** ou **pendente**.

```
### L-OP-NNN · <título curto>
- **Data:** AAAA-MM-DD
- **Erro:** o que a Jana fez de errado (operação, não saída)
- **Sintoma:** como apareceu / como foi detectado
- **Regra:** a lição — o que passa a valer daqui pra frente
- **Ref:** ADR/PR/incident/arquivo que ancora
- **Graduação:** MEC · check:`<nome_do_check>` · status:done   ← mecanizável vira check
        ou
- **Graduação:** JULG · regra:`<ponteiro>` · status:done       ← julgamento vira regra sempre-lida
```

**Regras de graduação (espelham a 5ª camada do [CC] — `APRENDER-COM-ERRO`, agora no runtime):**

| Tipo | Significado | Destino | `status:done` exige |
|---|---|---|---|
| **MEC** | dá pra detectar por SQL/código determinístico | vira **check** no `jana:health-check` (igual `profile_distiller_drift`) | `check:` apontando um check existente |
| **JULG** | exige julgamento humano/contexto, não mecanizável | vira **regra sempre-lida** (SCOPE/BRIEFING da Jana ou skill) | `regra:` apontando o documento que carrega a regra |

`status:pendente` = lição registrada mas ainda **não** graduada → o check advisory acende
amarelo até alguém fechar (criar o check ou escrever a regra). Loop fechado por métrica
(Constituição v2 §4).

---

## Gatilho (quando registrar)

Quando um **incidente de operação da Jana** é resolvido — *não* um bug de resposta (esses já
têm golden/RAGAS) — append uma entrada aqui + atribua `Graduação:`. Operacionalizado pela
skill `incident-done-checklist` (Bloco D), que já é o ponto onde se declara um fix fechado.
Erro de operação ≠ feedback de cliente (`feedback-capture` cobre fricção de **saída/UX**).

---

## Lições

<!-- append-only · nunca editar/deletar entrada existente · só acrescentar abaixo -->

### L-OP-001 · Webhook MCP respondia 5xx por config cache stale
- **Data:** 2026-05-21
- **Erro:** `'mcp' => [...]` duplicado no `Modules/Jana/Config/config.php` + config cache stale fez `config('copiloto.mcp.sync_webhook_token')` retornar NULL → webhook GitHub→MCP respondia 500. SPEC.md/memory pushados não viravam task no DB; o time (Maiara/Felipe/Eliana) ficou sem ver tasks atribuídas, em silêncio.
- **Sintoma:** entregas 5xx no webhook; drift DB↔git invisível até alguém cobrar.
- **Regra:** drift de sync do MCP tem que ser **observável**, não descoberto por humano. Config duplicada é erro de operação, não de saída.
- **Ref:** incident US-FIN-043 (2026-05-21) · `HealthCheckCommand::checkMcpWebhookHealth2h`
- **Graduação:** MEC · check:`mcp_webhook_5xx_2h` · status:done

### L-OP-002 · ProfileDistiller parou e ninguém viu (Brain A 24h sem rodar)
- **Data:** 2026-05-13
- **Erro:** o job diário que regenera `jana_business_profile` parou; profiles ficaram >7d stale e o chat passou a responder com contexto velho — degradação silenciosa de operação (o LLM "acertava" a resposta sobre dados errados, então golden/RAGAS não pegavam).
- **Sintoma:** profile com `gerado_em` antigo; narrativa Brain A desatualizada.
- **Regra:** job de manutenção que para é incidente de operação — precisa de sentinela própria, não confiar que "se quebrasse alguém notaria".
- **Ref:** COPI-26 · `HealthCheckCommand::checkProfileDrift`
- **Graduação:** MEC · check:`profile_distiller_drift` · status:done

### L-OP-003 · Declarar "fechado" sem smoke real prod
- **Data:** 2026-05-28
- **Erro:** declarei fixes/PRs "fechados" com PR mergeado + Pest verde + deploy OK, mas **sem** smoke real ponta-a-ponta — 10.144 mídias seguiam órfãs em prod. Confundir "código em prod" com "funciona em prod" é erro de operação, não mecanizável por um único SQL (cada fix tem seu próprio smoke).
- **Sintoma:** Wagner cobrou "ainda não consolidou"; audit honesto revelou estado real divergente do declarado.
- **Regra:** sem Bloco B (smoke real prod) completo, status ≠ `done` — usar `awaiting-smoke`. É julgamento por-fix, não um check único.
- **Ref:** incident 2026-05-28 · skill `incident-done-checklist` (DoD-v1) · `PATTERN-INCIDENT-RESPONSE-VELOCITY.md`
- **Graduação:** JULG · regra:`.claude/skills/incident-done-checklist/SKILL.md` · status:done

### L-OP-004 · Flag local ligada na fila web não alcança o juiz CT 100
- **Data:** 2026-07-29
- **Erro:** o online-eval estava pronto para ser ligado por config, mas o job sem fila própria seria consumido no Hostinger, onde o hostname Docker `ollama-embedder` não existe. A flag poderia ficar `true` e produzir zero score.
- **Sintoma:** Hostinger tinha worker `default` saudável; CT 100 via o modelo local, mas não consumia a fila. Cada metade parecia verde isoladamente.
- **Regra:** feature que depende de runtime separado só está ligada quando emissão, transporte, consumidor e destino deixam recibo no mesmo circuito.
- **Ref:** US-COPI-137 · `JudgeTraceOnlineJob` · `docker/oimpresso-mcp/docker-compose.yml`
- **Graduação:** MEC · check:`online_eval_score_uptime_7d` · status:done

### L-OP-005 · Provedor LLM sem crédito derrubou a Jana e nenhum check nomeou a causa
- **Data:** 2026-09-02
- **Erro:** o crédito da conta do provedor acabou em 2026-08-31 ~20:02 e a Jana emudeceu — chat, sugestão de metas, PR UI Judge e Daily Brief pararam juntos. O `jana:health-check` viu só o sintoma, e um dos checks pintou **verde**: o `custo_brain_b_24h` mede `custo <= teto`, e custo zero por provedor morto passa no teto folgado. Gastar nada não é saúde — é a assinatura do silêncio.
- **Sintoma:** `brief_uptime_24h` STALE (último brief 2026-08-31 20:02:26) + `custo_brain_b_24h` `ok=true` com `in=0 out=0` tokens. A causa (`HTTP 429` · `insufficient_quota` / `credit_balance_exhausted`) só apareceu quando um humano abriu `storage/logs/brief-cron.log` à mão. Agravante medido no mesmo dia: a via de alerta do brief (`GenerateBriefCommand::alertOps` → `mcp_inbox`) está **morta em produção desde 2026-06-26** — a tabela não existe, então o `catch` só loga; 19 eventos datados no `laravel.log`. Ninguém seria avisado mesmo se o alerta tivesse disparado.
- **Regra:** métrica de **consumo com teto superior** não detecta parada — zero sempre passa em `<= X`; quem vigia dependência que pode morrer precisa de predicado de **presença**, não de limite. E toda dependência externa paga precisa de um check que pergunte ao **oráculo** ("a conta aceita a chamada?") em vez de inferir do rastro, porque o rastro pode nem existir: nos logs de prod o par (timestamp, motivo) **nunca coexiste na mesma linha**. Por fim, o alarme tem que **nomear a causa**: "o brief parou" manda o humano investigar; "o provedor está sem crédito" manda o dono decidir.
- **Ref:** `HealthCheckCommand::checkLlmProviderQuota` (FP medido 0/30 em 30d de log de prod) · US-COPI-145 (credencial é [W]) · US-COPI-135 (fallback ausente) · [PR #6540](https://github.com/wagnerra23/oimpresso.com/pull/6540) (a distinção entre os dois 429)
- **Graduação:** MEC · check:`llm_provider_quota` · status:done

---

## Refs

- Origem: sessão [CC] 2026-06-02 — comparação [CC]×Jana×Champion (view `rep-cc-vs-jana` no `metricas.html`); pesquisa Reflexion · Voyager · Letta · Zep/Graphiti.
- Proposta §10.4: [`memory/decisions/proposals/jana-ledger-licoes-operacao-reflexion.md`](../../decisions/proposals/jana-ledger-licoes-operacao-reflexion.md)
- Mecanismo: `Modules/Jana/Console/Commands/HealthCheckCommand.php` → check `jana_lesson_ledger_graduation`
- Constituição v2 §4 (loop fechado por métrica) · §8 (confiabilidade com fallback)
