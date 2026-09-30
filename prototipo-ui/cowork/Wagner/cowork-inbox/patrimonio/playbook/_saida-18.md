---
sessao: "18"
titulo: Saída da thread 18 — drawers de alocar, editar e devolver, com Excluir devolução
dono: "[CL]"
medido_em: 2026-09-30
base_medida: 58f6614ed (origin/main)
autorizacao: "[W] 2026-09-30 — executar a 18 antes do drawer de detalhe do bem (inverte a ordem da _saida-16b)"
---

# 18 · Alocações ganhou escrita, e a ordem da `_saida-16b` foi invertida por decisão [W]

## Ordem
A `_saida-16b` punha a sequência **drawer de detalhe do bem → 18 → 16**. Em 2026-09-30 o [W]
autorizou executar a 18 primeiro: tudo nela mora na tela Alocações e não depende do drawer do
bem. O drawer do bem está sendo feito em paralelo por outra sessão (aviso trocado: ela não põe
ação de escrita no drawer do bem, esta não toca `_shared/**` nem `AssetController`). O ciclo
16 ↔ 18 do índice continua sendo do Cowork. O que o índice precisa refletir: a 18 está **feita**;
a 16 passa a depender só do drawer do bem.

## Campo a campo — Blade → drawer

| Blade (`asset_allocation/create`) | Drawer Alocar | Observação |
|---|---|---|
| `ref_no` (vazio = gera) | Código da alocação | só no modo novo, como o Blade de edição, que não o mostrava |
| `asset_id` select + qtd entre parênteses | Bem, com "N livre" e ajuda "Saldo livre agora" | saldo vem do `Asset::forDropdown`, o mesmo número do Blade |
| `receiver` (usuários) | Alocar para | `User::forDropdown`, igual ao Blade |
| `quantity` | Quantidade alocada | `NumericInputPtBR`, 2 casas, enviado por `paraNumUf` |
| `transaction_datetime` | Alocado de (`datetime-local`) | convertido pro formato da empresa + hora (12h/24h) |
| `allocated_upto` | Alocado até | vazio = indeterminado |
| `reason` | Razão | opcional, como no Blade |

| Blade (`asset_revocation/create`) | Drawer Devolver | Observação |
|---|---|---|
| `parent_id`, `asset_id` ocultos | só `parent_id` | `asset_id` agora vem **da alocação**, no servidor |
| `ref_no` | — | o servidor gera pelo prefixo |
| `quantity` (max = restante, só HTML) | Quantidade devolvida, começa no restante | restante agora é **travado no servidor** |
| `transaction_datetime` | Devolvido em | idem alocar |
| `reason` | Razão | opcional |
| (não havia) | Lista de devoluções 1 : N com **Excluir** | Excluir só existia na Blade `/asset/revocation` |

## Antes → depois (escrita de quantidade)

| Caso | Antes | Depois |
|---|---|---|
| Alocar acima do saldo | recusado pela trava da 02, mas a tela dizia "algo deu errado" | recusado, com a mensagem da trava no campo quantidade |
| Devolver mais do que falta | **aceito** (o `max` era só HTML) | recusado no campo, nada gravado |
| Devolver com `parent_id` de outra empresa | **aceito**, devolução pendurada em alocação alheia | 404, nada gravado |
| `asset_id` da devolução | lido do POST | tirado da alocação |
| Excluir com id de **alocação** no endpoint de devolução | **apagava a alocação**, sem passar pelo Service | 404, nada apagado |
| Excluir pelo drawer (Inertia) | resposta JSON que o Inertia rejeita | redireciona de volta ao drawer |
| Registros já gravados | — | **nenhum alterado**: sem migration, sem backfill |

Não medido: quantas devoluções em produção já excedem o alocado ou apontam pai de outra
empresa. Os dois defeitos de gravação fecham daqui pra frente; o estoque histórico não foi auditado.

## O que ficou de fora, com motivo
- `AssetAllocationService::atualizar()` (editar) **não tem trava de saldo** — o Service é
  `nao_toca` (dono: threads 01/02). Editar pode subir a quantidade além do saldo, como já podia no Blade.
- `RevokeAllocatedAssetController::{edit,update}` seguem vazios (view inexistente, `update` sem
  corpo). O protótipo não tem formulário de edição de devolução; retirar a rota é da linha da
  thread 15 e decisão [W].
- Excluir **alocação** não entra: a thread não pediu, e o `destroy` de alocação continua com a
  resposta JSON antiga.
- As 3 Blades (`asset_allocation/{create,edit}`, `asset_revocation/create`) ficam sem chamador
  vivo no repo mas **não foram apagadas** neste PR: remoção de view é o cutover (F5).

## Provas
- vitest `tests/js/patrimonio-alocacoes-envio.test.tsx` — 10 testes; mutante (quantidade sem
  `paraNumUf`) derruba o UC-ALOC-06, restauração conferida por hash.
- Pest `Modules/AssetManagement/Tests/Feature/AlocacoesFormContratoTest.php` — 7 cenários,
  tenant 98 × 99, roda na lane `assetmanagement-pest` (MySQL real).
- `CrossTenantAssetTest` (dono: thread 01) é guarda e roda na mesma lane.
- Gates locais: `contrato-de-tela` (contrato + mapa + anti-tautologia), `casos-coverage-guard`,
  `uc-id-lint`, schema do charter, `layout-primitives-guard`, `typecheck-baseline` (302 < 333).
