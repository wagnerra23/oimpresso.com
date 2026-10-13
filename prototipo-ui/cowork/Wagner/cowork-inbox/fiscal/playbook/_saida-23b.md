---
sessao: "23b"
titulo: Retenção federal ligada à emissão da NFS-e (DPS + nota + tela)
autor: "[CL]"
data: 2026-10-13
base: wagnerra23/oimpresso.com@main (pós-#9188)
prefixo_tocado: Modules/NFSe/{Services,DTO,Adapters,Models,Http/Requests} · Modules/NfeBrasil/Database/Migrations · Modules/NfeBrasil/Tests/Feature/NfseRetencoesDpsTest.php · resources/js/Pages/Nfse/Emitir.{tsx,charter.md,casos.md} · memory/requisitos/NFSe/RUNBOOK-emitir.md · SPEC/BRIEFING NfeBrasil e NFSe · este arquivo
decisao: "[W] 2026-10-13 — quem decide se o serviço é do art. 30: marcação na nota (desligada por padrão)"
---
# _saida-23b · Retenção federal ligada à emissão

> Continuação da thread 23 pedida pelo [W] no chat ("ligar retenções na DPS"). Não há thread
> numerada no `00-INDICE.md` para isto; o índice é do lado Cowork e não foi editado aqui.

**Resposta curta:** a tela Emitir NFS-e ganhou a marcação "Serviço sujeito à retenção de PIS/COFINS/CSLL (Lei 10.833/2003, art. 30)", desligada por padrão. Marcada, o `montarPayload` chama o `RetencoesNfseService` (regime do próprio tenant), a nota grava PIS/COFINS/CSLL retidos em `nfse_emissoes` e a DPS leva o grupo `tribFed` no leiaute da NT SE/CGNFS-e nº 007. Desmarcada, nada muda — nem a chave de idempotência.

## 1 · Feito

| arquivo | o quê |
|---|---|
| `Modules/NfeBrasil/Database/Migrations/2026_10_13_000001_add_retencoes_federais_to_nfse_emissoes.php` | `retencao_federal_sujeito` + `valor_ret_pis/cofins/csll` (default 0, idempotente). |
| `Modules/NFSe/Services/NfseEmissaoService.php` | `montarPayload` calcula quando marcado; `emitir` grava os valores. |
| `Modules/NFSe/DTO/NfseEmissaoPayload.php` | carrega a marcação e os três valores em centavos; a chave de idempotência só muda quando marcado. |
| `Modules/NFSe/Adapters/SnNfseAdapter.php` | `buildDps` (agora público) acrescenta `tribFed.piscofins{CST 01, tpRetPisCofins 3}` + `vRetCSLL` = soma, só quando há retenção. |
| `Modules/NFSe/Models/NfseEmissao.php` · `StoreNfseRequest.php` | fillable/casts e validação do campo novo. |
| `resources/js/Pages/Nfse/Emitir.tsx` | a marcação, com texto dizendo que o valor é calculado no envio. |
| charter / casos / RUNBOOK da tela | Non-Goal "sem IR/PIS/COFINS retidos" revisado com a decisão; UC-NFSEM-04; F1 do MWART (`RUNBOOK-emitir.md`). |
| `Modules/NfeBrasil/Tests/Feature/NfseRetencoesDpsTest.php` | R-NFSE-003 / UC-NFSEM-04: retenção, DPS, desmarcado igual a antes, Simples 98 × presumido 99, dispensa, tomador sem CNPJ e gravação via `emitir()`. Na lane `nfebrasil-pest`, que agora dispara também em mudança no service/adapter/model do NFSe. |

**Leiaute (NT 007, lida no PDF oficial em 2026-10-13):** *"Se houver valores de retenções de PIS, de COFINS e/ou de CSLL, eles deverão ser SOMADOS e informados no campo 'vRetCSLL'"*; `vPis`/`vCofins` são débito próprio e não levam retido; `tpRetPisCofins` 3 = "PIS/COFINS/CSLL Retidos" (aceito em produção desde 09/02/2026).

## 2 · Regra mestre de valor: dupla confirmação

```
caso (1.500,00 · PJ · presumido · marcado)   caminho 1 · Python Decimal    caminho 2 · DTO + SnNfseAdapter novos (PHP 8.4, CT 100)
PIS                                          9,75                          9.75
COFINS                                       45,00                         45
CSLL                                         15,00                         15
vRetCSLL na DPS                              69,75                         69.75 · tpRetPisCofins 3 · CST 01
marcado, sem retenção (Simples/dispensa)     —                             sem tribFed
desmarcado                                   —                             sem tribFed · chave de idempotência = a antiga
```

**Antes → depois em produção:**

```
antes:  nenhuma NFS-e leva retenção federal; a tela não oferece a opção
depois: nota DESMARCADA (padrão) — idêntica a antes, mesma chave de idempotência
        nota MARCADA, presumido/real, tomador CNPJ, acima da dispensa — grava PIS/COFINS/CSLL
        retidos e envia tribFed na DPS; nenhuma nota existente muda (colunas novas com default 0)
```

## 3 · Não feito, e por quê

- **A DPS ainda não chega à Sefin de verdade.** O `SnNfseAdapter` envia JSON em `/nfse`; a API Sefin espera a DPS em XML assinado. Isso é anterior a esta thread. Os campos estão com os nomes do leiaute, mas só o adapter em XML prova que a prefeitura aceita.
- **Achado, não corrigido:** `NfseEmissaoService::montarPayload` chama `pfxDecriptado()` e `senhaDecriptada()` em `NfeCertificado`, e nenhum dos dois existe no repositório. Empresa com certificado configurado quebra ao montar a nota. O teste contorna montando o payload antes de cadastrar o certificado.
- **CST 01 é suposição:** o grupo `piscofins` exige CST; 01 ("alíquota básica") é o enquadramento do prestador em presumido/real, único caso em que há retenção. Confirmar com o contador.
- **Valor líquido na tela não desconta a retenção:** o regime só o backend lê. A tela avisa que o cálculo é no envio.
- **Show / título no Financeiro:** a nota grava os valores, mas a tela de detalhe e o título a receber ainda não os mostram/descontam.
- **IRRF e INSS:** fora, como na thread 23.

## 4 · Pedido literal pro [CL]/[W]

```
1. [W] merge do PR (seção 2 é o antes→depois).
2. Corrigir pfxDecriptado()/senhaDecriptada() no certificado da NFS-e antes de qualquer emissão real.
3. Adapter SN-NFSe em XML assinado — só ele valida o grupo tribFed contra a Sefin.
4. Contador confirmar o CST 01 do grupo piscofins.
```
