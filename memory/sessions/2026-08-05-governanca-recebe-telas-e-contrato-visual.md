---
date: "2026-08-05"
topic: "Governança recebe as telas do Jana (ADR 0366), ganha contrato visual — e o contrato acha um bug de produção no 1º uso"
authors: [C]
related_adrs:
  - 0366-fronteira-jana-forja-governance-kb
  - 0367-cockpit-unico-forja-project-mgmt-morre
  - 0087-drift-resolution-sem-mover-url
  - 0093-multi-tenant-isolation-tier-0
  - 0114-prototipo-ui-cowork-loop-formalizado
---

# Governança recebe telas do Jana + contrato visual (7 PRs)

> Continuação executiva de [`2026-08-05-duplicacao-roadmap-forja.md`](2026-08-05-duplicacao-roadmap-forja.md),
> que é o recibo da conferência de duplicação exigida pela ADR 0366 §D-C. Aquele analisou;
> este executou.

## Pedido e o conflito de canon que ele atravessou

[W] pediu: *"deve sair da jana e ir para govenança. as sub-pages"* (a partir de `/ia/admin/roadmap`).

Antes de mover qualquer coisa, a [ADR 0366](../decisions/0366-fronteira-jana-forja-governance-kb.md)
(`aceito`, decidida por [W] dois dias antes) mandava o **Roadmap para a Forja**, não Governança —
*"usa `TaskCrudService`/`McpTask`; mandar pro Governance criaria a 3ª tela de roadmap"*.
Levado a [W], que escolheu **seguir a ADR**. O resto (Governança MCP, Custos, Qualidade IA) foi
para Governança.

## O que entrou no `main`

| PR | O quê |
|---|---|
| [#5308](https://github.com/wagnerra23/oimpresso.com/pull/5308) | Reativa a entrada de sidebar da Governança (morta desde 2026-05-25) + `GovernancaSubNav` |
| [#5310](https://github.com/wagnerra23/oimpresso.com/pull/5310) | Roadmap Gantt → Forja (o pedido original), URLs antigas por 301 |
| [#5312](https://github.com/wagnerra23/oimpresso.com/pull/5312) | Governança MCP fundida no painel da Governança |
| [#5309](https://github.com/wagnerra23/oimpresso.com/pull/5309) | Custos de IA + Qualidade IA → Governança |
| [#5328](https://github.com/wagnerra23/oimpresso.com/pull/5328) | **fix**: `SCOPE.md` com chave YAML duplicada quebrava `/governance/drift` |
| [#5326](https://github.com/wagnerra23/oimpresso.com/pull/5326) | Contrato visual das 5 telas da Governança (manifesto 7→12 + baselines) |
| [#5311](https://github.com/wagnerra23/oimpresso.com/pull/5311) | Faixa + 4 RUNBOOKs nas telas restantes |

## Achado 1 — o contrato visual pagou por si no primeiro uso

`/governance/drift` estava **quebrada em produção** e ninguém sabia. O frontmatter do
`Modules/Governance/SCOPE.md` tinha `drift_alerts` **duas vezes**: `Yaml::parse` lança, o
`DriftAlertService::declaredControllers` cai no `catch`, a tela estoura.

**Como nasceu:** merge paralelo **sem conflito textual**. O #5312 declarou `drift_alerts: []`
(drift resolvido) + 2 tabelas `mcp_*` consumidas; o #5309 mexeu no mesmo frontmatter, em outro
ponto. Os hunks pousaram em regiões diferentes, o git aceitou ambos, e o resultado tem chave
repetida. **Nenhum gate pegava** — esse YAML só é parseado em *runtime*, pela tela, e a tela não
estava sob contrato visual.

Segundo defeito da mesma origem: `mcp_audit_log`/`mcp_usage_diaria` são **tabelas consumidas**
mas viraram itens da lista `drift_alerts`.

**Bite-test antes do fix:** `ANTES → duplicated mapping key (82:1)` (erro idêntico ao do CI) ·
`DEPOIS → parseia, drift_alerts=[], db_tables_consumed=6`.

> **Lição perene:** merge paralelo em **frontmatter YAML** produz chave duplicada sem conflito de
> git. Dois PRs que editam o mesmo bloco em pontos distintos passam pelo merge e quebram o parser.
> O git protege contra sobreposição de linhas, não contra violação de esquema.

## Achado 2 — DÍVIDA: baselines visuais com data relativa apodrecem sozinhas

O `visual-regression` do #5311 travou em **zona cinza** (não em falha de teste). Os 4 diff-views
eram todos de `financeiro-unificado`, e a diferença é **o calendário**:

| | Baseline | Atual |
|---|---|---|
| Navegador | **Julho 2026** | **Agosto 2026** |
| Vencimento | 11/06 · *vencendo* | 06/06 · *em atraso* |
| Status | 🟡 Vencendo | 🔴 Atrasado |
| Só atrasados | 0 | 1 |

O seed cria o título **relativo a `now`**. Baseline capturada em julho; hoje é 05/08 → o mesmo
título envelheceu. **Zero mudança de layout.** [W] inspecionou as imagens e aprovou via label
`visreg-gray-approved` (gate F1.5).

**Isto corrige o que escrevi no corpo do #5326:** eu disse que as **19 baselines divergentes**
(Financeiro, Compras, Oficina, Clientes) estavam *"defasadas do código atual"*. **Estão erradas por
outro motivo — envelhecem pelo relógio.** A causa muda o conserto.

### A dívida, e por que ela não é cosmética

Custo **recorrente e mal distribuído**: todo mês, o **primeiro PR que tocar `.tsx`** dispara o diff
global do núcleo-6, bate na zona cinza e trava — sobre quem não tem nada a ver com o Financeiro.

**Conserto durável:** congelar o relógio no teste (`Carbon::setTestNow` ou seed ancorado em data
fixa). Deve ir em **PR próprio**: é infra de teste compartilhada, regenera todas as baselines do
Financeiro e merece revisão isolada — não carona num PR de faixa de navegação.

## Recuperação de baseline sem `COWORK_BOT_PAT` (receita que funcionou)

O MODO UPDATE **gera** as baselines e falha só no **push** (`403`, o PAT não estava disponível).
O próprio workflow documenta o plano B em [`visual-regression.yml:485`](../../.github/workflows/visual-regression.yml):
*"Baixe o artifact `pixel-snapshots` e publique manualmente."*

1. `gh run download <run> -n pixel-snapshots -D <dir>`
2. **Comparar byte-a-byte com o repo** e copiar **só os inexistentes** — o artifact traz os 65
   (o `visreg:update` regenera tudo). Aqui: 5 novos, 41 idênticos, **19 divergentes**.
   Sobrescrever os 19 mudaria em silêncio a referência de telas que o PR não toca.
3. Commitar os novos.

> Para ler os diff-views: as imagens vêm **embutidas em base64** dentro dos `.html` do artifact
> `pixel-diff-views` (3 por arquivo: Diff · Baseline · Atual). Extrair com um decode simples.

## Erros meus nesta sessão

**Seis tentativas no mesmo alerta do gitleaks, todas deduzindo a causa do código suspeito.** A
resposta estava no log da ferramenta, a **um comando** (`gh run view --log-failed`): o arquivo
acusado era o **próprio `.gitleaksignore`**, cujo comentário — escrito por mim para *explicar* o
falso positivo — reproduzia o padrão que a regra procura. Classe **LC-08**; lápide em
[`proibicoes.md` §5](../proibicoes.md).

**Contagem por página cortada.** Reportei *"7 required faltando"* no #5309 usando `per_page=100`
num commit com **122** check-runs. Não existiam — eram os 22 cortados. Peguei antes de virar
decisão, mas é a mesma classe.

## Estado final

7 PRs no `main`. Contrato visual ativo nas 5 telas da Governança.

⚠️ **Nenhuma dessas telas foi aberta em produção.** Sete PRs sem smoke real (R1). Defesa contra
regressão não é o mesmo que ter visto a tela funcionando.
