---
slug: 0444-mdfe-cte-entram-por-sped-mdfe-e-sped-cte-no-nfebrasil
number: 444
title: "MDF-e e CT-e entram pelas bibliotecas sped-mdfe e sped-cte, dentro do Modules/NfeBrasil"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-27"
module: nfebrasil
quarter: 2026-Q4
tags: [fiscal, mdfe, cte, dependencia]
related_adrs: [0321-pin-sped-nfe-dev-master-ibs-cbs, 0093-multi-tenant-isolation-tier-0, 0438-artefato-substitui-cowork-como-superficie-do-prototipo]
supersedes: []
pii: false
review_triggers:
  - "o portal SEFAZ publicar MOC CT-e ou MOC MDF-e novo que as versões fixadas não cobrem"
  - "o [W] entregar o certificado A1 de produção (libera a trava de ambiente)"
---

# ADR 0444 · MDF-e e CT-e entram pelas bibliotecas sped-mdfe e sped-cte, dentro do Modules/NfeBrasil

## Contexto

A US-FISCAL-029 (MDF-e e CT-e) e a US-COMVIS-016 (entrega da gráfica) estão `_pendente_`. Medido em `origin/main`
no dia 2026-10-27:

- **CT-e:** existe código de 2020 herdado do fork: `app/Cte.php`, `app/ComponenteCte.php`, `app/MedidaCte.php`,
  `app/Http/Controllers/CteController.php`, `app/Services/CTeService.php` e as tabelas `ctes`,
  `componente_ctes` e `medida_ctes`. Ele está morto. Nenhuma rota chega ao controller, e o `CTeService` importa
  `NFePHP\CTe\*`, mas `nfephp-org/sped-cte` não está no `composer.lock`, então a classe nem carrega. Ele também
  tira o `business_id` da sessão (não funciona em fila) e lê o certificado de `business.certificado`, fora do
  `NfeCertificado` que o módulo novo usa.
- **MDF-e:** não há código. A tabela `manifestos` (2020) não é MDF-e: guarda a manifestação do destinatário da
  NF-e de entrada (`nsu`, `num_prot`, `tipo`).
- O `composer.lock` tem só `sped-common` v5.1.17, `sped-da` v1.1.6, `sped-gtin` v1.1.2 e `sped-nfe` dev-master.

As duas bibliotecas da mesma família, conferidas no Packagist em 2026-10-27:

| Pacote | Última versão | Exige |
|---|---|---|
| `nfephp-org/sped-cte` | v5.0.1 (2025-06-06) | `sped-common ^5.0`, `justinrainbow/json-schema ^5.2` |
| `nfephp-org/sped-mdfe` | V4.2.0 (2025-05-08) | `sped-common ^5.0`, `justinrainbow/json-schema ^5.2` |

O lock já tem `sped-common` 5.1.17 e `json-schema` 5.3.4, então as duas restrições batem no papel. O
`composer require` vai confirmar isso no CI, que é onde roda.

## Decisão

1. **Dependências:** `nfephp-org/sped-cte` `^5.0.1` e `nfephp-org/sped-mdfe` `^4.2.0`, com **versão marcada**.
   Não usamos `dev-master` (é o contrário da [ADR 0321](0321-pin-sped-nfe-dev-master-ibs-cbs.md): lá era preciso
   o branch por causa do IBS/CBS; aqui as tags cobrem o leiaute vigente).
2. **Lugar:** o código fica em `Modules/NfeBrasil`, que já é dono do certificado (`NfeCertificado`,
   `CertificadoService`), do ambiente por empresa e do pipeline de emissão. As tabelas são novas e próprias, cada
   uma com `business_id` indexado e FK ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)). As telas entram como
   aba **"MDF-e · CT-e"** do módulo Fiscal (`resources/js/Pages/Fiscal/`), ao lado de "NF-e · NFC-e" e "NFS-e".
3. **O legado não é reaproveitado.** `app/Cte*`, `CTeService` e `CteController` saem num PR próprio, depois que o
   código novo emitir em homologação. As tabelas `ctes`/`componente_ctes`/`medida_ctes` só caem depois de contar
   linhas em produção.
4. **Só homologação.** Tudo nasce atrás de `nfebrasil.transporte.enabled` (padrão `false`), e o ambiente fica
   forçado em homologação (`tpAmb=2`) enquanto `nfebrasil.transporte.producao_liberada` for `false`. Liberar
   produção depende do certificado A1 do [W] e é ato dele.
5. **Recorte de entrada:**
   - **MDF-e de carga própria** (emitente não transportador, modal rodoviário), montado a partir das NF-e
     autorizadas de uma ou mais vendas com entrega fora do município. Inclui encerramento, cancelamento e
     inclusão de condutor. É o caso da gráfica (US-COMVIS-016). A regra de quando o MDF-e é obrigatório varia por
     UF; ela fica configurável por empresa, não fixa no código.
   - **CT-e emitido** (modelo 57, rodoviário) para a empresa que presta serviço de transporte.
   - O **CT-e tomado** (frete de compra) não é emissão: chega por XML de entrada e fica com a DF-e. É dele que o
     bloco D100/D190 do SPED se alimenta no caso comum.
6. **Leiaute:** os testes citam a versão do Manual de Orientação do Contribuinte que provam: MOC CT-e 4.00 e
   MOC MDF-e 3.00a. A fatia do backend confere as duas no portal oficial antes do primeiro teste. Se a versão
   publicada for outra, este item recebe emenda.

## Justificativa

- A mesma família de bibliotecas que já roda a NF-e evita uma segunda forma de assinar, montar e transmitir XML.
  O certificado e o SOAP saem do `sped-common` que já está em produção.
- Escrever o XML na mão, ou contratar uma API de terceiro, traria uma tecnologia nova e uma segunda fonte de
  verdade do certificado.
- O legado tem três defeitos estruturais (sessão no lugar de `business_id`, certificado fora do lugar, classe sem
  biblioteca). Consertá-lo custa mais que escrever no módulo certo.

## Consequências

- O `composer.json` ganha dois pacotes, no PR do backend, não neste.
- O SPED (US-FISCAL-028) tem onde ler o CT-e emitido. A sessão do SPED recebe o nome das colunas quando a tabela
  existir.
- A aba nova precisa de protótipo em `prototipo-ui/cowork/Wagner/` antes do `.tsx`
  ([ADR 0438](0438-artefato-substitui-cowork-como-superficie-do-prototipo.md)). Ele entra junto com esta ADR.
- Enquanto a flag está desligada, nada muda para nenhuma empresa.

## Rollback

Remover os dois pacotes e as tabelas novas. Nenhum dado de outro módulo depende deles enquanto a flag estiver
desligada.

## Referências

- US-FISCAL-029 em `memory/requisitos/Fiscal/SPEC.md` · US-COMVIS-016 em `memory/requisitos/ComunicacaoVisual/SPEC.md`
- Protótipo: `prototipo-ui/cowork/Wagner/fiscal-transporte.html`
