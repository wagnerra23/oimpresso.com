---
date: "2026-07-31"
time: "23:20 BRT"
slug: ads-parte6-nucleo-removido-dado-pendente
tldr: "Parte 6 executada em 3 PRs (#5134 preserva · #5135 remove 174 arquivos/-15.889 linhas · #5139 fecha permissions+baselines+errata), com smoke real em prod passando: 4 rotas sobreviventes 302, 6 removidas 404. O DADO continua no banco — E5 (dump das 41 + ARCHIVE→DROP) é o único irreversível e exige [W]+[F]. Os 4 erros da sessão foram meus e nenhum foi pego por mim: 2 por [W], 3 pelas máquinas."
prs: [5134, 5135, 5139]
decided_by: [W]
related_adrs:
  - 0363-governance-incorpora-ads-nucleo-sem-receptor
  - 0087-drift-resolution-sem-mover-url
  - 0273-anchor-implementado-em
  - 0302-doneness-fonte-unica-ancora
next_steps:
  - "E5 (BLOQUEIA o resto) — dump das 41 linhas com resolved_by no CT 100 + migration ARCHIVE→DROP com mcp_decision_links FORA da lista. Irreversível: matriz TEAM.md exige ✅[W]+✅[F]"
  - "E7 — lápide §5 + BRIEFING final do ADS"
  - "Resíduo: href morto do KPI 'Skill approvals' em governance/Dashboard.tsx:180 (hook MWART exige RUNBOOK-dashboard.md inexistente)"
  - "Resíduo: 18 entradas ads/ em config/eslint-baseline.json — só via gerador, o _meta.total_violations já estava dessincronizado (2664 vs soma real 2575) ANTES desta sessão"
  - "Dívida de terceiro: system-map.mjs --write TRAVADO no main (emite Modules/SRS porque memory/requisitos/SRS/ sobreviveu à ADR 0357) — por isso Jana/ARCHITECTURE.md carrega links mortos no baseline em vez de regenerados"
---

# ADS parte 6 — núcleo removido, dado pendente

> A parte 6 ("a arriscada") saiu em **3 PRs**, todos mergeados, com **smoke real em prod**. O que
> **não** saiu é justamente o item central: o **dado**. As ~36.9k linhas seguem no banco.

## O que está no ar

| PR | Intent | Prova |
|---|---|---|
| [#5134](https://github.com/wagnerra23/oimpresso.com/pull/5134) | preserva o que sobrevive — 2 classes + 3 rotas → Forja | 102 checks, 0 falhas |
| [#5135](https://github.com/wagnerra23/oimpresso.com/pull/5135) | remove o núcleo — **174 arquivos, −15.889 linhas** | 125 checks, 0 falhas |
| [#5139](https://github.com/wagnerra23/oimpresso.com/pull/5139) | fechamento — 10 permissions, 2 baselines, errata 6b | 98 checks, 0 falhas |

**Smoke real pós-deploy (E6), status literal:**

```
/ads/admin/graph        < HTTP/1.1 302 Found     /ads/admin/decisoes      < HTTP/1.1 404
/ads/admin/projects     < HTTP/1.1 302 Found     /ads/admin/policy        < HTTP/1.1 404
/ads/admin/tools        < HTTP/1.1 302 Found     /ads/admin/skills        < HTTP/1.1 404
/ads/admin/team-scopes  < HTTP/1.1 302 Found     /ads/admin/skills-review < HTTP/1.1 404
/login                  < HTTP/1.1 200 OK        (controle)
```

## As 5 correções que a medição pegou — e que teriam virado bug

1. **`DecisionLinksService` + `ProjectDecomposerAgent` não morriam.** O chip os dava como mortos;
   o #5131 tinha envelhecido a afirmação — o `ProjectDecomposerService` da Forja injeta o service no
   construtor. Eram os **2 únicos** `use Modules\ADS` fora do ADS. **Achado do [W].**
2. **`mcp_decision_links` é a TERCEIRA tabela com consumidor sobrevivente.** A E3 pegou
   `mcp_projects`; o C2 pegou `mcp_dual_brain_decisions`; ninguém olhou esta — e a Forja **escreve**
   nela a cada decompose. Precisa sair do DROP no E5.
3. **São 14 telas, não 12.** Plano, ADR e handoff divergiam; derivei dos `Inertia::render`.
4. **`loadMigrationsFrom` não precisou ser decidido** — as 7 tabelas sobreviventes já estão em
   `database/schema/mysql-schema.sql`, o baseline do CI e do CT 100.
5. **`DecomposeProjectRequest`** — conferido ANTES de deletar: o `decompose` da Forja usa
   `Illuminate\Http\Request` genérico. Não quebrou.

## Erros meus — os 4, e quem pegou cada um

**Nenhum foi pego por mim.** Vale mais que o placar de PRs.

| Erro | Quem pegou |
|---|---|
| `/kb/graph` "não existe" — glob `Routes/` maiúsculo × o KB usa `Http/routes.php` (**LC-08**) | **[W]** |
| `DecisionLinksService` dado como morto | **[W]** |
| `what-oimpresso.md` afirmando `Modules/ADS` (e a 1ª correção continha a string literal) | `fact-anchor` (check T) |
| `status: done` × âncora `_pendente_` nas 2 US desimplementadas | `doneness-lint` (ADR 0302) |
| 2 skills deletadas sem regenerar o índice que as listava | selftest P31 |

O do Graph tem lápide própria (**errata 6b** no `DEPRECATION-PLAN`): a **conclusão** de preservar
sobreviveu — as telas são diferentes, a do KB é fachada (closure sem props, `/kb/graph/data` devolve
vazio; o próprio `KbGraphContratoTest` diz isso) e a do ADS tem 5 fontes reais — mas ela **sobreviveu
por sorte, não por rigor**. A medição que a sustentava estava podre.

## Nenhum baseline foi maquiado pra passar

Os 4 que mexi têm razão declarada e diff inspecionado:

- `deadlink` +16 em 9 arquivos (4 ADR append-only, 1 cycle, 2 docs do ADS, Infra/SPEC, o ARCHITECTURE gerado) — **zero de terceiro**;
- `screen-coverage` regravado (charter 221→207) — a catraca compara **conjunto**, não contagem;
- `ghost_count` 11→12 com trailer `BASELINE-ABSORB` — **3ª ocorrência** da mesma razão já documentada pra Admin e Brief: *remover módulo cria ghost por construção*;
- `casos-coverage` e `module-grades`: entradas do ADS fora (débito de casos caiu **−59**).

E **um que reverti**: o `eslint-baseline`. Editei, vi que o `_meta.total_violations` já estava
dessincronizado antes de mim, e desfiz — mexer à mão ali seria inventar número.

## O que falta

**E5 é o item central e é [W]+[F].** Dump das 41 linhas com `resolved_by` no CT 100 (87,75 MB,
**nunca em git**) + migration `ARCHIVE → DROP` com `mcp_decision_links` **fora**. Depois E7
(lápide §5 + BRIEFING). O daemon **não religa** — decisão [W] registrada no handoff das 16:36.

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → 6 tasks, **todas em REVIEW**: US-COPI-123 `p0` · US-TR-309/310 `p1` · US-PG-008 `p1` ·
  US-PROD-027 `p1` · US-INFRA-023 `p1` — nenhuma tocada nesta sessão
- ADRs 24h → **0363** (esta linha de trabalho) e a do "módulo Governance FICA" (#5126)
- Handoff anterior desta linha: [2026-07-31 16:36](2026-07-31-1636-ads-incorporado-pelo-governance-3-de-7.md)
