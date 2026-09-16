<!-- SESSÃO FRIA · abra esta thread sozinha. Read-order mínimo e prompt de abertura: `_SESSAO-FRIA.md` (linha "data-contract no .tsx").
     Os ids de decisão (D-*) só existem em `ATA-DECISOES-2026-09-14.md` — leia a ata antes, ou as siglas ficam órfãs.
     Não leia as outras threads: cada uma é 1 PR e o contexto delas não é pré-requisito desta. -->

# 17 · `data-contract` no `.tsx` — o par das 26 âncoras criadas no protótipo

> **Dependência:** thread **16** (e as 20–26) nomeiam as regiões; esta grava o par do lado vivo.
> **Por que existe:** o `_doc` do `dashboard-index.map.json` é explícito — *"range de linha do lado vivo é INFORMATIVO (frágil); a âncora verificável é `vivo.ancora: true` + `data-contract="<id>"` no `.tsx` (declarada e ausente = DRIFT)"*. Em 2026-09-14 o protótipo ganhou **26 `data-contract`** em `ponto-telas.jsx` (antes: **zero**). Sem o par no `.tsx`, a âncora não fecha e o `design-code-map-check` acusa.

## O que fazer

Para cada região nomeada nas threads 16 e 20–26, acrescentar no `.tsx` da tela viva o **mesmo string** de id que o protótipo usa. Exemplos medidos:

| tela viva | id (string idêntica dos dois lados) |
|---|---|
| `Aprovacoes/Index.tsx` | `aprovacoes-fila-de-aprovacoes` |
| `Intercorrencias/Index.tsx` | `intercorrencias-intercorrencias` |
| `BancoHoras/Index.tsx` | `bancohoras-saldos-por-colaborador` |
| `BancoHoras/Show.tsx` | `bancohoras-historico-de-movimentos` · `bancohoras-ajuste-manual` |
| `Escalas/Index.tsx` | `escalas-escalas-cadastradas` |
| `Colaboradores/Index.tsx` | `colaboradores-colaboradores` |
| `Colaboradores/Edit.tsx` | `colaboradorform-configuracao-de-ponto` · `colaboradorform-dados-do-hrm` |
| `Importacoes/Index.tsx` | `importacoes-historico-de-importacoes` |
| `Importacoes/Show.tsx` | `importacoes-dados-do-arquivo` · `importacoes-resumo-do-processamento` · `importacoes-diagnostico-do-processamento` · `importacoes-amostra-de-erros` |
| `Relatorios/Index.tsx` | `relatorios-gerar` · `relatorios-pedidos-desta-sessao` |
| `Configuracoes/Index.tsx` | `configuracoes-regras-clt-reforma-trabalhista` · `configuracoes-banco-de-horas` · `configuracoes-rep-e-imutabilidade-de-marcacoes` · `configuracoes-afd-importacao-esocial` · `configuracoes-ia-do-ponto` |
| `Configuracoes/Reps.tsx` | `configuracoes-reps-cadastrados` · `configuracoes-cadastrar-novo-rep` |

**A lista completa dos 26** está no `_PATCH-INDICE-2026-09-14.md` §2 e sai do próprio build (`grep 'contrato=' ponto-telas.jsx`).

## Regras

- **String idêntica**, não "equivalente" — o gate compara texto.
- **2 ids nasceram feios** (`intercorrencias-card`, `escalaform-card`, de Cards sem título fixo): quando o gap nomear a região, **renomear nos dois lados no mesmo PR**.
- Região que **não existe no vivo** (as 🟠 "a nascer" das threads) **não ganha id agora** — id sem elemento é DRIFT ao contrário.

**PARAR SE** — o `.tsx` já tiver `data-contract` com outro string para a mesma região ⇒ **o vivo manda**, e quem muda é o protótipo (é mais barato e o id é meu).
