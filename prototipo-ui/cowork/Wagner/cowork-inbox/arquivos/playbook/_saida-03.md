---
sessao: "03"
titulo: Arquivos — excluir + restaurar no grace (PR-7) (recibo)
autor: "[CL]"
base: origin/main 20d09c6c5 (2026-10-01)
---

# _saida-03 · Excluir + restaurar no grace (PR-7)

## O que entreguei

| arquivo | o quê |
|---|---|
| `Modules/Arquivos/Routes/web.php` | `POST arquivos/{arquivo}/excluir` e `POST arquivos/{arquivo}/restaurar` (`whereNumber`, `can:arquivos.access`), no grupo da tela |
| `Modules/Arquivos/Http/Controllers/ArquivosAdminController.php` | `excluir(DeleteArquivoRequest)` → `ArquivosService::softDelete()`; `restaurar(RestoreArquivoRequest)` → `restore()` só dentro do grace; a linha do acervo ganha `restaurar_ate` + `restauravel`; prop `pode_restaurar` |
| `resources/js/Pages/Arquivos/_components/ExcluirRestaurarSheet.tsx` | drawer PT-02 (`Sheet` do DS) com motivo ≥5, nos dois modos |
| `resources/js/Pages/Arquivos/Index.tsx` | ícones Excluir (linha não excluída) e Restaurar (**só** quando `restauravel` — fora do grace o botão não é desenhado); chip "Mostrar excluídos" |
| `Modules/Arquivos/Tests/Feature/ArquivosAdminControllerTest.php` | 6 `it()` UC-INDEX-08, incl. **cross-tenant 98 × 99** nas duas Requests + controller |
| `Index.casos.md` / `Index.charter.md` | UC-INDEX-08; 2 itens de backlog promovidos; pendência [W] da permissão |

Excluir é **soft-delete**: `deleted_at`, o blob fica. Nenhum caminho da tela faz hard-delete
ou purge — segue só no `arquivos:retention-cleanup` (D4). O grace vem de
`arquivos_retention.grace_period_days` (30), a mesma leitura do `RetencaoStatsReader`.

## Errata da ficha / do prefixo
- **Prefixo estendido em 3 arquivos**, todos pedidos pela sessão-mãe ("FormRequest que barra
  outro business" + "linha na trilha com motivo"):
  - `Http/Requests/DeleteArquivoRequest.php` e `RestoreArquivoRequest.php` — não checavam o
    business do arquivo (só a presença de sessão). Agora recusam arquivo de outro business,
    como a `ReclassifyArquivoRequest`; `reason` virou obrigatório (min 5).
  - `Services/ArquivosService.php` — `softDelete()`/`restore()` ganharam `array $contexto = []`
    opcional (default = comportamento antigo; os 2 consumidores da OficinaAuto não mudam). Sem
    isso o motivo exigiria uma 2ª linha `soft_delete` na trilha, que é append-only — duas
    linhas por exclusão inflariam contagem. Assim a mesma linha carrega o motivo.
- `Wave18RetryArquivosSaturationTest` afirmava `reason` *nullable*; atualizado para *required*.

## Pendente, e por quê
- **Permissão de restaurar (decisão [W]):** a `RestoreArquivoRequest` exige `superadmin` ou
  `arquivos.restore`, e `arquivos.restore` **não é declarada** no `DataController` (fora do
  prefixo). Hoje só superadmin restaura; a prop `pode_restaurar` esconde o botão de quem
  tomaria 403. Declarar a permissão (ou trocar por `arquivos.access`) é decisão, não faxina.
- **Copy por contexto** (excluir foto de OS × XML de NF-e com guarda de 5 anos) segue
  `[BACKLOG]` — o drawer usa um texto só.
- Smoke em produção: depende do deploy pós-merge (relatado no PR).

## Provas do json
- `contem` `Modules/Arquivos/Routes/web.php` ⊇ `{arquivo}/restaurar` — conferido pelo `placar.mjs`.
- `node scripts/contrato-de-tela.mjs --contract governance/design/contracts/arquivos-index.contract.json` → limpo, rc=0.
- `node scripts/casos-coverage-guard.mjs` → sem violações novas deste PR.
- `php -l` nos 7 PHP tocados → sem erro de sintaxe.
- Pest/typecheck: NÃO rodei local (regra do projeto). A prova é o CI do PR.

## PR
Branch `claude/arquivos-thread-03` (número no corpo do PR).
