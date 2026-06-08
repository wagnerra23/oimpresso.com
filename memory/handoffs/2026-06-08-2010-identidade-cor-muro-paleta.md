---
title: Identidade de cor — muro de gates (required + enforce_admins) + paleta auto-gerada
date: "2026-06-08"
time: "20:10"
owner: Claude [CC]
slug: identidade-cor-muro-paleta
related_adrs: [0263]
prs: [2436, 2443, 2446, 2448]
tldr: Handoff Cowork Mapa de Identidade ERP pedido por Wagner virou projeto de governança de cor. Conclusão do mapa (Compras navy para roxo) já estava no main. Entregue ADR 0263 + paleta auto-gerada (PALETA.html, gerada do cockpit.css) + muro de CI (gates de cor required + enforce_admins). Incidente de deadlock por required mais path-filtered, consertado fazendo gates rodarem em todo PR (#2446) e provado (#2448).
---

# Identidade de cor — muro + paleta

## Estado MCP no momento
- Branch protection `main` (final): required = PHP/Pest · Frontend/Vite · module-grades · ADR frontmatter · **Conformance · cor-crua** · **UI Lint** · (ruleset) Governance Gate. `enforce_admins: true`.
- 4 PRs desta sessão MERGED: #2436 #2443 #2446 #2448. Nenhum PR meu aberto.
- Abertos de OUTRAS sessões (não tocados): #2450 #2447 #2441 #2438.

## O que aconteceu
Wagner: *"fetch design file + readme + implement Mapa de Identidade ERP - CC.html"*. O arquivo é **artefato de decisão** (não tela): a identidade do ERP é UMA (roxo `oklch(0.55 0.15 295)`, ADR 0190/0235), modelo 2 camadas (chrome=1 cor; semântica=N governadas). Conclusão do mapa = a única ilha era o Compras em navy hex.

**Verifiquei no main real: o Compras JÁ estava roxo** (`--cmp-accent:var(--accent)`). O navy que editei era cópia **stale** do branch local (lição REGRA 6). A edição do Compras foi descartada (já feita no commit `design(compras): chrome navy → roxo canon`).

Wagner então pediu para **garantir que ninguém mais quebre a cor** → virou projeto de governança:
1. **ADR 0263** (#2436) — fundamenta: chrome roxo único + semântica governada + gates bloqueantes.
2. **Paleta auto-gerada** (#2443) — `palette-generate.mjs` (lê cockpit.css, `--write`/`--check`) + `PALETA.html` (27 cores, claro+escuro). Padrão adr-index-generate.
3. **Muro de CI** (#2446) — fez `adr-lint`/`conformance`/`ui-lint` rodarem em TODO PR (sem path filter) → poderem ser required sem deadlock + `--check` da paleta no Governance Gate.
4. Re-adicionei `Conformance`+`UI Lint` aos required + religuei `enforce_admins`. **Provado** por #2448 (PR só-scripts mergeou limpo, gates reportaram).

## Incidente (a lição central)
Tornar gates **required + path-filtered** + ligar `enforce_admins` **travou TODO PR** que não dispara aquele gate: o check fica "Expected" pra sempre. O `ADR frontmatter` (já required, path-filtered a `memory/decisions/**`) tinha o mesmo defeito latente — só não travava porque admin furava (`enforce_admins=false`). Diagnóstico → reverti tudo, depois consertei na raiz (#2446: gates rodam sempre) e só então religuei.

## Artefatos gerados (canon, no main)
- `memory/decisions/0263-identidade-cor-gate-bloqueante.md` (ADR, ~70 linhas)
- `scripts/governance/palette-generate.mjs` (gerador determinístico)
- `memory/requisitos/_DesignSystem/PALETA.html` (paleta visível, auto-gerada)
- `.github/workflows/{adr-lint,conformance-gate,ui-lint}.yml` (sem path-filter em pull_request) + `governance-gate-umbrella.yml` (+palette --check)

## Persistência
- git: 4 PRs merged no main (webhook → MCP).
- Wagner: cópias na Área de Trabalho (`PALETA-oimpresso.html` + `PALETA-oimpresso-DARK.png`).

## Próximos passos pra retomar
- Identidade de cor: **fechada**. Nada pendente.
- (Opcional) Emendar ADR 0263: ele cita 3 gates required, são **2** (`Conformance`+`UI Lint`); droppei `UI architecture` (Pest pesado + accent já coberto pelo Conformance). Gap só de doc.
- (Opcional) Se quiser o 3º gate: fazer `ui-architecture-gate` rodar em todo PR + adicionar aos required.

## Lições catalogadas
1. **Required + path-filtered = deadlock** sob `enforce_admins`. Required check TEM que reportar em todo PR (rodar sempre, passar trivial). Padrão correto = `module-grades-gate` (sem path filter).
2. **Repo local driftado** — Compras já era roxo no main; o navy era espelho stale. Sempre `github_read_file @main` antes de afirmar (REGRA 6).
3. O repo **dependia de admin-bypass** para mergear PRs que não disparam todo required check (defeito latente exposto pelo enforce_admins).
4. `gh api DELETE .../required_status_checks/contexts` derrubou `ADR frontmatter` junto (quirk legado×moderno) — re-adicionado via POST.

## Pointers detalhados
- ADR 0263 + `palette-generate.mjs` (header documenta o gate) no main.
- Nomes exatos dos checks de cor: `Conformance · cor-crua ratchet vs baseline`, `UI Lint · ratchet vs baseline`.
