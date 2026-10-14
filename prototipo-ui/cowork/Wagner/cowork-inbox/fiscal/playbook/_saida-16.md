---
sessao: "16"
titulo: Bloqueio com saída de 1 clique — R-NFE-022 feito · R-NFE-022b parado por premissa errada
autor: "[CL]"
data: 2026-10-14
base: wagnerra23/oimpresso.com@main dffa1d7bbe
prefixo_tocado: Exceptions/NcmPadraoAusenteException.php (novo) · Services/NfeService.php (3 pontos de lançamento) · Http/Controllers/NfeEmissaoController.php (emitir) · Pages/Sells/_components/FiscalSection.tsx · Tests/Feature/NfeBloqueioComSaidaTest.php (novo) · SPEC NfeBrasil (R-NFE-022) · nfebrasil-pest.yml · SUPERFICIE (regenerada) · este arquivo
---
# _saida-16 · Bloqueio com saída

**Resposta curta:** o bloqueio por NCM padrão ausente agora traz a saída. A emissão continua recusada
(nunca sai nota sem NCM) e o erro carrega a ação "Escolher NCM padrão", que leva a
`/nfe-brasil/tributacao/config-default#ncm_default` — a mesma tela que a Saúde fiscal já aponta. A
emissão manual responde **422 com a ação**, não mais **500 só com texto**, e o drawer da venda
(`FiscalSection`) mostra o link junto da mensagem. O **R-NFE-022b não foi feito**: os códigos 110 e
539 do aceite não são rejeições cadastrais (§3).

## 1 · Priorização pela medição da 13

A 13 contou palavras em 30 dias de conversas do business 1: `ncm` **0**, `rejei` **0**, `sefaz` 0,
`certificado` 3. A medição **não separa** os dois bloqueios desta thread, e a própria 13 avisa que
não há linha de base classificada. Sem sinal de volume, a ordem saiu do alcance de cada bloqueio:
o de NCM padrão para **toda** emissão da empresa; o cadastral atinge uma nota de um cliente. Fiz o
R-NFE-022 primeiro. O "depois" desta thread não é medível até existirem as tags `fiscal-*` da
§3 da 13.

## 2 · R-NFE-022 · feito

| peça | o quê |
|---|---|
| `NcmPadraoAusenteException` | estende `RuntimeException` (quem já capturava segue igual) e expõe `acao()`: `label`, `url`, `campo`. Rota = `nfe-brasil.tributacao.config.show`, a mesma do item `ncm_padrao_invalido` da Saúde fiscal. A mensagem mantém "sem NCM padrão configurado", que 3 testes existentes procuram. |
| `NfeService` | os 3 lançamentos de NCM padrão (`emitirParaInvoice`, `emitirParaTransaction`, montagem dos itens) usam a exceção nova. A regra de bloqueio não mudou (tamanho ≠ 8 recusa). |
| `NfeEmissaoController::emitir` | captura o bloqueio antes do `Throwable` genérico e devolve 422 `{error: ncm_padrao_ausente, message, acao}`. |
| `FiscalSection.tsx` | quando a resposta traz `acao.url`, a mensagem de erro ganha o link. Fora isso, igual. |

"Já no campo": o link usa a âncora `#ncm_default`, que é o `id` do input na `ConfigDefault`. A tela
rola até o campo; não foi feito foco automático (pediria editar a Page).

## 3 · R-NFE-022b · não feito: a premissa está errada

O aceite pede "corrigir o cadastro e reenviar" para **cStat 110 e 539**, e o `sefaz-actions.ts`
descreve 110 como "IE inválida" e 539 como "CNPJ/CPF não confere". A tabela de cStat do SDK que o
repo declara como fonte (`SefazCstatService::CAMINHO_TABELA` = `vendor/nfephp-org/sped-nfe/storage/cstat.json`)
diz outra coisa, lida no CT 100 em 2026-10-14:

| cStat | texto da tabela |
|---|---|
| 110 | Uso Denegado |
| 539 | Duplicidade de NF-e com diferença na Chave de Acesso |

Nota **denegada** tem o número consumido na SEFAZ, e **duplicidade** não se resolve trocando cadastro
do cliente. Ligar "corrigir cadastro e reenviar" nesses dois códigos é o "conserto automático
mudando dado fiscal sem receita conhecida" que o próprio UC quer evitar. Por isso parei.

Rejeições do destinatário que são cadastrais de fato, na mesma tabela:
`208` CNPJ inválido · `210` IE inválida · `232` IE não informada · `233` IE não cadastrada ·
`234` IE não vinculada ao CNPJ · `237` CPF inválido · `246` CNPJ não cadastrado ·
`623` CPF não cadastrado · `624` IE não vinculada ao CPF.

## 4 · Prova (CT 100, worktree isolado `/tmp/wt16`, vendor copiado, tenant 98)

| rodada | resultado |
|---|---|
| `NfeBloqueioComSaidaTest` + `SaudeFiscalTest` + `EmitirParaInvoiceFallbackTest` + `NfeEmissaoPorItemTest` + `NfeServiceRetransmitirTest` + `NfeServiceEmitirParaTransactionTest` | **31 passed · 1 skipped · 106 assertions** |
| M1: controller não captura o bloqueio | cai (422 → 500) |
| M2: `emitirParaTransaction` volta ao `RuntimeException` cru | cai (2 casos) |
| M3: URL da ação sem o campo | cai (2 casos) |
| PHPStan nos 3 arquivos de produção | sem erros |

As 3 mutações foram conferidas aplicadas (grep no arquivo) antes de ler o resultado. O teste devolve
`nfe_business_configs` e `business.ncm_padrao` do tenant 98 ao estado de antes.
Typecheck/eslint do `FiscalSection.tsx` ficam com o CI (o worktree não tem `node_modules`).

## 5 · Valor

Nada muda de valor, estoque ou tributo: a regra de bloqueio é a mesma, só a forma do erro mudou.
Sem tabela antes→depois.

## 6 · Pedido literal pro [W] (via Cowork)

```
1. R-NFE-022b: trocar "cStat 110/539" pela lista cadastral real do destinatário
   (208 · 210 · 232 · 233 · 234 · 237 · 246 · 623 · 624), ou dizer quais dela entram.
   Com a lista certa, a 022b vira PR próprio.
2. sefaz-actions.ts (e a cópia em Fiscal/_components/NotaDrawer.tsx): as receitas de 110 e 539
   descrevem outra rejeição. Corrigir o texto é pedido à parte (as duas cópias divergem).
```

## 7 · Descobertas que mudam outra sessão

- **`NfeService::retransmitir` marca a nota antiga como `inutilizada` também quando ela é `denegada`.**
  Número denegado não se inutiliza na SEFAZ; o status local passa a dizer outra coisa. Não mexi: é
  emissão (🔴) e tem regra própria.
- **NCM `00000000`:** a Saúde fiscal (thread 14) o trata como bloqueante, a emissão aceita. Uma das
  duas está errada; decidir antes de mexer.
- **Emissão automática** (job da NFC-e, listener da cobrança): o bloqueio só vai para o log; a saída
  ali é o card da Saúde fiscal.
- **`NfceStatus.tsx`** chama o `emitir` (JSON) por `router.post` do Inertia; o 422 novo não muda isso,
  mas a tela não mostra a ação. Ela não tem RUNBOOK, então ficou fora.
