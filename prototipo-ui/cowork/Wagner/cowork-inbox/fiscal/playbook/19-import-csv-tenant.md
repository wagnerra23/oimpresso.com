---
sessao: "19"
titulo: Linhas conferidas num tenant não gravam noutro (UC-NFIM-04)
dono: "[CL]"
base: wagnerra23/oimpresso.com@main 794f606dcecc (2026-10-06)
prefixo: Modules/NfeBrasil/Http/Controllers/ImportRegrasController.php · Services/Tributacao/ImportRegrasCsvService.php
nao_toca: Modules/NfeBrasil/Services/MotorTributarioService.php · resources/js/
depende: — . 🔴 T0 (multi-tenant/permissão): sozinho no PR, com o teste
implementa: UC-NFIM-04 (já existe no main — fica verde)
us: UC-NFIM-04 (ImportCsv.casos.md) · ADR 0093
---
# 19 · Linhas conferidas num tenant não gravam noutro (UC-NFIM-04)

**Já catalogado no `main` como falha esperada** (o teste é failing-first; o vermelho é o recibo). Antes de abrir PR: `gh pr list --state open` — pode já haver conserto em curso.

O `preview` guarda as linhas em `session('nfe_import_csv_linhas')` e o `aplicar` resolve o tenant de novo, noutro request. Se a empresa da sessão mudar entre os dois, centenas de regras entram **na empresa errada**, em silêncio. Amarrar o lote ao `business_id` do preview (chave de sessão por business, ou `business_id` gravado junto e conferido no aplicar, recusando a divergência).

## Casos de uso que esta thread implementa
Nenhum UC novo: o caso já está escrito no `main` e esta thread só o tira de ❌. Atualizar o status na tabela de rastreabilidade do casos.md.

## Prova
`TributacaoGatesContratoTest` › UC-NFIM-04 passa de ❌ para verde · UC-NFIM-01..03 seguem verdes · casos.md atualiza o status.

Terminou: `_saida-19.md`. Pare.
