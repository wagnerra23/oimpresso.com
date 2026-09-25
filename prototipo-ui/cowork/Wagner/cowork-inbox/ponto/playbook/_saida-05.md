---
sessao: "05"
titulo: "Painel de Conformidade CLT — saída da thread"
autor: "[CL]"
criado: 2026-09-25
base: 45a687387
thread: 05-conformidade-bloqueada.md
veredito: "entregue em 3 PRs (#7988 service · #7994 tela · este recibo) — service de leitura + tela /ponto/conformidade; 5 de 6 verificações medidas, NSR fica 'não medido' pela regra PARAR SE da própria thread."
---

# _saída 05 · Conformidade CLT

## Destrave

A thread dizia *"depende de 04 e W1"*. Superado pela **ADR 0413 D0** (Conformidade somente leitura,
independente do fechamento) e registrado no `_DECISOES-W-2026-09-24.md`. O placar já a lia como
`proximo`.

## O que saiu

| PR | conteúdo |
|---|---|
| [#7988](https://github.com/wagnerra23/oimpresso.com/pull/7988) | `Services/ConformidadeService.php` · `ConformidadeContratoTest` (UC-CONF-01..07) · lane `ponto-pest.yml` · US-PONTO-016 |
| [#7994](https://github.com/wagnerra23/oimpresso.com/pull/7994) | `ConformidadeController` · rota `ponto.conformidade.index` · menu + ghost no `DataController` · `Pages/Ponto/Conformidade.tsx` + charter + casos · contrato `ponto-conformidade` · stub e2e · UC-CONF-08 |
| este PR | `RUNBOOK-conformidade.md` · este recibo |

## As 6 verificações

| verificação | lei | fonte | estado |
|---|---|---|---|
| jornada sem fechamento | CLT Art. 74 §2º | `qtd_marcacoes` ímpar · chave `falta` | medida |
| interjornada | CLT Art. 66 | `interjornada_violacao_minutos` | medida |
| intrajornada | CLT Art. 71 | `intrajornada_violacao_minutos` | medida |
| HE acima do limite | CLT Art. 59 | chave `he_acima_limite` | medida |
| NSR fora de sequência | Portaria MTP 671/2021 Anexo I | — | **não medida** (regra PARAR SE) |
| ativo sem PIS | — | `ponto_colaborador_config.pis` | medida, como **conferência** |

Nada de apuração foi reimplementado: o service só lê `ponto_apuracao_dia`, que o `ApuracaoService` grava.

## Provas (CT 100, `oimpresso-staging`)

- Pest `ConformidadeContratoTest`: **7 passed (25 assertions)** com rota, controller e Page aplicados por patch e revertidos em seguida (checkout voltou aos mesmos 14 arquivos sujos de antes).
- Bite-test Tier 0: UC-CONF-04 só cai quando as **duas** defesas de `business_id` são removidas (defesa dupla); cai com a mensagem de vazamento, não com erro.
- `contrato:check ponto-conformidade` limpo · `pt-conformance` 83/83 · `casos-coverage-guard` sem violação nova · `anchor-lint --check-entry --check-covers` rc 0 · schema do charter e do RUNBOOK OK.
- **Não medido aqui:** typecheck/build do `.tsx` (worktree sem `node_modules`) e smoke visual — ficam com o CI e o smoke pós-deploy.

## Erratas do playbook (não editei o índice — é do Cowork)

- A thread diz **`PT-05`**; no DS o PT-05 é **Kanban**. Painel de KPI + tabela é **PT-04 Dashboard** — foi o usado.
- A thread diz `${PAGES}/Conformidade/Index.tsx`; o charter do protótipo diz `resources/js/Pages/Ponto/Conformidade.tsx` (tela flat). Segui o protótipo. O índice tem `provas: []` para a 05, então nada quebra.
- O texto *"depende de 04 e W1"* caducou (ADR 0413 D0).

## Resíduo

- **NSR:** `PontoHealthCommand::checkNsrSequencial` mede lacunas por REP, não por competência. Extrair para o `NsrService` é o caminho para a verificação sair do "—".
- **UC-CONF-03** (contagem casa com o Fechamento) ficou em `[BACKLOG]`: a thread 04 não existe. Quando nascer, a pré-checagem deve chamar `ConformidadeService`.
- **Sem PIS:** decisão [W] sobre a base legal a citar. Sem artigo, a Lei da thread manda tratar como conferência.
- **Permissão:** a tela usa `ponto.access`, como o resto do módulo. Se o painel precisar de permissão própria, é decisão [W].
