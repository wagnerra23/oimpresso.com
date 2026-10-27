---
id: resources-js-pages-financeiro-exportacaocontabil-index-casos
casos: Exportação contábil · /financeiro/exportacao-contabil
irmaos: charter e tela nascem na fatia da tela (protótipo antes do .tsx)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: o arquivo é contrato com sistema de terceiro; o caso ancora o leiaute na documentação oficial do fornecedor.
owner: wagner
last_run: "2026-10-27"
---

# Casos de uso — /financeiro/exportacao-contabil

> **Status:** ✅ passa (prova no manifesto) · 🧪 prova existe, ainda sem veredito · ⬜ sem prova · ❌ quebrou.
>
> Derivado da US-FIN-077 (`memory/requisitos/Financeiro/SPEC.md`) e dos leiautes oficiais: Domínio
> "Leiaute Domínio Sistemas com Separador" (suporte.dominioatendimento.com, solução 672) e Alterdata
> "Importação Personalizada - Texto" (ajuda.alterdata.com.br, artigo 150435497). Prova em
> `Modules/Financeiro/Tests/Feature/ExportacaoContabilContratoTest.php`.

Fatia 1 (backend): a rota `GET /financeiro/exportacao-contabil/{layout}?competencia=AAAA-MM` entrega o
arquivo. A tela (de-para + botões) vem na fatia seguinte, a partir do protótipo.

## UC-EXPC-01 — Domínio sai no leiaute oficial com separador
Status: 🧪 (`ExportacaoContabilContratoTest`)
Dado um recebimento baixado no mês e as contas com código no de-para · Quando o dono exporta para Domínio
· Então o arquivo traz `|0000|CNPJ|`, um `|6000|X||||` e um `6100` com data dd/mm/aaaa, débito no banco,
crédito na conta do título e valor com vírgula, em ISO-8859-1 com CRLF.

## UC-EXPC-02 — Alterdata sai no leiaute de importação personalizada em texto
Status: 🧪 (`ExportacaoContabilContratoTest`)
Dado um pagamento baixado no mês · Quando o dono exporta para Alterdata · Então cada lançamento é uma linha
com as colunas A..J entre aspas e separadas por vírgula, débito na conta do título e crédito no banco.

## UC-EXPC-03 — Juros e desconto em linhas próprias, líquido fecha com o banco
Status: 🧪 (`ExportacaoContabilContratoTest`)
Dado um recebimento com juros e desconto · Então saem três lançamentos (principal, juros/multa, desconto) e
o movimento líquido no banco é igual a `valor_baixa + juros + multa − desconto`, conferido por dois caminhos.

## UC-EXPC-04 — De-para incompleto não gera arquivo
Status: 🧪 (`ExportacaoContabilContratoTest`)
Dado uma baixa cuja conta do plano não tem código contábil · Então nenhum lançamento sai e a pendência
é listada (a rota responde 422 com a lista). Arquivo incompleto nunca chega ao contador.

## UC-EXPC-05 — Só a empresa da sessão
Status: 🧪 (`ExportacaoContabilContratoTest`)
Baixa de outra empresa nunca entra no arquivo (`business_id` nas duas tabelas da consulta).

## [BACKLOG] Questor
Status: ⬜ sem prova — o Questor importa por leiaute configurado na própria instalação (arquivo de
leiaute do usuário); não há leiaute fixo publicado. Aguardando decisão [W] sobre qual leiaute seguir.

## [BACKLOG] Centro de custo no lançamento
Status: ⬜ sem prova — Domínio registro 6110 e Alterdata colunas H/I. Depende do rateio da US-FIN-075.

## Trilha do tempo
- 2026-10-27 · [CC] criado com a fatia 1 (backend) da US-FIN-077.
