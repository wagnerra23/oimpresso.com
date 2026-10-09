---
sessao: "_saida-10"
thread: "10 · Registro de acompanhamento respeita access_own_schedule (D5)"
dono: "[CL]"
data: 2026-10-09
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main ff67c8356
---
# _saida-10

## Entregue
Decisão D5 ([W] 2026-10-07): *"restringir: store respeita access_own_schedule (mesmo escopo da
leitura); o dono decide pelo papel; sem config nova"*.

- `ScheduleLogController` ganhou um resolvedor só, `acompanhamentoNoEscopo()`, usado em
  `index`, `create`, `store`, `show`, `edit`, `update` e `destroy`: acompanhamento fora do negócio
  → **404**; no negócio, fora do escopo de quem só vê os próprios → **403**. O recorte é o da
  leitura de um acompanhamento em `ScheduleController@edit` (atribuído **ou** criado por mim).
  O resolvedor roda **fora** do `try`: o `catch (Exception)` genérico transformaria o 403 em
  `success: false` com HTTP 200.
- **Achado no caminho, consertado junto (Tier 0):** o `update` só conferia o negócio do
  acompanhamento quando vinha `status`, e o `destroy` e o `show` nunca conferiam. `ScheduleLog`
  não tem `business_id` nem escopo global, então dava para editar (sem status) e excluir registro
  de outro negócio passando `schedule_id` + `id`. Fechado pelo mesmo resolvedor.

## Provas
`Modules/Crm/Tests/Feature/CrmRegistroEscopoTest.php`, na lane MySQL `verticais-pest.yml`
(allowlist + os dois filtros de path):
- UC-CRMACO-22: só-own → colega → 403, zero registros e status intacto; só-own → o próprio →
  grava; all → colega → grava. Editar/excluir do colega por só-own → 403, registro intacto.
- UC-CRMACO-23 [T0]: editar **sem status** e excluir registro de outro negócio → 404, registro
  intacto. Discriminante: no `main` o `update` sem status e o `destroy` gravavam/apagavam.
Valores lidos do banco. Pest local é proibido: o veredito é a lane.

## Nota sobre o "Reuse; não reescreva o filtro"
Não há método de escopo para reusar: o recorte de "só os próprios" aparece **inline** em
`ScheduleController` (e com duas regras diferentes — a lista filtra só os atribuídos; `edit`/
`show` aceitam atribuído ou criado). Segui a regra do acompanhamento único (`edit`), que é a
leitura que um registro pressupõe. Unificar as duas regras em `Schedule` fica fora do prefixo.
