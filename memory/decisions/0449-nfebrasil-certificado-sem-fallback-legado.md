---
slug: 0449-nfebrasil-certificado-sem-fallback-legado
number: 449
title: "NfeBrasil: o certificado A1 vem só de nfe_certificados — sai o fallback para business.certificado (Fase 4 parcial da ADR 0090)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-11-10"
module: nfebrasil
tags: [nfebrasil, certificado, seguranca, lgpd, legado]
supersedes: []
supersedes_partially:
  - 0090-nfe-replace-gradual-app-services
superseded_by: []
related:
  - 0090-nfe-replace-gradual-app-services
  - 0186-chain-certificado-sefaz-consulta-cadastro
  - 0093-multi-tenant-isolation-tier-0
pii: false
review_triggers:
  - "uma empresa sem nfe_certificados precisar emitir e só tiver o certificado legado legível"
  - "decidir apagar as colunas business.certificado e business.senha_certificado (resto da Fase 4)"
---

# ADR 0449 — O certificado A1 vem só de `nfe_certificados`

> **Status: `proposto`.** O merge do PR que traz esta ADR é a decisão do [W]. Ela fecha uma parte
> da Fase 4 que a ADR 0090 deixou condicionada a uma ADR própria ("Após 3 meses, Fase 4 remove
> legado sem quebrar nada — precisa ADR de Fase 4 pra confirmar").

## Contexto

A ADR 0090 (2026-05-06) pôs um fallback no `CertificadoService::carregarParaSefaz`: sem certificado
em `nfe_certificados`, a emissão lia `business.certificado` (o `.pfx` em claro, em BLOB) e
`business.senha_certificado` (a senha em base64, que é reversível, não cifra). Era para a coexistência
com a pilha antiga, por cerca de 3 meses. Passaram 6.

A auditoria do NfeBrasil (2026-11-10, achado N5-08) marcou o fallback como risco: o segredo fica em
claro numa tabela que vários módulos leem, e o comando de migração gravava no disco errado (este
ponto já foi consertado no #10692).

**Medido em produção em 2026-11-10, só leitura, contagens sem identificar empresa:**

| | |
|---|---|
| empresas no `business` | 100 |
| com `certificado` e `senha_certificado` preenchidos | 40 |
| dessas, sem certificado ativo em `nfe_certificados` (dependem do fallback) | 39 |
| certificados legados que o `NFePHP\Common\Certificate::readPfx` consegue abrir com a senha guardada | 1 (vencido) |
| ilegíveis | 39 — 36 estão gravados como texto base64 em vez de binário, 3 usam algoritmo que o OpenSSL 3.5 do servidor recusa sem o provider legado |
| linhas `FALLBACK_LEGACY usado` em `storage/logs/*.log` | 0 |

Ou seja: o fallback não dá certificado utilizável a nenhuma empresa hoje. Quando dispara, a emissão
falha mais adiante com "Impossível ler o certificado", em vez de pedir o A1.

## Decisão

1. `carregarParaSefaz` não cai mais em `business.certificado`. Sem certificado ativo em
   `nfe_certificados`, recusa e manda enviar o A1 na tela do certificado.
2. Na cadeia da consulta de cadastro (ADR 0186), a camada 2 (legado) sai; ficam a 1 (certificado da
   empresa) e a 3 (institucional).
3. `lerCertLegado` e o comando `nfe:migrate-cert-business` ficam, só como leitores da linha antiga.

## Fora desta decisão (o resto da Fase 4)

- Apagar ou zerar `business.certificado` e `business.senha_certificado`. É escrita em produção e a
  ADR 0090 pede backup do BLOB antes. Fica para decisão do [W].
- A pilha antiga `app/Services/*NFe*` não foi medida aqui; esta ADR não muda o que ela lê.

## Consequências

- Empresa que um dia emitiu pelo legado e não enviou o A1 pela tela nova recebe a mensagem certa
  ("envie o certificado") em vez de um erro de leitura de pfx.
- O segredo em claro deixa de ser lido por qualquer caminho de emissão do módulo.
- Se aparecer uma empresa com certificado legado legível e sem A1 novo, o caminho é o
  `nfe:migrate-cert-business` (com o conserto de disco do #10692), não a volta do fallback.
