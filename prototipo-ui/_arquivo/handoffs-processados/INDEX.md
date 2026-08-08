# Handoffs processados — lápide (origem → destino → por quê)

> **Append-only.** Nada aqui foi deletado — foi **movido** de `prototipo-ui/` (L-07/L-22: registro nunca se apaga, move com rastro).
>
> **Por que este dir existe:** o gate `npm run handoff:check` (§16 · IT8) varre `prototipo-ui/` **não-recursivamente** e acusa como **órfão** todo `PROMPT_PARA_CODE_*.md` que exista no dir sem estar citado na fila **ativa** de [`COWORK_NOTES.md`](../../COWORK_NOTES.md). Prompt que já pousou não tem por que voltar à fila ativa — a saída correta, que a própria mensagem do guard oferece, é **arquivar**. Este é o destino.
>
> **Antes de mover um prompt pra cá, prove que ele pousou** (§16 Regra 4: retorno em [`CODE_NOTES.md`](../../CODE_NOTES.md)) **ou que ficou stale** (o pedido já está no canon). Mover prompt de tarefa **viva** esconde trabalho — é o defeito que o gate existe pra pegar, cometido pela mão do arquivista.

## 2026-08-08 — os 3 órfãos de 2026-07-10 (IT8 vermelho 29 dias)

Os três nasceram em [#4096](https://github.com/wagnerra23/oimpresso.com/pull/4096) e [#4099](https://github.com/wagnerra23/oimpresso.com/pull/4099) e **nunca foram citados na fila** — nem acima, nem abaixo da linha d'água (`grep` = 0 em `COWORK_NOTES.md`). O gate acusou corretamente a partir de 2026-07-10: **última run verde 2026-07-03**, depois **75 `failure` e zero verdes**. Auditoria em [handoff 2026-08-08 21:53](../../../memory/handoffs/2026-08-08-2153-teste-07-auditoria-15-e-a-lane-revivida.md).

| prompt | origem | veredito | por quê |
|---|---|---|---|
| `PROMPT_PARA_CODE_DS-ESPELHAR-DOMINIO.md` | `prototipo-ui/` | **POUSOU** | `CODE_NOTES.md` 2026-07-10 responde: decisão **PATH 2 — curadoria intencional**, `/design-sync push` seria no-op. Zero mudança de valor, zero edit no app. Residual (**Opção A**: emitir companion `cockpit_domains.css`) é **Tier 0 aguardando [W]** — não é trabalho de [CL] pendente. |
| `PROMPT_PARA_CODE_ESTRUTURA-COWORK-ATUALIZADA.md` | `prototipo-ui/` | **POUSOU** | `CODE_NOTES.md` 2026-07-10 responde e **recusa conscientemente**: atualizar o protocolo de retorno afirmaria estado inexistente no git e no DS vivo (*"seria mentir"*). Recusa fundamentada **é** processamento. |
| `PROMPT_PARA_CODE_DS-DOMINIO-RETIRAR-DSV6.md` | `prototipo-ui/` | **STALE — e enfileirar seria REGRESSÃO** | Pedia adicionar os tokens de domínio (`--canal-*`, `--kind-*-soft`, `--kpi-feature-*`, `--origin-*`) ao SSOT. **Medido em 2026-08-08 direto no `resources/css/tokens/semantic.tokens.json`**: as famílias **já estão lá** (canal 38 · kind 26 · kpi-feature 17 · origin 32 · sla 61 · stage 23). Pior: os valores do prompt são os do `ds-v6` do Cowork e o canon **venceu** — `origin CRM bg` no SSOT é `oklch(0.92 0.06 220)`, enquanto o prompt propõe `oklch(0.93 0.07 245)`. Executá-lo **sobrescreveria canon com valor superado**. |

**A lição do terceiro, que é a que importa:** ele era a *tarefa invisível* que o §16 Regra 1 existe pra impedir — e a leitura óbvia (*"órfão sem resposta ⇒ enfileira"*) estava **errada**. Foi a medição na fonte, não a prosa do `CODE_NOTES` nem o raciocínio sobre o gate, que mostrou que o pedido já estava atendido e que a "correção" regrediria o canon. **Órfão sem retorno não é automaticamente tarefa viva — pode ser tarefa que o mundo resolveu por outro caminho.**

**O que NÃO foi feito, de propósito:** `npm run handoff:baseline:write`. Congelar a dívida devolveria o job ao verde escondendo três true-positives — está barrado na §5 de [`PROCESSO_MEMORIA_CC.md`](../../PROCESSO_MEMORIA_CC.md) (entrada 2026-08-08).
