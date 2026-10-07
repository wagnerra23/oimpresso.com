---
sessao: "08"
titulo: Simulador read-only em /nfe-brasil/tributacao (UC-NFTR-08 · 09)
autor: "[CL]"
data: 2026-10-07
base: wagnerra23/oimpresso.com@main (já com #8923 e #8941)
prefixo_tocado: TributacaoController (simular + localPadrao no index) · Routes/web.php · Http/Requests/SimularTributacaoRequest.php (novo) · Index.tsx · _components/SimuladorNota.tsx (novo) · Index.charter.md · Index.casos.md · TributacaoSimuladorTest (novo) · nfebrasil-pest.yml · SUPERFICIE (regenerada) · este arquivo
---
# _saida-08 · Simulador read-only

**Resposta curta:** `GET /nfe-brasil/tributacao/simular` monta a mesma linha que a emissão monta para
um item e chama `NfeService::montarItensNfe`, a função que a NF-e e a NFC-e usam. Não há cálculo
próprio nem no backend nem no front, e nada é gravado. O card "Simular nota" no Index mostra nível
N1–N4, CFOP, NCM, os tributos e o aviso literal de prévia do protótipo.

## 1 · Confirmação dos símbolos (C12)

- `NfeService::montarItensNfe` é público e não acessa o banco: é o caminho de cada item na emissão.
- O NCM padrão e a UF de origem a emissão calcula dentro de `emitirParaTransaction` e de `resolverUF`
  (privado). A thread proíbe mexer em `Services/`, então o controller repete as duas leituras. A
  igualdade com o `resolverUF` real fica travada pelo teste, que o chama por reflexão.
- `products.ncm` e `business.ncm_padrao` existem no schema.

## 2 · Prova (CT 100, worktree isolado, sha `b36e8de67`)

| rodada | resultado |
|---|---|
| `TributacaoSimuladorTest` | **2 passed · 28 assertions** |
| mutação: simulador ignora a UF de destino | UC-NFTR-08 cai (*"3 is identical to 2"*: o nível não troca para N2) |
| mutação: busca do produto sem `business_id` | UC-NFTR-09 cai (*"Expected 404 but received 200"*) |
| PHPStan (controller + request) | sem erros |
| `TributacaoControllerTest` · `NfeEmissaoPorItemTest` | 7 · 9 passed |
| `TributacaoIndexContratoTest` | 1 failed **igual com o controller do `main`**: o staging está sem as colunas da thread 07 (a migração foi revertida lá depois da prova da 07); no CI as migrações rodam |

**Dupla prova de valor (item de 3 × 333,33):** (1) o simulador × `montarItensNfe` chamado direto com a
UF do próprio `NfeService` → CFOP, nível e os 5 valores idênticos; (2) conta à mão → ICMS 120,00 ·
PIS 16,50 · COFINS 76,00 · IBS 1,00 · CBS 9,00 · total 222,50. Controle: UF da regra N2 → nível 2,
CFOP 6102, ICMS 70,00.

## 3 · Diferença do pedido

- **Sem operação, destinatário e regime no card.** O protótipo tem os três; a emissão de hoje não os
  passa ao motor. Mostrá-los faria a prévia dar um número que a nota não daria — o oposto do D-SIM.
  Entram quando a emissão passar a operação (depois da thread 07, que já preparou o motor).
- **`regra_id` não aparece.** `montarItensNfe` não devolve o id da regra (só o nível); devolver exigiria
  mexer em `Services/`.
- **UC-NFTR-08 e UC-NFTR-09**, não "08b": o guard normaliza sufixo para maiúsculo e o caso fica órfão
  (lição da thread 04).
- **A bateria de 27 notas não virou dataset.** Ela usa alíquotas do protótipo e cobre casos que a
  emissão ainda não monta (importação, NFS-e, devolução, DIFAL). Fica como alvo das threads que os
  implementarem.
- **Permissão:** a mesma `nfe.tributacao.manage` das regras.
