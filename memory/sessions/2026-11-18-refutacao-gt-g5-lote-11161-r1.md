---
date: "2026-11-18"
topic: "Refutação GT-G5 r1 do lote do PR #11161 (docs do Officeimpresso, lote L5) — 44 itens, 0 erros, 0 PII; aprovado"
authors: ["C"]
prs: [11161]
outcomes:
  - "44 afirmações factuais adicionadas pelo diff verificadas contra origin/main (100%): todas CONFIRMADAS"
  - "87 de 87 links relativos dos arquivos tocados resolvem na branch"
  - "0 hits de PII ou valor em R$ no diff"
  - "1 imprecisão sem impacto no veredito (logs-timeline-gap apontava app.jsx para a ViewTimeline); corrigida no mesmo PR"
---

# Refutação GT-G5 — PR #11161 (lote L5 da auditoria do Officeimpresso, 12 arquivos em `memory/requisitos/Officeimpresso/`)

## TL;DR

As afirmações que o lote acrescenta ao BRIEFING, CHANGELOG, README, SCOPE, CAPTERRA-FICHA, RUNBOOK-migracao-react, aos 4 `*-parity.md` e aos 2 gap docs de Logs conferem com o código em `origin/main`. Resultado: 44 itens, 0 erros, 0 dados pessoais, aprovado.

- **Data:** 2026-11-18
- **Refutador:** Claude Opus 5.5 (`claude-opus-5-5`), subagente em contexto próprio, sem contexto do gerador; declarou não ter aberto nenhum `memory/sessions/*refutacao*`.
- **Gerador:** Claude Opus 5.5, sessão do lote L5. Mesmo tier: aceito pela §4.2 (teto de política = opus), com sessão fresca.
- **Tipo:** `anchors` · amostra 100% das afirmações factuais verificáveis do diff. Sem amostragem aleatória, logo sem seed.
- **Base:** `git diff origin/main...origin/claude/officeimpresso-l5-docs -- memory/requisitos/Officeimpresso`.

## Checklist §3

- [x] Sessão fresca (sem nenhum contexto do gerador)
- [x] Tier: opus × opus, aceito pelo teto de política da §4.2
- [x] Amostra: 100% das afirmações factuais (não há amostragem, sem seed)
- [x] Cada item verificado contra o código em `origin/main`, não contra o diff
- [x] Nenhum REFUTADO
- [x] Scan de PII no diff (CPF, CNPJ, e-mail, telefone, sequências de 11+ dígitos, R$, nomes de clientes): 0 hits
- [x] `error_rate_pct` = 0 (< 2)
- [x] Entry no ledger no mesmo PR

## O que foi verificado (resumo)

- Estado de cada tela: cutovers de Licenças (#8783, 2026-10-06) e Empresas (#10035, 2026-10-27), flags `useV2OfficeimpressoEmpresa` e `useV2OfficeimpressoLogs` ligadas em `app/Services/FeatureFlagService.php`, `useV2OfficeimpressoClientes` fora dos padrões, Blades que seguem no repo.
- Log e acesso: `LogPassportAccessToken`, `LogDelphiAccess` (alias `log.delphi`, aplicado em `/connector/api`), `LogDesktopAccess`, `POST /api/officeimpresso/audit`, `admin_action`, `AcessoOperador`.
- Comandos: os 5 existem e nenhum é agendado.
- Fronteiras: rotas do Connector, escrita do Connector nas tabelas do módulo, listeners do Superadmin sobre `officeimpresso_bloqueado`.
- Importador: modo mock sem `--source-fb` e contador que soma insert com falha.
- ADR 0017 aceita e sem sucessora; inexistência do comando de expurgo; divergência entre `module.json` e `Config/retention.php`; lápide §5 de 2026-07-27.
- Datas e números de todos os PRs citados no CHANGELOG, contra `git log`.
- Protótipo: `ViewLog` "Máquinas Cadastradas" (D7) e `ViewTimeline`; `ancora.mjs Officeimpresso/Logs/Index` imprime a tela viva.
- Contagem de itens `alta` por `*-parity.md`: 21, 23, 11 e 9.

## Recibo

```json
{"pr": 11161, "itens_verificados": 44, "erros_confirmados": 0, "error_rate_pct": 0, "pii_hits": 0, "links_resolvidos": "87/87", "veredito": "aprovado"}
```
