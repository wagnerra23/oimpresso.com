# ADR TECH-0001 — Drivers de apuração plugáveis (SQL / PHP / HTTP)

**Data:** 2026-04-24
**Status:** Aceita
**Escopo:** Módulo Copiloto — sistema de cálculo do realizado
**Autor/a:** Claude

---

## Contexto

Metas do Copiloto têm naturezas muito diferentes:

- **Faturamento** = `SUM(final_total)` no `transactions` — SQL puro.
- **Churn mensal** = lógica com tabela de assinaturas + ausências em window — SQL complexo ou PHP com múltiplas queries.
- **NPS** (futuro) = média de pesquisas externas — pode vir de um SaaS (TypeForm/Google Forms/API própria).
- **Meta de aquisição de clientes** = contagem em `businesses` com filtro.
- **Meta ligada a ação humana** (ex.: "implantar PontoWr2 em 3 clientes") = PHP com lógica de status.

Fixar em "só SQL" limita o escopo. Fixar em "só PHP callable" obriga toda meta simples a virar classe.

## Decisão

Sistema de **drivers plugáveis** com 3 tipos iniciais:

| Driver | Quando usar | `config_json` esperado |
|---|---|---|
| **`sql`** | Queries parametrizadas simples/médias contra o DB local | `{query, binds_extra}` |
| **`php`** | Lógica complexa, múltiplas queries, regras de negócio | `{callable: "FQCN@metodo"}` |
| **`http`** | Métrica vinda de sistema externo (API/webhook) | `{url, method, auth, json_path}` |

Cada driver implementa a interface:

```php
interface CalculaMeta {
    public function apurar(Meta $meta, CarbonInterval $janela): float;
}
```

Registrados via container Laravel:
```php
$this->app->tag([SqlDriver::class, PhpDriver::class, HttpDriver::class], 'copiloto.drivers');
```

Resolver recebe `Meta`, lê `meta_fonte.driver`, resolve a implementação, invoca.

## Regras de segurança (não negociáveis)

### Driver `sql`
- **Binds `:business_id`, `:data_ini`, `:data_fim` sempre injetados pelo driver** — a `config_json.query` nunca interpola string.
- Quando `meta.business_id IS NULL` (meta da plataforma), `:business_id` é bindado com `NULL` e a query deve ter `WHERE business_id IS NOT NULL` explicitamente, OU aceitar agregação cross-business (decisão do autor da meta).
- `PERMISSION copiloto.fontes.edit` exigida pra criar/editar fonte SQL (usuário não-técnico não tem acesso).
- Query **read-only** — driver valida que o primeiro token não-whitespace é `SELECT` ou `WITH`.
- Timeout de 10s por default, configurável por meta.

### Driver `php`
- `config_json.callable` resolvido via container — não aceita `eval`, não aceita closure serializada.
- Classe **precisa estar em `Modules\Copiloto\Drivers\Php\`** (namespace fixo) e implementar `CalculaMeta`.
- Registro explícito no `CopilotoServiceProvider::$phpDrivers` — array allowlist.
- Sem acesso ao filesystem ou rede a partir do callable (pode chamar queries — isso passa por permissões do DB).

### Driver `http`
- Autenticação via envs (`auth: "bearer:env:EXTERNA_TOKEN"`) — nunca credencial em plain text na config.
- Timeout de 15s por default.
- Retry 3× com backoff exponencial.
- Se falhar após retry, meta fica com `apuracao_status = 'erro'` e dispara alerta para superadmin.

## Alternativas consideradas e rejeitadas

- **Só SQL** — não cobre churn, lógica composta, fontes externas.
- **Só PHP callable** — obriga toda meta simples a virar classe, barreira pra usuário técnico não-dev.
- **DSL própria** (tipo formula builder Excel) — reinvento de roda, delay de semanas.
- **Query builder visual no frontend** — complexidade altíssima, não agrega em v1.

## Consequências

**Positivas:**
- Cobre 95% dos casos com SQL simples; expande para casos complexos sem mudar schema.
- Drivers isolados = testáveis individualmente (PHPUnit por driver).
- Adicionar "driver Eloquent" ou "driver GraphQL" no futuro é plug-in novo, sem migration.
- Fallback seguro: se driver explode, meta fica órfã mas módulo não derruba.

**Negativas/Custos:**
- `config_json` dinâmico = UI de edição complexa (formulário condicional por tipo de driver).
- Validação distribuída entre FormRequest + driver — risco de drift.
- Driver `php` requer deploy (classes não são hot-reloadable) — documentar claramente no RUNBOOK.

## Testes obrigatórios (DoD)

- SQL injection attempt em `query` → bloqueado pela validação "starts with SELECT".
- Query com `DROP TABLE` → rejeitada.
- Query sem bind `:business_id` em meta com `business_id NOT NULL` → rejeitada.
- PHP driver não registrado em allowlist → rejeitado.
- HTTP driver com URL não-HTTPS em produção → rejeitado.
- Idempotência: mesma apuração (mesma `data_ref` + `fonte_query_hash`) não duplica linha.

## Referências

- Padrão Chain of Responsibility já usado em `ApuracaoService` do PontoWr2 (regras CLT).
- `reference_hostinger_analise.md` (auto-memória) — exemplos de queries contra `transactions`.

---

**Última atualização:** 2026-04-24
