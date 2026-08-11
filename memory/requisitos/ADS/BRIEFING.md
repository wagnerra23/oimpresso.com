---
id: requisitos-ads-briefing
module: ADS
status: deprecated
status_nota: "REMOVIDO em 2026-07-31 (ADR 0363). O Modules/Governance incorporou o módulo; o núcleo dual-brain foi aceito como PERDA, não realocado — nenhum módulo vivo decide. Código: o núcleo saiu no #5135; dado: 5 tabelas dropadas e 6 preservadas por terem consumidor vivo fora do ADS (#5143, deploy com migrate --force em 2026-08-01). Smoke real em prod conferiu 5 ausentes, 6 de pé, 4 rotas /ads/admin/* em 302. Esta pasta é canon HISTÓRICO. URLs e route names congelados na Forja (ADR 0087). Lápide em proibicoes.md §5."
updated_at: "2026-08-02"
owner: W
related_adrs:
  - 0363-governance-incorpora-ads-nucleo-sem-receptor
  - 0145-ia-administradora-pivot-ads-fsm-piloto-cobradora
  - 0087-drift-resolution-sem-mover-url
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0076-skills-db-primary-git-destino-drift-alert
lifecycle: arquivado
---

# BRIEFING — `ADS` (REMOVIDO)

> **Estado:** ⚰️ **REMOVIDO em 2026-07-31** ([ADR 0363](../../decisions/0363-governance-incorpora-ads-nucleo-sem-receptor.md)) — `Modules/ADS/` não existe mais, e as 5 tabelas do núcleo foram dropadas em produção. | **Owner:** [W]
>
> ⛔ **Não recriar, e não re-propor.** A lápide vive em [`proibicoes.md` §5](../../proibicoes.md) — leia-a **antes** de qualquer proposta que ressuscite roteamento por risco/confiança. Se um módulo vivo passar a decidir de fato, o caminho é ADR sucessora da 0363, não recriação.
>
> Esta pasta sobrevive como **canon histórico**: registra por que o módulo existiu, o que ele produziu e por que saiu. Encontrá-la **não é** sinal de que ele deve voltar. Todo caminho `Modules/ADS/...` citado abaixo é **histórico** — descreve código removido.

## O que era

**ADS = Adaptive Decision System.** Meta-sistema que roteava uma ação do codebase por **risco × confiança** até um outcome, decidindo *quem* age (Brain A local / Brain B Anthropic / humano) e *com qual autoridade* (HITL 4 níveis), com memória append-only pra alimentar um learning loop. A arquitetura canônica está nas [ARQ-0001..0011](adr/arq/ARQ-0002-dual-brain-papeis.md) desta pasta.

**Não era** executor de domínio, não substituía a Jana e nunca falou com cliente final.

## Por que saiu

Duas razões, ambas medidas — o detalhe e os recibos estão na [ADR 0363](../../decisions/0363-governance-incorpora-ads-nucleo-sem-receptor.md):

1. **Posse partida da política.** `mcp_governance_rules` tinha migration + escrita no ADS e leitura + toggle no Governance — dois donos pro mesmo fato. Fundir resolve por construção; apagar deixaria a UI do Governance lendo tabela sem dono.
2. **O núcleo não tinha receptor.** A capacidade "rotear por risco/confiança até um outcome que fecha" **não é exercida por nenhum outro lugar do sistema**. Herdar o código sem herdar a capacidade seria criar dono por acidente de acoplamento.

O que a fila produziu está medido na ADR (prod, 2026-07-31): a esmagadora maioria das decisões nunca saiu do estado inicial, `pr_url` e `commit_sha` em zero, volume 100% em `business_id=1`. **`outcome='cancelled'` era o `default` da coluna, não "canceladas"** — o retrato honesto é fila que ninguém consumia. Não repita esses números daqui: eles são medição datada, e quem os sabe melhor é a ADR (e, para o retrato de então, o [handoff de 2026-07-31 16:36](../../handoffs/2026-07-31-1636-ads-incorporado-pelo-governance-3-de-7.md)).

## Onde cada capacidade foi parar

| Capacidade | Destino | Recibo |
|---|---|---|
| `PolicyEngine` · `GovernanceRulesService` · `mcp_governance_rules` | **Governance** ([SCOPE](../Governance/SCOPE.md)) | [#5128](https://github.com/wagnerra23/oimpresso.com/pull/5128) |
| `SkillsService` · `ScaffoldSkillFromMissionService` · `skill:scaffold` | **Jana** | [#5129](https://github.com/wagnerra23/oimpresso.com/pull/5129) |
| `ToolRegistry` · `UserScopeService` · `ProjectDecomposerService` · `DecisionLinksService` | **Forja** ([SCOPE](../Forja/SCOPE.md)) | [#5131](https://github.com/wagnerra23/oimpresso.com/pull/5131) |
| 9 rotas `/ads/admin/*` de controllers de Forja/KB | **Forja**, com URL e route name **congelados** ([ADR 0087](../../decisions/0087-drift-resolution-sem-mover-url.md)) | [#5132](https://github.com/wagnerra23/oimpresso.com/pull/5132) |
| **Núcleo dual-brain** (`DecisionRouter` · `RiskEngine` · `ConfidenceEngine` · `BrainBService` · `PatternLearning` · `Planner` · `Reviewer` · `DecisionPresenter`) | **ninguém — morreu** | [#5135](https://github.com/wagnerra23/oimpresso.com/pull/5135) |

## Estado terminal (o que sobrou, e o que não)

| Superfície | Estado |
|---|---|
| `Modules/ADS/` | **não existe** — o núcleo saiu no [#5135](https://github.com/wagnerra23/oimpresso.com/pull/5135) (o corpo do commit declara 174 arquivos / −15.889 linhas; o `--stat` do merge conta mais porque inclui sobreviventes patchados) |
| 14 telas do núcleo + `scripts/dual-brain/` + `config/retention.ads.php` | removidas na mesma leva |
| `resources/js/Pages/ads/Admin/` | **5 telas ficam** — `Graph` · `Projects` · `ProjectShow` · `TeamScopes` · `Tools`. Nunca foram do ADS: são **KB** e **Forja**, e o diretório é só o endereço congelado |
| Tabelas do núcleo | **5 dropadas** por [migration](../../../database/migrations/2026_07_31_235000_drop_ads_dual_brain_core_tables.php), **6 preservadas** — o cabeçalho dela nomeia cada uma e o consumidor vivo de cada uma |
| Permissions Spatie | **10 removidas** (as que governavam telas que caíram), concessões antes da permission. `ads_module` em `package_details` **intocada** — chave de assinatura é ato de superadmin |
| Crons + daemon | **5 crons `ads:` desligados** e o systemd `ads-brain-a` do CT 100 `inactive`/`disabled` ([#5127](https://github.com/wagnerra23/oimpresso.com/pull/5127)). **Não volta a ser ligado** |
| Skills `.claude/skills/ads-route` · `ads-decision-flow` | **aposentadas** junto do núcleo ([#5135](https://github.com/wagnerra23/oimpresso.com/pull/5135)) |
| Arquivo do dado | dump em `/root/archive/ads-2026-07-31/` no **CT 100**, **nunca em git** — conferido por SHA-256 e por contagem de INSERT nas duas pontas, com `MANIFEST.md` ao lado |

Smoke real em produção (E6, 2026-08-01): **5 tabelas ausentes · 6 de pé · 4 rotas `/ads/admin/*` em 302 · `/login` 200**.

## O padrão que este módulo ensinou (vale pros próximos)

**Antes de dropar tabela de um módulo em deprecação, procure o consumidor FORA dele.** A lista de DROP encolheu **três vezes** — erratas **E3**, **C5** e **D1** do plano — somando **5 tabelas** salvas, sempre pelo mesmo motivo: uma tabela dada como morta tinha consumidor vivo em módulo sobrevivente. As duas últimas (`mcp_tool_executions`, `mcp_user_module_access`) alimentavam rotas que o smoke já tinha registrado **vivas**; dropá-las converteria 302 em **500**. As três erratas só apareceram porque alguém foi olhar.

## Portas canônicas

- **Decisão:** [ADR 0363](../../decisions/0363-governance-incorpora-ads-nucleo-sem-receptor.md) — incorporação, recorte peça-a-peça, o que ela **não** decide
- **Plano executado (E1→E7) + erratas:** [`DEPRECATION-PLAN.md`](DEPRECATION-PLAN.md) — leia as erratas de cima pra baixo; a do topo vence
- **Lápide "não re-propor":** [`proibicoes.md` §5](../../proibicoes.md)
- **Requisitos históricos:** [`SPEC.md`](SPEC.md) · [`SPEC-US-COMPLEMENTAR.md`](SPEC-US-COMPLEMENTAR.md) · [`UI-CATALOG.md`](UI-CATALOG.md)
- **Arquitetura histórica:** [`adr/arq/`](adr/arq/ARQ-0002-dual-brain-papeis.md) (ARQ-0001..0011)
- **Fechamento do ciclo:** [handoff 2026-08-02 19:16](../../handoffs/2026-08-02-1916-e5-ads-b3-rag-redactor-ciclo-fechado.md)

> Não há `SUPERFICIE.md`: ela era derivada da árvore de código, e a árvore não existe mais. Não regenerar.

## Decisões e riscos que exigem atenção

- **A obrigação do Audit Card / LGPD Art. 20 sobrevive à supersessão da 0145** — ela é do sistema, não do ADS, e a exposição hoje é zero e **prospectiva**. Ponteiro: [ADR 0363 §Herança](../../decisions/0363-governance-incorpora-ads-nucleo-sem-receptor.md).
- **Reabertura tem gatilho declarado, não hipótese** — os `review_triggers` da 0363 mandam: módulo vivo que passe a decidir, cliente pagante que peça agente autônomo ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)), ou decisão automatizada que atinja titular.
- **O destino do próprio `Modules/Governance` ficou em aberto pela 0363** — e desde então há decisão [W] de que ele **não sai** (lápide de 2026-07-31 em `proibicoes.md` §5).

## Próxima ação verificável

**Nenhuma no ciclo de deprecação** — E1→E7 fechados. Resíduos conhecidos, para quem tocar as áreas:

- `Modules/Governance/SCOPE.md` ainda declara em `not_contains` que *"Decision flow … → Modules/ADS"* e *"Skills governance → Modules/ADS"* (linhas 15-16, repetidas em prosa nas 81-82). A [ADR 0363](../../decisions/0363-governance-incorpora-ads-nucleo-sem-receptor.md) previa a saída da linha 15 na parte 6 e isso não foi feito. Evidência: `grep -n "Modules/ADS" memory/requisitos/Governance/SCOPE.md`. **Não corrigido aqui de propósito** — é SCOPE de outro módulo (1 PR = 1 intent) e tocá-lo acorda gates diff-aware sobre dívida pré-existente.
- `.claude/hooks/tier-a-banner.mjs` imprime, em **toda** sessão, `DORMENTE: ads-route` — skill removida no #5135. É anúncio de capacidade inexistente. Evidência: `grep -n "ads-route" .claude/hooks/tier-a-banner.mjs`.

## Regra de manutenção

1. **Este BRIEFING não recebe mais atualização de capacidade** — o módulo não existe. Só correção de fato errado ou de link quebrado.
2. **Fato novo sobre o encerramento** entra como errata datada no [`DEPRECATION-PLAN.md`](DEPRECATION-PLAN.md), no topo, em ordem do mais recente pro mais antigo — como as cinco que já estão lá.
3. **Não regenerar `SUPERFICIE.md`** nem re-apontar SPEC/UI-CATALOG: são canon histórico, e reescrevê-los apaga o registro do que existiu.
4. **Reabrir a capacidade** exige ADR sucessora da 0363 com sinal qualificado ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) — nunca PR direto.

---

**Atualizado:** 2026-08-02 — E7 do plano: BRIEFING terminal + lápide §5 [C]
