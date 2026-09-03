---
id: modules-fiscal-tests-fixtures-sped-icms-ipi-golden-meta
tipo: fixture-meta
fixture: sped-icms-ipi-golden.txt
owner: wagner
last_run: "2026-09-03"
---

# Cabeçalho do golden `sped-icms-ipi-golden.txt`

> O TXT é layout de posição fixa pipe-delimited: **não aceita linha de comentário**
> sem virar arquivo inválido. Por isso o cabeçalho que o `UC-FSF1-05` pede mora
> aqui, ao lado, e não dentro do arquivo.

## O que este arquivo é

Saída **real** do `Modules\Fiscal\Services\SpedIcmsIpiGeneratorService::gerar()`.
Não foi escrito à mão, nem transcrito: foi capturado em base64 na saída do processo
e materializado por script, com o SHA-256 conferido nas duas pontas.

| Campo | Valor |
|---|---|
| **Tenant** | `business_id = 98` — tenant fictício canônico ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)). Nunca biz=4. |
| **Competência** | `2026-01` (janeiro/2026) — mês encerrado, passa pela guarda `competenciaFechada` |
| **Layout** | CONFAZ Guia Prático EFD-ICMS/IPI **v3.1.1**, `COD_VER = 018`, `IND_PERFIL = A`, `COD_FIN = 0` (original) |
| **Bytes** | 1794 |
| **Linhas** | 47 (terminador `CRLF`) |
| **SHA-256** | `e4eeccd4c90ea67a656621cfed6d16862a73694bd1d5cb34f591e805c4408f23` |
| **Gerado em** | 2026-09-03, CT 100 (`oimpresso-staging`, MySQL `oimpresso_staging`) |

## Insumo declarado

O `business` 98 **como está** no staging (nenhum `UPDATE`) mais duas `nfe_emissoes`
determinísticas inseridas dentro de uma transação com `rollBack` — nada persistiu:

| # | `transaction_id` | Número | Valor | Emissão | `metadata` |
|---|---|---|---|---|---|
| 1 | 90001 | 1001 | 1.500,00 | 2026-01-10 09:00 | `dest_uf=SC`, `dest_cnpj=99888777000166`, `ncm=61091000` |
| 2 | 90002 | 1002 | 2.350,75 | 2026-01-22 14:30 | `dest_uf=RS`, `dest_cpf=52998224725`, `ncm=62034200` |

O `transaction_id` é **fixo de propósito**: o código do item do registro `0200` é
`PDV-{transaction_id}`, então sem fixá-lo o arquivo mudava a cada execução (medido:
dois SHA distintos antes de fixar). Determinismo comprovado por duas execuções
consecutivas com SHA idêntico.

## ⚠️ O que este golden EXPÕE (achado, não defeito da fixture)

O registro `0000` do arquivo sai com **CNPJ vazio, IE vazia e `UF = SP` fixo**, e o
`0005` sai com CEP `00000000` e endereço `NAO INFORMADO`. A causa foi medida no
CT 100 em 2026-09-03: a tabela `business` **não tem** nenhuma das colunas que
`registro0000`/`registro0005` leem — `state`, `city`, `zip_code`, `landmark`,
`tax_number`, `inscricao_estadual`, `mobile`, `email` são todas `NAO`; no
UltimatePOS elas moram em `business_locations`. O Service cai no fallback `'SP'`,
e daí também sai o `COD_MUN = 350000`.

Consequência em cadeia: como o CFOP interno×interestadual é decidido pela UF do
emitente, **toda** operação é comparada contra SP. No golden, a nota SC→SC saiu com
CFOP `6102` (interestadual) em vez de `5102`.

Isto **não foi consertado nesta onda** — é motor fiscal, fora do escopo declarado, e
a liberação depende de decisão do responsável. É exatamente o risco que a trava
`fiscal.sped_simples_only_lock` (default `true`, fail-secure) já cobre e que o
`Sped.charter.md` já anti-hooka em *"NÃO declarar o gerador validado sem smoke no
PVA-EFD"*. O golden serve para tornar o defeito **visível e versionado** em vez de
suposto.

## Como regerar

Se o gerador mudar de propósito, o golden é regerado — nunca editado à mão. O script
de captura vive na descrição do PR desta onda; ele semeia o insumo da tabela acima
dentro de transação, chama `gerar(98, 2026, 1)` e imprime bytes + linhas + SHA-256 +
o conteúdo em base64. Rodar no CT 100 ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).
