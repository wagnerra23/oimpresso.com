# Casos de uso — /arquivos (contrato de teste)

> `UC-ARQ-01`…`UC-ARQ-14`. Proposta F1: a tela não existe no vivo (US-ARQ-013).

| ID | Cenário | Esperado |
| --- | --- | --- |
| UC-ARQ-01 | Acervo com 10 arquivos | 4 abas; subtítulo com contagem, tamanho total e quantos estão cifrados |
| UC-ARQ-02 | Filtrar bucket `sensitive` | só os do cofre; contador do chip bate com a lista |
| UC-ARQ-03 | Arquivo em disco `vault` | selo com cadeado; baixar avisa que o link assinado vale 60 min e passa pelo DownloadController |
| UC-ARQ-04 | Arquivo sem `arquivable` | linha marcada como urgente + selo "órfão" com o motivo em tooltip |
| UC-ARQ-05 | Arquivo a ≤30 dias do prazo | coluna "Vence em" em vermelho + linha urgente |
| UC-ARQ-06 | Arquivo com prazo vencido | rótulo "prazo vencido", nunca contagem negativa |
| UC-ARQ-07 | Aba Retenção | tabela com os 8 contextos, prazo em anos/dias e a base legal literal |
| UC-ARQ-08 | Existe arquivo além do prazo + grace | banner vermelho citando HealthCheckCommand check #4 e LGPD Art. 16 |
| UC-ARQ-09 | Aba Cofre | espaço por disco (vault × local) + os 3 achados com contagem |
| UC-ARQ-10 | Arquivo de 65 MB | listado como acima do cap de 50 MB, com a razão (OOM) e ADR 0126 |
| UC-ARQ-11 | Dois arquivos com o mesmo MD5 | agrupados como duplicado, com a ressalva de que nem sempre é erro |
| UC-ARQ-12 | Excluir foto de OS | confirmação fala do grace de 30 dias e do hard_delete do job |
| UC-ARQ-13 | Excluir XML de NF-e | confirmação avisa da guarda legal de 5 anos ("problema fiscal, não faxina") |
| UC-ARQ-14 | Papel sem `arquivos.access` | sem-permissão explicando que o anexo da OS continua acessível por quem vê a OS |

## Anti-regressão

- Nenhum caminho de upload nesta tela.
- Nenhum botão de editar/apagar linha da trilha.
- Nenhuma vista lista arquivo de outro `business_id`.
- Excluir nunca chama hard-delete direto — só soft-delete + grace.
- Prazo exibido sempre com base legal; mudar prazo exige mudar os dois arquivos de config.
