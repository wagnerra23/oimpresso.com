# 19 — `charter-evolve` skill (L2 propose)

> **Spec da skill que detecta drift e propõe charter v2 supersede automaticamente.**
> Nível L2 da automação ([ADR 0101](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md) §3 níveis). Sempre output PR draft pra Wagner aprovar — **NUNCA auto-merge**.

---

## Quando ativar (futuro pós-F4)

Trigger automático: cron ou skill ativada por `charter:health` daily quando algum charter Tier A:
- Status `live` mas `last_validated > 30d` (stale por tier A)
- 1+ Non-Goal violado em prod (Pest GUARD red em CI nos últimos 7d)
- 1+ UX target falha em prod canary (M5/M6 piorou)

Ou trigger manual: `/charter-evolve /repair/dashboard`.

---

## Os 5 passos

### 1. Validar pré-condições

- Charter alvo existe e tem `tier: A | B`
- Charter NÃO está em supersede (não pode evoluir um já-superseded)
- Telemetria 7d disponível

### 2. Ler artefatos atuais

- `*.charter.md` atual
- `mcp_audit_log` últimas 7d filtradas pra essa tela
- Pest GUARD results (CI history)
- Diff git desde `last_validated` (mudanças no `.tsx` desde última validação)

### 3. Inferir mudanças pro charter v2

| Sinal | Mudança proposta |
|---|---|
| Tela ganhou prop nova | Apender Goal correspondente |
| Tela removeu feature | Apender Non-Goal "❌ Não faz X (removido em PR #Y)" |
| Pest GUARD fail recorrente | Renomear Non-Goal em "exceção justificada" + ADR |
| `last_validated` velho mas tela estável | Bumpar `last_validated` (sem v2) |
| UX target falhando consistentemente | Ajustar target pra realidade ou abrir bug |

### 4. Gerar `*.charter-v2.md` ao lado

```yaml
---
page: /repair/dashboard
charter_version: 2
supersedes: [v1]
last_validated: {today}
status: wip                # SEMPRE wip — Wagner aprova pra live
generated_by: charter-evolve skill
generated_at: {ISO datetime}
---
```

Body: Goals/Non-Goals atualizados, com diff visual em comentário top:
```markdown
> **Diff vs v1 (gerado por skill):**
> - Goal +1: "Filtrar OS por período" (introduzido em PR #245)
> - Non-Goal +1: "❌ Não exporta PDF" (removido — virou job separado em PR #232)
> - UX target ajustado: p95 800ms → 1000ms (Pest GUARD vermelho 5/7 dias)
```

### 5. Abrir PR draft pra Wagner

```bash
git checkout -b claude/charter-evolve-{slug}
git add resources/js/Pages/{X}/{Y}/Index.charter-v2.md
git commit -m "feat(governance): charter v2 propose for /repair/dashboard"
git push -u origin claude/charter-evolve-{slug}
gh pr create --draft --title "Charter v2 propose: /repair/dashboard" --body "..."
```

Body do PR explica:
- O que mudou e por quê (sinais detectados)
- Por que é v2 e não edit em-place (append-only)
- Wagner revisa, aprova → squash merge → status: live

---

## Por que NUNCA auto-merge

Skill é **assistente, não autor**. 3 razões:
1. Charter é decisão estratégica — auto-merge violaria publication-policy
2. Non-Goals são anti-alucinação — exige humano filtrar inferência da IA
3. Histórico append-only — humano carimba aprovação no commit

---

## Critério de aceite F4

- [x] Spec aqui
- [ ] Implementação em F5 ou próximo sprint quando telemetria + cron `charter:health` rampedup
- [ ] Smoke test: roda em 1 charter manualmente, gera PR draft sem ruído
- [ ] Métrica: % de PRs charter-evolve aprovados (ROI da skill)
