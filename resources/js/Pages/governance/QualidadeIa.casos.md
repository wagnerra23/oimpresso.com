---
id: resources-js-pages-governance-qualidade-ia-casos
casos: QualidadeIa · Qualidade da memória/recuperação da IA · /governance/qualidade-ia
irmaos: QualidadeIa.charter.md (lei, draft) · memory/requisitos/Governance/RUNBOOK-qualidade-ia.md
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-10-14"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente das lanes (vitest governance-qualidade-ia + Pest QualidadeIaContratoTest)"
---

# Casos de uso — /governance/qualidade-ia

> **Status:** ✅ passa (provado por teste no manifesto) · 🧪 em teste (teste escrito que morde, aguarda veredito) · ⬜ não verificado · ❌ quebrou.

> **De onde os casos vêm.** O charter [`QualidadeIa.charter.md`](QualidadeIa.charter.md) (§Goals, §Anti-hooks), o [`RUNBOOK-qualidade-ia.md`](../../../../memory/requisitos/Governance/RUNBOOK-qualidade-ia.md) (§1 alvos, §4 controller e partial reload, §5 estados) e a ADR 0049/0050 (gates e métricas — MEM-MET-4). O `SPEC.md` da Governança **não tem US** para esta tela. O `.tsx` e o controller entraram só para confirmar comportamento, nunca para derivar caso (lápide §5 2026-06-05). O charter é `draft`: os Non-Goals dele são herdados da origem Jana e **não foram ratificados** por [W], por isso nenhum UC sai deles.

> **Nenhum UC nasce ✅.** O G-7 só carimba ✅ com veredito colhido de JUnit no manifesto, e os dois testes deste trio nascem neste PR **sem lane de PR** (ver §Lanes). O teto honesto é 🧪.

> **Força do veredito.** Nenhuma das lanes abaixo existe ainda para estes arquivos; quando existirem, se bloqueiam merge é do [`required-checks-baseline.json`](../../../../governance/required-checks-baseline.json), não desta prosa.

## UC-GQIA-01 [T0] [must] — Ler a tela exige `jana.mcp.usage.all`
Status: 🧪 (Pest `tests/Feature/Governance/QualidadeIaContratoTest.php` — "UC-GQIA-01 [T0]: sem jana.mcp.usage.all a tela responde 403…")
Persona: Wagner (superadmin/auditor).
A permissão segue com o prefixo legado `jana.` de propósito (charter §Anti-hooks; RUNBOOK §6) e é permissão de plataforma: o papel `Admin#{biz}` não a herda (ADR 0415). Relacionado: `tests/Feature/Roles/GateBeforePlataformaTest.php` já cobre o lado do bypass do Admin.
**Dado** um usuário do tenant 98 sem a permissão **quando** abre `/governance/qualidade-ia` **então** recebe 403; **e** com a permissão, recebe a página `governance/QualidadeIa`.
Regressão que defende: renomear/afrouxar o gate no movimento de tela.

## UC-GQIA-02 [should] — A janela é de 7 a 90 dias, padrão 30
Status: 🧪 (Pest — "UC-GQIA-02: a janela fica entre 7 e 90 dias, e o padrão é 30")
Charter §Goals oferece janelas 7/30/60/90; o RUNBOOK §4 passo 1 fixa o clamp `[7..90]` no servidor, então a querystring não alarga a janela.
**Dado** a tela **quando** pede `dias=1`, `dias=999`, `dias=60` ou nada **então** `filtros.dias` volta 7, 90, 60 e 30.

## UC-GQIA-03 [must] — Cada KPI da última leitura mostra o veredito do gate contra o alvo canônico
Status: 🧪 (Pest — "UC-GQIA-03: os gates chegam com os alvos canônicos…" · vitest `tests/js/governance-qualidade-ia.test.tsx` — "UC-GQIA-03: Recall@3 abaixo de 0.80 sai reprovado…", "…violação cross-tenant reprova…", "…latência p95 acima de 2000ms reprova…", "…um conjunto de cards por business…")
Alvos (RUNBOOK §1, ADR 0049/0050): `recall_at_3 ≥ 0.80` (bloqueante de evolução de camada) · `precision_at_3 ≥ 0.60` · `mrr ≥ 0.70` · `latencia_p95_ms ≤ 2000` · `tokens_medio ≤ 3000` · `memory_bloat ≥ 0.60` · `taxa_contradicoes_pct ≤ 2.0` · `cross_tenant_violations == 0` · `faithfulness ≥ 0.85` (RAGAS).
**Dado** a última leitura de um business **quando** a tela renderiza **então** há um conjunto de cards por business, e cada card de gate sai aprovado/reprovado conforme o alvo (ex.: Recall@3 0.70 reprovado, 0.85 aprovado; 1 violação cross-tenant reprova).
Mordida provada no vitest por mutação em 2026-10-14 (`>=` sempre aprovado → 1 failed; restauro conferido por sha256).

## UC-GQIA-04 [must] — O KPI do business é a leitura MAIS RECENTE da janela
Status: ❌ (Pest — "UC-GQIA-04: o KPI do business é a leitura MAIS RECENTE da janela") — **medido vermelho** no CT 100 em 2026-10-14 (checkout `e57b78bf5`; `QualidadeIaController`, `MemoriaMetrica` e as rotas da tela sem diff até `origin/main` neste dia): esperado `apurado_em` de ontem, veio o de 3 dias atrás. Run: `1 failed, 30 passed (118 assertions)` nos 5 contratos Pest de governança.
Charter §Goals ("KPI cards por business … da última leitura") e RUNBOOK §2 ("1 Card por business"). Causa, conferida no código depois do vermelho: `MemoriaMetrica::scopeUltimosDias` já ordena `apurado_em DESC`, o `orderBy('apurado_em')` do controller vira critério secundário, e `end($pontos)` pega a leitura mais antiga. A mesma ordem alimenta a série da sparkline. O conserto não foi feito aqui: é decisão [W].
**Dado** três leituras do tenant 98 (há 3, 2 e 1 dia, recall 0.810/0.620/0.930) **quando** a tela abre **então** o KPI do business tem `apurado_em` de ontem e recall 0.930.

## UC-GQIA-05 [should] — Aplicar filtro re-busca só séries, KPIs e filtros, na rota da Governança
Status: 🧪 (vitest — "UC-GQIA-05: Aplicar faz partial reload de series/kpis/filtros em /governance/qualidade-ia")
Charter §Goals ("partial reload … `only: ['series','kpis','filtros']` — gates e gabarito não retrafegam") e RUNBOOK §4 passo 4 (rota nova `/governance/qualidade-ia`, não `/ia/admin/qualidade`).
**Dado** a tela aberta **quando** clica em Aplicar **então** há uma visita a `/governance/qualidade-ia` com `only` exatamente `series`, `kpis`, `filtros`.
Mordida provada no vitest por mutação (acrescentar `gates` ao `only` → 1 failed).

## UC-GQIA-06 [should] — Métrica ausente aparece como travessão, nunca como zero
Status: 🧪 (vitest — "UC-GQIA-06: recall nulo mostra \"—\" e não \"0.0%\"")
RUNBOOK §5 (estado `metric null` → `'—'`). Mostrar 0% numa métrica não apurada fabricaria uma reprovação de gate.
**Dado** um KPI com `recall_at_3` e `latencia_p95_ms` nulos **quando** a tela renderiza **então** os dois cards mostram `—`.

## UC-GQIA-07 [should] — Sem métricas na janela, a tela diz como popular
Status: 🧪 (vitest — "UC-GQIA-07: trend vazio mostra a dica com o comando de apuração" + controle)
RUNBOOK §5 (estado `empty (trend)`) e charter §Non-Goals/§Automation hooks (a tela não calcula; quem alimenta é `copiloto:metrics:apurar` / `copiloto:eval --persist`).
**Dado** `series` vazio **quando** a tela renderiza **então** aparece "Sem dados de métricas" citando os dois comandos; **e** com série presente a dica não aparece.

## UC-GQIA-08 [should] — O tamanho do gabarito de avaliação fica visível
Status: 🧪 (vitest — "UC-GQIA-08: mostra total e contagem por categoria do gabarito")
Charter §Goals ("Mostra tamanho do gabarito de avaliação (total + por categoria)") e RUNBOOK §3 (`jana_memoria_gabarito` com perguntas ativas).
**Dado** gabarito de 12 perguntas (7 factual, 5 temporal) **quando** a tela renderiza **então** o texto mostra o total e as duas contagens.

## UC-GQIA-09 [should] — Runs recentes: as 30 mais novas, da mais nova para a mais antiga
Status: 🧪 (vitest — "UC-GQIA-09: 35 pontos viram 30 linhas, a primeira é a data mais recente")
Charter §Goals ("últimas ~30 runs … ordenadas por data desc") e RUNBOOK §2 ("Runs recentes — top 30 desc").
**Dado** 35 pontos numa série **quando** a tela renderiza **então** a tabela de runs tem 30 linhas, a primeira com a data mais recente.
Mordida provada no vitest por mutação (`slice(0, 40)` → 1 failed).

## Backlog e perguntas (sem id — contrato em 1 fonte ou fontes em conflito)

- [BACKLOG] **Cross-business: charter × código divergem — pergunta [W].** O charter e o RUNBOOK dizem que a tela é de plataforma e que cross-business é intencional (filtro `?business_id=` lê qualquer empresa). O código lê via `Modules\Jana\Entities\MemoriaMetrica`, que usa `HasBusinessScope` → `ScopeByBusiness`: quem tem só `jana.mcp.usage.all` vê apenas o business da sessão; quem tem `jana.superadmin` vê o próprio + linhas `NULL` (plataforma). Nenhuma das duas personas vê outra empresa. Isto é **hipótese por leitura**, não medida. Qual é o contrato: visão de plataforma (então o escopo precisa de dispensa comentada) ou visão do tenant (então charter/RUNBOOK estão errados)? É intenção, não fato — não corrijo nenhum dos lados.
- [BACKLOG] Dropdown de business só lista quem já tem métrica (RUNBOOK §6, bug funcional herdado) — 1 fonte.
- [BACKLOG] Sparkline normaliza por min/max local e cores HEX fora do DS (RUNBOOK §6 / charter §Pendências) — dívida declarada, decisão de onda é [W].
- [BACKLOG] `gabarito_total === 0` sem empty state explícito (RUNBOOK §6) — 1 fonte.
- [BACKLOG] GET read-only, sem polling (charter §Anti-hooks) — 1 fonte.
- Non-Goals do charter (sem HITL, sem disparar drift, sem editar gates pela UI) ficam **fora** até [W] ratificar o charter.
