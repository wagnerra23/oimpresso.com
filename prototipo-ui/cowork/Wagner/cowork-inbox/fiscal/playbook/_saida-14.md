---
sessao: "14"
titulo: Saúde fiscal — pendências que viram rejeição (UC-NFTR-24 · UC-NFTR-25)
autor: "[CL]"
data: 2026-10-13
base: wagnerra23/oimpresso.com@main 94c005aec5
prefixo_tocado: Services/Tributacao/SaudeFiscalService.php (novo) · TributacaoController@index · Pages/NfeBrasil/Tributacao/Index.tsx + _components/SaudeFiscal.tsx (novo) · Index.casos.md · Tests/Feature/SaudeFiscalTest.php (novo) · nfebrasil-pest.yml · SUPERFICIE (regenerada) · este arquivo
---
# _saida-14 · Saúde fiscal

**Resposta curta:** a Tributação tem um card no topo que lista o que vai virar rejeição. Cada item
traz o link que resolve, e um selo diz se a empresa está pronta pra emitir. Ninguém escreve nada:
o service só lê, e não toca o motor nem a nota. Saiu em dois PRs empilhados: **#9271** (service e
teste) e **#9279** (prop deferida e card).

## 1 · Antes de começar

- Não havia entrega anterior: nenhum `_saida-14`, nenhum PR e nenhum `SaudeFiscal` no código de
  `origin/main` em `94c005aec5`.
- As dependências (07, 12, 15 e 17) estão entregues. A 07 só aparecia "em curso" por causa da prova
  do índice (`_ERRATA-INDICE-2026-10-13.md`, #9263).
- Os números UC-NFTR-11 e 13 da ficha já estavam ocupados no `Index.casos.md` pelas threads 10 e 15.
  Os casos entraram como **UC-NFTR-24** e **UC-NFTR-25**.

## 2 · O que a lista mostra

As fontes são as mesmas da emissão, para a lista não dizer outra coisa do que a nota faz.

| tipo | bloqueia? | fonte |
|---|---|---|
| NCM padrão ausente ou `00000000` | sim | `tributacao_default.ncm_default` → `business.ncm_padrao` (`NfeService:125`) |
| certificado ausente ou vencido | sim | `nfe_certificados` ativo, maior `valido_ate` |
| certificado a ≤ 30 dias | aviso | idem, mostra os dias |
| produto que saiu em nota com o NCM padrão e continua sem NCM | aviso | `nfe_emissoes.metadata.itens_ncm_padrao` (thread 17), últimos 30 dias |
| produto ativo sem NCM válido | aviso | mesma regra de `NfeService:465` |

"Pronto pra emitir" é zero bloqueantes. Aviso não muda o selo.

## 3 · Prova

- **#9271**, head `491f6f6829`, lane `nfebrasil-pest`, run **38456743903**: success.
  `SaudeFiscalTest` rodou com 2 testes, 2 passed, 0 skipped e **21 assertions**:
  `UC-NFTR-24 · pendência aparece e some ao corrigir` ✓ e `UC-NFTR-25 · pronto pra emitir` ✓.
  A lane inteira deu `4 skipped, 430 passed (3553 assertions)`.
- **#9279**, head `cb60fbef3a`, run **38458954583** por `workflow_dispatch`, porque a lane só dispara
  em PR com base `main`. Acrescenta `UC-NFTR-24 · a tela recebe cada pendência com o link que
  resolve`. O veredito vai ao gerente no head que cair para `main` depois do merge do #9271.
- Os casos rodam no tenant 98, com o 2 como adversário. O estado do tenant que o teste altera
  (config e certificados ativos) é guardado e devolvido no `afterEach`. Fixtures marcadas: SKU
  `SAUDE14-*`, notas série `914` e CNPJ `91400000000000`.
- Valor e estoque: nada muda, então não há tabela antes→depois.
- Tela: typecheck sem regressão (baseline 300 contra 333) e eslint limpo. Não renderizei, porque a
  máquina não tem PHP. O smoke com print fica com o gerente depois do merge.

## 4 · Diferença do pedido

- **Produto sem NCM é aviso, não bloqueio.** O protótipo (`fiscal-tributacao.jsx`, item `s1`) pinta
  de vermelho com *"Nota com esses itens volta da SEFAZ"*. A emissão troca o NCM inválido do item
  pelo NCM padrão da empresa (`NfeService:465`), então com o padrão válido a nota não volta. O card
  diz *"A nota sai com o NCM padrão da empresa no lugar"*. **Pedido ao Cowork:** alinhar a
  severidade e o texto do `s1`.
- **Ficaram de fora**, todos aparecem no protótipo:
  - cidade do cliente sem IBGE (`s7`): a emissão casa a cidade por nome em `cidades`
    (`NfeService:1747`), e conferir isso por cliente pede uma consulta por contato;
  - IBGE do emitente;
  - serviço sem código municipal (`s2`);
  - rejeição cadastral recente (`s3`): a "receita determinística" citada na ficha não foi achada no
    código;
  - regra sem cClassTrib com a Reforma ligada (`s4`) e versão sem aceite do contador (`s5`): as duas
    tinham código, mas saíram do PR-a porque não tinham teste.

  Cada um precisa de thread ou PR próprio com teste.
- **Sem abas.** A Index real não tem a estrutura de abas do protótipo. O card entra no topo da
  página, na ordem da Index atual. O bloco "Chamados fiscais por motivo" do protótipo é dado de
  demonstração e ficou fora.
