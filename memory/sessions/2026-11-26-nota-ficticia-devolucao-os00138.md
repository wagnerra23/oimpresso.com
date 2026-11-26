---
date: "2026-11-26"
topic: "NF-e fictícia na venda OS00138 (biz=1) para o Luiz testar a devolução com nota — dado de teste em produção, a remover quando ele terminar"
authors: ["W", "C"]
outcomes:
  - "Venda OS00138 (transaction 87123, biz=1) ligada a uma NF-e fictícia autorizada: série 900, nº 1, nfe_emissoes.id 1873"
  - "XML fictício em nfe-brasil/1/notas/900-1.xml (disco nfe_fiscal), marcado como homologação e com aviso em infCpl"
  - "Tentativa rejeitada nº 12 (nfe_emissoes.id 1871) desligada da venda para liberar a trava de uma emissão por venda"
  - "A remover quando o Luiz avisar que terminou os testes"
---

# NF-e fictícia na OS00138 para testar a devolução com nota

## Por que existe

O Luiz precisava de uma venda com NF-e autorizada e XML guardado para testar a tela
`/sell-return/create` no caminho "Com NF-e". A empresa WR2 Sistemas (biz=1) não está
habilitada na SEF/SC para emitir NF-e em homologação: as tentativas reais da OS00138
voltaram com rejeição 781 ("Emissor não habilitado"). [W] autorizou montar uma nota
fictícia e ligá-la à venda.

**É dado de teste em produção. Não existe na SEFAZ.** [W] combinou que o Luiz avisa
quando terminar, e aí a nota é removida.

## O que foi criado (2026-11-26)

| Item | Valor |
|---|---|
| Venda | OS00138 · `transactions.id` 87123 · biz=1 |
| Emissão fictícia | `nfe_emissoes.id` **1873** · modelo 55 · série **900** · nº **1** · status `autorizada` · cstat 100 |
| Chave | `42261136613150000118559000000000011933282650` |
| XML | `nfe-brasil/1/notas/900-1.xml` no disco `nfe_fiscal` (`ArquivoFiscal`) |
| Metadata | `{"ficticia": true, "remover_apos_teste": true, "desvinculou_emissao": 1871, ...}` |

Como o XML foi montado: a partir do XML assinado que o próprio sistema gerou na
tentativa nº 12 (`nfe-brasil/1/notas/1-12.xml`). Troquei série e número, recalculei a
chave e o dígito, tirei a assinatura, pus o aviso "NOTA FICTICIA - teste de devolucao"
em `infCpl` e embrulhei num `nfeProc` com `protNFe` fictício (`tpAmb` 2, `nProt` zerado).
A série 900 não usa o contador da série padrão (`business.ultimo_numero_nfe` não mudou).

**Ajuste necessário:** `nfe_emissoes` tem a trava única `(business_id, transaction_id)`.
A tentativa rejeitada nº 12 (`id` 1871) foi desligada da venda do mesmo jeito que o
`NfeService` faz numa nova tentativa: `transaction_id` = null e
`metadata.original_transaction_id` = 87123. Ela continua `rejeitada`.

A gravação foi direta no banco (script avulso via SSH, apagado depois), sem seeder nem
comando versionado. Este registro existe por isso.

## Verificado

- `/sell-return/create?nota=nfe` lista a OS00138 com "NF-e 1".
- `DevolucaoService::montarDevolucao` e `quantidadesPorItem` leem o XML: 1 item, chave confere.
- **O que não funciona, por construção:** "Emitir NF-e de devolução" é rejeitado pela SEFAZ
  (empresa não habilitada e chave de origem inexistente lá).

## Efeitos colaterais enquanto a nota existir

- Pedir a NF-e da OS00138 não envia nada à SEFAZ: o sistema vê a venda já com nota autorizada.
- biz=1 não tem contador cadastrado (`nfe_contadores`), então `nfe:enviar-xml-contador` não manda o XML fictício.

## Como remover (quando o Luiz avisar)

Dentro de uma transação, em biz=1:

1. Antes de apagar, confira se o teste do Luiz criou uma NF-e de devolução referenciando
   a nota fictícia (`nfe_emissoes` com `metadata.devolucao` apontando para 1873) e trate-a junto.
2. Apague a emissão fictícia: `DELETE FROM nfe_emissoes WHERE id = 1873 AND business_id = 1 AND serie = '900' AND numero = 1`
   (é fictícia; a regra de não apagar vale para nota que existe na SEFAZ).
3. Religue a tentativa rejeitada: `UPDATE nfe_emissoes SET transaction_id = 87123 WHERE id = 1871 AND business_id = 1 AND transaction_id IS NULL`.
4. Apague o arquivo `nfe-brasil/1/notas/900-1.xml` do disco `nfe_fiscal`.
5. Confira em `/sell-return/create?nota=sem` que a OS00138 voltou para "Sem nota".

As devoluções de venda que o Luiz registrar na OS00138 durante o teste são dele: decidir
com ele se ficam ou saem.
