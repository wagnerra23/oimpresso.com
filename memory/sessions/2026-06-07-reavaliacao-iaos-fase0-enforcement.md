---
date: "2026-06-07"
topic: "Reavaliação do IA-os (aparato de IA/governança) — Fase 0 triagem das 8 partes + deep-dive A (enforcement dos gates)"
authors: [W, C]
---

# Reavaliação do IA-os — Fase 0 + deep-dive A (enforcement)

## TL;DR

Wagner pediu reavaliar "todo o sistema" — o **IA-os** (gates, skills, agentes, ADRs, memória, MCP/brief, hooks), por partes, achando falhas, e perguntou "isso evolui sempre?". Fase 0 (triagem barata, evidência real) achou a **falha-mãe**: dos 40 gates de CI, só 1 era *required* (`ADR frontmatter`) + `enforce_admins:false` → dava pra mergear com Pest/build/multi-tenant/segredo quebrados. **Resposta a "evolui sempre?":** sim, dá pra provar (eslint-baseline só desce 1455→1365; ~96 commits/30d na maquinaria), MAS por **disciplina**, não por **mecanismo** — a tranca da catraca (enforcement) estava solta. **Ação tomada:** Alavanca 1 aplicada (required 1→4: + Pest + build + module-grades). Formalizado em ADR 0261.

## "Isso evolui sempre?" — resposta com evidência

- ✅ **Evolui (provado):** `eslint-baseline` monotônico decrescente `1455→1432→1373→1365` (catraca não deixa subir); screen-grades 37 telas <70→≥70 travadas; cadência ~96 commits/30d na governança (ADRs 44 · workflows 25 · skills/hooks 14 · scripts 13); sentinelas novas (memory-health #2386→#2401, adr-index #2391).
- ❌ **Mas não garantido:** catraca existe, tranca solta. 40 gates, 1 required (o mais trivial), admin fura. Evolui *porque W+C constroem catracas e respeitam CI vermelho* — não porque o sistema **proíbe** regredir.
- **Insight:** enforcement (Parte A) **É** a garantia de evolução. Conserta A → "evolui sempre" vira lei física, não comportamento.

## Placar Fase 0 — IA-os em 8 partes

| Parte | Severidade | Evolui? | Achado real |
|---|---|---|---|
| A · Enforcement | 🔴 crítico | ❌ não trava | 1/40 required + `enforce_admins:false` — **falha-mãe** |
| B · MCP/brief/cycle | 🔴 alto | ⚠️ detecta não corrige | Cycle drift 0% (217/217 commits/7d off-cycle) — princípio #4 quebrado |
| E · Memória/segredos | 🔴 alto | ✅ estrutura / ❌ segredo | memory-health ENFORCE+ratchet forte; mas 10 arquivos credencial em claro, rotação pendente |
| D · ADRs (258→265) | 🟡 médio | ✅ mais forte | adr-index auto-gerado + supersede-gate-duro + anti-ressurreição; 13 colisões sendo limpas |
| C · Skills (69) | 🟡 médio | ⚠️ parcial | só brief-first com telemetria; risco de skills mortas/sobrepostas |
| F · Agentes (22) | 🟡 médio | ⚠️ parcial | validador local divergiu do CI (stub python3) — tooling com furo |
| G · Hooks (45) | 🟢 bom | ✅ testado | 45 hooks, muitos `.test`. Ressalva: PowerShell locais — time MCP pode não tê-los |
| H · Health-check | ⚪ desconhecido | ? | `jana:health-check` (5 SQL) existe mas precisa runtime — não rodado |

## Deep-dive A — enforcement (detalhe)

**Causa-raiz:** 25/28 gates de PR são path-scoped (correto p/ custo). GitHub: required-check que não roda → trava PR em "waiting". Exigir path-scoped congelaria PRs não-relacionados → time exigiu quase nada. Ironia: o próprio Multi-tenant gate já foi "Tier 0 dormente" antes (comentário no `.yml`); agora roda mas não é required.

**3 alavancas** (ver ADR 0261 pra detalhe):
- **A1 (feita):** promover always-run → `PHP / Pest (Unit)`, `Frontend / Vite build`, `module-grades-gate`. Required 1→4. Comando: `gh api -X PATCH .../required_status_checks`.
- **A2 (próxima):** converter Tier-0 path-scoped → always-run + skip-as-pass, aí exigir. Ordem: Multi-tenant → Secrets → Memory schema → PHPStan → Governance Gate/Pest.
- **A3 (por último):** `enforce_admins:true` após ~1 semana estável.
- **Nunca exigir:** PR UI Judge, RAGAS (LLM, não-determinístico), Charter/MWART soft.

## Estado pós-sessão

- ✅ Alavanca 1 aplicada: `required_status_checks` = [ADR frontmatter, PHP / Pest (Unit), Frontend / Vite build, module-grades-gate], `strict:true`, `enforce_admins:false`, reviews:1.
- ✅ ADR 0261 criado (formaliza o faseamento, fecha decisão-pendente-enforcement).
- 🔜 Alavanca 2 PR-amostra (Multi-tenant skip-as-pass) — PR separado.
- 🔜 Partes B/E (cycle drift, rotação de segredos) ficam pro próximo deep-dive.

## Pointers

- Decisão: [ADR 0261](../decisions/0261-enforcement-faseado-gates-ci.md)
- Gates: `.github/workflows/*.yml` (40) · branch protection `main`
- Maquinaria de evolução: `scripts/governance/memory-health.mjs` + `adr-index-generate.mjs` (ADR 0256)
