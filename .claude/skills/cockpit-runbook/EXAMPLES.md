# EXAMPLES — RUNBOOK de tela (input → output)

Examples pattern recomendado pela Anthropic ([best-practices §Examples pattern](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices)). 1 exemplo end-to-end concreto pro Claude calibrar profundidade e tom — **não** abstrato/inventado.

---

## Exemplo canônico — Dashboard de Metas (Copiloto)

**Input do usuário:**

> "Gera runbook da tela `Pages/Copiloto/Dashboard.tsx` no padrão cockpit-runbook."

**Reasoning interno (não vai pro arquivo final):**

1. **Tela alvo:** [`resources/js/Pages/Copiloto/Dashboard.tsx`](../../../resources/js/Pages/Copiloto/Dashboard.tsx) — confirmar via `Glob` que existe (PascalCase!)
2. **Módulo:** `Copiloto` (idem PascalCase no path)
3. **Skill irmã ativa em paralelo:** `copiloto-arch` (porque toca conceitos do Copiloto)
4. **Read paralelo das 8 fontes canônicas + a tela + components shared importados** (Button/Card/Badge shadcn + AppShellV2 props + FabCopiloto local)
5. **Detecções na tela real:**
   - Usa `<AppShellV2>` ✅ via `Dashboard.layout = ...`
   - Recebe `Props { metas: Meta[] }` — apenas 1 chave, nada de KPIs ou permissions inventados
   - Função `calcularFarol()` regra R-COPI-FAROL-001 — relevante pra documentar
   - `Sparkline` SVG inline custom — relevante
   - `MetaCard` subcomponente local
   - `FabCopiloto` no canto inferior direito com `contextRoute` preservado
   - `AppShellV2` recebe `title` + `breadcrumbItems`
   - **NÃO TEM:** master/detail interno, atalhos J/K/E/A, coluna direita (LinkedApps), filtros locais, KPIs centrais, permissions
6. **ADRs candidatas (do header `@memcofre` da tela):** 0026, 0031, 0035, 0036, 0039 + _DS UI-0008/0009. Confirmar slugs via `Glob`.
7. **Stories cobertas:** US-COPI-010, 011, 012 (do header)
8. **Pegadinhas específicas detectadas:**
   - Trajetória linear hardcoded em `calcularFarol`
   - Loading/error state ausentes
   - Cards inteiros não-clicáveis (só Link interno)
   - `new Date(periodo.data_ini)` parsing implícito timezone
9. **Path final:** `memory/requisitos/Jana/RUNBOOK-dashboard.md`
10. **Antes de salvar:** gerar `.PLAN.md`, validar contra [CHECKLIST.md](CHECKLIST.md), corrigir, salvar final, apagar PLAN

**Output gerado (concreto, não inventado):**

➡️ Ver arquivo real em [`memory/requisitos/Jana/RUNBOOK-dashboard.md`](../../../memory/requisitos/Jana/RUNBOOK-dashboard.md)

Esse runbook foi produzido por esta skill em 2026-05-05 lendo a tela real. Use como referência de profundidade quando gerar novos.

---

## Sinais de calibração — quando aprofundar / quando enxugar

| Sinal detectado na tela | Aprofundar? | Onde |
|---|---|---|
| Tela tem regra de negócio com slug formal (R-XXX-YYY) | ✅ | §3 passo dedicado + §11 menciona rule slug |
| Tela usa dados sensíveis (PII, financeiro) | ✅ | §10 com pegadinhas LGPD |
| Tela é piloto de padrão novo (1ª no módulo) | ✅ | §11 com ADR substituta + §10 lições |
| Tela é CRUD genérico copiando outra | ❌ enxugar | §3 mais raso ("imitar `Modules/Repair/...`") |
| Tela é stub/legado | ❌ marcar `status: stub` | runbook curto + TODO migrar |
| Persona é cliente final (não dev) | ✅ | §1 ganha 1 parágrafo extra de contexto persona |
| Tela tem race condition / state machine | ✅ | §3 com diagrama Mermaid |
| Tela é read-only sem interação (Dashboard) | ❌ enxugar §5/§7 | §7 `—`, §5 sem hover/active complexos |
| Sem master/detail interno | ❌ enxugar §7 | Atalhos todos `—`, nota explicativa |
| Sem coluna direita (Apps Vinculados) | ❌ marcar n/a §9 | §9 DoD: `[n/a]` em vez de `[x]` ou `[ ]` |

## Anti-padrões observados na 1ª iteração da skill (2026-05-05)

Preservar como cautela:

- ❌ **Inventar exemplo educativo** — a v1 da skill tinha um EXAMPLES.md fictício com KPIs/LinkedFinanceiro/atalhos J/K que NÃO existiam na tela real. Bug detectado quando Wagner pediu pra testar — meu reasoning ia direto pelo exemplo inventado em vez de ler a tela. Fix: este arquivo agora aponta pro RUNBOOK real, não inventa.
- ❌ **Path com case errado** — usei `Pages/copiloto/Dashboard.tsx` (lowercase). Repo usa `Pages/Copiloto/Dashboard.tsx` PascalCase. Sempre confirmar via `Glob` antes.
- ❌ **Forçar atalhos J/K/E/A em tela read-only** — CHECKLIST original exigia tabela completa de atalhos. Tela Dashboard não tem nenhum. Relaxado: tabela com tudo `—` é válida quando justificada.
- ❌ **Esperar 11 seções `## ` mas template tem 12** — "Estado final esperado" pode aparecer como `## ` standalone (régua `Infra/RUNBOOK-criar-modulo.md` faz assim). CHECKLIST.md A2 ajustado pra aceitar 11 ou 12.
