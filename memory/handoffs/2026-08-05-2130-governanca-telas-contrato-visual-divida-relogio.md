# Handoff 2026-08-05 21:30 — Governança recebe telas, ganha contrato visual, e a dívida do relógio

> Session log: [`2026-08-05-governanca-recebe-telas-e-contrato-visual.md`](../sessions/2026-08-05-governanca-recebe-telas-e-contrato-visual.md)
> Análise prévia: [`2026-08-05-duplicacao-roadmap-forja.md`](../sessions/2026-08-05-duplicacao-roadmap-forja.md)

## Onde o trabalho parou

**Fechado.** 7 PRs no `main`, fila mergeada em ordem de dependência.

| PR | O quê |
|---|---|
| [#5308](https://github.com/wagnerra23/oimpresso.com/pull/5308) | Sidebar da Governança reativada + `GovernancaSubNav` |
| [#5310](https://github.com/wagnerra23/oimpresso.com/pull/5310) | Roadmap Gantt → Forja (ADR 0366), URLs por 301 |
| [#5312](https://github.com/wagnerra23/oimpresso.com/pull/5312) | Governança MCP fundida no painel |
| [#5309](https://github.com/wagnerra23/oimpresso.com/pull/5309) | Custos de IA + Qualidade IA → Governança |
| [#5328](https://github.com/wagnerra23/oimpresso.com/pull/5328) | **fix prod**: `SCOPE.md` YAML duplicado quebrava `/governance/drift` |
| [#5326](https://github.com/wagnerra23/oimpresso.com/pull/5326) | Contrato visual das 5 telas (manifesto 7→12 + baselines) |
| [#5311](https://github.com/wagnerra23/oimpresso.com/pull/5311) | Faixa + 4 RUNBOOKs nas telas restantes |

## O que a PRÓXIMA sessão precisa saber

### 1. DÍVIDA ABERTA — baselines com data relativa apodrecem todo mês

**Não está consertada.** O seed do Financeiro cria títulos **relativos a `now`**; a baseline
congela um mês. Na virada, o mesmo título muda de *vencendo* → *atrasado* e o pixel-diff acusa
zona cinza.

- **Sintoma:** `visual-regression` trava com **testes VERDES e `exit 2`**. Isso é o código da
  **zona cinza**, não falha de teste. O `visreg-flake-retry.sh` não reconhece a assinatura (não é
  flake nem regressão clara) e barra por precaução — comportamento correto.
- **Quem paga:** o **primeiro PR do mês que tocar `.tsx`**, qualquer que seja o autor.
- **Paliativo:** label `visreg-gray-approved` **após [W] ver as imagens** (gate F1.5). Usado no
  #5311 em 05/08.
- **Conserto durável:** `Carbon::setTestNow` ou seed ancorado em data fixa, em **PR próprio** —
  infra compartilhada, regenera todas as baselines do Financeiro.
- **Alcance medido:** 19 das 60 baselines regeneradas divergiram (Financeiro, Compras, Oficina,
  Clientes) — todas telas com data relativa.

> ⚠️ **Errata do #5326:** o corpo daquele PR diz que as 19 estavam *"defasadas do código"*. **Está
> errado** — envelhecem pelo relógio. A causa muda o conserto.

### 2. Receita: publicar baseline sem `COWORK_BOT_PAT`

O MODO UPDATE **gera** e falha só no **push** (403). Plano B documentado em
[`visual-regression.yml:485`](../../.github/workflows/visual-regression.yml): baixar o artifact
`pixel-snapshots`, **comparar byte-a-byte** e copiar **só os inexistentes** — o artifact traz os 65
porque `visreg:update` regenera tudo. Sobrescrever os divergentes muda em silêncio a referência de
telas não tocadas.

Diff-views: imagens em **base64 dentro dos `.html`** do artifact `pixel-diff-views` (Diff ·
Baseline · Atual).

### 3. Armadilha nova: merge paralelo em frontmatter YAML

Dois PRs editando o **mesmo frontmatter em pontos diferentes** passam pelo merge **sem conflito** e
produzem **chave duplicada** → o parser lança. Foi o que quebrou `/governance/drift` em produção.
O git protege contra sobreposição de linhas, não contra violação de esquema. Nenhum gate pega:
o YAML de `SCOPE.md` só é lido em runtime, pela tela.

## Pendências (decisão [W])

| # | O quê | Por que não fiz |
|---|---|---|
| 1 | **Smoke real** nas 7 telas | Nenhuma foi aberta em produção. R1 não cumprido — o mais importante desta lista |
| 2 | Congelar o relógio nas baselines | PR próprio; infra compartilhada |
| 3 | Tokenizar cores do gráfico Qualidade IA (`ui:lint` R1, 15 violações) | Muda o render das séries → aprovação visual [W] |
| 4 | `related_us` do `QualidadeIa.charter.md` | O próprio lint diz *"só [W] sabe qual US a tela atende"*; inventar é pior que ausente |
| 5 | `COWORK_BOT_PAT` | Expirado/ausente. Credencial, não código — a receita acima contorna |

## Estado MCP no momento do fechamento

⚠️ **Servidor MCP inalcançável** nesta sessão — o hook `brief-fetch` do SessionStart caiu em
fallback (`SyntaxError`, servidor fora). Logo **não há** snapshot de `cycles-active` / `my-work` /
`sessions-recent` / `decisions-search`, e o protocolo de fechamento
([ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md)) não pôde ser cumprido na parte
MCP-first. Registro a ausência em vez de omitir — o handoff seria promessa, não prova.

Substituto verificável (medido em `origin/main`, não lembrado): os 7 PRs da tabela acima, todos
`MERGED`, conferidos por `gh pr view --json state`.
