---
id: requisitos-essentials-runbook-metas
title: "RUNBOOK — Metas de venda (`/hrm/sales-target`)"
module: Essentials
tela: Essentials/Metas
owner: W
status: rascunho
last_validated: "2026-09-05"
preconditions:
  - "Usuário autenticado com a permission `essentials.access_sales_target` (ou admin/superadmin do business)"
  - "Módulo `essentials_module` habilitado no pacote do business (Camada 1 — superadmin/packages)"
  - "`business_id` na sessão — `User` e `EssentialsUserSalesTarget` são escopados por business (ADR 0093, Tier 0)"
  - "Tabela `essentials_user_sales_targets` migrada (2021_09_28_091541)"
preconditions_short: permission essentials.access_sales_target, business_id na sessão, módulo habilitado
---

# RUNBOOK — Metas de venda (`/hrm/sales-target`)

> **F1 PLAN do MWART (ADR 0104).** Escrito ANTES de codar a Page — o hook
> `block-mwart-violation` exige, e está certo.
>
> Trio da tela: [`Metas.charter.md`](../../../resources/js/Pages/Essentials/Metas.charter.md) (lei) ·
> [`Metas.casos.md`](../../../resources/js/Pages/Essentials/Metas.casos.md) (contrato de teste) ·
> `prototipo-ui/contrato/essentials-metas.contract.json` (fidelidade visual).
>
> Onda 9 do [`EXPORT-HRM-2026-09-04`](../../../prototipo-ui/design-docs/cowork-inbox/hrm/EXPORT-HRM-2026-09-04.md) ·
> PR-9 do [`PEDIDO-CL-hrm`](../../../prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md).

## 1. Objetivo

Dar a quem administra o RH um lugar para **ver e definir a faixa de venda que paga comissão**
a cada colaborador.

Hoje a tela legada (`sales_targets/index.blade.php`) mostra **duas colunas** — nome e um botão —
e as faixas só aparecem depois de abrir um modal, um colaborador por vez. Quem quer saber
"quem está sem meta" precisa abrir todos, um a um.

## 2. Persona principal

Wagner / administrador do business (1280px). **Não é tela de balcão** — a Larissa não define
meta; ela é sujeito da meta, não operadora dela.

## 3. Pré-requisitos

Ver `preconditions` no frontmatter. A permission `essentials.access_sales_target` nasce `false`:
mesmo com o módulo habilitado, o item só responde depois de ligá-la numa função em
`/roles/{id}/edit` (Camada 3 — dado de runtime, nenhum gate cobra).

## 4. Fluxo principal (golden path)

1. Admin abre `/hrm/sales-target`.
2. A lista mostra os colaboradores (`allow_login = 1`) do business, com as faixas já gravadas:
   quantas são, o valor inicial da menor, o final da maior, e o percentual (ou o intervalo de
   percentuais) que elas pagam.
3. Busca por nome/usuário/e-mail filtra server-side (`?q=`).
4. "Definir meta" / "Editar faixas" abre o diálogo com uma linha por faixa
   (**vendido de** · **até** · **comissão %**).
5. Salvar envia para `POST /hrm/save-sales-target`. O servidor valida e responde:
   - faixa com fim ≤ início, faixas sobrepostas, ou percentual fora de 0–100 ⇒ mensagem de erro
     dizendo **qual** faixa e **por quê**, e **nada é gravado**;
   - conjunto válido ⇒ grava e volta com sucesso.

## 5. Onda desta entrega, e o que fica pra depois

**Entra:** a lista (PT-01) + o editor de faixas, substituindo a Blade de 2 colunas e o modal
jQuery, sem perder capacidade nenhuma.

**Fica pra depois — declarado, não esquecido:** a **apuração**. O protótipo desenha
`Mês anterior`, `Mês atual`, `Faixa atingida`, `Progresso na faixa` e `Comissão` em dinheiro.
Todas dependem de **quanto o colaborador vendeu**, e o único produtor desse número hoje é
`DashboardController::getUserSalesTargets` — que é **admin-only** (`abort(403)`) e responde
DataTables, não JSON.

Trazer a apuração é **caminho de valor**: exige a dupla prova por caminhos independentes e o
impacto antes→depois que a regra mestre de [`proibicoes.md`](../../proibicoes.md) manda.
É PR próprio. Esta tela **declara a ausência** num aviso no topo, em vez de renderizar cinco
colunas de travessão ou — pior — inventar o número.

## 6. Estados (loading / empty / error / success)

| Estado | O que aparece |
|---|---|
| loading | `Skeleton` enquanto o `Inertia::defer` do paginator resolve (a lista é a prop cara) |
| empty | "Nenhum colaborador encontrado." (busca sem resultado ou business sem colaborador) |
| sem meta | linha com `Badge` "sem meta" e travessão nas colunas de valor — ausência real, não erro |
| error | toast vermelho com a mensagem do servidor (o middleware já mapeia `status.msg` → `flash.error`) |
| success | toast verde + a lista recarrega com as faixas novas |

## 7. Atalhos de teclado

Nenhum próprio. A busca do `DataTable` e o `Dialog` do DS já respondem a `Tab`/`Esc`.

## 8. Dependências de API/backend

| Rota | Método | Papel |
|---|---|---|
| `/hrm/sales-target` | GET | `SalesTargetController@index` — **Inertia** quando navegação normal; o ramo `request()->ajax()` (DataTables) fica **intacto** enquanto a Blade existir (sai na HRM-O8) |
| `/hrm/save-sales-target` | POST | `SalesTargetController@saveSalesTarget` — **inalterado nesta onda** |
| `/hrm/set-sales-target/{id}` | GET | modal Blade legado — **inalterado**; a tela Inertia não o consome |

**Contrato de campo do POST** (idêntico ao do modal Blade — `montarFaixas` lê exatamente estes):

- `user_id`
- `edit_target[<id>][target_start|target_end|commission_percent]` — faixas já gravadas
- `sales_amount_start[]`, `sales_amount_end[]`, `commission[]` — faixas novas

Os valores vão como **texto pt-BR com 2 casas** (`formatDecimalPtBR`), nunca float cru.

## 9. Multi-tenant + LGPD

- `index` e `saveSalesTarget` leem `business_id` da sessão; a listagem é
  `User::where('business_id', $business_id)`.
- O `whereIn('user_id', ...)` das faixas usa **os ids da página já escopada** — nunca alcança
  colaborador de outro tenant.
- O **gate Tier 0 do `saveSalesTarget`** (`User::where('business_id',...)->findOrFail($request->user_id)`)
  segue **intacto**: o `user_id` chega cru do body e o global scope filtra `SELECT`, não `INSERT`.
  Provado por [`SalesTargetShiftCrossTenantTest`](../../../Modules/Essentials/Tests/Feature/SalesTargetShiftCrossTenantTest.php) (4 casos).
- Sem PII nova: a tela mostra nome de colaborador, que já é o dado da lista legada.

## 10. Smoke check pós-deploy

1. `curl -sv https://oimpresso.com/hrm/sales-target` autenticado → `200` e HTML Inertia.
2. Abrir em 1280px, dark: a lista carrega depois do skeleton; colaborador sem faixa mostra
   `sem meta`.
3. Definir meta com uma faixa válida e conferir que a linha passa a "com meta":

   ```
   vendido de   1.000,00
   até          2.000,00
   comissão %   5,00
   → toast verde · coluna "Meta inicial" mostra o início da faixa formatado
   ```

4. Faixa invertida — o fim menor que o início — deve ser **recusada**, com toast vermelho
   citando a faixa, e **nada gravado**:

   ```
   vendido de   1.000,00
   até            500,00
   → "o valor final precisa ser MAIOR que o inicial"
   ```

5. Duas faixas encostadas ponta com ponta devem ser **recusadas** por sobreposição
   (a comparação é inclusiva nos dois extremos):

   ```
   faixa 1:  0,00      até  1.000,00
   faixa 2:  1.000,00  até  2.000,00
   → "se sobrepõem … a comissão paga ficaria indefinida"
   ```

## 11. O que NÃO fazer

- ❌ **Calcular comissão na tela.** Quem transforma faixa em dinheiro é o `PayrollController`.
  Um segundo cálculo no front divergiria do que a folha paga.
- ❌ **Enviar float cru** para `/hrm/save-sales-target`. A heurística de `Util::num_uf` trata
  "1 ponto + **exatamente 3** dígitos" como separador de milhar, então `String(1.234)` do JS
  é lido como `1234` — mil vezes maior. O parser não tem como distinguir milhar pt-BR de
  decimal en-US nessa faixa: a string é a mesma. A defesa é a **forma de envio** (sempre 2
  casas com vírgula decimal), não o parser. Fixado pelo controle negativo do UC-METAS-06.

  > ⚠️ **Errata (2026-09-05, pega pelo CI):** a 1ª versão desta linha dizia que `204.99605`
  > viraria `20499605`. **Falso hoje** — foi justamente o incidente de 2026-06-05 que fez o
  > `num_uf` ganhar a regra "1 ponto + ≥4 dígitos = decimal" (`Util.php` ~L80-90), que trata
  > esse número corretamente. A afirmação veio de ler o parser até a metade. Fica registrada
  > em vez de apagada.
- ❌ **Renomear os campos do POST.** `montarFaixas` lê os nomes literais do Blade.
- ❌ **Remover o ramo `request()->ajax()`** do `index` enquanto `sales_targets/index.blade.php`
  existir — o DataTables daquela view consome esta mesma rota.
- ❌ **Afrouxar `SalesTargetFaixaValidator`** para "deixar salvar". As três regras vêm de
  defeitos medidos na query do `PayrollController`.
- ❌ **Usar `biz=4`** (ROTA LIVRE) em teste ou smoke. Teste é tenant **98**, adversário **99**
  ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).

## 12. Diagnóstico/Troubleshoot

| Sintoma | Causa provável |
|---|---|
| 403 ao abrir | `essentials.access_sales_target` desligada na função do usuário (Camada 3) |
| Item não aparece no menu | módulo `essentials_module` fora do pacote do business (Camada 1) |
| Lista vazia com colaboradores existindo | todos com `allow_login = 0` — o predicado é o mesmo da Blade |
| Salvou e o valor multiplicou | float cru chegou ao `num_uf` — conferir que o front manda texto pt-BR |
| "as faixas se sobrepõem" em faixas que parecem contíguas | a comparação é **inclusiva**: uma faixa que termina em X e outra que começa em X casam ambas em X. Some um centavo ao início da segunda |

## 13. Refs

- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) MWART · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) multi-tenant Tier 0
- [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md) doutrina de teste (tenant 98)
- [UI-0013](../_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) Constituição UI v2 · PT-01 Lista
- `Modules/Essentials/Services/SalesTargetFaixaValidator.php` — as 3 regras e o porquê de cada uma
- Regra mestre de valor: [`memory/proibicoes.md`](../../proibicoes.md)
