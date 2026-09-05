---
id: resources-js-pages-essentials-licencas-index-casos
casos: HRM · licenças · /hrm/leave
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-09-05"
---

# Casos de uso — /hrm/leave · Licenças (HRM)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) ·
> ⬜ não verificado · ❌ quebrou.

> Os UC derivam do **contrato** — [`Index.charter.md`](Index.charter.md) (lei) + o
> `EssentialsLeaveController` real — **nunca** do `.tsx` (§5 2026-06-05). O `✅` vem do manifesto
> `scripts/casos-test-results.json`, derivado do JUnit; **não se escreve à mão** (G-7).

> ⚠️ **A lane que roda isto não é required.** `PHP / Pest (Essentials · MySQL)` **não** está
> entre os contexts de [`governance/required-checks-baseline.json`](../../../../../governance/required-checks-baseline.json).
> Logo o vermelho **não bloqueia merge** — o que obriga a ler o resultado da lane, não o selo.
> Pest local é proibido ([ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)):
> o run acontece no CI ou no CT 100.

---

## Servidor — o pedido e a decisão

## UC-HRM-23 — Canário: pedido válido cria a licença
Status: ✅ (**controle positivo**, não enfeite: sem ele, um `403` do gate de assinatura seria
indistinguível de "o servidor validou" nos casos abaixo — os dois falham o `assertStatus(422)`
do mesmo jeito. §5 2026-08-01.)
Um POST em `/hrm/leave` com tipo do próprio negócio, período coerente e motivo preenchido cria
a licença e responde 200.
**Pronto quando:** o status é 200 **e** a contagem de `essentials_leaves` do tenant sobe em 1.

## UC-HRM-02 — Fim antes do início é recusado
Status: ✅ (era o achado **A2**; fechado pelo `StoreLeaveRequest` no PR #6797.)
Pedir licença com `end_date` anterior a `start_date` tem que ser recusado pelo **servidor** —
validação de tela não conta, o endpoint é chamável direto.
**Pronto quando:** responde 422 **e** nada é gravado em `essentials_leaves`.

## UC-HRM-15 — Motivo vazio e tipo ausente são recusados
Status: ✅ (achado **A2**, fechado.)
`reason` é obrigatório (sem motivo, o aprovador decide no escuro) e
`essentials_leave_type_id` idem.
**Pronto quando:** responde 422 **e** nada é gravado.

## UC-HRM-05 — Tipo de licença de OUTRO negócio é recusado
Status: ✅ (achado **A2** + Tier 0 [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
O teste usa um tipo que **existe de verdade** no tenant alheio — é o caso perigoso, porque um
`exists` sem escopo de business aceitaria esse id.)
Enviar `essentials_leave_type_id` de outro `business_id` tem que ser recusado. Complementa o
`MultiTenantLeaveTest`, que prova o isolamento na **query** (listagem/show/update/destroy
scoped) mas não no **write HTTP** com id cru do corpo.
**Pronto quando:** responde 422, nada é gravado, **e** não existe licença do tenant apontando
pro tipo do outro.

## UC-HRM-03 — Pedido que estoura o limite do tipo é recusado
Status: ✅ (era o achado **A3**; fechado pelo `LeaveBalanceService` no PR #6797.)
Tipo com limite de 30 dias/ano e 22 já aprovados: pedir mais 15 (37 no total) tem que ser
recusado, dizendo o saldo. Dias contam inclusivos nas duas pontas (R6).
**Pronto quando:** responde 422 **e** nada é gravado.

## UC-HRM-09 — Aprovar também não pode estourar o limite
Status: ✅ (achado **A3** na outra porta: só validar no `store()` deixaria o buraco — duas
licenças dentro do limite, aprovadas as duas, estouram.)
Aprovar uma licença pendente que leva o tipo além do limite tem que ser recusado.
**Pronto quando:** responde 422 **e** a licença continua `pending`.

## UC-HRM-19 — Tipo com limite 0 não bloqueia (0 = sem limite)
Status: ✅
Limite `0` significa "sem limite", não "zero dias permitidos" — senão o tipo fica inutilizável.
**Pronto quando:** o pedido responde 200 **e** a licença é criada.

## UC-HRM-18 — Excluir tipo de licença EM USO é recusado com motivo
Status: ✅ (era o achado **A4**; fechado no PR #6789 — devolve 422 com `blocked_by`.)
Excluir um tipo que tem licenças vinculadas tem que devolver erro dizendo **quantas** travam.
**Pronto quando:** responde 422 **e** o tipo continua existindo.

---

## Tela — o que o PR-9 acrescenta

## UC-HRM-30 — A rota entrega a tela Inertia, não o Blade
Status: 🧪
`GET /hrm/leave` passa a responder o componente Inertia `Essentials/Licencas/Index`. É o coração
da migração: enquanto a rota devolver Blade, nada mais nesta seção significa alguma coisa.
**Pronto quando:** a resposta tem `X-Inertia: true` **e** `component` é
`Essentials/Licencas/Index`.

## UC-HRM-31 — As props que a tela precisa para renderizar chegam
Status: 🧪
A tela lê `filtros`, `permissoes`, `situacoes` e `date_format` como props **eager** (as demais
são `Inertia::defer` e por construção não vêm no primeiro payload). `date_format` não é detalhe:
sem ele o formulário posta ISO, o `uf_date` lança e o erro chega como "algo deu errado" (R13).
**Pronto quando:** as quatro props existem no payload **e** `situacoes` traz as três situações
do R1.

## UC-HRM-32 — Tier 0: a lista não vaza licença de outro negócio
Status: 🧪
Com uma licença existindo no tenant alheio, o payload da tela do tenant 98 não pode conter o
`ref_no` dela. Complementa o UC-HRM-05, que cobre a **escrita**; este cobre a **leitura da tela**.
**Pronto quando:** o `ref_no` da licença alheia não aparece no payload Inertia.

## UC-HRM-33 — Quem só tem `crud_own_leave` não recebe a lista de colaboradores
Status: 🧪
R3: o recorte é do controller. A UI esconde o seletor de colaborador, mas esconder não é
defender — o teste prova que a **prop** `permissoes.ver_todos` vem `false` e que o filtro
`user_id` é ignorado, não que o `<select>` sumiu.
**Pronto quando:** `permissoes.ver_todos` é `false` **e** `filtros.user_id` é `null` mesmo tendo
sido enviado na querystring.

## UC-HRM-34 — O DataTables do Blade continua respondendo (anti-regressão)
Status: 🧪
A blade `leave/index.blade.php` ainda existe e é o consumidor do ramo `request()->ajax()`. Ela só
sai no HRM-O8; até lá, migrar a rota **não pode** matar o JSON que ela consome.
**Pronto quando:** um GET com `X-Requested-With: XMLHttpRequest` e **sem** `X-Inertia` devolve o
JSON do DataTables (`data` no corpo), não o payload Inertia.

## UC-HRM-35 — `show()` e `edit()` redirecionam em vez de estourar 500
Status: 🧪
R10: as duas rotas do resource retornavam views inexistentes (`essentials::show`/`essentials::edit`)
e respondiam 500 a quem as alcançasse. O detalhe da licença vive no drawer do Index.
**Pronto quando:** as duas respondem 302 para `/hrm/leave`.

---

## Backlog — prosa sem id (vira UC quando ganhar teste que o cite)

> Convenção do [`how-trabalhar.md`](../../../../../memory/how-trabalhar.md): bullet `[BACKLOG]`
> sem id é contrato visível **sem gate**. Declará-los como `UC-` sem teste que os defenda criaria
> UC órfão (violação G-2 do [ADR 0264](../../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md),
> gate **required**) ou obrigaria a citá-los num teste que não os prova, que é carimbo.

- `[BACKLOG]` Os KPIs mostram pendentes, aprovadas e tipos cadastrados — e são do negócio
  inteiro, **não** do filtro (é decisão de desenho, e o número precisa dizer isso na tela).
- `[BACKLOG]` Filtro situação + tipo reflete na lista, na contagem "N de M" e na paginação;
  limpar devolve tudo.
- `[BACKLOG]` Busca sem resultado mostra estado vazio **com motivo** e ação "Limpar busca e
  filtros" — nunca tabela vazia muda.
- `[BACKLOG]` Linha pendente com início já vencido aparece marcada como urgente; cancelada
  aparece esmaecida.
- `[BACKLOG]` Clique na linha abre o drawer; clique no checkbox ou nos botões de ação **não**
  abre (é o `stopPropagation`).
- `[BACKLOG]` Aprovar notifica o colaborador (`LeaveStatusNotification`) e o activitylog
  registra quem mudou (R4).
- `[BACKLOG]` Sem `essentials.approve_leave`, nem a linha nem o lote oferecem Aprovar/Cancelar.
- `[BACKLOG]` Lote de cancelamento confirma dizendo **quantas** licenças e que cada colaborador
  é notificado, antes de executar.
- `[BACKLOG]` Licença cancelada abre o drawer com histórico e observação, e **sem** ação de
  editar (R9).
- `[BACKLOG]` Criar para 3 colaboradores gera 3 referências sequenciais no prefixo configurado,
  e cada admin recebe 3 notificações (R2, R5).
- `[BACKLOG]` A aba "Saldo por tipo" mostra limite, aprovado, em análise, consumo e marca de
  risco quando aprovar estoura.
- `[BACKLOG]` Tipo novo com limite 0 aparece como "sem limite" na aba de saldo.
- `[BACKLOG]` O drawer mostra "Feriados dentro do período" quando a licença cobre um feriado —
  hoje ele mostra **conflitos com outras licenças**, e diz que olha só a página carregada.
- `[BACKLOG]` `Esc` fecha o drawer sem perder filtro nem página.
- `[BACKLOG]` `/` foca a busca e `n` abre o pedido, e nenhum dos dois dispara com o foco dentro
  de um campo de texto.
- `[BACKLOG]` O formulário mostra `essentials_settings.leave_instructions` antes dos campos (R2)
  — hoje a instrução **não** é carregada pela tela nova.
