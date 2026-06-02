# Jana ganha um ledger de auto-reflexão de erros de OPERAÇÃO (Reflexion runtime) — 2026-06-02

> **Status:** PROPOSAL §10.4 · **não-canon, não-ADR** · número de ADR **não cunhado** (soberania [W], ADR 0238 — [W] numera se promover).
> **Tipo:** evolução de módulo (Modules/Jana) · **Tier 0** (toca governança de módulo) → PR aberto, **espera [W]**.
> **Autor:** [CL] Claude Code · **Origem:** peer-review (L-17) do pedido [CC] `PROMPT_PARA_CODE_JANA-LICOES-REFLEXION.md` (sessão 2026-06-02, comparação [CC]×Jana×Champion — view `rep-cc-vs-jana` do `metricas.html`).
> **Natureza:** peer-review, não ordem — [CL] avaliou se procede e onde mora melhor.

---

## 1. Veredito do peer-review: **procede** (aditivo, alto ROI, espelha padrão já aceito)

O achado é real e checável contra `origin/main`:

- A Jana já é estado-da-arte em erro de **saída** do LLM: golden de alucinação 30Q + RAGAS gate bloqueante + drift sentinel. ✅
- A Jana **não tinha** o que o [CC] tem: um ledger dos próprios erros de **operação/comportamento** que **gradua cada um** em check ou regra — o padrão *Reflexion* (auto-reflexão verbal em memória persistente) aplicado ao runtime. ❌ → lacuna #1 no placar (Aprendizado-com-erro: Jana ~6.5 vs [CC] ~9.0).

Não é mecanismo novo a construir: a infra de graduação **já existe** (`jana:health-check` é o harness; basta mais um check, igual `profile_distiller_drift`). O salto champion (Voyager) é a lição virar **artefato verificável**, não prosa — e o destino de toda lição MEC aqui já é um **check**. Atende os 4 testes da meta-skill (substitui memória humana do erro · repetitivo a cada incidente · ROI = erro evitado + onboarding · acelera autonomia).

## 2. Passo 0 contra `origin/main` fresco (`de72198ae`) — onde mora, sem duplicar

| Verifiquei | Achado | Decisão |
|---|---|---|
| `memory/LICOES_CC.md` / `APRENDER-COM-ERRO.md` | **Não estão no canon** — só em `prototipo-ui/_BACKUP-NAO-USAR/...` (design/[CC]) | Não duplico. O ledger da Jana é o **gêmeo runtime**, novo, escopo Jana. |
| `memory/proibicoes.md` (34 KB, proibições globais Tier 0) | Cobre proibição **global**, não lição de operação de módulo | Não duplico. Regras JULG **globais** poderiam ir lá; JULG **escopo-Jana** vão no SCOPE/BRIEFING da Jana. |
| `Modules/Jana/Console/Commands/HealthCheckCommand.php` | 10 checks duros + advisory (CharterHealthChecker); flag `advisory` já existe | **Estendi** com 1 check advisory — não recriei harness. |
| `incident-done-checklist` / `feedback-capture` (skills) | Existem | **Estendi** (Bloco D / nota de fronteira) — não recriei. |
| Erros já graduados | `mcp_webhook_5xx_2h` (L-OP-001) e `profile_distiller_drift` (L-OP-002) **já são checks** em `main` | Seed do ledger ancora em fatos do `main` → check verde no dia 1. |

**Home canônico escolhido:** `Modules/Jana/LICOES-OPERACAO.md` (vive com o módulo, append-only, irmão runtime do ledger de design do [CC]). Lido pelo `jana:health-check` via `base_path()`.

## 3. O que entra neste PR (tudo aditivo)

1. **`Modules/Jana/LICOES-OPERACAO.md`** — ledger append-only. Formato `### L-OP-NNN` · Erro · Sintoma · Regra · Ref · **Graduação** (MEC→check / JULG→regra). Seed: 3 lições reais do `main` (webhook 5xx, profile drift, declarar-done-sem-smoke).
2. **`jana:health-check` → check `jana_lesson_ledger_graduation`** (advisory) — parser determinístico valida o **loop de graduação**: acende amarelo se alguma lição está malformada ou `status:pendente`. Não derruba cron (drift de processo não pagina à noite). `parseLessonLedger()` é estático/puro → testável.
3. **Pest** — 4 testes do parser (bem-formado / pendente→overdue / malformado / ledger canônico verde) + presença do check no smoke.
4. **`Modules/Jana/SCOPE.md`** — registra o ledger + o check no `contains:`.
5. **Skills (estendidas, não recriadas):** `incident-done-checklist` ganha **Bloco D** (gatilho: incidente de operação resolvido → append + graduar); `feedback-capture` ganha nota de fronteira (operação ≠ fricção de cliente).

## 4. O loop de graduação (a 5ª camada [CC], agora no runtime)

```
incidente de operação resolvido
        │  (incident-done-checklist Bloco D)
        ▼
append no ledger  ──►  Graduação:
                         ├─ MEC  → vira check no jana:health-check  (igual profile_distiller_drift)
                         └─ JULG → vira regra sempre-lida (SCOPE/BRIEFING Jana ou skill)
        │
        ▼
check advisory jana_lesson_ledger_graduation acende amarelo
enquanto houver lição pendente/malformada  (loop fechado por métrica · Constituição v2 §4)
```

## 5. O que NÃO é este pedido (anti-L-09/L-12 — não reprocessado)

- ❌ Guard de higiene Cowork (`no-new-root-html`, L-07/11/21/22) — já pendente ("Loop de graduação de lição"). Este é o **gêmeo runtime**, não substituto.
- ❌ Collector CT 100 + OTel + LGPD purge (#2073) — já pendente, falta só ENABLE Tier 0 de [W]. A comparação champion **confirma**; não adiciona trabalho.
- ❌ Score/rubrica de design (`design:review` #2078) — já em `main`.

## 6. Decisão aberta pra [W] (Tier 0)

1. **Aprovar** o ledger como mecanismo canônico da Jana (vira canon ao mergear) — ou ajustar o home.
2. **Numerar ADR** se quiser elevar de proposal a decisão (soberania [W] — não cunhei número).
3. Confirmar que o check certo é **advisory** (não derruba cron) — minha recomendação é sim.

— [CL], 2026-06-02. Não mergeei (Tier 0 · publication-policy). Retorno em `prototipo-ui/CODE_NOTES.md`.
