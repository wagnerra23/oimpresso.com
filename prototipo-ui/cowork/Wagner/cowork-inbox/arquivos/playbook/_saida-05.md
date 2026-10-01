---
sessao: "05"
titulo: Arquivos — aviso ao titular (PR-9, D5) (recibo)
autor: "[CL]"
base: origin/main fd3866d9b (2026-10-01, já com a thread 04 #8356)
---

# _saida-05 · Aviso ao titular (PR-9 · LGPD Art. 18 VI)

## Por que a thread rodou com o placar dizendo `pendente`
O placar acusou só **"decisão pendente D5"**. D5 foi respondida por [W] em 2026-10-01
(`_DECISOES-W-2026-10-01.md`: *"sim — ADR + titular_avisado_at, desenho da ficha 05"*). O
`00-INDICE.md` ainda traz `"respondida": false` porque é do Cowork — a edição está pedida naquele
arquivo de decisões. A dependência 04 entrou no #8356.

## O que entreguei

| arquivo | o quê |
|---|---|
| `memory/decisions/0421-arquivos-aviso-ao-titular-registro-e-janela.md` (nova) | ADR **aceita**, fonte "[W] 2026-10-01 — D5 aprovada"; índice `_INDEX-GENERATED.md` regenerado |
| `Modules/Arquivos/Database/Migrations/2026_10_01_000001_add_titular_avisado_at_and_notice_to_arquivos.php` | `arquivos.titular_avisado_at` (nullable) + `notice` no enum de `arquivos_audit_log.action` (3º alargamento, MySQL-only). Idempotente; `down()` recusa reverter com aviso gravado |
| `Modules/Arquivos/Services/AvisoTitularService.php` | `elegiveis(biz)` (leitura) + `registrarAviso(biz, id, canal)` (coluna + linha `notice` na mesma transação, idempotente, payload sem PII) |
| `Modules/Arquivos/Tests/Feature/AvisoTitularServiceTest.php` | 6 testes: janela, registro idempotente, **não apaga e não envia** (`Notification::fake`/`Mail::fake`), **cross-tenant 98 × 99**, enum aceita `notice`, canal vazio recusado |
| `.github/workflows/arquivos-pest.yml` | +1 linha: a lane lista ARQUIVO, então o teste novo só roda se entrar na lista (§5 2026-08-02) |

Regras aplicadas (ficha 05): só `bucket = sensitive`; janela = 1 a 30 dias antes do vencimento;
vencimento = `created_at` + prazo, com o prazo resolvido igual à tela do acervo
(`ArquivosAdminController::linha`). Avisar não toca `deleted_at` nem chama purge.

## Pendente, e por quê
- **Canal do aviso — pendência [W].** Nem a ficha nem a proposta dizem por qual canal o titular
  é avisado. Não inventei: o service **não envia** e **nada o chama**. Gravar "avisado" sem ter
  avisado seria registro falso. Quem implementar o canal chama `registrarAviso()` depois de enviar.
- **"Titular identificado" — critério conservador, a confirmar por [W].** O schema não tem coluna
  de titular. Adotei: dono do arquivo é `App\Contact`. Ticket/OS/venda ficam fora até [W] dizer.
- **Arquivo já vencido sem aviso** fica fora da janela (o aviso é prévio). Decisão [W] se deve
  entrar.
- **Tela:** não mexi em `Pages/Arquivos/`. Ela diz que o aviso não existe, e isso segue verdade
  enquanto não houver canal.
- **Errata do prefixo:** a ADR (`memory/decisions/`), o teste e a linha no workflow ficam fora do
  prefixo da thread — os três foram pedidos pela sessão-mãe ou exigidos para o teste rodar.
- **Índice:** pedir ao Cowork `D5.respondida = true` no json (já está em `_DECISOES-W-2026-10-01.md`).

## Provas do json
- `provas: []` — a prova é este recibo. Migration com o nome acima agora existe.
- Pest: NÃO rodei local (regra do projeto). A prova é o CI do PR, lane `PHP / Pest (Arquivos · MySQL)`.

## PR
Branch `claude/arquivos-thread-05` (número no corpo do PR).
