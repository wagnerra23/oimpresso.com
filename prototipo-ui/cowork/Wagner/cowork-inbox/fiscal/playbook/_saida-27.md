---
sessao: "27"
titulo: CNPJ alfanumérico — cadastro e destinatário do XML
autor: "[CL]"
data: 2026-10-06
base: wagnerra23/oimpresso.com@main a8e0624504 (pós-#8806)
prefixo_tocado: app/Support/BR · app/Rules/BR · Modules/NfeBrasil · memory/requisitos/NfeBrasil/SPEC.md (destino que a thread nomeia) · .github/workflows/nfebrasil-pest.yml (só a allowlist e o trigger, para o teste rodar) · este arquivo
---
# _saida-27 · CNPJ alfanumérico

**Resposta curta:** o cadastro passou a aceitar CNPJ com letra e a recusar o DV errado, e o destinatário da NF-e passou a ir para o XML com as letras. **A nota ainda não sai para a SEFAZ com CNPJ alfanumérico**: o XSD que o sped-nfe instalado valida declara `TCnpj` como `[0-9]{14}`. Isso não se conserta no oimpresso; é atualização de biblioteca (abaixo, §3).

## 1 · Feito

| arquivo | o quê |
|---|---|
| `app/Support/BR/Cnpj.php` (novo) | normaliza sem tirar letra, valida forma `[0-9A-Z]{12}[0-9]{2}` e DV módulo 11 com valor ASCII − 48; `documentoFiscal()` devolve o documento pronto pro XML e cai no comportamento antigo (só dígitos) quando a forma não bate |
| `app/Rules/BR/CpfCnpj.php` | CNPJ com letra vai pro validador novo; DV errado → *"não é um CNPJ alfanumérico válido (dígito verificador não confere)"*. Sem letra, segue a `Util` de antes. É a rule de `StoreContactRequest`/`UpdateContactRequest` (`cpf_cnpj`) |
| `Modules/NfeBrasil/Services/NfeService.php` | `emitirParaInvoice` (documento do contato) e `buildXml` (`<dest>`) trocam `preg_replace('/\D/')` por `Cnpj::documentoFiscal()` |
| `Modules/NfeBrasil/Tests/Feature/CnpjAlfanumericoTest.php` (novo) | R-NFE-033: DV, cadastro aceita/recusa, XML do `<dest>` com letras; controle positivo numérico em cada eixo |
| `.github/workflows/nfebrasil-pest.yml` | teste na allowlist; `app/Support/BR/Cnpj.php` e `app/Rules/BR/CpfCnpj.php` no trigger (senão mexer neles não roda o teste) |
| `memory/requisitos/NfeBrasil/SPEC.md` | entrada R-NFE-033 com a lei citada e o "fora deste item" |

**Lei citada literal** (IN RFB nº 2.229/2024, parágrafo acrescido à IN RFB 2.119/2022): *"O CNPJ adotará o formato alfanumérico composto por quatorze posições, conforme disposto no Anexo XV, com previsão de implementação a partir de julho de 2026."* — **o Anexo XV não foi transcrito**: a fonte lida em 2026-10-06 trazia a IN sem o anexo. A regra do DV foi conferida contra o exemplo publicado pela Receita (`12.ABC.345/01DE-35` ⇒ `35`), por dois caminhos (o PHP e um cálculo independente em node).

**Prova:** `php -l` nos 4 PHP e parser YAML no workflow, localmente. **Os testes não rodaram aqui** (Pest só no CI/CT 100): o veredito é o run da lane `nfebrasil-pest` do PR. Os 2 casos de XML pulam em SQLite e só provam algo na lane MySQL.

## 2 · Não feito, e por quê

| item da thread | estado | por quê |
|---|---|---|
| **XSD da NF-e** | ❌ bloqueia a emissão real | `vendor/nfephp-org/sped-nfe/schemes/PL_009_V4` e `PL_010_V1.30` → `TCnpj` = `[0-9]{14}` (medido no vendor do checkout principal, sped-nfe `dev-master` no lock). O `Tools::sefazEnviaLote` valida contra o XSD, então o lote com letra é recusado antes de sair |
| **Emitente** com CNPJ alfanumérico | ❌ | `buildXml` `:1466`, `montarConfigSefaz` `:1282`, `CertificadoService:387`, `NfeCartaCorrecaoService:245`, `NfeInutilizacaoService:104/242`, `SefazConsultaCadastroService:117/204`, `ManifestacaoService:275`, `DistribuicaoDfeService:390` ainda arrancam letras. É outro assunto (a empresa do tenant, não o cliente) e não cabe no PR de 300 linhas |
| **Chave de acesso e QR Code** | ❌ não medido | dependem do emitente; a chave é montada pela biblioteca — conferir se ela aceita letra na posição do CNPJ só depois do XSD |
| **Máscara no front** | ❌ | nenhuma tela tocada; cadastro já aceita o valor com ou sem máscara no backend |
| **Armazenamento como texto** | 🟡 parcial | `App\Contact` não tem cast numérico para `tax_number`/`cpf_cnpj` (grep). O tipo da coluna no banco **não foi medido** |

## 3 · Pedido literal pro [W]

> Para a NF-e sair com CNPJ alfanumérico falta atualizar o sped-nfe para uma versão cujo XSD aceite letra no `TCnpj` (é a mudança de schema da SEFAZ para o CNPJ alfanumérico). Isso mexe em `composer.lock` e em toda emissão — quer que eu abra como thread própria (🔴, sozinha no PR, com os testes de emissão rodando)?

## 4 · Descobertas que mudam outra sessão

- **Thread 26 (filial)** e qualquer thread que toque o emitente: o CNPJ do emitente é arrancado para dígitos em 10 pontos (§2). Quem mexer ali, use `App\Support\BR\Cnpj::documentoFiscal()`.
- **Thread 11 (entrada por XML)**: o XML de fornecedor com CNPJ alfanumérico precisa do mesmo cuidado na leitura (`PurchaseXmlController::formataCnpj`, fora do NfeBrasil, não tocado).

## 5 · Prefixo tocado

`app/Support/BR/Cnpj.php` · `app/Rules/BR/CpfCnpj.php` · `Modules/NfeBrasil/Services/NfeService.php` · `Modules/NfeBrasil/Tests/Feature/CnpjAlfanumericoTest.php` · `memory/requisitos/NfeBrasil/SPEC.md` · `.github/workflows/nfebrasil-pest.yml` · este `_saida-27.md`. Os dois últimos de fora do prefixo da thread vão declarados aqui e no PR.
