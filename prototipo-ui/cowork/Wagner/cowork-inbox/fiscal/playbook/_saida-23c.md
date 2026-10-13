---
sessao: "23c"
titulo: Adapter da NFS-e em XML assinado (DPS v1.01 → Sefin Nacional)
autor: "[CL]"
data: 2026-10-13
base: empilhado sobre o #9215 (retenção na DPS) e o #9218 (certificado da config)
prefixo_tocado: Modules/NfeBrasil/Services/Nfse/DpsXml.php · Modules/NfeBrasil/Resources/schemas/nfse/v1.01/ · Modules/NFSe/{Adapters,DTO,Services,Config} · Modules/NfeBrasil/Tests/Feature/NfseDpsXmlTest.php · testes do #9215 e AmbientePorBusinessTest ajustados · SPEC NfeBrasil · BRIEFING NFSe · este arquivo
decisao: "[W] 2026-10-13 no chat: 'faça o adapter da NFS-e em XML assinado'"
---
# _saida-23c · Adapter da NFS-e em XML assinado

> Continuação da thread 23, pedida pelo [W] no chat. Sem thread numerada no `00-INDICE.md`.

**Resposta curta:** o `SnNfseAdapter` deixa de mandar JSON. A DPS sai em XML no leiaute dos **XSDs oficiais v1.01**, o `infDPS` é assinado com o certificado A1 e vai em GZip+Base64 no `POST /SefinNacional/nfse`, com mTLS. A DPS gerada **valida contra os XSDs oficiais**, que agora estão versionados no repositório, e a assinatura confere. **Nenhuma DPS foi enviada à Sefin de verdade:** falta o certificado A1 em homologação.

## 1 · Feito

| arquivo | o quê |
|---|---|
| `Modules/NfeBrasil/Services/Nfse/DpsXml.php` | serializa a estrutura (ordem do XSD) em XML no namespace `http://www.sped.fazenda.gov.br/nfse`; assina com `NFePHP\Common\Signer` (já vinha com o `sped-nfe`); empacota/desempacota GZip+Base64. |
| `Modules/NFSe/Adapters/SnNfseAdapter.php` | `buildDps` no leiaute v1.01 (Id de 45 posições, `regTrib/opSimpNac` pelo regime, `cTribNac` pelo LC 116, `tpRetISSQN`, `tribFed` do R-NFSE-003, `indTotTrib` 0); `emitir` assina, envia `dpsXmlGZipB64` com mTLS em PEM e lê `chaveAcesso` + `nNFSe`; erros `erros[{Codigo, Descricao}]`. |
| `Modules/NFSe/DTO` · `Services` · `Config` | série numérica, regime do prestador, nDPS = id da `nfse_emissoes` (o provisório tinha 18 dígitos e não era único); endpoints com `/SefinNacional`; algoritmo de assinatura configurável. |
| `Modules/NfeBrasil/Resources/schemas/nfse/v1.01/` | os 4 XSDs oficiais que a DPS usa, sem alteração, com README de fonte e sha256. |
| `Modules/NfeBrasil/Tests/Feature/NfseDpsXmlTest.php` | R-NFSE-005: XSD em 4 cenários + controle negativo, assinatura, Id, mapeamentos, falhas visíveis, `comNumeroDps`, envio fingido nos dois ambientes, retorno e erro E0714. |

## 2 · Prova (CT 100, PHP 8.4, certificado autoassinado)

```
caso                               XSD v1.01   assinatura   gzip+b64 ida/volta   Id
presumido, tomador CNPJ            válido      confere      ok                   45 posições
retenção federal                   válido      confere      ok                   45 posições
Simples, tomador CPF, ISS retido   válido      confere      ok                   45 posições
tomador sem documento              válido      confere      ok                   45 posições
cTribNac com 5 dígitos (controle)  REPROVADO
envio fingido (homologação)        URL .../SefinNacional/nfse · tpAmb 2 · DPS assinada · nNFSe 42 lido · PEMs apagados
erro fingido                       NfseException E0714 "Arquivo enviado com erro na assinatura"
```

**Defeito do XSD oficial:** o `TSSerieDPS` usa `^0{0,4}\d{1,5}$`; em XML Schema `^` e `$` são caracteres literais, e nenhuma série passa no libxml. O teste valida numa cópia temporária com só esse padrão corrigido; os arquivos versionados ficam como o governo publicou.

## 3 · Não feito, e por quê

- **Envio real à Sefin:** sem certificado A1 de homologação. É o próximo passo e o único que confirma o resto.
- **Algoritmo da assinatura:** o XSD não restringe; ficou SHA-256 (o que usam as bibliotecas que emitem na Sefin), configurável por `NFSE_ASSINATURA_ALGORITMO=sha1`.
- **cTribNac:** item + subitem + desdobro `01`. Subitem com mais de um desdobro nacional precisa da tabela oficial.
- **pAliq omitido:** no município do Sistema Nacional a alíquota é parametrizada e "será fornecida pelo sistema" (XSD). Se o município não for conveniado, precisa voltar.
- **consultar() e cancelar() deste adapter** seguem no formato antigo (o cancelamento real é do NfeBrasil).

## 4 · Pedido literal pro [CL]/[W]

```
1. [W] merge na ordem #9215 → #9218 → este PR.
2. Subir o certificado A1 da empresa em homologação e emitir 1 NFS-e de teste.
3. Se a Sefin recusar a assinatura, trocar NFSE_ASSINATURA_ALGORITMO para sha1 e repetir.
```
