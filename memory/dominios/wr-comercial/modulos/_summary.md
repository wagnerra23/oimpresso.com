---
auto_generated: true
generated_at: 2026-05-09
target_version: 1468
---

# Sumário schema reconstruído — versão 1468

- **Total de tabelas registradas:** 448
- **Vivas na versão 1468:** 393
- **Dropadas em versões anteriores:** 55
- **Statements DDL não-reconhecidos:** 764 (esperado: INSERTs, procedures, etc — não-schema)

## Distribuição por módulo

| Módulo | Tabelas vivas | Link |
|---|---|---|
| `nfe` | 54 | [_index.md](nfe/_index.md) |
| `estoque` | 48 | [_index.md](estoque/_index.md) |
| `financeiro` | 46 | [_index.md](financeiro/_index.md) |
| `cadastros` | 39 | [_index.md](cadastros/_index.md) |
| `agenda` | 35 | [_index.md](agenda/_index.md) |
| `wr_metadata` | 34 | [_index.md](wr_metadata/_index.md) |
| `producao` | 31 | [_index.md](producao/_index.md) |
| `vendas` | 23 | [_index.md](vendas/_index.md) |
| `equipamento` | 21 | [_index.md](equipamento/_index.md) |
| `configuracao` | 19 | [_index.md](configuracao/_index.md) |
| `bi` | 15 | [_index.md](bi/_index.md) |
| `ui_metadata` | 13 | [_index.md](ui_metadata/_index.md) |
| `rh` | 8 | [_index.md](rh/_index.md) |
| `api` | 4 | [_index.md](api/_index.md) |
| `tributario` | 3 | [_index.md](tributario/_index.md) |

## Statements não-reconhecidos (amostra de 10)

> ℹ️ Esperado pra DDL fora de escopo (INSERT, EXECUTE PROCEDURE, etc). Se algum for ALTER TABLE relevante, reportar pra ajustar parser em `lib/ddl_parser.py`.

- v12: `ALTER TABLE AGENDA     ALTER CODPERGUNTA TO CODAGENDA_FAQ,     ALTER PERGUNTA TO AGENDA_FAQ,     ALTER CODRESPOSTA TO CO`
- v12: `ALTER TABLE AGENDA_HISTORICO     ALTER CODPERGUNTA TO CODAGENDA_FAQ,     ALTER PERGUNTA TO AGENDA_FAQ,     ALTER CODRESP`
- v27: `ALTER TABLE PARAMETROS SET URL_SPC = 'http://consulta.tubarao.cdl-sc.org.br/sasfx/swf/APP.php' (WHERE  CODEMPRESA = 1)`
- v164: `end^ set term ;^  alter table PRODUTO_MOVIMENTO alter OBSERVACAO type varchar (300)`
- v168: `UDPDATE 169`
- v179: `abono de  firmas; coleta e entrega de documentos, bens e valores; comunicação com outra agência ou com a administração c`
- v179: `transferência de veículos; agenciamento fiduciário ou depositário; devolução de bens em custódia.', 'SERVIÇO')`
- v179: `serviços relativos  a abertura de crédito, para quaisquer fins.', 'SERVIÇO')`
- v179: `fornecimento de posição de cobrança, recebimento ou pagamento; emissão de carnês, fichas de compensação, impressos e doc`
- v179: `transporte do corpo  cadavérico; fornecimento de flores, coroas e outros paramentos; desembaraço de certidão de óbito; f`

