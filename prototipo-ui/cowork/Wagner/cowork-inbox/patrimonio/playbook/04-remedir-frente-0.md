---
sessao: "04"
titulo: Remedir D1/D5 e os não-lidos — thread de MEDIÇÃO (não escreve código)
dono: "[CL]"
base: cb475c0ca2f4
constituicao: CONSTITUICAO-COWORK.md (C1–C12)
prefixo: — (nenhum arquivo de produção)
nao_toca: tudo. Esta thread é read-only.
depende: — (vaga 1)
antes:  D1 e D5 vêm da medição de 2026-09-01 e não foram reconfirmados
depois: cada defeito tem veredito com linha, ou está declarado inexistente
---
# 04 · Remedir a frente 0

## Por que esta thread existe
O pedido de 04/09 listava 9 defeitos (D1–D9) medidos em **01/09**. Hoje reconfirmei **3 com linha exata** (viraram as threads 01, 02 e 03) e **1 caiu**: o `&&` da permissão **não apareceu** nas 40 ocorrências que li nos controllers — o padrão de lá é `! (can('superadmin') || hasThePermissionInSubscription(...))`.

**Retrato de 7 dias produz PR fantasma.** Foi o que aconteceu no Compras, onde 6 de 8 pedidos já estavam feitos. Esta thread paga o passo 0 do §13 pelo módulo inteiro, de uma vez.

## O que medir (e o veredito que cada um exige)
| # | alegação de 01/09 | onde olhar | veredito exigido |
|---|---|---|---|
| D1 | permissão com `&&` no `AssetMaitenanceController` | `AssetMaitenanceController.php` (15.724 B) | linha exata **ou** "não existe" |
| D5 | whitelist de auditoria com coluna morta | `Config/retention.php` (3.391 B) + `Entities/Asset.php` (4.281 B) | nome da coluna e onde |
| D6 | `purchase_amount` morto em `Asset.php` | `Entities/Asset.php` | é gravado? é lido? |
| D7 | `exists` sem tenant no `StoreAssetAllocationRequest` | Request (1.964 B) | **atenção:** a thread 02 toca esse arquivo — se confirmar, avise a 02 |
| D8 | `depreciation` gravada e nunca calculada | `AssetService.php` (7.456 B) | onde grava, quem lê |
| D9 | permissões `asset.*` × SCOPE `assetmanagement.*` | seeder/registro de permissões | **destrava ou trava a thread 03** |

## Regras desta medição
- **Read-only.** Achou defeito? Não conserta: escreve no `_saida-04.md` no formato de âncora do §13.4 — `arquivo :: símbolo :: linha :: sha` —, pronto pra virar thread.
- **Ausência não é prova** quando a busca voltar *bounded*: diga "busca limitada, não verifiquei", nunca "não existe".
- **Campo `invalida:` é obrigatório** no `_saida`: se a medição matar alguma das threads 01–03 ou 05, nomeie qual. É o canal de correção de plano que hoje só existe por humano ler.

## Checklist de saída
1. veredito de D1 · 2. D5 · 3. D6 · 4. D7 (+ aviso à 02 se confirmar) · 5. D8 · 6. D9 (+ destrava ou trava a 03) · 7. campo `invalida:` preenchido, ainda que "nenhuma" · 8. nenhum arquivo de produção tocado
