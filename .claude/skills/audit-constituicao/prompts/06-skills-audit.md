# Sub-agent prompt — Dimensão 6: Skills audit pós-Constituição

> Prompt canônico do sub-agent #6 da skill `audit-constituicao`.
> Output PT-BR. Limite: ≤600 palavras no diagnóstico final.

## Missão

Auditar saúde do catálogo de skills em `.claude/skills/<nome>/SKILL.md` contra a baseline em [memory/sprints/s3-constituicao/03-skills-audit.md](memory/sprints/s3-constituicao/03-skills-audit.md) e a convenção formal em [ADR 0095](memory/decisions/0095-skills-tiers-convencao-interna.md):
- Toda skill tem `tier:` declarado (A/B/C)?
- Skills Tier A estão em `SessionStart` hook (`.claude/settings.json`) ou justificadamente fora (dormentes)?
- Skills duplicadas (mesma intent / overlap claro)?
- Skills Tier A sem `parent_adr` ou sem ADR mãe específica (ADR 0095 exige)?
- Skills com `description` fraca (não começa com "Use ANTES" / "ATIVAR quando" / "Use ao")?
- Telemetria batendo com baseline?

## O que fazer (passo a passo)

1. `Glob .claude/skills/*/SKILL.md` → lista completa.
2. Ler frontmatter de cada (limit baixo — não corpo).
3. Tabular: name, tier, status, parent_adr, charter_adr, description (preview ≤80 chars).
4. Validar:
   - `tier:` declarado e valor `A`/`B`/`C`?
   - Se `tier: A`, `parent_adr` declarado? skill aparece referenciada em [s3-constituicao/03-skills-audit.md] Tier A?
   - `description` começa com pattern Anthropic ("Use ANTES", "ATIVAR quando", "Use ao", "Trigger when")?
   - Há 2+ skills com intents fortemente sobrepostas? (ex: 2 skills cobrindo "git commit discipline")
5. Cross-check com `.claude/settings.json` `hooks.SessionStart` — Tier A "ativas" devem aparecer; "dormentes" não.

## Como entregar

```markdown
# Dimensão 6 — Skills audit pós-Constituição

## Saúde: 🟢/🟡/🔴
## Headline (1 frase): <ex: "29 skills, 5 Tier A; 2 sem parent_adr e 1 duplicação leve">

## Métrica
- Total skills: <N>
- Tier A (ativas): <N>
- Tier A (dormentes — `enabled: false`): <N>
- Tier B: <N>
- Tier C: <N>
- Sem `tier:`: <N>
- Tier A sem `parent_adr`: <N>
- Description fraca: <N>
- Duplicações detectadas: <N>

## Achados detalhados

### Sem tier
| Skill | Description preview | Tier sugerido |
|---|---|---|

### Tier A sem parent_adr
| Skill | Mecanismo | ADR sugerida |
|---|---|---|

### Description fraca
| Skill | Atual (preview) | Sugestão |
|---|---|---|

### Duplicações (overlap >50% intent)
| Skill A | Skill B | Overlap | Recomendação |
|---|---|---|---|
| <a> | <b> | "ambas X" | merge / rename / clarify boundary |

### Cross-cuts esperados (não duplicação)
- mwart-process / mwart-quality / mwart-comparative — convergem mas têm boundary clara (process vs implementation vs visual)
- brief-first / mcp-first — both Tier A SessionStart, complementares (brief carrega, mcp-first orienta uso)

## Recomendação 3-tiers

- **Tier A (safe agora):** adicionar `tier:` faltante; corrigir description fraca (texto-only, não muda comportamento)
- **Tier B (precisa ADR):** promover Tier B → A OU criar Tier A nova → ADR específica (ADR 0095 exige); merge de duplicações reais
- **Tier C (backlog):** revisar telemetria 30d pós-S3 + considerar archive de skills com 0 disparos (baseline em [s3-constituicao/03-skills-audit.md])
```

## Heurística de saúde

- 🟢 100% skills com tier; Tier A todas com parent_adr + SessionStart sync; zero duplicação
- 🟡 1-3 sem tier OU 1 duplicação leve OU 1 Tier A sem parent_adr
- 🔴 >3 sem tier OU Tier A sem ADR OU duplicação evidente (2 skills mesma intent)

## Restrições

- NÃO editar SKILL.md automaticamente — só listar gap.
- Cross-cut com Dimensão 2 (MWART coverage) é esperado em skills `mwart-*` — reporte achados próprios sem suprimir.
- Skill `audit-constituicao` (esta) deve aparecer no catálogo — checar que ela mesma cumpre as regras (auto-checagem).
- Telemetria 30d pós-S3: se ainda não há 30d desde s3, marcar "telemetria insuficiente — esperar baseline" em vez de recomendar archive.
