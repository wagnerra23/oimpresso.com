---
sessao: "04"
titulo: casos.md com UC · Sells/Caixa
executor: "[CL]"
base: 317e1b4ec33
---
# _saida 04

## Checklist
1. ✅ `resources/js/Pages/Sells/Caixa/Index.casos.md` com 9 UC reconhecidos pela `uc-regex` (`UC-SCAIXA-01..09`, prefixo sem outra ocorrência no repo — `git grep SCAIXA` vazio antes)
2. ✅ `tests/Feature/Sells/SellsCaixaContratoTest.php` cita cada UC-id no título de um `it()`
3. ✅ charter reconciliado nos 4 pontos divergentes (nota datada, decisão [W])
4. ✅ teste ligado na lane `sells-pest.yml`
5. ✅ `prototipo-readiness`: `Sells/Caixa/Index` saiu de 1-ciclo e está em PRONTAS

## Histórico: o PARAR SE foi acionado e resolvido

Na 1ª rodada parei porque o charter contradizia o `.tsx` em 4 pontos (KPIs, link da OS, botão
Fechar/Abrir, Imprimir Z). Decisão [W] 2026-09-23, textual: *"o caixa segue o código atual,
escreve os UC da thread 04"*. Então os UC vêm do código (`SellController@inertiaCaixa` +
`Index.tsx`) e o charter é o lado que perdeu (regra de precedência de `memory/proibicoes.md`:
corrigir o perdedor no mesmo PR).

## O que mudou no charter (só os 4 pontos, cada um com nota "reconciliado 2026-09-23 (decisão [W])")

| Ponto | Antes | Depois |
|---|---|---|
| KPIs | Faturado · Esperado em caixa · Conferido · Diferença | Faturado · Vendas em dinheiro · Caixa aberto/fechado · Origens hoje (os 3 antigos viram `[BACKLOG]` no casos.md) |
| Link da OS | `CustomEvent('oimpresso:open-venda')` (Goals e Anti-hooks) | navega para `/sells?open=<id>` |
| Botão primário | "Fechar caixa" sempre | só com `close_cash_register`; "Fechar caixa" com caixa aberto, "Abrir caixa" (`/cash-register/create`) sem caixa |
| Imprimir Z | placeholder | abre `/cash-register/register-details` legado em nova aba |

O `.tsx` não foi tocado. O `Caixa-r1-visual-comparison.md` ainda diz "✅ paridade" para KPIs e
CustomEvent — está fora do prefixo, fica como ponta solta.

## O teste

- Tenant 98 (`seededTenant()`) × adversário 99 (`seededSupportClientTenant()`, que cria o 99 se
  faltar — o `[T0]` não vira skip). Precondição asserta que os dois ids são distintos.
- `DatabaseTransactions`; usuários criados pelo próprio teste com permissões explícitas.
- Requisição com `X-Inertia` + `X-Inertia-Version` + `X-Requested-With`, como o browser manda.
- Valores: nenhum cálculo foi alterado. Os esperados são derivados à mão da fixture num dia
  isolado (2031-03-17) e a derivação está escrita no cabeçalho do teste: 150.00 / 2 vendas;
  dinheiro 2 vendas / 110.00 (o troco de 5.00 com `is_return=1` fica fora); cartão 1 / 40.00;
  balcão 1 / 100.00; oficina 1 / 50.00 com ref da venda V2. A venda de 70000.00 do tenant 99
  nunca pode aparecer.
- Matchers com mensagem: nenhum. Não há `toContain` com 2º argumento.
- Limites declarados no casos.md: o desenho dos KPIs, a navegação `/sells?open=` e o
  rótulo/destino do botão são do front e não têm teste de render — o teste prova o dado e o `id`
  que eles usam. UC-SCAIXA-09 pergunta ao router vivo se a rota do Imprimir Z existe.
- **Sintaxe PHP NÃO conferida** (sem `php` local). Conferir no CT 100.

## Lane de CI

`.github/workflows/sells-pest.yml` — `PHP / Pest (Sells · MySQL)`. Linha adicionada ao fim da
lista explícita do `pest`, ancorada na linha inteira anterior (que ganhou o ` \`):

```
            tests/Feature/Sells/SellsCaixaContratoTest.php
```

Os filtros de mudança (`push.paths` e `dorny/paths-filter`) já cobriam `tests/Feature/Sells/**` e
`app/Http/Controllers/SellController.php` — não precisaram de mudança. YAML conferido com
`js-yaml`: carrega, e o step do Pest contém o arquivo novo. A lane roda o
`junit-summary --check-assertions`. Se ela bloqueia merge, quem diz é o
`governance/required-checks-baseline.json`.

## Saídas (árvore 317e1b4ec33 + as mudanças desta thread)

`node scripts/casos-coverage-guard.mjs`:
```
casos:check · 73 violações (telas: 220, casos.md: 159)
✅ Sem violações novas DESTE PR (débito caiu −10 vs baseline).
```
(antes desta thread: 74 violações, 158 casos.md, −9)

`node scripts/qa/prototipo-readiness.mjs`:
```
  ✅ PRONTAS pra aplicar HOJE (trio + casos+UC + scorecard trava o comportamento): 60
       ...
       [core] Sells/Caixa/Index
       ...
  🟡 PRECISAM DE 1 CICLO de blindagem antes (o metabolismo MV faz): 34
  Total de telas com protótipo real: 94
```

## Tamanho

Passa de 300 linhas: teste 317 + casos.md 97 + charter +10/−7 + lane +2/−1 (~430). Não dá
para separar o casos.md do teste (UC sem teste que o cite reprova o G-2) nem o charter do
casos.md (regra de precedência: corrigir o perdedor no mesmo PR). Se precisar dividir: PR 1 com
UC-SCAIXA-01..06 (agregados e dados) + charter + lane; PR 2 com UC-SCAIXA-07..09 (caixa aberto,
permissão de fechar, Imprimir Z). Recomendo 1 PR só, porque os dois seriam do mesmo arquivo de
teste.
