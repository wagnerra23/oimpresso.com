---
id: resources-js-pages-nfse-emitir-casos
casos: Emitir NFS-e · /nfse/emitir
irmaos: Emitir.charter.md (lei) · Emitir.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
owner: wagner
related_us: [US-NFSE-009, US-NFSE-006]
last_run: "2026-10-13"
last_run_ci: "2026-10-13 — UC-NFSEM-04 verde na lane PHP / Pest (NfeBrasil · MySQL), run 38414970371 (NfseRetencoesDpsTest); UC-NFSEM-01..03 na lane PHP / Pest (Unit), NfseTelasContratoTest; UC-NFSEM-05 nasce no PR do R-NFSE-006, veredito pendente da lane NfeBrasil MySQL (ListaServicoNacionalTest)"
---

# Casos de Uso & Aceite — Emitir NFS-e (`/nfse/emitir`)

> **Âncora:** os UC derivam do [charter](Emitir.charter.md) e de
> [US-NFSE-009 / US-NFSE-006](../../../../memory/requisitos/NFSe/SPEC.md) — **nunca do `Emitir.tsx`**
> (que este PR não tocou: está no `nao_toca` da thread).

⚖️ **FORÇA DO VEREDITO — leia antes de confiar no status.** O teste que cita estes UC
([`NfseTelasContratoTest`](../../../../tests/Feature/Modules/NFSe/NfseTelasContratoTest.php)) roda na lane sqlite per-PR `PHP / Pest (Unit)` do `ci.yml`
(registrado em `.github/ci-sqlite-pest.list` neste mesmo PR — **registrar o arquivo no repo não é
a lane executá-lo**, §5 2026-08-02). É **DB-less de propósito**: a lane não migra o schema, então
nenhum caso toca tabela — isolamento Tier 0 é provado no SQL que o Eloquent monta, regra fiscal no
`Validator` com as rules reais do FormRequest, autorização no `authorize()` do FormRequest com um
usuário-dublê. Nenhum desses caminhos vira skip-as-pass. Os 10 arquivos de `Modules/NFSe/Tests/`
**não rodam em lane nenhuma** (medido 2026-09-30 por `test-lane-coverage.mjs --json`: 10 de 10
órfãos) — por isso nenhum UC daqui se apoia neles.

Tenant fictício **98** ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).
Nunca biz=4.

**Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC, veredito pendente da lane ·
⬜ não verificado · ❌ quebrou. Este PR **não executou teste** (CT 100/CI — ADR 0062).

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-NFSEM-01 | Emissão exige competência, tomador, descrição, LC 116, valor e alíquota | must | charter Goals (campos do formulário) · US-NFSE-009 | `NfseTelasContratoTest` | 🧪 |
| UC-NFSEM-02 | Alíquota de ISS é fração 0..1 e o valor dos serviços é positivo | must `[V0]` | charter Goals (alíquota ISS, valor) · `StoreNfseRequest` (*"NÃO relaxar — compliance fiscal"*) | `NfseTelasContratoTest` | 🧪 |
| UC-NFSEM-03 | Só quem tem `nfse.emit` envia a emissão | must `[T0]` | charter (*"Gate `nfse.emit`"*) · US-NFSE-006 | `NfseTelasContratoTest` | 🧪 |
| UC-NFSEM-04 | Marcar "serviço sujeito à retenção" retém PIS/COFINS/CSLL na DPS; desmarcado não muda nada | must `[V0]` `[T0]` | charter Goals (marcação art. 30, decisão [W] 2026-10-13) · R-NFSE-003 | `NfseRetencoesDpsTest` | 🧪 |
| UC-NFSEM-05 | Item LC 116 com vários desdobros nacionais exige escolher o desdobro; com um só, resolve sozinho | must | charter Goals (desdobro nacional) · R-NFSE-006 · Anexo B oficial | `ListaServicoNacionalTest` | 🧪 |

## UC-NFSEM-01 · Emissão exige os dados fiscais mínimos · `must`

- **Aceite:** Dado o formulário sem competência, nome do tomador, descrição, código LC 116, valor
  ou alíquota · Quando o operador envia · Então o campo que falta é apontado; e o formulário
  completo passa. Competência é **mês fiscal** (`Y-m`), não data cheia.
- **Teste:** `NfseTelasContratoTest` — *"UC-NFSEM-01 · emissao exige competencia, tomador…"*.
- **Status: 🧪**

## UC-NFSEM-02 · Alíquota é fração e valor é positivo · `must` `[V0]`

- **Aceite:** Dado alíquota `0.05` (5%) · Então passa; Dado `5` (percentual digitado cru) ou
  negativa · Então é recusada. Valor `0.01` passa; `0` é recusado.
- **Por que importa:** alíquota `5` aceita geraria ISS 100× maior na nota fiscal.
- **Limite honesto:** prova só a **validação** do servidor. O cálculo de ISS/líquido em tempo real
  vive no `Emitir.tsx` (fora do escopo desta thread) e **não** é coberto aqui — qualquer mudança
  nele segue a regra-mestre de valor (dupla prova + antes→depois, [W]).
- **Teste:** `NfseTelasContratoTest` — *"UC-NFSEM-02 · aliquota de ISS e fracao entre 0 e 1…"*.
- **Status: 🧪**

## UC-NFSEM-03 · Só quem tem `nfse.emit` envia · `must` `[T0]`

- **Aceite:** Dado um request sem usuário, ou com usuário que só tem `nfse.view` · Então o envio é
  recusado; com `nfse.emit` · Então é aceito. A rota `nfse.store` passa por `auth`.
- **Teste:** `NfseTelasContratoTest` — *"UC-NFSEM-03 · so quem tem a permissao nfse.emit…"*.
- **Status: 🧪**

## UC-NFSEM-04 · Retenção federal marcada vai para a DPS e para a nota · `must` `[V0]` `[T0]`

- **Aceite:** Dado prestador em lucro presumido, tomador com CNPJ e a marcação ligada · Quando
  monto o payload de um serviço de 1.500 reais · Então a nota grava PIS 9,75, COFINS 45,00 e
  CSLL 15,00, e a DPS leva `tribFed.piscofins.tpRetPisCofins = 3` e `vRetCSLL = 69,75` (soma,
  NT SE/CGNFS-e nº 007). Desmarcado · Então nenhum grupo federal e a chave de idempotência é a
  de antes. Prestador no Simples ou valor dispensado · Então nada retido e nenhum grupo. O regime
  lido é o do próprio tenant (98 Simples × 99 presumido).
- **Contrato:** Lei 10.833/2003 arts. 30–32 · NT SE/CGNFS-e nº 007 · R-NFSE-003.
- **Teste:** `NfseRetencoesDpsTest` (lane `nfebrasil-pest`).
- **Status: 🧪**

## UC-NFSEM-05 · Desdobro nacional do serviço · `must`

- **Aceite:** Dado o item 7.10 (dois desdobros na lista nacional) · Então a tela mostra o seletor
  "Desdobro nacional do serviço" com 071001 e 071002, e enviar sem escolher é recusado no campo
  `ctrib_nac`. Dado o item 1.05 (um desdobro) · Então nada a escolher e a DPS leva 010501. Item
  fora da lista (99.99) · Então recusado no campo `lc116_codigo`.
- **Contrato:** Anexo B v1.01 (aba LISTA.SERV.NAC.) · R-NFSE-006.
- **Teste:** `ListaServicoNacionalTest` (lane `nfebrasil-pest`).
- **Status: 🧪**

## Fora do contrato por enquanto

- `[BACKLOG]` pré-preenchimento a partir de venda só resolve `Transaction` do próprio business
  (`create()` filtra `business_id` + `type=sell`) — exige banco; entra quando houver lane NFSe MySQL.
- `[BACKLOG]` o job assíncrono recebe `business_id` no payload (charter Automation hooks) — idem.
- `[BACKLOG]` alerta de módulo sem configuração / certificado inválido — comportamento de cliente.
