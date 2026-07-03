---
casos: Cockpit Fiscal · /fiscal
irmaos: Cockpit.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-07-03"
---

# Casos de Uso & Aceite — Cockpit Fiscal

> Persona: **Eliana (contadora)** — leitura/conferência fiscal. Tela do cockpit Fiscal (agregador thin sobre NfeBrasil).
> Passo 3 do template-onda-modulo (régua por tela) — complementa a CAPTERRA-FICHA Fiscal (nota 75) sem roadmap paralelo.
>
> **Status:** ✅ passa (UC-id citado por teste no manifesto) · 🧪 tem teste Feature mas **sem UC-id** (débito rastreabilidade G-2 · ADR 0264) · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **Débito desta tela = rastreabilidade, não ausência de teste.** O comportamento já é defendido por `CockpitControllerTest`, `CockpitMultiTenantTest` e `CockpitCacheTest` (11 casos Pest reais). Falta a G-2: nenhum teste **cita** um `UC-FISCAL-NN`. Cada item vira `UC-FISCAL-NN` no mesmo PR que adicionar o id ao teste existente. Testes rodam no CT100 (ADR 0062).

## Backlog de casos (sem id — entram quando um teste citar o UC-id)
- **[BACKLOG · 🧪 tem teste] Gate de permissão bloqueia acesso sem `fiscal.access` nem `superadmin`** — Dado usuário sem permissão fiscal · Quando faz GET /fiscal · Então recebe 403. _Coberto por `CockpitControllerTest::('GET /fiscal aborta 403 sem permission superadmin nem fiscal.access')`._
- **[BACKLOG · 🧪 tem teste] Renderiza o componente Inertia `Fiscal/Cockpit` com props canônicas** — Dado contadora autenticada com permissão · Quando abre /fiscal · Então recebe component `Fiscal/Cockpit` com `kpis`, `sparklines` e `alerts`. _Coberto por `CockpitControllerTest::('GET /fiscal renderiza Inertia component Fiscal/Cockpit com props canon')`._
- **[BACKLOG · 🧪 tem teste] KPIs entregam as 7 chaves obrigatórias do ribbon** — Dado /fiscal renderizado · Quando inspeciona `props.kpis` · Então tem `emitidas`, `autorizadas`, `autorizadasPct`, `rejeitadas`, `faturamentoFiscal`, `dfeAguardando`, `certificadoValidadeDias`. _Coberto por `CockpitControllerTest::('props.kpis tem shape canon (6 chaves obrigatorias)')` e `CockpitMultiTenantTest::('computeKpis scope per business…')`._
- **[BACKLOG · 🧪 tem teste] Alertas são determinísticos (sem campos de LLM)** — Dado /fiscal renderizado · Quando inspeciona `props.alerts` · Então cada item tem `level/icon/title/sub/action/goto`, `level ∈ {crit,warn,info}`, e **não** tem `thought` nem `reasoning`. _Coberto por `CockpitControllerTest::('props.alerts é array de items deterministicos…')` e `CockpitMultiTenantTest::('computeAlerts não usa LLM — receitas determinísticas por estado')`._
- **[BACKLOG · 🧪 tem teste · Tier 0] KPIs isolam por business_id (cross-tenant não vaza)** — Dado emissões de biz=1 e biz=99 · Quando computeKpis roda na sessão biz=1 · Então conta só a emissão de biz=1 (HasBusinessScope ADR 0093). _Coberto por `CockpitMultiTenantTest::('computeKpis scope per business: biz=99 não aparece em counts de biz=1')`._
- **[BACKLOG · 🧪 tem teste · Tier 0] Cache de KPIs é isolado por business** — Dado cache de biz=1 e biz=4 · Quando invalida biz=1 · Então biz=4 sobrevive; chaves seguem `fiscal:cockpit:kpis:biz:{id}`. _Coberto por `CockpitCacheTest::('cache keys de businesses diferentes são INDEPENDENTES (multi-tenant ADR 0093)')` e `::('cache key segue padrão fiscal:cockpit:kpis:biz:{id}')`._
- **[BACKLOG · 🧪 tem teste] Cache de KPIs tem TTL de 60s e não re-executa dentro da janela** — Dado KPIs já em cache · Quando /fiscal é acessado de novo em <60s · Então o callback de query não roda de novo (hit). _Coberto por `CockpitCacheTest::('TTL é 60s…')` e `::('Cache::remember não re-executa callback quando key existe')`._
- **[BACKLOG · 🧪 tem teste] Chave de cache bate com o listener de invalidação (contrato de consistência)** — Dado NFe/NFCe autorizada dispara evento · Quando o `InvalidaCockpitCacheListener` invalida · Então a chave invalidada é exatamente a que o Controller lê (`KEY_PREFIX` casa). _Coberto por `CockpitCacheTest::('cache key prefix bate com InvalidaCockpitCacheListener (consistency contract)')` e `::('Listener invalida a key correta dado um event com business_id')`._
- **[BACKLOG · ⬜ sem teste] Ribbon exibe faturamento fiscal formatado em BRL** — Dado KPIs carregados · Quando o ribbon renderiza · Então "Faturado fiscal" aparece com `brl()`. (Frontend-only; sem teste Feature — comportamento derivado do `computeKpis().faturamentoFiscal`.)
- **[BACKLOG · ⬜ sem teste] Sparklines têm 14 pontos por status sem N+1** — Dado emissões nos últimos 14 dias · Quando computeSparklines roda · Então retorna arrays `emitidas/autorizadas/rejeitadas/faturamento` de 14 ints via 1 query agrupada. (Anti-hook do charter; hoje sem teste dedicado.)
- **[BACKLOG · ⬜ sem teste · mock] Filtros/saved-views/densidade/seleção-em-lote da tabela unificada** — Dado a tabela de notas · Quando aplica preset, tipo, status, busca ou seleção · Então as linhas filtram no client. (Client-side sobre `notasMock` do Controller — stub Wave Cowork, `NotasUnifiedService` é TODO[CL]; sem cobertura Feature.)

## Como rodar a suíte
1. **Pest (MySQL real):** lane Fiscal no CT100 (ADR 0062) — `CockpitControllerTest`, `CockpitMultiTenantTest`, `CockpitCacheTest` verdes. Testes que tocam DB skipam em SQLite (`nfe_emissoes` requer schema MySQL); os de cache/config rodam sempre.
2. **Cadência:** rodar ao fim de toda mexida na tela. UC ❌ = regressão.

## Trilha do tempo
- 2026-07-03 · [CC] criado no Passo 3 do programa de ondas (régua por tela), complementando a CAPTERRA-FICHA. Débito = UC-traceability (0 UC-id apesar de baseline Feature forte: 11 casos Pest em 3 arquivos, incluindo 2 Tier 0 de isolamento).
