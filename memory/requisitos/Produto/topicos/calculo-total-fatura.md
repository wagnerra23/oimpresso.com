---
id: calculo-total-fatura
module: Produto
title: "Cálculo do total da fatura"
kind: regra-negocio
status: contestado
updated_at: "2026-07-21"
valid_from: "2026-06-05"
anchors:
  screens: []
  routes: []
  controllers: []
  functions:
    - app/Utils/ProductUtil.php::calculateInvoiceTotal
    - app/Utils/Util.php::num_uf
  models:
    - app/TaxRate.php
  tables:
    - tax_rates
  tests:
    - tests/Feature/Calculo/CalculoValorSellsTest.php
    - tests/Unit/Utils/NumUfHeuristicPtBRTest.php
    - tests/Unit/Utils/IncidentValorInfladoNumUfTest.php
  adrs:
    - 0093-multi-tenant-isolation-tier-0
review:
  state: revisado-central
  verdict: discordo
  confidence: alta
  central_reviewer: codex
  human_approver: null
critiques:
  - critic: claude-opus-4-8-tres-juizes
    at: "2026-07-21"
    verdict: discordo
    summary: "O scorecard inicial discordou de tenant, parsing, retorno e tipos; a síntese central preservou os pontos comprovados e rejeitou o C2 circular."
    evidence:
      - type: codigo
        ref: "memory/governance/scorecards/funcoes/app-utils-productutil.yaml"
claims:
  - id: C01
    status: observado
    text: "A função retorna false quando a lista de produtos está vazia e array nos demais caminhos."
    evidence:
      - type: codigo
        ref: "app/Utils/ProductUtil.php:642-690"
  - id: C02
    status: observado
    text: "Existe teste golden que protege o total e o desconto percentual contra inflação de parsing numérico."
    evidence:
      - type: teste
        ref: "tests/Feature/Calculo/CalculoValorSellsTest.php:137"
  - id: C03
    status: observado
    text: "A taxa é carregada com TaxRate::find sem filtro business_id e o model não declara global scope tenant."
    evidence:
      - type: codigo
        ref: "app/Utils/ProductUtil.php:680"
      - type: codigo
        ref: "app/TaxRate.php:8"
---

# Cálculo do total da fatura

## Escopo

- **Inclui:** subtotal, modificadores, desconto, imposto e total final calculados por `calculateInvoiceTotal()`.
- **Não inclui:** alteração da fórmula, correção automática ou decisão sobre UX de venda.

## Comportamento observado

A função somou preço × quantidade, adicionou modificadores, aplicou desconto fixo ou percentual, buscou a taxa por ID e retornou subtotal, imposto, desconto e total. Para entrada vazia retornou `false`; para entrada preenchida retornou array.

O teste golden `calculate_invoice_total_desconto_percentual_nao_infla` cobriu o vetor histórico de inflação e confirmou o caminho atual de `num_uf()`. Portanto, usar `num_uf()` não foi tratado como defeito por presunção.

## Intenção e critério de sucesso

- O cálculo de valor deve permanecer protegido por dupla confirmação e impacto antes→depois conforme `memory/proibicoes.md`.
- Dados de negócio tenant-owned não podem ser lidos por ID cru sem isolamento, conforme ADR 0093.
- O contrato de entrada vazia precisa ser confirmado nos consumidores antes de mudar `false` para outro tipo.

## Validade (bi-temporal)

- **`valid_from: 2026-06-05`** — o caminho de parsing atual (`num_uf()`) está em vigor e protegido por golden desde a correção do incidente de valor inflado (ROTA LIVRE biz=4, fix #2279, coberto por `tests/Unit/Utils/IncidentValorInfladoNumUfTest.php` + `tests/Feature/Calculo/CalculoValorSellsTest.php`). É a data do evento de domínio verificável — não a data de escrita deste tópico (`updated_at`).
- **`valid_until`: ausente** — o fato **não** foi superado. `status: contestado` significa que o *parecer* é disputado (leitura de `TaxRate` por ID sem escopo tenant, ADR 0093), **não** que o comportamento expirou. Contestado ≠ superado: só se atribui `valid_until` quando um ADR supersessor ou tópico sucessor invalidar o fato, e a data espelha o `decided_at` dessa âncora.

## Parecer crítico

- **Cálculo numérico — `concordo`:** há uma prova golden específica para a regressão de parsing observada.
- **Contrato de retorno — `discordo` como desenho, não autorização de fix:** `false|array` obriga consumidores a conhecer dois tipos. A correção depende de contar e validar todos os consumidores.
- **Isolamento da taxa — `discordo`:** `TaxRate` é dado de negócio com `business_id`, não possui global scope e é lido por `find($tax_id)`. Isso contraria a regra irrevogável da ADR 0093 independentemente de um caller também tentar validar. O impacto/explorabilidade ainda é `incerto` até mapear callers.
- **Veredito geral — `discordo`:** o caminho numérico tem defesa positiva, mas a violação estrutural Tier 0 impede um parecer global positivo.

## Fazer: benefícios e custos

- **Benefício de manter o cálculo atual:** preserva comportamento coberto pelo golden e reduz risco de nova inflação de valor.
- **Custo de manter:** perpetua retorno polimórfico e deixa a defesa tenant depender de contexto externo não explícito na função.

## Não fazer: benefícios e custos

- **Benefício de não refatorar agora:** evita mudança de valor sem dupla confirmação e sem mapa completo de consumidores.
- **Custo de não investigar:** uma taxa de outro tenant pode continuar possível se nenhum caller aplicar a validação; o risco precisa ser resolvido por evidência, não por suposição.

## Evidências e contradições

- `app/Utils/ProductUtil.php:642-690` — retornos `false|array` observados.
- `tests/Feature/Calculo/CalculoValorSellsTest.php:137` — golden específico do total/desconto.
- `app/Utils/ProductUtil.php:680` + `app/TaxRate.php:8` — consulta por ID e ausência de global scope no model.
- Lacuna: falta mapa dos callers e prova do vínculo `tax_rates.business_id` no ponto de entrada.

## Histórico de revisão

| Data | Papel | Revisor | Decisão | Evidência nova |
|---|---|---|---|---|
| 2026-07-21 | IA central | codex | `discordo` (impacto ainda incerto) | código + model + golden + ADR 0093 |
