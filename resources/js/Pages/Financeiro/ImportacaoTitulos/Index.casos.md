---
id: resources-js-pages-financeiro-importacaotitulos-index-casos
casos: Importação de títulos · /financeiro/importacao-titulos
irmaos: charter e tela nascem na fatia da tela (protótipo financeiro-contabil-page.jsx, aba Importar)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: importar mexe em valor; o caso prova que nada é gravado sem prévia e confirmação, e que o total fecha.
owner: wagner
last_run: "2026-10-27"
---

# Casos de uso — /financeiro/importacao-titulos

> **Status:** ✅ passa (prova no manifesto) · 🧪 prova existe, ainda sem veredito · ⬜ sem prova · ❌ quebrou.
>
> Derivado da US-FIN-034 (`memory/requisitos/Financeiro/SPEC.md`) e da regra mestre de valor
> (`memory/proibicoes.md`). Prova em `Modules/Financeiro/Tests/Feature/ImportacaoTitulosContratoTest.php`.

Backend: `POST /financeiro/importacao-titulos/previa` (arquivo) e `POST /financeiro/importacao-titulos/gravar`
(token da prévia). Modelo fixo de colunas: tipo, contraparte, documento, emissão, vencimento, valor,
plano de contas, observação. A tela vem do protótipo da aba Importar.

## UC-IMPT-01 — A prévia classifica linha a linha e não grava
Status: 🧪 (`ImportacaoTitulosContratoTest`)
Dado uma planilha com linhas boas e ruins · Quando o usuário envia · Então cada linha volta como válida ou com
erro e o motivo (vencimento inválido, contraparte em branco, valor zero ou negativo, plano inexistente), linha
em branco é ignorada, os totais a receber e a pagar saem em centavos exatos, e nenhum título é gravado.

## UC-IMPT-02 — Gravar só depois de confirmar, e o total fecha
Status: 🧪 (`ImportacaoTitulosContratoTest`)
Quando o usuário confirma a prévia · Então só as linhas válidas viram títulos em aberto, com o número
sequencial do lançamento manual, e a soma gravada no banco é igual ao total mostrado na prévia (dois caminhos).

## UC-IMPT-03 — O que já existe não entra de novo
Status: 🧪 (`ImportacaoTitulosContratoTest`)
Título com mesmo tipo, documento, valor e vencimento já gravado aparece como "Já existe" e fica de fora; linha
repetida dentro do próprio arquivo também.

## UC-IMPT-04 — O mesmo arquivo não grava duas vezes
Status: 🧪 (`ImportacaoTitulosContratoTest`)
Gravar de novo com o mesmo token é recusado e nada é duplicado.

## UC-IMPT-05 — Só a empresa da sessão
Status: 🧪 (`ImportacaoTitulosContratoTest`)
A prévia fica guardada sob a empresa que a gerou; o token não grava em outra empresa.

## [BACKLOG] Mapear colunas de planilha livre
Status: ⬜ sem prova — a US-FIN-034 pede mapeamento coluna → campo; esta fatia usa o modelo fixo.

## Trilha do tempo
- 2026-10-27 · [CC] criado com o backend da importação (US-FIN-034).
