---
id: resources-js-pages-hrm-licencas-index-casos
casos: HRM · licenças · /hrm/leave
irmaos: Index.charter.md (lei) · Index.tsx (tela — chega no PR-9)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-09-04"
---

# Casos de uso — /hrm/leave · Licenças (HRM)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) ·
> ⬜ não verificado · ❌ quebrou.

> Os UC derivam do **contrato** — [`Index.charter.md`](Index.charter.md) (lei) + o
> `EssentialsLeaveController` real — **nunca** do `.tsx` (§5 2026-06-05). A tela ainda não
> existe: este trio nasce no PR-1 da onda HRM-O5 do
> [`PEDIDO-CL-hrm.md`](../../../../../../../prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md),
> e a Page vem no PR-9. Por isso todo UC aqui é de **servidor** — o que existe hoje pra ser
> defendido.

> ⚠️ **Seis UC nascem `❌` e isso é a ENTREGA, não um defeito a esconder.** São os achados
> **A2** (`store()` sem FormRequest), **A3** (`max_leave_count` nunca aplicado) e **A4**
> (`destroy` de tipo com corpo vazio), lidos no controller. Viram ✅ com os PRs 2, 3 e 5 do
> pedido. O `✅` vem do manifesto `scripts/casos-test-results.json`, derivado do JUnit —
> **não se escreve à mão** (G-7).

> ⚠️ **A lane que roda isto não é required.** `PHP / Pest (Essentials · MySQL)` **não** está
> entre os 45 contexts de [`governance/required-checks-baseline.json`](../../../../../../../governance/required-checks-baseline.json)
> (as lanes Pest required são Compras, Estoque, Financeiro, KB, NfeBrasil, Ponto, Sells e Unit).
> Logo o vermelho **não bloqueia merge** — deixa a lane advisory vermelha até o PR-3.
> O arquivo entrou na allowlist do [`essentials-pest.yml`](../../../../../../../.github/workflows/essentials-pest.yml)
> **failing-first**, pelo mesmo desvio já declarado no `forja-pest.yml` e no `compras-pest.yml`:
> rodar Pest local é proibido (Tier 0, [ADR 0062](../../../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)),
> então o primeiro run só pode acontecer no CI.

## UC-HRM-23 — Canário: pedido válido cria a licença
Status: 🧪 (1 teste cita este UC. É **controle positivo**, não enfeite: sem ele, um `403` do
gate de assinatura seria indistinguível de "o servidor validou" nos seis casos abaixo — os dois
falham o `assertStatus(422)` do mesmo jeito. §5 2026-08-01.)
Um POST em `/hrm/leave` com tipo do próprio negócio, período coerente e motivo preenchido cria
a licença e responde 200.
**Pronto quando:** o status é 200 **e** a contagem de `essentials_leaves` do tenant sobe em 1.

## UC-HRM-02 — Fim antes do início é recusado
Status: ❌ (achado **A2** — hoje grava. O `store()` faz `$request->only([...])` sem FormRequest,
e o `try/catch` devolve array → HTTP 200 mesmo em erro.)
Pedir licença com `end_date` anterior a `start_date` tem que ser recusado pelo **servidor** —
validação de tela não conta, o endpoint é chamável direto.
**Pronto quando:** responde 422 **e** nada é gravado em `essentials_leaves`.

## UC-HRM-15 — Motivo vazio e tipo ausente são recusados
Status: ❌ (achado **A2** — hoje grava, com `essentials_leave_type_id` nulo.)
`reason` é obrigatório (o charter R2 diz que a instrução ao colaborador vem das configurações;
sem motivo, o aprovador decide no escuro) e `essentials_leave_type_id` idem.
**Pronto quando:** responde 422 **e** nada é gravado.

## UC-HRM-05 — Tipo de licença de OUTRO negócio é recusado
Status: ❌ (achado **A2** + Tier 0 [ADR 0093](../../../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
O teste usa um tipo que **existe de verdade** no tenant alheio — é o caso perigoso, porque um
`exists` sem escopo de business aceitaria esse id.)
Enviar `essentials_leave_type_id` de outro `business_id` tem que ser recusado. Complementa o
`MultiTenantLeaveTest`, que prova o isolamento na **query** (listagem/show/update/destroy
scoped) mas não no **write HTTP** com id cru do corpo.
**Pronto quando:** responde 422, nada é gravado, **e** não existe licença do tenant apontando
pro tipo do outro.

## UC-HRM-03 — Pedido que estoura o limite do tipo é recusado
Status: ❌ (achado **A3** — `max_leave_count` e `leave_count_interval` são informativos hoje:
nenhum código os lê na hora de decidir.)
Tipo com limite de 30 dias/ano e 22 já aprovados: pedir mais 15 (37 no total) tem que ser
recusado, dizendo o saldo. Dias contam inclusivos nas duas pontas (R6).
**Pronto quando:** responde 422 **e** nada é gravado.

## UC-HRM-09 — Aprovar também não pode estourar o limite
Status: ❌ (achado **A3** na outra porta. Só validar no `store()` deixa o buraco: duas licenças
dentro do limite, aprovadas as duas, estouram — e `changeStatus` grava `status` cru sem checar
nada.)
Aprovar uma licença pendente que leva o tipo além do limite tem que ser recusado.
**Pronto quando:** responde 422 **e** a licença continua `pending`.

## UC-HRM-18 — Excluir tipo de licença EM USO é recusado com motivo
Status: ❌ (achado **A4** — `EssentialsLeaveTypeController::destroy()` é **corpo vazio**: a rota
responde 200 e não apaga nada. Fica verde com o PR-5.)
Excluir um tipo que tem licenças vinculadas tem que devolver erro dizendo **quantas** travam —
ou a rota sai do resource (HRM-O8). Hoje a UI não oferece a ação, mas a rota existe e responde.
**Pronto quando:** responde 422 **e** o tipo continua existindo.

---

## Backlog — prosa sem id (vira UC quando ganhar teste que o cite)

> Convenção do [`how-trabalhar.md`](../../../../../../../memory/how-trabalhar.md): bullet
> `[BACKLOG]` sem id é contrato visível **sem gate**. Estes vieram do artefato original do
> pedido e descrevem a **tela** — que não existe. Declará-los como `UC-` hoje criaria UC órfão
> (violação G-2 do [ADR 0264](../../../../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md),
> gate **required**) ou obrigaria a citá-los num teste que não os defende, que é carimbo.

- `[BACKLOG]` A lista mostra 4 KPIs (pendentes, aprovadas, dias no mês, tipos) e a contagem do
  KPI bate com a lista filtrada por situação.
- `[BACKLOG]` Quem só tem `essentials.crud_own_leave` vê apenas as próprias licenças e o filtro
  de colaborador **não aparece** (R3).
- `[BACKLOG]` Filtro situação + tipo reflete na lista, na contagem "N de M" e na paginação;
  limpar devolve tudo.
- `[BACKLOG]` Busca sem resultado mostra estado vazio **com motivo** e ação "Limpar busca e
  filtros" — nunca tabela vazia muda.
- `[BACKLOG]` Aprovar notifica o colaborador (`LeaveStatusNotification`) e o activitylog
  registra quem mudou (R4).
- `[BACKLOG]` Sem `essentials.approve_leave`, nem a linha nem o lote oferecem Aprovar/Cancelar.
- `[BACKLOG]` Lote de cancelamento confirma dizendo **quantas** licenças e que cada colaborador
  é notificado, antes de executar.
- `[BACKLOG]` Licença cancelada abre o drawer com histórico e observação, e **sem** ação de
  editar (R9).
- `[BACKLOG]` O formulário mostra `essentials_settings.leave_instructions` antes dos campos (R2).
- `[BACKLOG]` Criar para 3 colaboradores gera 3 referências sequenciais no prefixo configurado,
  e cada admin recebe 3 notificações (R2, R5).
- `[BACKLOG]` A aba "Saldo por tipo" mostra limite, aprovado, em análise, barra de consumo e
  marca de risco quando aprovar estoura.
- `[BACKLOG]` Tipo novo com limite 0 aparece como "sem limite" na lista e no saldo.
- `[BACKLOG]` O drawer mostra "Feriados dentro do período" quando a licença cobre um feriado.
- `[BACKLOG]` `Esc` fecha o drawer sem perder filtro nem página.
- `[BACKLOG]` **Depende de D3:** o drawer diz o que acontece com marcação de presença já
  lançada dentro do período aprovado. Sem a decisão [W], escrever o aceite seria inventar
  comportamento — anti-padrão que parece canon.
