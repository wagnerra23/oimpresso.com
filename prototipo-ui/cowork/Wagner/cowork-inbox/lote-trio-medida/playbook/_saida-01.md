---
sessao: "01"
titulo: Casos Auditoria Index + Detail — recibo
autor: "[CL]"
data: 2026-09-30
base: origin/main 7201ce433
---

# _saida-01 · Casos: Auditoria Index + Detail

## Entregue

| arquivo | o quê |
|---|---|
| `resources/js/Pages/Auditoria/Index.casos.md` | UC-AUDI-01 (lista só o próprio business, paginada, desc) · UC-AUDI-02 (filtro + whitelist descarta chave estranha) · UC-AUDI-03 (301 `/reports/activity-log` → `/auditoria` preservando querystring) |
| `resources/js/Pages/Auditoria/Detail.casos.md` | UC-AUDD-01 (detalhe traz `old` + `attributes`) · UC-AUDD-02 (cross-tenant não abre → 404) |
| `tests/Feature/Auditoria/AuditoriaTelasContratoTest.php` | 1 teste por UC, citando o id; tenants fictícios 98 × 99; `DatabaseTransactions` (activity_log é append-only); âncora positiva em cada caso negativo |

Fonte dos UCs (ordem canônica): SDD-auditoria-v1.0 §6 CU-AUD-01/02 + charters + SPEC US-AUDIT-010. Código (`AuditoriaController` → `AuditEntryService`) só conferido. `.tsx` não tocados.

## Provas do json conferidas

- `${PAGES}/Auditoria/Index.casos.md` — existe.
- `${PAGES}/Auditoria/Detail.casos.md` — existe.
- `node scripts/casos-coverage-guard.mjs` → `✅ Sem violações novas DESTE PR`; zero violação Auditoria no `--json`.
- `node scripts/qa/uc-id-lint.mjs` → nenhum id fora do formato.

## Pendente (não inventado)

1. **Status 🧪, não ✅ — nenhuma lane de PR executa os testes da Auditoria.** Medido com `test-lane-coverage.mjs --json`: os 10 de `Modules/Auditoria/Tests/` e os 7 de `tests/Feature/Auditoria/` estão em `arquivos_orfaos`. O teste novo também fica órfão. Ligar numa lane é edição de `.github/` (fora do prefixo desta thread). PHP também não foi linted local (`php` ausente no ambiente) — a prova é o CI.
2. **Decisão [W] — revert pela tela.** Os charters divergem: `Index.charter.md` promete revert (Goal 3, bulk panel, export); `Detail.charter.md` lista revert como Non-Goal. O código não tem botão e `AuditoriaController@revert` responde **501** (placeholder) — o `RevertService` existe mas não é chamado por HTTP. O SDD §6 CU-AUD-03..10 descreve o revert via `@revert`, o que não bate com o controller. Ficou no backlog dos dois `casos.md`, sem UC.
3. **Export** (charter Index Wave 25) — sem rota medida; backlog.

## Errata ao índice

Nenhuma.

## PR

O PR que adiciona este arquivo — branch `claude/lote-trio-medida-thread-01`.
