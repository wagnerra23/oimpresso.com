---
id: requisitos-nfe-brasil-adr-tech-0004-emenda-tech0002-ui0001-sem-fsda-status-por-polling
---

# ADR TECH-0004 (NfeBrasil) · Emenda de TECH-0002 e UI-0001: sem FS-DA, status por polling

- **Status**: accepted (ratificada pelo [W] em 2026-11-12)
- **Data**: 2026-11-11
- **Decisores**: Wagner (ratifica)
- **Categoria**: tech
- **Emenda**: [TECH-0002](0002-contingencia-epec-fsda-retentativa-ordenada.md) · [UI-0001](../ui/0001-fluxo-emissao-1-clique-no-pos.md) — as duas ficam como estão (append-only); esta ADR registra onde o código se afastou delas
- **Relacionado**: US-NFE-002, US-NFE-006, US-NFE-071, [ADR 0058](../../../../decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md), [ADR 0062](../../../../decisions/0062-separacao-runtime-hostinger-ct100.md)
- **Origem**: auditoria NfeBrasil 2026-11-05, achado N3-14 (lote 5.4)

## Contexto

Duas ADRs aceitas em 2026-04-24 descrevem um desenho que o código não seguiu, e nenhuma emenda registrou a
mudança. Quem lê as ADRs hoje procura coisas que não existem.

**TECH-0002 (contingência).** A ADR escolhe EPEC e **FS-DA** como modos de contingência, com detecção por um
`SefazHealthCheckJob` a cada 30 s numa fila `nfe-health`. Medido em `origin/main` (`444b248e60`, 2026-11-11):

| A ADR diz | O código tem |
|---|---|
| FS-DA (`tpEmis=5`, NF-e 55) | **não existe**: `ModoContingencia` conhece `epec`, `svc-an` e `svc-rs`; nenhum caminho gera `tpEmis=5` |
| EPEC | existe: `tpEmis=4`, evento 110140 no AN (`NfeService::registrarEpec`) |
| — | SVC-AN e SVC-RS (`tpEmis` 6 e 7), que a ADR só citava na tabela, são os modos de transmissão imediata |
| off-line NFC-e | existe: `tpEmis=9` em qualquer modo, porque a NFC-e não tem SEFAZ Virtual |
| `SefazHealthCheckJob` a cada 30 s | não existe. O ping é o `nfe:health --ping-sefaz` (sob demanda) e, para empresa em contingência, o `nfe:retentar-contingencia` a cada 5 min; o `SefazStatusRecorder` grava o resultado por UF e conta falhas seguidas |
| ativação manual com sugestão | igual: `POST /nfe-brasil/contingencia/ativar`, recusa SVC que não atende a UF |
| retentativa ordenada | existe: `nfe:retentar-contingencia` a cada 5 min, um job por empresa |

**UI-0001 (emissão em 1 clique).** A ADR põe o status da nota na tela por **broadcast Echo** no canal
`business.{id}.nfe-status`. O runtime do app é o Hostinger, sem daemon (ADR 0058 e 0062), e o broadcast nunca foi
ligado. O que existe é **polling**: a tela de status chama `GET /nfe-brasil/api/transactions/{tx}/nfe-status`
a cada 2 s (`useNfceStatus`, `NfceStatus.tsx`) até um estado terminal. O resto da UI-0001 vale: emissão
assíncrona pelo listener da venda, caixa liberado sem esperar a SEFAZ.

## Decisão (proposta)

1. **FS-DA fica fora do escopo** enquanto nenhum cliente pedir. Os modos de contingência do NfeBrasil são
   SVC-AN, SVC-RS, EPEC (NF-e 55) e off-line (NFC-e 65). FS-DA exige formulário de segurança impresso; volta
   como US nova se um cliente pedir (ADR 0105).
2. **A detecção fica como está:** ping sob demanda pelo `nfe:health --ping-sefaz` e, em contingência, a cada 5 min pelo retentar, gravado por UF; a
   contingência é ativada à mão. O job de 30 s da TECH-0002 não será construído.
3. **O status da emissão é entregue por polling**, não por broadcast. Broadcast só volta a ser opção se o app
   ganhar um canal de tempo real que rode fora do Hostinger.

## Consequências

- Quem implementar contingência a partir da TECH-0002 deve ler esta ADR primeiro: FS-DA e o job de 30 s não são
  pendência, são escolha.
- O SPEC (US-NFE-006, US-NFE-071) e o SDD (R12) já dizem "FS-DA não existe" desde o lote 5 da auditoria; esta
  ADR é o registro da decisão por trás disso.
- Custo do polling: uma requisição a cada 2 s por tela de status aberta, com teto de 30 consultas no cliente
  (`useNfceStatus`, `maxPolls = 30`).

## Ratificação

Esta ADR nasce `proposto`. O [W] ratifica trocando a linha de status em PR próprio. Até lá, as ADRs TECH-0002
e UI-0001 seguem `accepted` e esta emenda é a leitura proposta do que o código já faz.
