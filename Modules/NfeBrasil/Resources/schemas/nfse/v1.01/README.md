# Esquemas XSD da DPS — NFS-e Nacional v1.01

Cópia de 4 arquivos do pacote oficial
`nfse-esquemas_xsd-v1-01-20260209.zip`, publicado em
`https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/documentacao-atual`
(baixado em 2026-10-13, pasta `Schemas/1.01/`). São só os que a DPS usa: o
`DPS_v1.01.xsd` inclui o `tiposComplexos`, que inclui o `tiposSimples`, e os dois
importam o `xmldsig-core-schema`.

Única mudança: fim de linha CRLF → LF (o repositório exige LF, `.gitattributes`). O
conteúdo é o publicado; os sha256 abaixo são dos arquivos já em LF, e os do pacote
original, para conferência, ficam na última coluna.

| arquivo | sha256 (LF, versionado) | sha256 no pacote (CRLF) |
|---|---|---|
| `DPS_v1.01.xsd` | `c7dab363d8cf7c83fc2b3b21e72cf669a51bd30947a5690685ea96c4b3e39dcd` | `fe45e5250a48e519aba89fc6a472863b8e602ed957778fb64692804933a00d0c` |
| `tiposComplexos_v1.01.xsd` | `6f792f408a33c11e799042a8d61cac7d1c9f5992c53e07e60ce75a15f157d1ac` | `e8e09d525574cc224ca6d1f8d8eb0366043ab2a3aa8d0d234058e6244d60e371` |
| `tiposSimples_v1.01.xsd` | `a2769845a367fdee495eb5a6ea6343639d36e0e5474ff65eb7b79a763fba9341` | `830ea116c34d7310699e34b214b7214a65f7e5d3b1f09aeaa702f7e3f4283b17` |
| `xmldsig-core-schema.xsd` | `5baf55ea1ef7cfb89ca33209d7a764c594d26feca814116dacc04bee51b868ce` | `49848f732663aecb618d72ad6130c5c3240f0a10f3a1a8544b7d48a6c726046f` |

Quem usa: `Modules/NfeBrasil/Tests/Feature/NfseDpsXmlTest.php` valida a DPS gerada
pelo `SnNfseAdapter` contra estes esquemas (R-NFSE-005).

## Defeito conhecido do esquema oficial

`TSSerieDPS` (em `tiposSimples_v1.01.xsd`) declara o padrão `^0{0,4}\d{1,5}$`. Em XML
Schema, `^` e `$` não são âncoras: o libxml os lê como caracteres literais, e nenhuma
série passa. O teste valida contra uma cópia temporária em que só esse padrão perde o
`^`/`$` — fora o fim de linha, os arquivos daqui ficam como o governo publicou.

Atualizar: baixar o pacote novo, copiar os 4 arquivos, converter para LF, refazer os hashes acima.
