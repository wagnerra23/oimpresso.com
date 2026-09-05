---
id: requisitos-repair-decisao-w-portal-publico-2026-09-04
---

# Decisão [W] · Portal público de consulta de status (Repair)

> **Gate da Onda 2** do export do Repair (`prototipo-ui/design-docs/cowork-inbox/REPAIR-ONDAS-2026-09-04.md` §RESÍDUO item 1).
> Tudo abaixo foi medido em 2026-09-04 contra `origin/main` no tip `d6457184ea` e contra o **MySQL de produção** (`u906587222_oimpresso`), a partir do CT 100. O que não foi medido está declarado como tal (§7).

## A pergunta (uma só)

**A rota pública `/repair-status` fica com UM fator (número OU telefone, como o código faz hoje) ou com DOIS (número + telefone/CPF, como a ADR ARQ-0002 aceita exige)?**

E, junto dela, a que esta medição acrescentou: **o filtro `business_id` entra na mesma leva?** (ver §1 — é Tier 0 e independe da escolha acima).

---

## 1 · Achado que não estava na pauta: a consulta pública roda SEM `business_id`

Não é o fator duplo. É isolamento multi-tenant — [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md), princípio duro #6, **Tier 0 IRREVOGÁVEL**.

Cadeia provada, elo a elo:

| # | Elo | Prova (recibo) |
|---|---|---|
| 1 | A rota não tem `auth` | `route:list --path=repair-status` → `post-repair-status` = `["web","throttle:30,1"]`. A irmã autenticada `repair/update-repair-status` = `["web","authh","auth","SetSessionData",...]` |
| 2 | Logo `auth()->check()` é falso ali | medido no mesmo runtime: `AUTH_CHECK=false` |
| 3 | O scope desiste quando não há auth | `app/Scopes/ScopeByBusiness.php:26` → `if (! auth()->check()) { return; }` |
| 4 | O SQL sai sem `business_id` | `JobSheet::query()->where("job_sheet_no","1")->toSql()` **sem auth** → `select * from repair_job_sheets where job_sheet_no = ?` |
| 5 | **Controle positivo** — o scope funciona quando há auth | mesma linha **com** auth → `... and (repair_job_sheets.business_id = ? or repair_job_sheets.business_id is null)` |

O elo 5 existe para provar que a sonda mede o que diz medir: o filtro **não** some por acaso — some **porque** não há sessão autenticada, que é a condição permanente de uma rota pública.

**Consequência:** com dado na tabela, a consulta pública devolve OS de **qualquer** business. `job_sheet_no` é sequencial por tenant, então números baixos colidem entre tenants por construção — quem buscar `1` recebe uma linha de cada.

**Hoje isso é latente, não explorável** — ver §3: a tabela está vazia.

---

## 2 · O que a resposta pública devolve, campo a campo

Lido agora: `customer_repair/repair_details.blade.php` + a partial `repair_activities.blade.php` que ele inclui na linha 52. (O doc de ondas declarava este arquivo como não-lido — *"por isso não afirmo se o Blade legado do portal mostra preço"*. Fica fechado.)

| # | Campo renderizado | Origem | ADR ARQ-0002 |
|---|---|---|---|
| 1 | Número da OS | `job_sheet_no` | permitido |
| 2 | Marca | `b.name` | além de "status e data", não proibido nominalmente |
| 3 | Dispositivo | `device.name` | idem |
| 4 | Modelo | `rdm.name` | idem |
| 5 | **Número de série** | `serial_no` | idem — mas é identificador do bem do cliente |
| 6 | Status + cor | `rs.name` / `rs.color` | é o objetivo da tela |
| 7 | Data estimada | `delivery_date` | permitido |
| 8 | **Nome completo do funcionário** | `activity->causer->user_full_name` (partial, linha 22) | ❌ **viola** — a ADR diz *"sem assignee"* |
| 9 | Nota livre do técnico | `getExtraProperty("update_note")` (partial, linha 25) | ❌ **viola** — texto interno, não previsto |
| 10 | Datas de conclusão (de→para) | `completed_on_from` / `_to` | idem |

**Preço: NÃO aparece.** `estimated_cost` existe na tabela e chega ao objeto pelo `select('repair_job_sheets.*', ...)` do controller (linhas 79-86), mas **nenhuma das duas blades o imprime**. `parts` idem. Portanto **não há achado de preço a subir em separado** — a violação da ADR está no **responsável** (linha 8) e na **nota interna** (linha 9).

**Risco latente de mesma origem:** o `select *` carrega para a view, sem imprimir, `security_pwd` e `security_pattern` — **senha e padrão de desbloqueio do aparelho do cliente**. Basta alguém acrescentar uma linha na blade. A defesa hoje é a omissão, não um filtro.

---

## 3 · Alcance real em produção

Medido do CT 100 contra o MySQL de prod (`docker exec oimpresso-mcp php artisan tinker`, apenas `SELECT`):

| Métrica | Valor |
|---|---|
| Businesses no total | **88** |
| Packages com `repair` em `custom_permissions` | **2** de 75 |
| Businesses com assinatura citando `repair` | **12** |
| Businesses que configuraram status de OS (`repair_statuses`) | **0** |
| **Ordens de serviço existentes (`repair_job_sheets`)** | **0** |
| A rota `/repair-status` responde em prod | **HTTP 200** (`curl -sv https://oimpresso.com/repair-status`) |

Controle positivo do mesmo banco, para provar que não é um DB vazio: `transactions` = **75.421**, `contacts` = **30.108**.

**Leitura:** o módulo está habilitado em 12 businesses, a rota pública está **aberta e respondendo**, e **nunca foi usada** — zero status configurado, zero OS. A rota é **global e incondicional**: não passa por `hasThePermissionInSubscription`, então não é o módulo que a expõe; ela vale para o app inteiro.

---

## 4 · Custo de cada caminho

### (a) Implementar a ADR — exigir número **+** telefone/CPF

- **Quem quebra:** hoje, ninguém. **0 OS** e **0 status** em produção; não existe consulta que retorne resultado.
- **Aviso ao cliente:** dispensável pelo mesmo motivo — não há uso a interromper.
- **Quantas consultas/dia? Não há como saber pelo app — e isso é um achado.** O controller **não loga consulta bem-sucedida**: o único `Log` é `logSafeEmergency` no `catch` (linha 112). O que se sabe é o teto: com 0 OS, toda consulta hoje cai em `invalid_repair_details`.
- **Esforço:** o form já tem um segundo campo, mas é o errado — `serial_no` (`index.blade.php:33`, opcional) é do aparelho, não do titular. O que a ADR pede exige comparar contra `contacts.mobile` ou documento, ou seja, campo novo no form + validação no controller.

### (b) Emendar a ADR — assumir um fator

- **Forma:** append-only. **ADR nova com `supersedes`**, nunca editar a aceita (`memory/proibicoes.md`).
- **O que se assume:** que o telefone sozinho é chave de consulta — um número conhecido devolve o histórico de OS de quem o possui.
- **Agravante medido:** enquanto o §1 não for corrigido, "um fator" não é "um fator no meu tenant" — é **um fator no banco inteiro**.

---

## 5 · Antes → depois

| Dimensão | Hoje (legado) | (a) ADR ARQ-0002 | (b) Emenda de 1 fator |
|---|---|---|---|
| Fatores exigidos | 1 (`search_number`) | 2 (número + telefone/CPF) | 1 (declarado) |
| Consulta só por telefone | permitida | bloqueada | permitida |
| Responsável exposto | **sim** (viola ADR) | remover | decisão explícita |
| Nota interna exposta | **sim** (viola ADR) | remover | decisão explícita |
| Preço exposto | não | não | não |
| **Filtro `business_id`** | **ausente** | ausente, se não entrar junto | ausente, se não entrar junto |
| Clientes impactados pela mudança | — | **0** | **0** |
| Rate limit | `throttle:30,1` por IP | mantém | mantém |

---

## 6 · O que NÃO foi feito (de propósito)

Nada de comportamento foi alterado. Sem código, sem ADR nova, sem Page Inertia. A Onda 2 segue fechada.

## 7 · Não medido, declarado

- **Log de acesso do servidor** (LiteSpeed/Hostinger) não foi consultado — a medição foi restrita ao CT 100. Se [W] quiser o número real de hits em `/repair-status`, ele estaria lá, não no app.
- **`RepairController.php` e `JobSheetController.php`** não foram lidos: não participam da rota pública.
- **O vazamento cross-tenant não foi testado empiricamente** — com 0 linhas não há o que vazar. A prova do §1 é de **mecanismo** (SQL gerado + controle positivo), não de efeito observado.

---

## 8 · A decisão

1. **Um fator ou dois?** (a) implementar a ADR ARQ-0002, ou (b) ADR nova com `supersedes`.
2. **O `business_id` entra na mesma leva?** É Tier 0, vale para as duas opções, e o custo de corrigir agora é o menor que jamais será: **0 OS, 0 clientes impactados**.
3. **Remover responsável e nota interna da resposta pública?** Hoje violam a ADR aceita, independente da escolha 1.
