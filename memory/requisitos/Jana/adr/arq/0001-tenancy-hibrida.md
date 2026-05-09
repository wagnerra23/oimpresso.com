# ADR ARQ-0001 — Tenancy híbrida por `business_id` nullable

**Data:** 2026-04-24
**Status:** Aceita
**Escopo:** Módulo Copiloto
**Autor/a:** Claude (decisão registrada com aval de Wagner em conversa 2026-04-24)

---

## Contexto

Copiloto precisa servir **dois públicos distintos** no mesmo módulo:

1. **Clientes (businesses)** — ROTA LIVRE, e outros 6 businesses ativos do UltimatePOS. Cada um com suas próprias metas (ex.: meta de vendas do mês).
2. **Plataforma oimpresso** — Wagner/superadmin gerenciando a saúde do SaaS como um todo (ex.: meta R$ 5mi/ano de faturamento, churn < 2%/mês, etc.).

Três opções foram avaliadas:

| Opção | Descrição |
|---|---|
| A | Só superadmin (meta da casa). Simples, mas deixa de virar produto. |
| B | Só por business. Meta da plataforma fica fora — precisaria de ferramenta separada. |
| C | Híbrido — mesma estrutura serve os dois, scope por `business_id` nullable. |

Wagner escolheu C explicitamente em 2026-04-24.

## Decisão

**`business_id` é uma coluna nullable** em todas as tabelas de domínio do Copiloto:

- `copiloto_metas.business_id BIGINT NULL`
- `copiloto_conversas.business_id BIGINT NULL`

Regras:

1. **`business_id IS NOT NULL`** → meta/conversa pertence ao business. Scope padrão aplicado via global scope `ScopeByBusiness` no model.
2. **`business_id IS NULL`** → meta/conversa é da plataforma. Só usuários com permissão `copiloto.superadmin` acessam.
3. Global scope **sempre aplicado**, exceto em query explicitamente marcada `->withoutGlobalScope()` (só em controllers superadmin).
4. Criar meta com `business_id = null` requer permissão `copiloto.superadmin` (validado no FormRequest).
5. Superadmin **enxerga seu próprio business + metas da plataforma** — UI tem toggle entre os dois escopos.

## Alternativas consideradas e rejeitadas

- **Tabela separada `copiloto_metas_plataforma`** — duplica estrutura, força if/else em todos os services, não compartilha driver de apuração.
- **Coluna `owner_type` (polimórfico)** — overkill; os únicos tipos seriam `business` e `platform`.
- **`business_id = 0`** como sentinela — desrespeita convenção Laravel (null é nulo, não 0).

## Consequências

**Positivas:**
- Uma única estrutura de dados serve os dois públicos.
- Services (`ApuracaoService`, `SuggestionEngine`) recebem `Meta` sem se importar com dono.
- Superadmin pode comparar metas da plataforma × metas de businesses sem joins exóticos.
- Facilita futuro "benchmarking" opt-in (ver X businesses parecidos, anônimos).

**Negativas/Custos:**
- Toda query precisa passar pelo global scope ou sair explicitamente dele — fácil esquecer → teste de leak é obrigatório.
- FK `business_id` nullable quebra a garantia de integridade "toda meta pertence a um business" — aceitar esse custo como parte do design.
- UI precisa deixar muito claro em qual escopo o usuário está (risco de superadmin editar meta de cliente sem intenção).

## Testes obrigatórios (DoD)

- Usuário de business A **nunca** vê nem edita meta de business B (Feature test).
- Usuário comum **nunca** vê meta com `business_id = null` (Feature test).
- Superadmin vê meta de plataforma + do próprio business (Feature test).
- Global scope aplicado por padrão — teste regressivo (ler model sem escopo explícito → NÃO retorna metas de outro business).

## Referências

- Auto-memória `reference_db_schema.md` — "UltimatePOS multi-tenant por business_id".
- Módulo Officeimpresso (superadmin-only) — padrão de gate `copiloto.superadmin` copiado da ideia.

---

**Última atualização:** 2026-04-24
