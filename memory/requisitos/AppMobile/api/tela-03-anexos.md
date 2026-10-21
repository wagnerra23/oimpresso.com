# Telas 03 e 22 · Anexos (P2) ✅

> ✅ **Rota no ERP desde o PR 09 do playbook `propostas-app`** (2026-10-15). Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6). Origem: handoff `propostas-app` do Design, conferido em 2026-10-05.
>
> Código: [`OsArquivosController`](../../../../app/Http/Controllers/Api/App/OsArquivosController.php) · rotas em [`routes/api/app/os-arquivos.php`](../../../../routes/api/app/os-arquivos.php) · teste [`AppOsArquivosApiContratoTest`](../../../../tests/Feature/Sells/AppOsArquivosApiContratoTest.php) (UC-APPANX-01..08).

## Já existia
- Tabela `arquivos` (morph `arquivable_type` / `arquivable_id`, `business_id`, `deleted_at`). Guarda as fotos da OS: `arquivable_type = 'Modules\OficinaAuto\Entities\ServiceOrder'`.
- `GET /api/app/os/{id}` → `fotos_laudo` (só a contagem).

## Novo (PR 09)
Colunas `arquivos.legenda` (≤ 160) e `arquivos.etapa` (≤ 80), nulas: os arquivos antigos seguem sem elas.

`GET /api/app/os/{id}/arquivos` → até 100 itens, do mais novo para o mais antigo (UC-APPANX-01):

```json
{ "itens": [ { "id": 812, "nome": "IMG_0412.jpg", "tipo": "image/jpeg", "tamanho": 284113,
    "legenda": "Fachada · antes", "etapa": "recepcao", "enviado_em": "2026-10-05T12:41:00Z",
    "enviado_por": "Ana Souza", "url": "https://…/api/app/arquivos/812/conteudo?biz=…&expires=…&signature=…",
    "miniatura": "https://…" } ],
  "pode_enviar": true }
```

- `url` é assinada (HMAC do `APP_KEY`), vale 10 min e é gerada na hora. Nunca expõe o caminho do disco.
- `url` abre **sem token** (um `<img>` não manda o token): a assinatura é a autorização e cobre o business do anexo. Adulterar qualquer parâmetro → `403` (UC-APPANX-08). O consumo grava `signed_url_consumed` na trilha `arquivos_audit_log`.
- `miniatura`: o ERP não gera miniatura. Foto → a mesma `url`; PDF → `null`.
- `enviado_por`: nome de quem enviou (usuário do mesmo business), `null` se não houver.
- `pode_enviar`: o usuário tem `oficinaauto.service_order.update` (ou é superadmin).

`POST /api/app/os/{id}/arquivos` (multipart: `arquivo`, `legenda?`, `origem?: "camera"|"galeria"`, header `Idempotency-Key` obrigatório) → `201` com o item (UC-APPANX-02).
- Aceita **jpg, png e pdf**, até 10 MB. O tipo é lido do conteúdo, não da extensão.
- Throttle 30/min. `etapa` = a chave da etapa atual da OS, gravada pelo servidor (OS de mecânica sem pipeline conta na etapa inicial, como no quadro web).
- `origem` serve só para métrica (vai para o log). Não vira regra nem coluna.
- Gravação pelo `ArquivosService` (dedupe por MD5, Curador, vault para o que for sensível, trilha de upload). Reenviar o mesmo arquivo na mesma OS devolve a mesma linha.
- Idempotência (`app_idempotencia`, rota `os_arquivos`): mesma chave + mesmo corpo (OS, MD5 do arquivo, legenda) → o mesmo anexo, com URL nova; corpo diferente → `422 idempotencia_conflito`; outra requisição com a mesma chave ainda em curso → `409 em_andamento` (UC-APPANX-07).

| Código | Quando |
|---|---|
| `403 sem_permissao` | sem `oficinaauto.service_order.view` (GET) ou sem `oficinaauto.service_order.update` (POST) — UC-APPANX-04 |
| `404 nao_encontrado` | OS de outro business ou apagada; nada é gravado — UC-APPANX-03 |
| `409 em_andamento` | a mesma `Idempotency-Key` ainda está sendo processada |
| `413 arquivo_grande` | arquivo acima de 10 MB — UC-APPANX-05 |
| `422 validacao` | tipo não aceito → `campos.arquivo`; sem chave → `campos.idempotency_key`; câmera com o ajuste desligado → `campos.origem` |
| `422 idempotencia_conflito` | chave já usada com outro corpo |
| `429` | throttle |

## Fora do PR 09
- **HEIC**: o ERP não tem biblioteca de imagem para converter (seria dependência nova, com ADR). O app deve mandar JPEG (o plugin de câmera já entrega JPEG); HEIC hoje volta `422 campos.arquivo`.
- **Pedido** (`/api/app/pedidos/{id}/arquivos`, com `arquivable_type` da venda): fica para um PR próprio.

## LGPD
Foto de fachada ou de veículo **pode conter placa ou rosto**. Ela fica só no business, sem índice público, e só abre pela URL assinada de 10 min. Apagar um anexo é feito na web (soft delete); anexo apagado some da lista e a URL dele dá 404.

## Ajuste da empresa
`app_anexos_camera` (padrão `true`), lido de `AjustesDaEmpresa::valor($bizId, 'app_anexos_camera')` no `store`. Com `false`, o app mostra só "Da galeria", e a rota recusa `origem: "camera"` com `422 campos.origem` (UC-APPANX-06).
