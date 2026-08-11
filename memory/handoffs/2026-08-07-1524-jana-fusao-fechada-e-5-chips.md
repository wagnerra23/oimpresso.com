---
date: "2026-08-07"
time: "15:24 BRT"
slug: "jana-fusao-fechada-e-5-chips"
tldr: "As 4 ondas da fusão da Jana fecharam e estão em produção — /ia é o Painel, a Conversa foi pra /ia/conversa, o Cockpit morreu em 301 e o farol saiu do frontend. A US-COPI-123 (p0) fechou por remoção e de graça. No fim, a pergunta do [W] sobre o page header virou uma lista de diferenças protótipo × produção fatiada por aba, que saiu em 5 chips rodando em sessões paralelas."
cycle: null
prs: [5376, 5380, 5385, 5390, 5392, 5394]
decided_by: [W]
related_adrs:
  - "0180-sidebar-v3-5-grupos-ghosts-header"
  - "0264-governanca-executavel-trio-dominio-e2e"
  - "0364-trio-de-tela-mora-em-memory-emenda-0264"
---

# Handoff — fusão da Jana fechada, e o que sobrou em 5 chips

## Estado MCP no momento do fechamento

Consultado agora:

- **`cycles-active`** → *"Nenhum cycle ATIVO em COPI"*.
- **`my-work` (@wagner)** → **12 tasks**, todas em `REVIEW`. `US-TR-309` · `US-TR-310` · `US-PG-008` · `US-PROD-027` · `US-INFRA-023` · `US-TR-305` · `US-TR-306` · `US-INFRA-048` · `US-TR-311` · `US-KB-002` · `US-PROD-025` · `US-TR-307`.
- **`decisions-search`** → trouxe a [ADR 0364](../decisions/0364-trio-de-tela-mora-em-memory-emenda-0264.md) (aceita 2026-08-01), que **move o trio de tela** pra `memory/requisitos/<Modulo>/_telas/`. Ver §Armadilha abaixo.

> **A `US-COPI-123` (p0) SAIU do `my-work`.** Ela estava lá em REVIEW no início desta sessão e não está mais — é a prova de que o fechamento pegou, não afirmação minha.

## O que está em produção

| PR | onda | entrega |
|---|---|---|
| [#5376](https://github.com/wagnerra23/oimpresso.com/pull/5376) | PR-0 | os 5 achados que a US-COPI-148 não cobria |
| [#5380](https://github.com/wagnerra23/oimpresso.com/pull/5380) | 2 | abas `Painel │ Conversa │ Memória` |
| [#5385](https://github.com/wagnerra23/oimpresso.com/pull/5385) | 3 | `/ia` = Painel · `/ia/conversa` = Conversa · 301s · 31 arquivos |
| [#5390](https://github.com/wagnerra23/oimpresso.com/pull/5390) | 4 | Cockpit removido · **closes US-COPI-123** |
| [#5392](https://github.com/wagnerra23/oimpresso.com/pull/5392) | — | fecha a onda 2 no SPEC |
| [#5394](https://github.com/wagnerra23/oimpresso.com/pull/5394) | fidelidade | farol server-side |

Smoke real por onda, autenticado em biz=1. Último: `componentInertia: "Jana/Index"`, abas `["Painel","Conversa","Memória","Jana Pro"]`, `cockpitSumiuDaFaixa: true`, `erroJs: false`. `main` verde no PHPStan após o último merge (`ca7dddb38`).

## Onde a fusão parou

**As 4 ondas estão ✅ no SPEC.** O que sobrou não é onda — é **fidelidade ao protótipo**, medida no fim da sessão e saída em 5 chips (todos iniciados pelo [W], rodando em sessões paralelas):

| Chip | Fatia | O que tem de mais pesado |
|---|---|---|
| `task_5364cfce` | Painel | 4 das 6 análises faltam · drill-down "de onde vem o número" inexistente |
| `task_918bad28` | Cromo | 2 barras hand-rolled → 1 `<PageHeader>` (gate F1.5) |
| `task_589196a3` | Memória | 🔴 motivo obrigatório na edição (LGPD) ausente + 6 |
| `task_7c8670e3` | Conversa | 🔴 filtros são fachada (o código admite em `Chat.tsx:312`) |
| `task_4cf08189` | Nomenclatura | "Copiloto" em 16 lugares onde o protótipo diz "Jana" |

Cada chip carrega a medição pronta, os caminhos, o bloqueio de RUNBOOK que vai encontrar, e o que **não** fazer. Três têm pergunta explícita pro [W] — quais análises valem pra WR2, o que fazer com os filtros que não filtram, e a escala de relevância — porque são escopo, não técnica.

## ⚠️ Armadilha ativa pra quem pegar os chips

A **[ADR 0364](../decisions/0364-trio-de-tela-mora-em-memory-emenda-0264.md)** (aceita 2026-08-01, [F] patrocinou, [W] ratificou) move o trio de tela pra `memory/requisitos/<Modulo>/_telas/`. **A Jana ainda NÃO migrou** — os 4 charters seguem em `resources/js/Pages/Jana/` (medido agora: sem `_telas/`). Os chips citam esses caminhos. Se a migração rodar antes deles, os paths mudam.

## Resíduos declarados

- **`Pro.tsx`** mantém `voltar → /ia`, que hoje é o **Painel**, não o chat — e o comentário *"Esc volta ao chat"* ficou stale. Consertar exige criar `RUNBOOK-pro.md`; criar um RUNBOOK pra consertar um comentário é desproporcional. Voltar pra home do módulo é destino defensável.
- **`Index.casos.md` não existe, de propósito.** O G-2 do casos-gate pune UC sem teste, então escrevê-lo sem os testes deixaria o gate **pior**. A entrada segue grandfatherada no `casos-coverage-baseline` com trailer `BASELINE-GROW` justificando.
- **Dois medidores do mesmo tema** continuam de pé no `IndexController` (11 entradas no `phpstan-baseline`, todas pré-existentes) — não mexi, é dívida de antes.

## Armadilhas atravessadas (economizam tempo a quem repetir)

- **Mensagem de baseline do PHPStan é derivada do estado EXATO da árvore.** 4 idas ao CT 100: baseline do container defasado (dizia `DashboardController`) → 11 erros que eram artefato; remendar só metade da mensagem (ela tem `should return` **e** `but returns`); gerar com o serviço sem a anotação → `mixed` em vez de `string`. A 4ª deu `[OK] No errors`. Sempre copiar **todos** os arquivos envolvidos + o baseline antes de gerar.
- **Verde num modo não é verde no job** (reincidência da §5 de 2026-07-28, 2× aqui): `casos-coverage-guard` tem `--check-baseline-shrink` além do modo sem argumento, e `anchor-lint` tem `--check-entry --check-covers` além do `--check`. Ler o `.yml` antes de afirmar verde.
- **`BASELINE-ABSORB`/`BASELINE-GROW` valem só no commit que TOCA o baseline** — não em qualquer commit do range.
- **Screenshot antes do paint volta branco**, e sonda JS antes da hidratação volta vazia. Nos dois casos aqui a tela estava certa. O veredito é a medição (`componentInertia`, `rootHTMLLen`), não a imagem.
- **Regenerar baseline da árvore corta os dois lados:** no eslint/casos trouxe 64/66 e 204/207 linhas de **terceiros** (revertido, virou cirúrgico); no `screen-coverage` foi o que revelou que o scorecard tinha sumido e o `.yaml` precisava trocar de **nome**.

## Erro meu que virou recibo no ledger

**LC-19 (3ª ocorrência)** — escrevi um plano paralelo à `US-COPI-148` porque li o `routes.php` e **não li o SPEC**, pré-flight que `.claude/rules/modules.md` manda. Forma não-agravada (não abri o dono). Encolhi pro delta no mesmo PR; lápide no `§5` e contador no `LICOES_CODE.md`.

Quem pegou não foi gate nem revisão — foi **atualizar a base**: o merge de `origin/main` trouxe o handoff que cita a US pelo id. Na base velha teria ido pro merge com aparência de rigor.

## Para retomar

1. `brief-fetch` → estado consolidado.
2. Ler o [session log de hoje](../sessions/2026-08-07-jana-fusao-ondas-2-a-4-e-lista-de-diferencas.md) — a lista de diferenças completa está lá, fatiada.
3. Se for pegar um chip: **`whats-active` primeiro** — 5 sessões paralelas foram iniciadas sobre o mesmo módulo, e a base envelhece sozinha.
