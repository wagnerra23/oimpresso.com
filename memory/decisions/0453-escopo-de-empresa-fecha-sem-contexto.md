---
slug: 0453-escopo-de-empresa-fecha-sem-contexto
number: 453
title: "Escopo de empresa fecha quando não há contexto: consulta sem empresa definida lança exceção em vez de devolver tudo (emenda da 0093, Garantia 2)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-11-15"
module: governance
quarter: 2026-Q4
tags: [multi-tenant, tier-0, global-scope, fail-closed, api, jobs, seguranca]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0093-multi-tenant-isolation-tier-0
pii: false
review_triggers:
  - "o modo log acusar mais de 50 pontos distintos sem contexto (o rollout precisa ser fatiado por módulo)"
  - "algum job ou comando legítimo de plataforma não couber em ContextoEmpresa::comoPlataforma()"
---

# ADR 0453 — Escopo de empresa fecha quando não há contexto

## Contexto

A ADR 0093 (Garantia 2) manda todo model de negócio usar global scope por `business_id`. Os scopes existentes leem a empresa de `session('user.business_id')` e, **quando não há sessão, não aplicam filtro nenhum** — devolvem as linhas de todas as empresas:

- `App\Scopes\ScopeByBusiness` (trait `HasBusinessScope`): retorna sem filtrar se `! auth()->check()` ou se a sessão não tem empresa.
- `BusinessScopeImpl` (Financeiro), `NfseBusinessScope` (NFSe), `EscopoPorNegocioDaSessao` (ComunicacaoVisual, OficinaAuto), o scope do KB, do Fisio e os closures avulsos seguem a mesma regra.

Na web isso não aparece, porque `SetSessionData` preenche a sessão. Fora dela aparece:

- **API com token (Connector, app):** `auth()->check()` é verdadeiro, mas a sessão não tem empresa. Todo model "escopado" responde sem filtro.
- **Jobs e comandos:** sem sessão. A Garantia 3 manda passar `$businessId` e filtrar à mão, mas esquecer o filtro não dá erro — devolve tudo.

Ou seja, a camada que a 0093 chama de "defesa em profundidade" desliga exatamente onde a primeira camada (o filtro manual) mais falha. A auditoria de permissões de 2026-11-14 achou um caso real na API do Connector.

Prior art: spatie/laravel-multitenancy (`Tenant::current()` + middleware `NeedsTenant`) e stancl/tenancy resolvem com um **contexto de tenant explícito** em vez de ler a sessão. Não adotamos o pacote (o modelo deles é banco/tenant por domínio); adotamos o padrão.

## Decisão

1. **Contexto de empresa explícito.** Nasce `App\Tenancy\ContextoEmpresa` (singleton por request/job) com `definir(int $businessId)`, `atual(): ?int`, `exigir(): int` e `comoPlataforma(callable)`.
   - Web: `SetSessionData` define a partir da sessão.
   - API com token: middleware novo no grupo `auth:api` define a partir de `auth()->user()->business_id`.
   - Jobs: o job que já recebe `$businessId` (Garantia 3) chama `definir()` no `handle()`; um trait `ComEmpresa` faz isso.
   - Comandos e jobs de plataforma (varrem todas as empresas): rodam o trecho dentro de `comoPlataforma()`, que fica registrado no log com o motivo.

2. **Os scopes leem o contexto, não a sessão, e fecham sem ele.** Com contexto → `where business_id = contexto`. Sem contexto e fora de `comoPlataforma()` → **lança `ContextoEmpresaAusente`**. Nunca mais devolve tudo em silêncio. `withoutGlobalScope(s)` com o comentário `// SUPERADMIN:` (0093) continua sendo a saída explícita.

3. **Rollout em três modos**, pela flag `tenancy.escopo_sem_contexto` = `aberto | log | fechado`:
   - `aberto`: comportamento atual (padrão no merge desta ADR).
   - `log`: comportamento atual + um registro (`ContextoEmpresaAusente` com model, rota/comando e stack curta) para cada consulta sem contexto. Roda em produção até zerar os pontos não-plataforma.
   - `fechado`: lança. Liga primeiro para a API (`auth:api`), depois jobs, por último comandos.

4. **Teste de contrato** em cada fase: consulta sem contexto lança no modo `fechado`; com contexto filtra; `comoPlataforma()` não filtra e registra.

## Justificativa

- Falhar fechado é o comportamento correto para isolamento: um 500 visível é recuperável, um vazamento silencioso não é (0093 §Contexto).
- Contexto explícito tira a dependência da sessão, que é o motivo da falha na API e nos jobs.
- O modo `log` existe porque há centenas de `withoutGlobalScope` e comandos que hoje contam com o filtro aberto; virar a chave de uma vez derrubaria jobs em produção. A medição vem antes da trava.

## Consequências

**Positivas:** a API e os jobs passam a ter o mesmo isolamento da web; esquecer o filtro manual vira erro, não vazamento; `comoPlataforma()` deixa auditável quem lê todas as empresas.

**Negativas / Trade-offs:** cada job/comando de plataforma precisa ser marcado; o modo `log` gera volume no período de medição; scopes de módulo (Financeiro, NFSe, ComVis…) precisam migrar para o contexto, um PR por módulo.

**Riscos mitigados:** vazamento entre empresas via API com token, via job que esqueceu o `where`, e via comando que roda sem sessão.

## Referências

- ADR 0093 — Multi-tenant isolation Tier 0 (Garantias 2 e 3).
- `app/Scopes/ScopeByBusiness.php`, `Modules/Financeiro/Models/Concerns/BusinessScopeImpl.php`, `Modules/NFSe/Models/Concerns/NfseBusinessScope.php`.
- spatie/laravel-multitenancy — `Tenant::current()`, `NeedsTenant` (padrão, não dependência).
