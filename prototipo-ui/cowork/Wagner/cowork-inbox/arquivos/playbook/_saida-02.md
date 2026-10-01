---
sessao: "02"
titulo: Arquivos — classificar (PR-6) (recibo)
autor: "[CL]"
base: origin/main 7886571db (2026-09-30)
---

# _saida-02 · Classificar (PR-6)

## O que entreguei

| arquivo | o quê |
|---|---|
| `Modules/Arquivos/Routes/web.php` | `POST arquivos/{arquivo}/classificar` (`whereNumber`, `can:arquivos.access`, nome `arquivos.classificar`), no grupo da tela |
| `Modules/Arquivos/Http/Controllers/ArquivosAdminController.php` | `classificar(ReclassifyArquivoRequest, int)` → `ArquivosService::classify()` + linha `classify` na trilha com o motivo |
| `resources/js/Pages/Arquivos/_components/ClassificarSheet.tsx` | drawer PT-02 (`Sheet` do DS): bucket atual, motivo (≥5), "Reclassificar" |
| `resources/js/Pages/Arquivos/Index.tsx` | botão só-ícone "Classificar" na coluna de ações (só em linha não excluída) |
| `Modules/Arquivos/Tests/Feature/ArquivosAdminControllerTest.php` | 5 `it()` UC-INDEX-07 + limpeza da trilha das fixtures no `afterEach` |
| `Index.casos.md` / `Index.charter.md` | UC-INDEX-07; `[BLOQUEADO]` encolhido só pro `force_bucket` |

Como a ficha pede: grava `classified_by/at` (pelo Service) e audita `classify` com o `motivo`.

## Errata da ficha
- O Service **não recebe motivo** (`classify(Arquivo)`) e audita `reclassify`, não `classify`.
  `Services/` está fora do prefixo, então o motivo é gravado pelo controller numa **segunda**
  linha, `classify`, com o `business_id` do arquivo e o texto passado pelo `PiiRedactor`. A
  trilha fica com as duas: `reclassify` (resultado das regras) e `classify` (quem pediu e por quê).
- "Classificar" aqui é **re-aplicar as regras do curador**, não escolher bucket. O protótipo
  desenha seletores de bucket/visibilidade/retenção; não entraram, porque o servidor não tem
  como forçar classificação.

## Pendente, e por quê
- **`force_bucket`** (decisão [W] + Service novo): a Request o aceita no vocabulário
  `public/internal/sensitive/vault`, que não é o do banco. O controller agora o **recusa** com
  erro no campo, em vez de aceitar e ignorar.
- **Bucket × disco:** o `classify()` muda só o rótulo `bucket`. Um arquivo que vira `sensitive`
  continua no disco comum, sem cifra (e o inverso). É comportamento do Service, já existente —
  fica registrado pra thread que mexer no Service.
- **Permissão:** usa `arquivos.access`, a única que o módulo declara. O D3 do pedido previa
  permissão de governança só pra purge/aviso; se classificar também deve exigir uma segunda,
  é decisão [W].
- **Lugar da reclassificação** (pendência do charter): o playbook pôs nesta tela; a confirmação
  de [W] segue aberta no charter.

## Provas do json
- `contem` `Modules/Arquivos/Routes/web.php` ⊇ `{arquivo}/classificar` — ✅ (`placar.mjs`).
- `node scripts/contrato-de-tela.mjs --contract governance/design/contracts/arquivos-index.contract.json` → limpo, rc=0.
- `node scripts/casos-coverage-guard.mjs` → sem violações novas deste PR.
- Pest: NÃO rodei local (regra do projeto). A prova é a lane `PHP / Pest (Arquivos · MySQL)` do PR.

## PR
Branch `claude/arquivos-thread-02` (número no corpo do PR).
