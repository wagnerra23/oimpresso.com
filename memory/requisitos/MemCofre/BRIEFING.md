# BRIEFING — MemCofre · ⚰️ LÁPIDE (módulo renomeado + zumbi)

> ⚠️ **Pare aqui. Não invista neste módulo.** Esta pasta está **congelada em ~2026-04-25** e o nome **mente**.
>
> - **MemCofre = DocVault (nome antigo) → hoje o código vive em `Modules/SRS/`.**
> - **NÃO é cofre de senhas.** É ferramenta interna de *doc-as-code* do Wagner (ingest de evidência → requisitos rastreáveis + chat sobre o corpus).
> - **Estado vigente:** [`memory/requisitos/SRS/BRIEFING.md`](../SRS/BRIEFING.md)
> - **Futuro:** [`memory/requisitos/SRS/DEPRECATION-PLAN.md`](../SRS/DEPRECATION-PLAN.md) — deprecação **aprovada** (Caminho 1), execução **pendente**.

## Em 1 parágrafo (a verdade atual)

Ferramenta **interna** (não-cliente, só Wagner) que ingere evidências (screenshots, logs, erros, PDFs, URLs) → triagem manual ou IA opt-in → vira requisitos rastreáveis por módulo, com chat sobre o corpus e auditoria do triângulo fluxo/tela/teste. **Funcionalmente ZUMBI** desde ~12/mai/2026 — substituída na prática pelo **MCP server canon** (`mcp.oimpresso.com`, [ADR 0053]). ROTA LIVRE (biz=4) **não usa** (esperado 0 rows). Sem criptografia (não é secret vault). Cron `memcofre:sync-memories` **desligado** (comentado em `app/Console/Kernel.php`).

## Por que esta pasta mente (sintoma de "não preparado pro tempo")

- Os 33 docs aqui são **anteriores ao rename pra SRS** (2026-05-06) → ainda falavam do nome morto da pasta `MemCofre` (já corrigidos pro nome real `Modules/SRS/` via codemod ghost-fix, P11 KL-E2). O código real sempre foi `Modules/SRS/`.
- **Identidade tripla não-propagada:** pasta `SRS` · namespace `SRS` · mas URLs/permissions/Pages/config/lang todos `memcofre.*`.
- **Dois grades elogiam o cadáver:** module-grade **73/100** · auto-audit **97/100** (medem doc/forma, não se o módulo está vivo).

## Sucessores (pra onde cada capacidade vai — DEPRECATION-PLAN)

| Capacidade MemCofre/SRS | Sucessor canônico |
|---|---|
| Ingest + busca | `Modules/KB` |
| Chat sobre corpus | `Modules/Jana` |
| Audit/validação módulo | `Modules/Governance` |
| Trilha de auditoria | `Modules/TeamMcp` (`mcp_audit_log`) |
| Memória canônica | MCP server (`mcp.oimpresso.com`) |

## Proveniência (abrir só se precisar de história — NÃO pro estado atual)

- [`RUNBOOK.md`](RUNBOOK.md) — operacional (signatures `docvault:*` velhas) · [`COMPARATIVO_CONCORRENCIA.md`](COMPARATIVO_CONCORRENCIA.md) — Capterra 45/100
- [`adr/0008-rename-docvault-para-memcofre.md`](adr/0008-rename-docvault-para-memcofre.md) — o rename · [`audits/2026-04-22.md`](audits/2026-04-22.md) — auto-audit 97/100
- Demais 29 docs (incl. `adr/arq/0001-0008` sobre upgrade Laravel 9→13 **já consumado**) = **histórico congelado**, não estado vigente.

---

**Tipo:** LÁPIDE/redirect (não BRIEFING de módulo vivo). **Mantenedor:** congelado. **Verdade viva → `requisitos/SRS/`.**
**Caminho de leitura: este doc resolve "o que é o MemCofre hoje?" em 1 pulo — os outros 33 viram proveniência opcional.**
