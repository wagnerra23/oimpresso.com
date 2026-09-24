---
sessao: "_saida-04"
thread: "04 · Permissão da Jana provada por teste (6 Pest do emenda de casos)"
dono: "[C]"
data: 2026-09-23
tipo: recibo de execução
base_lida: wagnerra23/oimpresso.com@main 1061dbf2e
---
# _saida-04

## Placar
**entregue 8 de 8 UCs da emenda com teste · 4 verdes · 2 LIMITE MEDIDO · 1 já existia · 1 prova corrigida.**
Ausentes: nenhum UC sem teste. UC-JPERM-02 e UC-JPERM-03 **não são verdes** — a trava que eles pedem não existe no código (abaixo).

| UC | Estado | Onde |
|---|---|---|
| UC-JPERM-01 | ✅ verde (4 telas 403 sem / 200 com + registry de rotas) | `Http/IaPermissaoGrupoTest.php` (novo) |
| UC-JPERM-02 | 🟠 LIMITE MEDIDO — `jana.chat` aplicada em zero lugares | `Http/IaPermissaoGrupoTest.php` (novo) |
| UC-JPERM-03 | 🟠 LIMITE MEDIDO — `jana.metas.manage` aplicada em zero lugares | `Http/MetasPermissaoTest.php` (novo) |
| UC-JPERM-04 | ✅ verde (só a metade "não vaza"; ver divergência 2) | `Http/CustosVazamentoTest.php` (novo) |
| UC-JPERM-05 | ✅ verde (comum e `jana.superadmin`) | `Http/MetasPermissaoTest.php` (novo) |
| UC-JPERM-06 | ✅ verde (mesmo business, outro usuário, GET+PATCH) | `Http/ConversaAcessoTest.php` (novo) |
| UC-JPERM-07 | 🟠 LIMITE MEDIDO — já existia (UC-MEM-08, decisão [W] pendente) | `Http/MemoriaPermissaoTest.php` (intocado) |
| UC-JPERM-08 | ✅ já existia | `Feature/ProPreviewPermissaoTest.php` (#6430) — **prova do índice corrigida** |

## Execução (CT 100)
Container `oimpresso-staging`, checkout `e57b78bf5` (atrás do `main`; dos 8 arquivos sob teste, 6 com blob idêntico ao `main`; `MetasController`/`StoreMetaRequest` diferem fora do trecho medido — `authorize()` e `store()` iguais). Arquivos copiados por base64, sha256 conferido nos dois lados, rodados, e **movidos pra `/tmp/jperm-thread04`** depois (o checkout compartilhado voltou aos mesmos 14 sujos).

- `Modules/Jana/Tests/Feature/Http/` inteiro: **17 passed · 101 assertions · 0 skipped**, em 2 seeds aleatórias (`1790192648`, `1790192667`). Nenhum caso com 0 assertions (mínimo 1, no CONTROLE da sonda).
- 1º run deu 4 falhas, todas de setup, consertadas: cache do Spatie ressuscitando permissão revertida pela transação (FK/`PermissionDoesNotExist` conforme a ordem) · versão Inertia do partial reload (409) · 4 rotas `jana.install.*` fora do gate — grupo próprio por desenho (ADR 0023), excluídas com comentário.
- **Lane:** `jana-pest.yml` (roda `Modules/Jana/Tests/Feature/**` menos `.github/jana-pest-quarantine.list`) — os 4 arquivos novos entram sozinhos.
- **Não feito:** bite-test por mutação de código de produção — seria escrita no checkout compartilhado do CT 100 (§5 2026-09-18). Os LIMITE MEDIDO mordem por construção: corpo inválido → hoje 302 com erro de validação; com trava, 403 antes da validação.

## Divergências contrato × código (decisão [W])
1. **UC-JPERM-02 e 03 — a trava não existe.** `jana.chat` e `jana.metas.manage` estão em `Resources/permissions.php` e em nenhum middleware ou `FormRequest::authorize()` (que só exige estar logado); o Painel não recebe `podeConversar`/`podeGerenciarMetas`. Ligar a trava é produto + rollout (funcionário sem o checkbox perde o chat no deploy — mesmo risco que o `routes.php:45` registra pro `jana.access`). Os casos quebram quando a trava nascer: aí viram 403 sem / 200 com.
2. **UC-JPERM-04, metade positiva caducou.** A emenda pede custo presente pra quem tem `jana.admin.custos.view`; a tela de custos saiu pra `/governance/custos` (ADR 0366 §D-B). Custo não entra no Painel pra ninguém — asserir presença seria pedir a regressão.
3. **UC-JPERM-08 — rota da emenda errada.** A emenda cita `/copiloto/admin/jana-pro/preview`; a rota viva é `/ia/admin/jana-pro/preview` (o teste #6430 já usa a certa).

## Fora do prefixo (não feito, de propósito)
- Colar os UCs `UC-JPERM-01…06` nos `.casos.md` (`Index`, `Chat`) — está em `resources/js/`, `nao_toca` desta thread. Os testes já citam os ids; a emenda nos casos é uma thread própria.
- UC-JNAME-01 (rename do grupo "Copiloto" → "Jana" nas labels) está na emenda mas **não** nas 6 provas desta thread, e o teste dele mora em `tests/Feature/Permissions/` (fora do prefixo). Não tocado.
- **O Cowork precisa replicar a correção da prova do UC-JPERM-08 no `00-INDICE.md` da cópia dele** — senão o próximo import com `/PURGE` a desfaz.
