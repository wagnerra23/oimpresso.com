---
sessao: "01"
titulo: Recibo — JobSheet/Index.casos.md (trio completo)
autor: "[CL]"
data: 2026-09-30
base: origin/main 7201ce433
---

# _saida-01 · Trio do JobSheet/Index

## O que foi entregue
- `resources/js/Pages/Repair/JobSheet/Index.casos.md` — 6 UCs (`UC-JSIDX-01..06`), derivados do
  charter, do bloco *"Contrato que a tela não pode quebrar"* da US-REPA-004 e do
  `JobSheetController::index`. Nada do `.tsx` foi lido como fonte de caso, nem tocado.
- **Teste:** nenhum novo. `Modules/Repair/Tests/Feature/RepairJobSheetIndexContratoTest.php` já
  existia desde 2026-09-05 (#6879), já nomeava os 6 UCs no título de cada `it()` e já roda na lane
  `PHP / Pest (Verticais · MySQL)`. A ficha pedia "ligar pelo menos um teste por UC" — ligado: cada
  UC cita o título exato do teste que o defende.

## Provas do json conferidas
- `{"tipo": "arquivo", "path": "${PAGES}/Repair/JobSheet/Index.casos.md"}` — arquivo existe.
- `node scripts/casos-coverage-guard.mjs` → *"Sem violações novas DESTE PR"*, rc=0.
- `node scripts/qa/uc-id-lint.mjs` no arquivo → nenhum id fora do formato.
- `last_run: 2026-09-30` ≥ último commit do `Index.tsx` (2026-06-29, `49d49ac9ff`, via API do
  GitHub — o checkout local é raso e não serve pra data).

## Status por UC — medido, não declarado
Fonte: `scripts/casos-test-results.json` (gerado 2026-09-29) e o run
[36571337250](https://github.com/wagnerra23/oimpresso.com/actions/runs/36571337250) da lane
Verticais (JUnit baixado do artefato `pest-verticais-junit`).

| UC | veredito | Status no casos.md |
|---|---|---|
| 01 · 403 sem permissão | pass | ✅ |
| 02 · flag OFF → Blade | pass | ✅ |
| 03 · flag ON → Inertia + 3 props | **skipped** | ⬜ |
| 04 · `datatable_url` = endpoint compartilhado | **skipped** | ⬜ |
| 05 · ramo `ajax` vivo com flag ON | pass | ✅ |
| 06 · cross-tenant (Tier 0) | **skipped** | ⬜ |

## Pendente — e por quê
1. **3 de 6 UCs não são medidos** (03, 04, 06 saem `skipped`). O JUnit não carrega o motivo, então
   a causa **NÃO MEDI**. Hipóteses, não fato: 03/04 pulam no `status !== 200` do render Inertia
   (candidato: `X-Inertia-Version: test` divergente da versão real → 409); 06 pula no `catch` do
   insert mínimo em `repair_job_sheets` (candidato: coluna NOT NULL sem default). O conserto mexe em
   `Modules/Repair/Tests/`, fora do prefixo desta thread (`tests/` raiz) — fica para uma thread
   própria. **O 06 é Tier 0**: é a pendência mais séria.
2. **Charter, seção "Métricas vivas"** ainda diz "nenhum teste"; já há teste. Fora do prefixo —
   não editado.
3. **SPEC US-REPA-004**: o item de DoD *"`Index.casos.md` com ao menos 1 UC citado por teste"*
   agora está cumprido, mas o SPEC não foi marcado (fora do prefixo).
4. **Sem teste**, registrados como `[BACKLOG]` no casos.md: filtros local/status/cliente, tela não
   escreve no banco, tela não renderiza colunas HTML do payload.

## Errata do índice
- O índice chama esta thread de `01`, mas a ficha dela é `03-trio-jobsheet.md` (a `01-puxar-vivo.md`
  é da thread `00`). O campo `arquivo` do json está certo; só o nome da ficha confunde.
- O índice diz *"`JobSheet/Index` tem `.tsx` + charter, sem `.casos.md`"* — certo — mas não diz que
  o teste de contrato **já existia** com os 6 UCs. A thread foi menor do que parecia: escrever o
  contrato, não a defesa.

## PR
Ver corpo do PR `claude/repair-thread-01`.
