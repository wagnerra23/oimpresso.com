---
date: "2026-11-16"
time: "2245 BRT"
slug: "associacoes-6-ondas-no-main"
tldr: "As 6 ondas do Modules/Associacoes (ADR 0451, Uniafibro) estão no main e em produção, sem regressão no smoke. O módulo segue desligado: falta o [W] criar a empresa Uniafibro e o pacote associacoes_module. O UC-ASSOC-01 vermelho era defeito do teste (arrow fn capturando por valor), consertado no #11066."
decided_by: [W]
cycle: null
prs: [10862, 10878, 10883, 10882, 10881, 10880, 11066]
us: [US-ASSOC-001, US-ASSOC-002, US-ASSOC-003, US-ASSOC-004, US-ASSOC-005]
next_steps:
  - "[W] criar a empresa Uniafibro no superadmin e o pacote associacoes_module; só depois ligar o módulo para ela"
  - "Mergear o #11066 quando a lane Verticais · Pest (MySQL) confirmar o UC-ASSOC-01 verde"
  - "Sessão da NFSe: consertar o helper nfsidxLinhas (mesmo defeito, derruba UC-NFSL-04/05 na nfse-pest) — avisada"
related_adrs:
  - "0130-handoff-append-only-mcp-first"
  - "0451-associacoes-rede-uniafibro-area-de-membros"
  - "0093-multi-tenant-isolation-tier-0"
---

# Handoff 2026-11-16 22:45 BRT — Associações: 6 ondas no main

## TL;DR

As 6 ondas do `Modules/Associacoes` estão no main, na ordem 1→6, e a onda 6 está em produção. O
smoke de depois saiu igual ao de antes nas URLs de base. Nada foi ligado no superadmin: o módulo só
aparece para uma empresa que tenha o pacote `associacoes_module`, e criar a empresa Uniafibro e esse
pacote é do [W].

Detalhes do merge e do smoke: [session log](../sessions/2026-11-16-associacoes-merge-das-6-ondas.md).

## Estado dos artefatos

| Item | Estado |
|---|---|
| Ondas 1–6 (#10862, #10878, #10883, #10882, #10881, #10880) | mergeadas, último commit `ead16928be` |
| Deploy da onda 6 | 43995018644 (`f6482e6b12`), success |
| ADR 0451 | `aceito` desde a onda 1 (o schema não tem estado depois de `aceito`) |
| US-ASSOC-001..005 | `done` no MCP; comentários na 005 (merge + smoke) e na 002 (UC-ASSOC-01) |
| #11066 | aberto: conserto do UC-ASSOC-01, só arquivo de teste |

## UC-ASSOC-01: a causa

A lista da diretoria chegava vazia ao teste. Quatro rodadas de diagnóstico na CI descartaram, uma
a uma, permissão, sessão, escopo de empresa (ADR 0453, modo `aberto`) e a consulta: o servidor
devolvia as 2 associações. O defeito estava no helper do teste. `assertInertia(fn (...) => ...)`
é arrow function, que captura `$ids` por valor; o `use (&$ids)` do callback interno escrevia na
cópia dela. O #11066 troca por closure comum e exige que a lista chegue.

O mesmo padrão está em `tests/Feature/Modules/NFSe/NfseIndexTelaTest.php` (#11041) e derruba o
UC-NFSL-04 e o UC-NFSL-05 na `nfse-pest`. A sessão dona da NFSe foi avisada; o arquivo não foi
tocado aqui.

## O que fica para quem vier

- **Antes de ligar o módulo:** o [W] cria a empresa e o pacote. Para a área da associada, o link
  gerado abre em `uniafibro.com.br` (D6); o domínio já responde e o link inválido devolve só
  `erro: link_invalido`.
- **Branch de diagnóstico** `claude/assoc-uc01-diag`: descartável, sem PR. Pode ser apagado.
- `PAINEL-SISTEMA.md` e o `_STATUS-GENERATED` do Manufacturing estão desatualizados no próprio
  main; não são deste trabalho.

## Estado MCP no momento do fechamento

- `cycles-active`: nenhum cycle ativo em COPI.
- `sessions-recent limit:3`: 2026-11-14 RUNBOOK de deploy, 2026-11-15 smoke dos clientes vivos,
  2026-08-22 estado da arte de agentes de UI.
- `tasks-detail` US-ASSOC-001..005: todas `done`.
- `whats-active`: 13 sessões nas últimas 2h; nenhuma outra em `Modules/Associacoes`. A da NFSe
  (`beautiful-lehmann-8cc5e9`) é dona do `NfseIndexTelaTest`.
