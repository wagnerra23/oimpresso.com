---
thread: "06"
modulo: ancora
dono: "[CL]"
prefixo: ["governance/design/contracts", "scripts/qa"]
depende: []
base: medido em ffe69844cb71 (2026-09-13)
reescrita: 2026-09-13 — a versão de 11/09 partia de premissa FALSA
---
# 06 · Cobertura de Contrato de Tela — 35 para 188

## ⚠️ Errata da versão anterior desta thread (leia antes)
A v1 (11/09) dizia: *"`prototipo-ui/contrato/` tem 34 arquivos … e um `financeiro-unificado.intent.json` fora do glob"*. **As duas afirmações são falsas.** Medido em `ffe69844cb71`:

- **`prototipo-ui/contrato/` não existe.** Os contratos moram em **`governance/design/contracts/`**.
- **Não existe `financeiro-unificado.intent.json`** em nenhum lugar da árvore (filtro `contract\.json$|contrato/` sobre 16.569 arquivos → 39 hits; nenhum é esse).

Causa: eu citei caminho de memória e tratei a citação como medição. Não procure o arquivo — ele nunca esteve lá.

## O problema real (medido)
`governance/design/contracts/` tem **36** arquivos: **35 contratos** + `EXEMPLO.contract.json` (fixture). O corpus tem **188** charters. Ou seja: **~19% das telas** têm comportamento travado no CI; 81% dependem de revisão humana.

E a distribuição é torta: Fiscal tem 8 contratos, Patrimônio 5, Superadmin 4, Jana 4 — enquanto **`Financeiro/Unificado`, o maior charter do sistema (31 KB), não tem nenhum**. O nome mais próximo, `caixa-unificada.contract.json` (2.022 B), é **outra tela** (Caixa). Não promova um pelo outro por semelhança de nome.

## O que fazer
1. **Não criar contrato em massa.** Contrato sem caso que o defenda é enfeite. O que esta thread pede é o **número**: o gate que consome a pasta deve **imprimir quantos contratos carregou** e **quantos charters existem**, com a razão entre os dois no recibo. Hoje os dois números são invisíveis — é assim que 19% parece "coberto".
2. **Guarda de pasta:** `.json` em `governance/design/contracts/` que não case `*.contract.json` e não esteja na allowlist (`EXEMPLO.contract.json`) → falha citando o arquivo. (Hoje não há nenhum fora do padrão — a guarda é para continuar assim.)
3. **Declarar a lacuna do Financeiro:** `Financeiro/Unificado/Index` sem contrato entra como pendência nomeada, não como surpresa. Escrevê-lo é onda própria, com os UC do `Index.casos.md` ao lado (UC-F01…F05 + UC-FUNI-01…04 já existem — a matéria-prima está pronta).

## Provas (execução)
- Rodar o gate: recibo com `contratos_carregados` e `charters_encontrados`. O segundo tem que bater com `find resources/js/Pages -name '*.charter.md' | wc -l` = **188** em `ffe69844cb71`.
- **Caso de sanidade:** plantar `/tmp`→`governance/design/contracts/lixo.json`, rodar, ver falhar citando `lixo.json`, apagar. Guarda que não pega arquivo plantado não é guarda.
- **Controle positivo:** rodar com a pasta intacta → exit 0 e `contratos_carregados: 35`.

## Parar se
- Alguém propor gerar os 153 contratos que faltam → **pare**: contrato é derivado de caso, e caso é trabalho de charter. O número exposto é o pedido; a corrida não é.
