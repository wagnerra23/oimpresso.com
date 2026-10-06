---
date: "2026-10-05"
time: "21:55 BRT"
slug: fabricacao-por-unidade-painel-ordem
tldr: "Fabricação: #8741 conserta 'Por unidade' na receita (vermelho→verde, pronto, aguarda merge [W]); #8742 servidor do painel da ordem com as contas da tela antiga (verde, pronto, aguarda [W] por ser leitura de valor); #8743 o painel em si, empilhado no #8742. Ledger LC-08 +1 recibo (os 2 erros da sessão das 15:16), revisado pelo ciclo-adversary. Medições em produção do #8707/#8715 não feitas: o navegador do app ficou no login."
prs: [8741, 8742, 8743]
---

# Handoff — Fabricação: "Por unidade" na receita e painel da ordem

Continua o [handoff de 15:16](2026-10-05-1516-fabricacao-grade-ordens-seguranca-receita.md).

## O que foi feito

| PR | o quê | estado | prova |
|---|---|---|---|
| #8741 | salvar receita aceita custo **"Por unidade"** (o `StoreRecipeRequest` só aceitava fixo/percentual; a janela oferece os 3 e o cálculo conhece os 3) | pronto, `CLEAN`, **aguarda merge [W]** (regra de valor: o antes→depois está no corpo) | vermelho run 37392060984 (*"The selected production cost type is invalid"*) → verde run 37395078608, 159 passed / 477 assertions |
| #8742 | `ProductionService::detalheOrdem` + prop optional `ordem_detalhe` (`?ordem=ID`) na tela de Ordens — servidor do painel lateral | pronto, **aguarda [W]** (só leitura, mas mostra valor: conferência por 2 caminhos no corpo) | run 37395662798, 162 passed / 500 assertions; cada caso confere à mão e contra o `ProductionController::show()` antigo; PHPStan verde |
| #8743 | o painel lateral (`OrdemDrawer`), charter G7 | rascunho, **empilhado no #8742** (base = branch dele) | tsc, eslint, layout/components/reuse/pageheader verdes; sem medição visual ainda |

Teste antigo que fixava o defeito: o `Wave14LgpdSecurityTest` asseria `in:fixed,percentage`; passou a exigir as 3 formas (o `Wave18FormRequestsTest` já exigia no `UpdateRecipeRequest`).

## Próximos passos

1. **Merge do #8741 e do #8742** — decisão [W] (regra de valor). Depois do #8742 no `main`: mudar a base do **#8743** para `main` (`gh pr edit 8743 --base main`), esperar as lanes e medir o painel em produção, empresa 1: abrir uma ordem finalizada e um rascunho e comparar com o detalhe antigo (`/manufacturing/production?legacy=1`, ver a ordem).
2. **Medir em produção** os indicadores do #8707 e o canto 8px do #8715 (itens 1 e 2 do handoff das 15:16). Não feito: o navegador do app caiu no login e eu não digito senha.
3. Seguem do handoff das 15:16, sem mudança: indicadores das Ordens seguindo o filtro (aguarda [W], regra de valor) · as 3 perguntas da janela "Nova receita" · contrato visual das telas da Fabricação (molde ADR 0409) · migrar as 2 telas Blade restantes.

## Cuidados

- A lane `Manufacturing · Pest (MySQL)` roda só na abertura/`ready_for_review` do PR, **não** nos pushes seguintes. Depois de um push, dispare à mão (`gh workflow run manufacturing-pest.yml --ref <branch>`) e leia **assertions** no log — nesta sessão o "86 verdes" de um commit não incluía a lane.
- O painel usa preço de **hoje** nos ingredientes e o custo extra pela forma gravada na ordem, como a tela antiga e o protótipo. O "custo congelado" é o `final_total`. Copy que difere do protótipo de propósito está no topo do `OrdemDrawer.tsx` e no corpo do #8743.

## Estado MCP no momento do fechamento

- Servidor MCP `oimpresso` **indisponível** nesta sessão (HTTP 401 no cabeçalho de autorização): o checklist MCP-first não rodou. O estado acima vem do GitHub (`gh pr view`/`gh run view`) e das runs citadas.
