---
sessao: "04"
titulo: "Fechamento da competência — saída da thread"
autor: "[CL]"
criado: 2026-09-25
base: 45a687387
thread: 04-fechamento-bloqueada.md
veredito: "entregue em 6 PRs (domínio → serviço → rota → F1 → tela+contrato → GET+menu). Fonte: ADR 0413. Sem estado 'consolidada', sem totais de horas, sem NSR — declarados abaixo."
---

# _saída 04 · Fechamento da competência

## Pedido literal
"Executar a thread 04 do playbook do Ponto, Fechamento da competência. [...] Existe ADR recente
'Ponto — fechamento da competência': ela é a fonte, não invente regra. [...] A thread 07 depende de
`ponto-fechamento.contract.json`: gere o contrato de tela desta tela." ([W] 2026-09-25)

## O que foi feito (6 PRs, na ordem da ADR 0413 §Consequências)

| PR | conteúdo | lei |
|---|---|---|
| #7985 (mergeado) | `ponto_competencias` + `Entities/Competencia` + `CompetenciaAppendOnlyTest` | W1 · D1 — triggers MySQL + model recusam UPDATE/DELETE |
| #7993 | `FechamentoService` (`preChecagem` só lê · `fechar` grava 1 linha) + `FechamentoContratoTest` | D2 · W3 · Portaria MTP 671/2021 |
| #7996 | `POST /ponto/fechamento` com `can:ponto.fechar` + permissão declarada | D1 |
| #7997 | `RUNBOOK-fechamento.md` (F1 do MWART) + testes citam `UC-PTF-*` | ADR 0104 |
| #7998 | `Pages/Ponto/Fechamento/Index.{tsx,charter.md,casos.md}` + `ponto-fechamento.contract.json` | contrato no PR da tela (ADR 0413) |
| (PR 6) | `GET /ponto/fechamento` + menu/ghost + `UC-PTF-07` + US-PONTO-015 ancorada + este recibo | — |

Provas no CT 100 (clone isolado, checkout compartilhado intocado, migration revertida): **12 passed (44 assertions)**.
Bite-tests por mutação — cada um derrubou o caso certo: sem trigger · sem unique · sem guard `updating` · sem guard
`deleting` · contagem sem `business_id` · sem checagem de graves · sem `can:ponto.fechar`.

## O que NÃO foi feito, e por quê

| item do protótipo / da thread | situação | por quê |
|---|---|---|
| 3 estados (aberta → **consolidada** → fechada) | 2 estados | a ADR 0413 W1 define **um** ato persistido; "consolidar" e "fechar" viram o mesmo registro (D2) |
| card **Totais da competência** (horas) | fora | soma de horas é cálculo de hora: `PARAR SE` da própria thread sem dupla prova |
| bloqueio **NSR fora de sequência** | fora | não há coluna apurada; calcular aqui duplicaria a Conformidade (thread 05) |
| tabela **Divergências por colaborador** | fora da v1 | o atalho da pré-checagem leva ao Espelho |
| "competência fechada desabilita Anular no `Espelho/Show`" (invariante citada no `04-*.md`) | **não implementado** | **contradiz a ADR 0413 D1**, que diz que depois de fechada a correção é *por anulação com trilha*. A ADR manda; a frase do playbook fica como divergência a reescrever no Cowork |
| carimbar `ponto_apuracao_dia.estado = FECHADO` ao fechar | não feito | a ADR não manda; seria UPDATE em apuração que nenhuma decisão autoriza |
| UC "chego pelo menu" | `[BACKLOG]` no casos | prova honesta é o clique em e2e com sessão; a suíte não tem esse fixture |
| smoke visual | pendente | a tela só fica alcançável quando o PR 6 entrar em produção |

## Decisões que ficam com [W]
1. **Carimbar a apuração** (`ponto_apuracao_dia.estado → FECHADO`) ao fechar — quer, ou o registro na `ponto_competencias` basta?
2. **Totais de horas** na tela — entram com dupla prova (regra de valor/hora), ou ficam no Espelho?
3. **Anular no Espelho depois de fechada** — o `04-*.md` diz "desabilita"; a ADR 0413 D1 diz "correção por anulação". Confirmar que vale a ADR.

## Descobertas
- O gerador `criar-tela.mjs` só aceita `<Mod>/<Tela>` plano, e o Ponto usa `<Tela>/Index.tsx`. O trio foi escrito à mão seguindo a irmã `Relatorios/Index`.
- `withHeaders()` do teste acumula entre requests: um GET depois de um partial reload herdava os
  `X-Inertia-Partial-*`. Registrado no próprio teste (`UC-PTF-07`).
- `--out /c/...` com `MSYS_NO_PATHCONV=1` foi parar em `D:\c\...` (o Node lê `/c/` como caminho do drive atual).

## Prefixo tocado
Declarado no `04-*.md`: `routes.php` (1 bloco, 2 rotas) · `FechamentoController.php` · migration · `Pages/Ponto/Fechamento/**` · `ponto-fechamento.contract.json` · `FechamentoContratoTest.php`.
**Fora do prefixo, declarado:** `Entities/Competencia.php` · `Services/FechamentoService.php` · `CompetenciaAppendOnlyTest.php` ·
`DataController.php` (permissão `ponto.fechar` + item/ghost de menu) · `Resources/lang/pt/ponto.php` (2 chaves) ·
`.github/workflows/ponto-pest.yml` (2 linhas na allowlist da lane) · `memory/requisitos/Ponto/{RUNBOOK-fechamento,SPEC}.md` · derivados (`SUPERFICIE.md`, `_STATUS-GENERATED.md`, `MAQUINAS-INVENTARIO.md`).
**`nao_toca` respeitado:** `ApuracaoService.php`, `ponto_marcacoes`, `ReapurarDiaJob.php` — zero linhas.
