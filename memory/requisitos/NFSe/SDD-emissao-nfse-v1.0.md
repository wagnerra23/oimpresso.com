---
id: requisitos-nfse-sdd-emissao-nfse-v1-0
slug: nfse-sdd
title: "SDD — Emissão de NFS-e pelo Sistema Nacional (domínio NFSe)"
type: sdd
module: NFSe
status: ativo
owner: W
version: 1.0.0
last_updated: "2026-11-17"
related_docs:
  - SPEC.md
  - SCOPE.md
  - BRIEFING.md
  - RUNBOOK-operacao.md
  - RUNBOOK-emitir.md
  - RUNBOOK-index.md
  - RUNBOOK-show.md
  - CAPTERRA-FICHA.md
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0351-sdd-from-source
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
---

# SDD — Emissão de NFS-e pelo Sistema Nacional (domínio NFSe)

> **Escopo:** o ciclo inteiro da NFS-e no `Modules/NFSe` — da venda (ou do formulário) até a
> nota autorizada, o DANFSe e o cancelamento —, com o ambiente (homologação × produção) e o
> certificado A1 resolvidos **por empresa**. Não substitui o [SPEC.md](SPEC.md) (as US), os
> charters das 3 telas (`resources/js/Pages/Nfse/*.charter.md`) nem os `*.casos.md`: é o mapa
> que amarra os três. Formato: [SDD-TEMPLATE](../_DesignSystem/SDD-TEMPLATE.md).
>
> **Como foi feito:** lido no código de `origin/main` em 2026-11-17, depois dos lotes 0 e 2 da
> auditoria do módulo (#10964, #10972, #10987, #11025, #11032, #11069). Referências ao código são
> por **símbolo** (`Classe::metodo`), não por linha, para não apodrecer no primeiro refactor.
> Não há legado Blade nem Delphi para este domínio: o módulo nasceu em React em 2026-05.
>
> **Fronteira com o NfeBrasil:** a decisão [W] de 2026-10-13 de consolidar a NFS-e no NfeBrasil
> (D-NFSE-CONSOLIDA) **não tem ADR**. Este SDD descreve o que roda hoje — a emissão sai pelo
> `Modules/NFSe` usando peças do NfeBrasil — e não escolhe o dono final. Ver [SCOPE.md](SCOPE.md).

---

## 0. Base empírica 🖐 curado (foto de 2026-11-17)

| Fonte | O que diz | Medido |
|---|---|---|
| Lane `nfse-pest` no `main` | 166 passed · 1 skipped · 567 assertions | run 44021885742, head `e0f425f425` |
| Produção (Hostinger) | `nfse_emissoes` **vazia**; 1 config (empresa 1, homologação, certificado vinculado) | leitura em 2026-11-17 |
| Produção — `nfse:health --json` | 5 OK, **1 FAIL**: "certificado vencido (business 1)" | 2026-11-17 08:38 BRT |
| Produção — fila | `QUEUE_CONNECTION=database`; 0 jobs e 0 falhos na fila `nfse`; nenhum `queue:work` agendado lê `nfse` | `schedule:list` + tabela `jobs` |
| [CAPTERRA-FICHA](CAPTERRA-FICHA.md) | nota de capacidade e gaps de mercado | foto de 2026-07 |

**Conclusão da foto:** nenhuma NFS-e real foi emitida até hoje. O caminho de código está testado
em CI; o caminho de produção tem três bloqueios, todos em §9: certificado vencido, fila sem
consumidor e DANFSe inexistente.

---

## 1. Visão geral ⚙️ derivado

O módulo emite, consulta e cancela NFS-e no **Sistema Nacional** (Sefin Nacional, API do Emissor
Público Nacional), sem provedor terceiro. A DPS vai em XML assinado (leiaute v1.01), comprimido e
em Base64; a conexão é mTLS com o mesmo certificado A1 que assina.

**Entradas da emissão:**

| Entrada | Rota | Quem chama |
|---|---|---|
| Formulário | `POST /nfse/emitir` → `NfseController@store` | tela `Nfse/Emitir` |
| Venda | `POST /nfse/transactions/{tx}/emitir` → `NfseController@emitirParaTransaction` | `Sells/_components/VdNfseEmitModal.tsx` |
| Venda recorrente | observer `TransactionNfseObserver::created` | geração de fatura recorrente (cria só **rascunho**) |

**Telas:** `/nfse` (lista), `/nfse/emitir` (formulário), `/nfse/{id}` (detalhe e cancelamento).
A leitura fiscal consolidada (`/fiscal/nfse`) é do `Modules/Fiscal`.

---

## 2. Público-alvo e personas 🖐 curado

- **Prestador de serviço da empresa** (gráfica, oficina, software) que fatura serviço e precisa da
  NFS-e junto da venda.
- **Contador/financeiro** que confere retenções e cancela nota errada.
- **Hoje, na prática:** só a empresa 1 tem config, em homologação. Nenhum cliente emite.

---

## 3. Governança aplicável ⚙️ derivado

| Regra | Onde morde neste domínio |
|---|---|
| Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) | config, certificado, nota e venda sempre da empresa da sessão (web) ou do `business_id` do payload (fila) |
| Regra mestre de valor ([proibicoes](../../proibicoes.md)) | valor dos serviços, ISS, retenções federais, parte serviço da venda separada |
| Doutrina de teste ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) | testes no tenant 98, adversário 99; nunca empresa real |
| PII (LGPD) | mensagens da Sefin e motivo de cancelamento passam pelo `PiiRedactor` antes de gravar/logar |
| Nota fiscal não se apaga | cancelada fica no banco; ver CU-NFSE-08 |

---

## 4. Design system aplicável ⚙️ derivado

As 3 telas seguem os charters e os RUNBOOKs de tela: [RUNBOOK-emitir](RUNBOOK-emitir.md),
[RUNBOOK-index](RUNBOOK-index.md), [RUNBOOK-show](RUNBOOK-show.md). Este SDD não repete regra de
tela.

---

## 5. Arquitetura ⚙️ derivado

### 5.1 Visão em camadas

```
Formulário/venda ─► NfseController (@store | @emitirParaTransaction)
                      └─► NfseEmissaoService::montarPayload      (config + ambiente + retenções + cTribNac)
                      └─► NfseEmissaoService::despacharEmissaoAsync ─► EmitirNfseJob (fila `nfse`, SEM certificado)
                                                                         └─► NfseEmissaoService::emitir
                                                                               ├─ idempotência (linha única por chave)
                                                                               ├─ config + certificado da empresa
                                                                               └─► SnNfseAdapter::emitir ─► Sefin (mTLS)
Detalhe ─► NfseController@cancelar ─► NfseEmissaoService::cancelar ─► SnNfseAdapter::cancelarNoAmbiente ─► Sefin
Detalhe ─► NfseController@pdf ─► redirect para pdf_url (404 sem pdf_url)
```

Peças do NfeBrasil usadas na emissão: `DpsXml` (serializa, assina, empacota),
`CertificadoService` (lê o PFX e a senha), `ListaServicoNacional` (cTribNac do Anexo B),
`RetencoesNfseService` (PIS/COFINS/CSLL), `SeparacaoNotasDaVenda` (parte serviço da venda).

### 5.2 Modelo de dados (núcleo)

| Tabela | Papel | `business_id` |
|---|---|---|
| `nfse_provider_configs` | 1 por empresa: CNPJ/IM do prestador, município, série, CNAE, LC 116 e alíquota padrão, **`ambiente`**, **`cert_id`** | coluna própria; escopo `NfseBusinessScope` |
| `nfse_emissoes` | a nota: status, `rps_numero` (nº da DPS), `idempotency_key` (UNIQUE), número, chave (`provider_protocolo`), `pdf_url`, `xml_retorno`, valores, retenções, `transaction_id` | coluna própria; escopo `NfseBusinessScope`; SoftDeletes |
| `nfe_certificados` | certificado A1 cifrado (dono: NfeBrasil) | coluna própria |

`nfse_emissoes` é **compartilhada** com o NfeBrasil, que tem model próprio e vocabulário de
status diferente ([SCOPE.md](SCOPE.md)).

**Status da nota** (`Modules\NFSe\Models\NfseEmissao`): `rascunho` → `processando` → `emitida` →
`cancelada`; ou `erro`. `isPendente()` = `rascunho` ou `processando`.

### 5.3 Fluxos críticos

**F1 — Emissão pelo formulário.** `StoreNfseRequest` valida os campos e exige que
`transaction_id`, quando vier, seja venda **da mesma empresa** (lote 0). `montarPayload` lê a
config da empresa (ignorando config apagada), resolve o ambiente **da config** (sem config:
`homologacao`), calcula retenções só quando o serviço foi marcado como sujeito, e resolve o
cTribNac. O job vai para a fila `nfse` **sem** PFX nem senha (lote 2.3). A tela responde
"enviada para processamento".

**F2 — Emissão a partir da venda.** `emitirParaTransaction` exige `nfse.emit`, procura a venda
com `type = sell` **na empresa da sessão**, exige config, monta o tomador pelo contato da venda e
usa os padrões da config (LC 116, alíquota). Com `separada`, emite só a **parte serviço**
(`SeparacaoNotasDaVenda`) e recusa: natureza fiscal pendente (422), venda sem serviço (422),
serviços com códigos LC 116 diferentes (422), NFS-e já emitida ou em curso para a venda (409).
Responde 202.

**F3 — Envio (dentro do job).** `NfseEmissaoService::emitir`:
1. Procura linha pela `idempotency_key` (sha256 de empresa + documento do tomador + valor +
   descrição + competência, mais a marcação de retenção). Emitida → devolve. Pendente → devolve,
   salvo na retentativa do próprio job. `erro` → reaproveita **a mesma linha**.
2. Sem linha: cria em `processando`. O nº da DPS passa a ser o `id` da linha.
3. Lê config e valida o certificado **depois** de gravar a linha: sem eles a nota fica `erro`
   com o motivo e aparece em `/nfse` (antes sumia).
4. Carrega o certificado pelo `business_id` (o job chegou sem ele).
5. `SnNfseAdapter::emitir`: endpoint e `tpAmb` do **ambiente do payload**; assina; POST `/nfse`.
6. Sucesso → `emitida` com número (lido do `nNFSe` do XML de retorno) e chave de acesso.

**F4 — Falhas no envio.**

| Situação | Comportamento | Estado final |
|---|---|---|
| Sem conexão / timeout | 3 tentativas no serviço com espera 1s, 2s, 4s; depois o job retenta (3×, 60s) com o **mesmo nº de DPS** | `processando` com aviso (a nota pode ter sido aceita) |
| Sefin responde DPS duplicada | com chave: consulta e grava número real; sem chave: fica `processando` com aviso | `emitida` ou `processando` |
| Certificado ausente, vencido, senha errada | não chama a Sefin | `erro`; job falha sem retentar |
| Rejeição (4xx/5xx com corpo de erro) | `parseErro` traduz o código; erro terminal | `erro`; job falha sem retentar |
| Config ausente ou apagada | não chama a Sefin | `erro` com "Configuração NFSe não encontrada" |

Atenção: **resposta 5xx da Sefin é tratada como rejeição** (erro terminal), não como
indisponibilidade — só falha de conexão retenta. Ver [RUNBOOK-operacao](RUNBOOK-operacao.md) §2.

**F5 — Cancelamento.** `NfseEmissaoService::cancelar` só aceita nota `emitida` com número; lê
ambiente e certificado **da empresa da nota** (lote 0); chama `cancelarNoAmbiente`, que faz
`DELETE /nfse/{numero}` com o motivo. Só grava `cancelada` se a Sefin confirmar; senão a nota
continua emitida e a tela mostra o erro. A troca para `cancelada` estorna a retenção no
Financeiro (`NfseEmissaoRetencaoObserver`). O motivo é logado redigido.

> Este `DELETE` **não é** o evento de cancelamento assinado do padrão nacional. O formato oficial
> do evento não está no repo e a forma é decisão [W] (lote 2.4). O NfeBrasil tem um caminho
> separado (`NfseCancelService` + `CancelarNfseJob`, tabela `nfse_eventos_cancelamento`).

**F6 — DANFSe.** O adapter grava `pdf_url = null` em toda emissão. `NfseController@pdf` dá 404
sem `pdf_url`, e o botão da tela só aparece com ele. Nenhuma nota tem DANFSe hoje (US-NFSE-016,
lote 2.1 bloqueado por falta do leiaute oficial da NT 008/2026 no repo).

**F7 — Rascunho da recorrência.** O observer cria `rascunho` (nº `RASCUNHO-<id>`) para fatura
recorrente `final` quando a empresa tem config. **Nada emite esse rascunho** (US-NFSE-007).

**F8 — Trava da venda.** `NotaDaVendaNfse::temNotaValida` diz à venda que ela tem NFS-e `emitida`
(US-SELL-072). Cancelada, erro, rascunho e processando não travam.

### 5.4 Onde os dois mundos ainda não se conversam

- **Dois models e dois cancelamentos** para `nfse_emissoes` (NFSe e NfeBrasil). Consolidar é a
  D-NFSE-CONSOLIDA, sem ADR.
- **Certificado enviado pela tela fiscal não liga na config da NFS-e.** O upload em
  `/fiscal/config` grava em `nfe_certificados`; só o comando `nfse:importar-cert` atualiza
  `nfse_provider_configs.cert_id`. Não há tela para criar a config da NFS-e (US-NFSE-014).
- **`SnNfseAdapter::consultar`** usa o ambiente global e não usa mTLS (US-NFSE-015). Só é
  chamado no tratamento de DPS duplicada.

---

## 6. Casos de uso

Estado pelo **veredito do teste**: `✅` = teste verde na lane `nfse-pest` (run 44021885742);
`🧪` = o teste existe e o `*.casos.md` da tela ainda o marca sem veredito; `⬜` = nenhum teste cita.

#### CU-NFSE-01 — Emitir NFS-e pelo formulário `[must]` `[V0]` `[T0]` 🟡
*Dado* empresa com config e certificado válido; *quando* envia o formulário; *então* a nota entra
em `processando` e aparece em `/nfse`.
1. Campos mínimos, alíquota fração 0..1, valor positivo — UC-NFSEM-01/02 🧪
2. Só quem tem `nfse.emit` — UC-NFSEM-03 🧪
3. Venda de outra empresa é recusada — UC-NFSEM-06, `NfseVendaDaPropriaEmpresaTest` ✅
4. Retenção federal só quando marcada — UC-NFSEM-04 🧪
5. Nota autorizada de verdade pela Sefin — ⬜ nunca emitida (US-NFSE-017)

#### CU-NFSE-02 — Emitir NFS-e a partir da venda `[must]` `[V0]` `[T0]` ⬜
*Dado* uma venda da empresa; *quando* aciona a emissão na venda; *então* sai a NFS-e com o tomador
da venda e o valor da venda (ou só da parte serviço, na venda separada).
1. Venda de outra empresa → 404 — ⬜ o código filtra pela empresa da sessão, mas nenhum teste
   chama esta rota (o `NfseVendaDaPropriaEmpresaTest` cobre o formulário e o detalhe)
2. Venda separada recusa natureza pendente, sem serviço, códigos diferentes e nota já em curso — ⬜

#### CU-NFSE-03 — Não emitir nota em dobro `[must]` ✅
*Dado* uma emissão que não teve resposta; *quando* o job retenta ou o usuário reenvia; *então*
existe uma linha só e o mesmo nº de DPS.
- `NfseEmissaoSemNotaEmDobroTest` (9 testes, incluindo DPS duplicada com e sem chave) ✅

#### CU-NFSE-04 — Recusar emissão sem certificado válido `[must]` `[T0]` ✅
*Dado* certificado ausente, vencido, com senha errada ou de outra empresa; *quando* emite; *então*
nada vai à Sefin e a nota fica `erro` com o motivo.
- `NfseComportamentoContratoTest` (vencido, senha errada, controle positivo, cert da 99 na 98) ✅
- Config apagada não emite — `NfseEmissaoSemNotaEmDobroTest` ("sem config…") ✅

#### CU-NFSE-05 — Certificado não viaja na fila `[must]` ✅
- `NfseCertificadoForaDaFilaTest` (job sem PFX/senha; resolve pelo `business_id`; mensagens redigidas) ✅

#### CU-NFSE-06 — Ambiente por empresa `[must]` `[T0]` 🟡
*Dado* uma empresa em produção e outra em homologação; *então* cada uma emite e cancela no seu.
1. Emissão — `AmbientePorBusinessTest` ✅
2. Cancelamento — `NfseCancelamentoHonestoTest` ("cancela no ambiente e com o certificado da empresa") ✅
3. Consulta — ⬜ usa o ambiente global (US-NFSE-015)

#### CU-NFSE-07 — Cancelar NFS-e `[must]` `[T0]` 🟡
*Dado* nota `emitida`; *quando* cancela com motivo; *então* só vira `cancelada` com confirmação da
Sefin.
1. Motivo de 15 a 255 caracteres; só `nfse.cancel` — UC-NFSD-02/03 🧪
2. Sem confirmação, continua emitida e a retenção não é estornada — `NfseCancelamentoHonestoTest` ✅
3. Nota que não está emitida não chama a Sefin — idem ✅
4. Cancelamento pelo evento assinado do padrão nacional — ⬜ (lote 2.4, decisão [W])

#### CU-NFSE-08 — Nota cancelada não some `[must]` 🟡
- Não há rota de apagar; `delete()` só marca `deleted_at` — `NfseComportamentoContratoTest` ✅
- `forceDelete()` não é barrado — `NfseCanceladaNaoSomeDoBancoTest` em **quarentena** (lote 4) 🔴

#### CU-NFSE-09 — Baixar o DANFSe `[must]` 🔴
Nenhuma nota tem DANFSe (F6). US-NFSE-016.

#### CU-NFSE-10 — Emitir o rascunho da recorrência `[should]` 🔴
O rascunho é criado e não tem como ser emitido (F7). US-NFSE-007.

#### CU-NFSE-11 — Saber a saúde do módulo `[should]` 🟡
`nfse:health` lê certificado por config e erros de 24h — `NfseHealthCommandTest` ✅. Não está
agendado: ninguém roda sozinho (US-NFSE-020).

### 6.4 Non-Goals

_Só [W] preenche._

---

## 7. Requisitos não-funcionais ⚙️ derivado

- **Observabilidade:** spans OTel `nfse.emissao` e `nfse.cancelar` com o `business_id`; log diário
  `storage/logs/nfse.log` (90 dias).
- **Tempo:** POST da emissão com `timeout(30)`; cancelamento e consulta com `timeout(15)`.
- **Fila:** `EmitirNfseJob` na fila `nfse`, 3 tentativas, 60s entre elas.

---

## 8. Estratégia de qualidade e rollout ⚙️ derivado

- Lane própria `nfse-pest` (MySQL); `.github/nfse-pest-quarantine.list` com 1 arquivo (CU-NFSE-08).
- E2E de render + axe das 3 telas: `tests/Browser/Nfse/NfseTelasRenderTest.php`.
- Rollout: produção é ligada **por empresa** em `nfse_provider_configs.ambiente`. Procedimento e
  checklist em [RUNBOOK-operacao](RUNBOOK-operacao.md) §6.

---

## 9. Riscos e dívidas conhecidas 🖐 curado (2026-11-17)

| # | Risco | Efeito | Onde |
|---|---|---|---|
| R1 | Nenhum `queue:work` agendado lê a fila `nfse` (produção usa fila `database`) | a primeira emissão em produção ficaria parada na tabela `jobs`, em `processando` para sempre | `app/Console/Kernel.php` (schedule); fora do módulo |
| R2 | Certificado da empresa 1 vencido em produção | emissão vira `erro` | `nfse:health` |
| R3 | Sem DANFSe | nota sem PDF para o tomador | US-NFSE-016 |
| R4 | Cancelamento por `DELETE`, não pelo evento oficial | pode ser recusado pela Sefin | lote 2.4 |
| R5 | 5xx da Sefin vira `erro` terminal | indisponibilidade da Sefin parece rejeição | F4 |
| R6 | `nfse:importar-cert` tem `--business=1` como padrão | importar sem `--business` grava na empresa 1 | comando |
| R7 | `forceDelete()` apaga nota fiscal | perde registro obrigatório | lote 4 |

---

## 10. Roadmap de evolução 🖐 curado

A ordem crítica do SPEC segue: **016 (DANFSe) → 017 (emissão real) → 013 (produção)**. R1 e R2 são
pré-condição da 017 e não estão no SPEC. Prioridade é decisão [W].

---

## 11. Referências ⚙️ derivado

- [SPEC.md](SPEC.md) · [SCOPE.md](SCOPE.md) · [BRIEFING.md](BRIEFING.md) · [RUNBOOK-operacao.md](RUNBOOK-operacao.md)
- Charters e casos: `resources/js/Pages/Nfse/{Index,Emitir,Show}.{charter,casos}.md`
- Código: `Modules/NFSe/Services/NfseEmissaoService.php`, `Modules/NFSe/Adapters/SnNfseAdapter.php`,
  `Modules/NFSe/Jobs/EmitirNfseJob.php`, `Modules/NFSe/Http/Controllers/NfseController.php`

---

- **v1.0.0** (2026-11-17) — Primeira versão. Auditoria NFSe, lote 5.3, depois dos lotes 0 e 2.
