---
proposal_id: emenda-0101-config-cliente-homologacao
status: proposed
created: "2026-06-24"
proposed_by: claude-code
decided_by: wagner
decided_at:
parent_adr: 0101 (Tests SEMPRE business_id=1)
related_adrs: [0093, 0101]
type: emenda-governanca-tests
---

# Proposta · Emenda à ADR 0101 — config de cliente em homologação (read-only) permitida; biz=1 segue default

> **Status:** 🟡 **PROPOSED 2026-06-24** — aguarda decisão do Wagner.
> Origem: durante a revisão do PR [#3331](https://github.com/wagnerra23/oimpresso.com/pull/3331) (cleanup `biz=7 → biz=1` no `EmitirNfceAoFinalizarVendaTest`), Wagner pediu, textual no chat:
>
> > *"teste de nota pode usar meu certificado empresa 1, em homologação. Ok se for em homologação pode fazer no cliente tbm para pegar as configurações do cliente. preferencia no meu."*
>
> Isso refina a [ADR 0101](../0101-tests-business-id-1-nunca-cliente.md), que hoje é mais estrita.

## Contexto

A [ADR 0101](../0101-tests-business-id-1-nunca-cliente.md) (aceita 2026-05-07, canônica) decide: **em qualquer test/fixture/smoke/exemplo, `business_id` default = 1 (Wagner); cross-tenant adversário = 99; nunca cliente real.** Ela cita Wagner textual: *"emitir na minha empresa 1 sempre, isso é um erro padrão grave prioridade não pode no cliente"* e lista como **risco #1**: *"smoke rápido… poderia disparar emissão fiscal real (mesmo em homologação) usando o cert da Larissa"*. A cláusula 4 manda: *"Smoke real homologação SEFAZ: sempre na biz=1"*.

Enforcement atual: **`tests/Unit/BusinessIdGuardTest.php`** varre `tests/` + `Modules/*/Tests/` e **só bloqueia hardcode `business_id=4`** (7 regex, todas mirando o `4`). biz=5/7 não são pegos pelo guard hoje — a ADR os proíbe na prosa ("4 vs 5/7 cliente vs cliente"), mas o guard mecânico não enforça.

**A tensão:** o pedido novo do Wagner afrouxa a cláusula 4 + o risco #1 — ele agora aceita usar a config de um cliente real **em homologação** para capturar a tributação real do cliente, mantendo a **preferência na empresa 1**. Como 0101 é canônica e tem guard de CI, a mudança precisa de emenda formal (append-only, ADR nova) antes de virar prática — não pode ser decidida só por edição de teste.

> **Nota importante:** os 7 testes do lane MySQL fiscal NfeBrasil (`nfebrasil-pest.yml`) **não emitem nota** — são `Queue::fake()` + transação `forceFill`, sem cert, sem SEFAZ. Eles não precisam de cert nem de config de cliente; `biz=1` cobre 100% e **nada muda neles** com esta emenda. O carve-out vale só pro fluxo de **smoke contra SEFAZ homologação** (o `runbook_smoke_sefaz_biz1.md`), que é outra categoria.

## Proposta (carve-out)

Manter a ADR 0101 intacta no essencial e **adicionar uma exceção estreita**:

1. **Inalterado (continua valendo):**
   - Default `business_id = 1` (Wagner) em **todo** test/fixture/exemplo/CI.
   - Cross-tenant adversário em testes = **`99`** (fictício).
   - `BusinessIdGuardTest` (anti-hardcode `business_id=4` em `tests/` e `Modules/*/Tests/`) **permanece** ligado.
   - **Proibido emitir** documento fiscal contra SEFAZ **produção** com cert/dados de cliente — sempre biz=1.

2. **Nova exceção (somente homologação):**
   - Em **smoke/validação contra SEFAZ homologação** (jamais produção), é permitido usar o `business_id` de um **cliente real** com a finalidade de **ler a configuração fiscal dele** (regime, tributação default, CFOP/CSOSN/CST, CSC de homologação) — quando o objetivo for validar a emissão contra o setup real do cliente.
   - **Preferência permanece em biz=1** (Wagner): o cliente é fallback/complemento, não o default.
   - PII do cliente continua protegida: nada de CPF/CNPJ/nome real vazando em log/PR/commit (PII scan + `PiiRedactor` seguem valendo).

3. **Limite mecânico (se a exceção virar código):**
   - Se um smoke de homologação for materializado como teste Pest tocando `business_id` de cliente, ele deve carregar marcador explícito (ex.: `// homologacao-only — ler config cliente, ADR <nova>`), e o `BusinessIdGuardTest` ganha uma allowance **estreita** só pra esse caso anotado (continua bloqueando `=4` em qualquer outro lugar). Enquanto a exceção for só runbook/comando manual (fora de `tests/`), o guard não precisa mudar.

## O que muda / o que não muda

| Aspecto | Hoje (0101) | Com a emenda |
|---|---|---|
| Default em CI/fixtures | biz=1 | **biz=1** (igual) |
| Cross-tenant adversário | biz=99 | **biz=99** (igual) |
| Guard anti-`biz=4` em tests | ligado | **ligado** (igual) |
| Emissão em produção | só biz=1 | **só biz=1** (igual) |
| Ler config de cliente em **homologação** | proibido (cláusula 4 / risco #1) | **permitido**, preferência biz=1 |

## Riscos + mitigações

- **Risco:** alguém copiar o pattern "cliente" pra um smoke de **produção** → emissão real indevida. **Mitigação:** o carve-out é literal "homologação only"; marcador obrigatório; emissão prod segue biz=1-only; runbook deixa o ambiente=2 (homologação) explícito.
- **Risco:** PII de cliente vazar em CI/log. **Mitigação:** PII scan + PiiRedactor inalterados; o carve-out é leitura de **config fiscal**, não dump de dados pessoais.
- **Risco:** guard afrouxar demais. **Mitigação:** allowance estreita por marcador anotado; `=4` segue bloqueado fora do caso anotado.

## Decisão pendente (Wagner)

Se aprovado, os follow-ups (nesta ordem):
1. **Ratificar** esta proposta para uma ADR **numerada** canônica (`amends/related: 0101`), com número alocado via `next-id.mjs` (ADR 0304) no momento da ratificação — registrando `accepted_via` com as palavras textuais do Wagner.
2. Ajustar `tests/Unit/BusinessIdGuardTest.php` para a allowance estreita (homologacao-only anotado), **se e quando** a exceção virar teste Pest.
3. Atualizar skill `multi-tenant-patterns` + `runbook_smoke_sefaz_biz1.md` com a regra refinada.

Nada disso bloqueia o trabalho atual: o ratchet do lane NfeBrasil (7/7 verde) já está em `main` e independe desta emenda.

## Refs

- [ADR 0101](../0101-tests-business-id-1-nunca-cliente.md) — Tests SEMPRE business_id=1 (regra que esta proposta emenda)
- [ADR 0093](../0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0304](../0304-alocacao-numero-ciente-trabalho-em-voo.md) — alocação de número na ratificação
- `tests/Unit/BusinessIdGuardTest.php` — guard anti-`business_id=4`
- PRs do ratchet NfeBrasil: #3321, #3323, #3326, #3327, #3328, #3331
- `memory/sessions/2026-06-23-nfebrasil-mysql-lane-achados.md` — diagnóstico das 12 falhas (test-debt)
