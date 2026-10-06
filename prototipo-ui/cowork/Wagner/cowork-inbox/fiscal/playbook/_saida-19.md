---
sessao: "19"
titulo: Linhas conferidas num tenant não gravam noutro (UC-NFIM-04)
autor: "[CL]"
data: 2026-10-06
base: wagnerra23/oimpresso.com@main a8e0624504 (pós-#8806)
prefixo_tocado: ImportRegrasController.php · ImportCsv.casos.md · SDD-emissao-fiscal-v1.0.md (3 linhas, reconciliação) · este arquivo
---
# _saida-19 · Import CSV só aplica no business em que foi conferido

**Resposta curta:** o `preview` passou a carimbar o business na sessão
(`nfe_import_csv_business_id`), e o `aplicar` recusa e descarta o lote quando o carimbo falta ou não
bate com o business corrente. Saída (a) do casos.md, a que a thread pedia. Vermelho antes, verde depois,
medido no CT 100.

## 1 · Feito

| arquivo | o quê |
|---|---|
| `Modules/NfeBrasil/Http/Controllers/ImportRegrasController.php` | `preview` grava o business junto das linhas; `aplicar` confere antes de chamar o service. Divergência ou carimbo ausente: apaga as duas chaves da sessão e volta para a tela com erro no campo `arquivo` (*"O arquivo foi conferido em outra empresa. Confira de novo nesta empresa antes de aplicar."*). O caminho feliz também apaga as duas chaves. |
| `resources/js/Pages/NfeBrasil/Tributacao/ImportCsv.casos.md` | UC-NFIM-04: `❌ falha esperada` → `🧪`, com o recibo do CT 100. O texto "Por que nasce ❌" fica como retrato de antes. |
| `memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md` | §5.3 F8 ganha a nota de fechamento · §6.2 item 4 deixa de ser "falha esperada" · §9 R2 vira ✅. Pedido pelo próprio casos.md e pela regra de precedência (corrigir o perdedor no mesmo PR). |

O `ImportRegrasCsvService` **não** mudou: o conserto é de fronteira de request, e o service já recebe o
`business_id` como argumento.

## 2 · Prova

CT 100, container `oimpresso-staging`, numa **cópia isolada** da branch (`/tmp/t19wm`, com `vendor`
copiado para o autoload não cair no checkout compartilhado). O checkout compartilhado não foi tocado.
`php artisan test Modules/NfeBrasil/Tests/Feature/TributacaoGatesContratoTest.php --filter=UC-NFIM`:

| controller | sha256 (início) | UC-NFIM-01 | 02 | 03 | 04 |
|---|---|---|---|---|---|
| anterior (`main`) | `b66b0ceb` | ✓ | ✓ | ⨯ | **⨯** (biz 2 ganhou a regra) |
| novo (esta thread) | `cb032b6a` | ✓ | ✓ | ⨯ | **✓** |

17 asserções nas duas corridas. A troca entre as corridas foi só o arquivo do controller, conferido por hash.

## 3 · Não feito, e por quê

- **O arquivo de teste não entrou na allowlist da lane `nfebrasil-pest.yml`.** Fora do prefixo, e a lane é
  required com `enforce_admins`: o arquivo ainda tem o UC-NFRF-04 vermelho (thread 18) e, no staging, o
  UC-NFIM-03 vermelho (abaixo). Por isso o status fica 🧪, não ✅.
- **O comentário de cabeçalho do `TributacaoGatesContratoTest.php`** ainda diz que o UC-NFIM-04 "nasce
  vermelho". Não editei: a thread 18 escreve no mesmo arquivo, e mexer ali agora é conflito certo.
  Quem fechar o último dos dois deve atualizar o cabeçalho.

## 4 · Descobertas que mudam outra sessão

- **UC-NFIM-03 falha no staging do CT 100, com ou sem esta mudança.** O usuário semeado do biz 1 lá tem
  os papéis `Admin#1` e `Admin#2`, e `can('nfe.tributacao.manage')` responde sim mesmo depois do
  `revokePermissionTo` do teste, porque o papel de admin concede tudo. O teste mede "sem permissão" com
  um usuário que continua admin. No CT 100 esse caso não prova nada. A thread 18 mediu o mesmo e
  informa que no CI o seed cria o usuário sem papel, então lá o fixture vale; para repetir no CT 100,
  ela tirou o papel do usuário 1 de `model_has_roles` só durante a corrida e devolveu depois.
  **Afeta a thread 18:** o UC-NFRF-04 usa o mesmo `nfgtLogar(comPermissao: false)` e vai bater no mesmo
  muro no staging. Saída provável: o teste usar um usuário sem papel de admin (fixture própria), não
  revogar a permissão direta.

## 5 · Pedido literal

Para quem decidir a allowlist: *"Pôr `TributacaoGatesContratoTest.php` na lane do NfeBrasil depois que
a thread 18 fechar o UC-NFRF-04 e o UC-NFIM-03 deixar de depender de um usuário admin."*
