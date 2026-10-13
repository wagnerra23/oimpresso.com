---
sessao: "23d"
titulo: cTribNac pela tabela nacional (Anexo B), com escolha do desdobro
autor: "[CL]"
data: 2026-10-13
base: empilhado sobre o #9221 (adapter em XML), que empilha #9215 e #9218
prefixo_tocado: scripts/fiscal/lista-servico-nacional.py · Modules/NfeBrasil/Resources/data/nfse/ · Modules/NfeBrasil/Services/Nfse/ListaServicoNacional.php · migração 2026_10_13_000002 · Modules/NFSe/{Adapters,DTO,Services,Http} · resources/js/Pages/Nfse/Emitir.{tsx,charter.md,casos.md} · Modules/NfeBrasil/Tests/Feature/ListaServicoNacionalTest.php · SPEC NfeBrasil · BRIEFING NFSe · este arquivo
decisao: "[W] 2026-10-13 no chat: 'faça o próximo: tabela cTribNac com desdobros nacionais'"
---
# _saida-23d · cTribNac pela tabela nacional

> Continuação da thread 23, pedida pelo [W] no chat. Sem thread numerada no `00-INDICE.md`.

**Resposta curta:** o código de tributação nacional da DPS deixa de ser "item + subitem + 01". Ele vem da **lista de serviços nacional do Anexo B oficial**: 338 códigos em 201 subitens. Nos **62 subitens que têm mais de um desdobro**, a tela Emitir NFS-e mostra um seletor e o formulário recusa enviar sem escolher. O código usado fica gravado na `nfse_emissoes`.

O "01 fixo" estava errado justamente nesses 62. O exemplo é do próprio teste do adapter: o item **7.10** de "manutenção predial" ia como `071001`, que é *"vias e logradouros públicos"*; o certo é `071002`, *"imóveis, chaminés, piscinas"*.

## 1 · Feito

| arquivo | o quê |
|---|---|
| `scripts/fiscal/lista-servico-nacional.py` | lê a aba `LISTA.SERV.NAC.` do Anexo B (`.xlsx` oficial, só biblioteca padrão), confere que cada código bate com item/subitem/desdobro e que não há duplicado, grava o JSON. |
| `Modules/NfeBrasil/Resources/data/nfse/lista-servico-nacional-v1.01.json` | a tabela gerada, com o nome e o sha256 do arquivo oficial. Rodar o gerador de novo dá o mesmo arquivo, byte a byte. |
| `Modules/NfeBrasil/Services/Nfse/ListaServicoNacional.php` | `desdobros`, `resolver` (único resolve sozinho; vários exige escolha do mesmo subitem) e `subitensComVariosDesdobros` para a tela. |
| `SnNfseAdapter` · `NfseEmissaoService` · `StoreNfseRequest` · `NfseController` | a DPS usa o resolvido; o formulário recusa item fora da lista e falta ou erro de desdobro; a tela recebe os 62 subitens. |
| migração `2026_10_13_000002` | `nfse_emissoes.ctrib_nac` (6 dígitos, nulo nas notas antigas). |
| `resources/js/Pages/Nfse/Emitir.tsx` | seletor "Desdobro nacional do serviço", só quando o item tem mais de um; zera quando o item muda. |

## 2 · Prova

Medido no PHP 8.4 do CT 100 com as classes novas:

```
total de códigos                338     subitens com vários desdobros   62
resolver('1.05')                010501  resolver('17.06')                170601
resolver('7.10', '071002')      071002
resolver('7.10')                erro: "tem 2 desdobros nacionais (071001, 071002); escolha um"
resolver('7.10', '010501')      erro: "não pertence ao item 7.10"
resolver('99.99')               erro: "não está na lista de serviços nacional"
formulário 1.05 / 7.10+071002   aceito
formulário 7.10 sem escolha     recusado em ctrib_nac
formulário 7.10 + 010501        recusado em ctrib_nac
formulário 99.99                recusado em lc116_codigo
```

Teste: `ListaServicoNacionalTest` (R-NFSE-006, UC-NFSEM-05) na lane `nfebrasil-pest`. O `NfseDpsXmlTest` e o `NfseRetencoesDpsTest` passaram a usar `071002` para a manutenção predial.

## 3 · Não feito, e por quê

- **cNBS** (código NBS, opcional na DPS) não é enviado; o Anexo B traz a tabela, mas nenhuma regra pede ainda.
- **Notas anteriores** ficam com `ctrib_nac` nulo: não reconstruo o desdobro de nota já montada.
- **Envio real à Sefin** segue dependendo do certificado A1 em homologação (_saida-23c).
- **Tela não renderizada por mim:** worktree sem `node_modules`; o print é do protocolo pós-merge.

## 4 · Pedido literal pro [CL]/[W]

```
1. [W] merge na ordem #9215 → #9218 → #9221 → este PR.
2. Quando o Anexo B mudar de versão: baixar o .xlsx, rodar scripts/fiscal/lista-servico-nacional.py
   e atualizar o sha256 no ListaServicoNacionalTest.
```
