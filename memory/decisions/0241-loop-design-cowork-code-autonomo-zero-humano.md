---
slug: 0241-loop-design-cowork-code-autonomo-zero-humano
number: 241
title: "Loop design Cowork↔Code autônomo — humano sai do loop (gates CI no lugar de [W2]), não da supervisão; merge autônomo; Tier 0 fica humano"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
proposed_at: "2026-05-31"
decided_at: "2026-05-31"
module: governance
quarter: 2026-Q2
tier: CANON
tags: [cowork-loop, autonomia, ci-gate, pr-ui-judge, visual-regression, merge-autonomo, zero-humano, design-system]
related:
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0239-governanca-design-system-git-ssot-regressao-ia
  - 0240-task-ledger-git-native-cowork-code
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0238-soberania-constituicao-wagner
related_adrs: [0114, 0107, 0239, 0240, 0094, 0238]
amends_adrs: [0114, 0107]
supersedes: []
authors: [wagner, claude-code]
dossier: prototipo-ui/AUTOMACAO-LOOP-AUTONOMO.md
pii: false
---

# ADR 0241 — Loop design Cowork↔Code autônomo (humano sai do loop, não da supervisão)

> **Status: aceito** (Wagner, decisão de fato 2026-05-31 00:45 — registrada por [CL]; ratificada no merge).
> **Emenda:** ADR 0114 (gates **F2 screenshot** + **F4 merge** [W2]) + ADR 0107 (gate visual **F1.5**).
> **Playbook vivo:** [AUTOMACAO-LOOP-AUTONOMO.md](../../prototipo-ui/AUTOMACAO-LOOP-AUTONOMO.md) · reconciliação na lei: [PROTOCOL.md §2/§10.1](../../prototipo-ui/PROTOCOL.md) (PR #2030).

## Contexto

ADR 0114 formalizou o loop em **7 fases** com **gates humanos** de [W]: F1.5 critique, **F2 screenshot síncrono [W2]**, F3.5 a11y, **F4 merge [W2]**. Na prática — time de 1 pessoa + instâncias Claude — [W] virou **carteiro a cada hop**. O gargalo era o **número de transportes manuais**, não a qualidade.

Dois movimentos já tinham reduzido isso:

- **ADR 0239 + `PROTOCOL §10.4`** (2026-05-30): o gate de validação `[CL]` **não depende de [W]** — Cowork manda proposta, `[CL]` valida contra o git sozinho. Tirou [W] do caminho da *validação*.
- **2026-05-31 00:45** — [W], textual: *"essa vai ser o padrão? 0 humano? faça o automatismo, decida como ser melhor, pode usar a máquina o chrome. crie e documente e vá evoluindo até não ter mais intervenção humana."* — adotou **0 intervenção humana no loop**. Documentado em `AUTOMACAO-LOOP-AUTONOMO.md` (doc vivo).

Faltava **formalizar a decisão como ADR**: a lei (`PROTOCOL.md`) ainda dizia *"7-hop + [W2] manual + `--admin` proibido"*, contradizendo a prática (drift reconciliado no PR #2030). Este ADR **registra a decisão de [W]** e emenda os gates de 0114/0107.

## Decisão

**Princípio:** o humano sai do **loop**, não da **supervisão**. Cada gate humano vira gate **automático equivalente**; a supervisão continua por **transparência** (Constituição v2 §7) + **reversibilidade** (revert) + **Tier 0 sempre humano**. Nunca bypassa segurança.

### 1. Gates humanos → gates automáticos

| Gate 0114/0107 (antes) | Agora (autônomo) | Trava objetiva mantida |
|---|---|---|
| F1.5 critique `[CD]` (fase-ferry) | **auto-check de quem produz** (`[CC]` roda a crítica) | score **≥80** |
| F3.5 a11y `[CA]` (fase-ferry) | **auto-check de quem produz** (`[CL]` roda a a11y) | **WCAG 2.1 AA** |
| **F2 screenshot síncrono `[W2]`** | **CI: PR UI Judge (Claude Sonnet 4.5) + visual-regression** | review visual/UX automatizado + diff vs baseline |
| **F4 merge `[W2]`** | **merge autônomo `gh --admin` quando todos os checks *required* verdes** | CI verde **é** o gate; branch protection |

**Override de segurança:** critique <70 ou a11y crítica → escala revisão dedicada (o humano volta **só no caso ruim**).

### 2. Cadeia efetiva (0-humano)

```
F0 [W] brief
   → F1 [CC] design + auto-crítica + auto-a11y
   → gates CI (PR UI Judge + visual-regression + lint + Pest)
   → F3 [CL] aplica no repo + PR
   → merge autônomo se CI verde
```

[W] tem **2 momentos**: *briefar* (início) e *supervisão estratégica* (assíncrona — lê `SYNC_LOG`/`HANDOFF`, reverte se discordar). Não há mais aprovação síncrona de screenshot nem merge manual por PR.

### 3. O que FICA humano (Tier 0 — irredutível)

- **ADR novo** · mudança **multi-tenant** · **segredos/Vaultwarden** · **lógica de lint/tooling** · **decisão de produto**.
- Estes **nunca** entram no merge autônomo. (Coerente com ADR 0238 — soberania de [W] sobre constituição/ADR.)

### 4. Merge autônomo — mecanismo e evolução

- **Interim (autorizado por [W] 2026-05-31):** `gh --admin` (conta `wagnerra23` é admin; `enforce_admins:false`). Custo trivial, atribuição honesta (conta real), CI verde é o gate.
- **Descartado:** Chrome dirigindo a UI do GitHub — cria "approve do Wagner" que foi do Claude (registro enganoso) + caro (pixels).
- **Alvo (zero-humano auditável):** bot `grokwr2` (collaborator ≠ autor) aprova+mergeia via Action quando todos os checks passam. **Único bootstrap humano restante:** [W] provisiona o token do `grokwr2` (1×). Aí o `--admin` sai e o approve fica auditável **sem humano**.

## Consequências

### Boas
- Loop **não trava** esperando [W] (era o gargalo real, não a qualidade).
- Gate de qualidade **mantido e objetivo** (≥80, WCAG AA, CI) — mais consistente que screenshot humano subjetivo.
- Transparência preservada (`SYNC_LOG` append-only + `HANDOFF` + histórico de PR); **tudo reversível** por revert.

### Riscos / mitigações
- **Auto-check complacente** (`[CC]`/`[CL]` passam de leve) → trava = **gate numérico objetivo** (≥80) + **PR UI Judge** independente.
- **`--admin` sem registro de review** → interim; mitigação = bot `grokwr2` (approve real de conta ≠ autor).
- **Mudança Tier 0 escapar pro merge autônomo** → lista explícita do §3 + gates de governança no CI (charter-gate, secrets:scan, module-grades-gate, ADR 0216 scan, bucket-label ADR 0160).

## Plano de aplicação
1. **[bootstrap [W], 1×]** token `grokwr2` → mata o `--admin`.
2. **[CL]** `.github/workflows/ds-automerge.yml` (label `ds-auto` + checks green → `grokwr2` approve + squash).
3. **[CL]** auto-advance: ao fechar o sync, dispara o próximo módulo da fila sem novo pedido.
4. Apêndice de custo-benefício **por Onda** em `AUTOMACAO-LOOP-AUTONOMO.md` §5 (doc vivo, evolui até zero intervenção).

## Refs
- [AUTOMACAO-LOOP-AUTONOMO.md](../../prototipo-ui/AUTOMACAO-LOOP-AUTONOMO.md) — playbook vivo + log custo-benefício
- [PROTOCOL.md §2 (overlay autônomo) + §10](../../prototipo-ui/PROTOCOL.md) — reconciliação (PR #2030)
- [ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md) — loop formalizado (**emendado:** gates F2/F4)
- [ADR 0107](0107-emendation-0104-visual-comparison-gate-f3.md) — gate visual F1.5 (**emendado:** vira auto-check + PR UI Judge)
- [ADR 0239](0239-governanca-design-system-git-ssot-regressao-ia.md) — git SSOT + §10.4 (tirou [W] da validação)
- [ADR 0240](0240-task-ledger-git-native-cowork-code.md) — task ledger git-native (peer)
- [ADR 0238](0238-soberania-constituicao-wagner.md) — soberania [W] (ADR/constituição = humano)
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (§7 transparência · §8 confiabilidade com fallback)

---

**Última atualização:** 2026-05-31
