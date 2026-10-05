# Telas 03 e 22 · Anexos (P2) ⬜

> Proposta. Vira `api/tela-03-anexos.md` no ERP.

## Já existe ✅
- Tabela `arquivos` (morph `arquivable_type` / `arquivable_id`, `business_id`, `deleted_at`). Já guarda as fotos da OS: `arquivable_type = 'Modules\OficinaAuto\Entities\ServiceOrder'`.
- `GET /api/app/os/{id}` → `fotos_laudo` (só a contagem).

## Novo ⬜
`GET /api/app/os/{id}/arquivos` → até 100 itens, do mais novo para o mais antigo:

```json
{ "itens": [ { "id": 812, "nome": "IMG_0412.jpg", "tipo": "image/jpeg", "tamanho": 284113,
    "legenda": "Fachada · antes", "etapa": "recepcao", "enviado_em": "2026-10-05T12:41:00Z",
    "enviado_por": "Ana Souza", "url": "https://…/assinada?exp=…", "miniatura": "https://…" } ],
  "pode_enviar": true }
```

- `url` e `miniatura` são assinadas, valem 10 min e são geradas na hora. Nunca expor um caminho público.

`POST /api/app/os/{id}/arquivos` (multipart: `arquivo`, `legenda?`, `origem: "camera"|"galeria"`) → `201` com o item.
- Aceita jpg, png, heic e pdf, até 10 MB. HEIC é convertido para jpg no servidor.
- Throttle 30/min. `etapa` = a etapa atual da OS, gravada pelo servidor.
- `origem` serve só para métrica. Não vira regra.

O mesmo vale para pedido depois: `/api/app/pedidos/{id}/arquivos`, com `arquivable_type` da venda.

| Código | Quando |
|---|---|
| `403 sem_permissao` | sem `oficinaauto.service_order.update` (OS) ou sem edição da venda (pedido) |
| `404 nao_encontrado` | outro business |
| `413` | arquivo acima de 10 MB |
| `422 validacao` | tipo não aceito → `campos.arquivo` |

## LGPD
Foto de fachada ou de veículo **pode conter placa ou rosto**. Ela fica só no business, sem índice público. Apagar um anexo é feito na web (soft delete).

## Ajuste da empresa
`app_anexos_camera` (padrão `true`). Com `false`, o app mostra só "Da galeria", e a rota recusa `origem: "camera"` com `422 campos.origem`.
