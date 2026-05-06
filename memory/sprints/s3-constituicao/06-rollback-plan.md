# Sprint 3 — Plano de Rollback

> **Status:** 🔴 ESQUELETO — referência operacional. Wagner ou Sonnet executam se algum critério S3 falhar.

---

## Quando rodar rollback

Disparadores (qualquer um justifica):

- ⚠️ Token médio onboarding **piorou** após CLAUDE.md reescrito
- ⚠️ Skill Tier A não dispara em ≥30% das sessões em 7 dias
- ⚠️ Hook `SessionStart` falha em ≥3 sessões consecutivas
- ⚠️ Felipe ou Wagner reportam regressão de comportamento (Claude "esqueceu" regra crítica)
- ⚠️ Charters/CLAUDE.md ficam órfãos por importação `@` quebrada

---

## Procedimento (10 minutos)

### 1. Snapshot do estado pós-S3

```bash
git log --oneline -10  # capturar SHAs dos 4 commits do S3
git tag s3-failed-snapshot-YYYY-MM-DD  # marcador
```

### 2. Reverter 4 commits do S3 em ordem reversa

```bash
git revert --no-edit <sha-passo8-hooks>
git revert --no-edit <sha-passo7-skills-tier-a>
git revert --no-edit <sha-passo6-claude-md>
git revert --no-edit <sha-passo5-adrs-novas>

# Push como PR de revert
git push -u origin revert/s3-rollback-YYYY-MM-DD
gh pr create --base main --title "revert(s3): rollback Constituição v2 — motivo: <X>"
```

### 3. Skills voltam de _archive/ se houver

```bash
# Se passo 5 do S3 moveu alguma skill (provável: nenhuma)
for skill in $(ls .claude/skills/_archive/); do
  git mv .claude/skills/_archive/$skill .claude/skills/$skill
done
```

### 4. Restaurar 5 imports do CLAUDE.md (se quiser manter algo)

Opção A: reverter tudo (CLAUDE.md volta às 390 linhas)
Opção B: manter os 5 arquivos novos em `memory/why,what,how,proibicoes,regras-time.md` mas restaurar conteúdo no CLAUDE.md (duplicação temporária — investigar)

### 5. ADR de fechamento

Criar `memory/decisions/NEXT-rollback-s3-constituicao-v2.md`:
- Status: `paused`
- Lessons: motivo do rollback (qual critério falhou)
- Próximos passos: re-tentar em qual cenário, ou abandonar

---

## Custo estimado do rollback

- Tempo: ~10 min execução + ~30 min postmortem + ~1h re-soak verificando que tudo voltou
- Custo Sonnet: ~$0.10 (postmortem)

---

## Cenários e resposta esperada

| Cenário | Severidade | Ação |
|---|---|---|
| Token médio piorou <10% | 🟡 baixa | Investigar 48h, ajustar hook ou CLAUDE.md sem rollback |
| Token médio piorou >20% | 🔴 alta | Rollback completo |
| Skill Tier A não dispara | 🟡 baixa | Ajustar description ou hook, sem rollback |
| Skill Tier A dispara MAL (info errada) | 🔴 alta | Rollback skill específica, manter resto |
| Hook SessionStart trava sessão | 🔴 alta | Rollback hook (manter resto S3) |
| Felipe reporta regressão crítica | 🔴 alta | Rollback completo, postmortem urgente |
| Imports `@` quebrados (5 níveis exceeded) | 🟡 baixa | Trazer 1 import inline pro CLAUDE.md |

---

## Plano de re-tentativa

Se rollback acontecer, NÃO repetir S3 imediatamente. Esperar:

1. Postmortem com causa raiz identificada
2. Pelo menos 2 semanas de uso em prod com versão revertida (baseline novo)
3. Ajuste do plano S3 incorporando lições
4. Wagner aprovar nova versão antes de rodar de novo

S4 (Page Charters) **NÃO depende** de S3 100% — pode rodar em paralelo com Constituição na versão atual. Se S3 atrasar, S4 não trava.
