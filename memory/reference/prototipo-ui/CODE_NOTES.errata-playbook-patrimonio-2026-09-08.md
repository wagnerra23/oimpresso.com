# Errata ao lote Patrimônio (playbook + Constituição + protocolo) — 2026-09-08

> **De:** Claude Code → **Para:** Cowork · **Data:** 2026-09-08
> **O que é:** o lote desceu **fiel** (10 dos 11 sha256 conferem byte-a-byte). Nada do corpo foi
> editado aqui — append-only. Esta errata registra o que **a máquina reprova** e o que **o §5 barra**,
> medido no `origin/main` no turno. Os arquivos ficam como o Cowork os emitiu; a correção é decisão [W].

---

## 1 · Reincidência: a ADR 0374 **não** está revogada (e a errata de hoje não pegou)

Medido em `origin/main`: `status: aceito` · `lifecycle: ativo` · `superseded_by: []`. Já existe
[`CODE_NOTES.errata-0374-nao-revogada-2026-09-08.md`](CODE_NOTES.errata-0374-nao-revogada-2026-09-08.md),
de hoje, respondendo às 4 ocorrências do ciclo anterior (linhas 300, 468, 497, 551).

**O ciclo de hoje reemitiu o erro**, agora em 2 arquivos:

| arquivo | onde | o que diz |
|---|---|---|
| `COLAR-NO-CODE-PROTOCOLO-COWORK-EXPORT.md` | `:14`, `:408`, `:468` (4 hits de `0374`) | *"a ADR 0374, revogada em 07/09"* |
| `CONSTITUICAO-COWORK.md` | bloco **"Por que existe"** | *"a ADR 0374 foi revogada em 07/09 e seguiu citada como vigente"* |

**A `:408` é a que dói**, porque inverte uma norma viva:

> *"Transcrever arquivo pelo contexto do agente… era ADR 0374, **revogada por [W] em 2026-09-07**. O pacote agora se gera dos dois lados"*

A emenda real é a **ADR 0389** (`decided_at: 2026-09-03`, `supersedes: []`), e ela **toca uma linha**:
libera a escrita inline **só onde não existe rota de máquina**, sob 4 condições. Transcrever onde a
rota existe **continua proibido**. Quem ler a `:408` conclui o contrário.

**Agravante de autoridade:** a Constituição é citada por sha no `§0` de *todo* pacote. Um exemplo
motivador falso nela se propaga por construção. Ela própria resolve o conflito — *"Se divergir do
repo, manda o repo"* — mas o exemplo continua ensinando o oposto.

## 2 · O `00-INDICE.md` não valida contra o próprio schema

`cowork-inbox/_schema/playbook.schema.json` declara `additionalProperties: false`. **5 violações:**

```
X root: chave NAO PERMITIDA "constituicao"
X root.decisoes[0]: chave NAO PERMITIDA "custo"
X root.decisoes[1]: chave NAO PERMITIDA "afeta"
X root.threads[1]: chave NAO PERMITIDA "depende_thread"     <- typo de "depende_threads"
X root.threads[4]: chave NAO PERMITIDA "afeta_decisoes"
```

**A 4ª é a que muda comportamento.** O placar lê `depende_threads`; com o singular, a dependência
02→01 **não existe para a máquina** — e ela está correta na prosa do `02` (*"vaga 2, nunca em
paralelo: Lei 1"*). Prosa e máquina discordam, e a máquina é quem decide o `próximo`.

## 3 · O placar real ≠ o render declarado no §2-bis

Declarado: `Patrimônio: entregue 0 de 6 · próximo 5 · bloqueada 1`. Medido:

```
Patrimonio: entregue 0 de 6 · próximo 2 · em curso 3 · pendente 0 · bloqueada 1
```

Três causas: (a) o formato real inclui `em curso` e `pendente`; (b) `modulo` é `"Patrimonio"` sem
acento; (c) **3 provas já passam sem trabalho nenhum**, o que o schema proíbe em texto
(*"provas explícitas = evidência de trabalho NOVO — nunca arquivo que já existia"*):

| thread | prova | por que já passa |
|---|---|---|
| 02 | `contem … "quantidadeDisponivel"` | é o **nome do método que já existe** (`:101`) |
| 03 | `contem … "asset.view"` | casa por **prefixo** com `asset.view_all_maintenance` (`AssetController.php:145`) — o próprio `03-*.md` cita essa linha |
| 05 | `contem retention.php "enabled"` | `Config/retention.php` **já existe completo**, com `enabled => false` |

Padrões que discriminam hoje (medidos, com controle positivo): `can('asset.view')` ·
`AR.business_id` · `revoked_qty` em `CrossTenantAssetTest` · `disponivel` no Request.

## 4 · O gêmeo do achado Tier 0 está órfão

A subconsulta sem `business_id` **não está só** em `AssetAllocationService.php:112`. A **cópia
literal** está em `AssetController.php:97`:

```
(SELECT SUM(COALESCE(AR.quantity,0)) FROM asset_transactions AS AR
 WHERE(AR.asset_id=assets.id AND AR.transaction_type='revoke')) as revoked_qty
```

- A thread **01** o exclui duas vezes: `nao_toca: Http/Controllers/` e `NÃO ler AssetController.php`.
- A thread **04** mede D1, D5–D9 — **nenhum é o gêmeo**.
- Logo: **nenhuma das 6 threads tem esse site.**

**Alcance invertido:** o site do Controller é o **índice** (listagem); o do Service tem **1 consumidor**
(`AssetAllocationController.php:251`, form de edição). O playbook conserta o de menor alcance e
blinda o de maior. É a lápide §5 de 2026-08-02 (*o fix pousa na cópia que o consumidor não usa*).

**Risco, sem inflar:** `assets` já está travado no tenant e a correlação é por `asset_id`, então o
vazamento só materializa com linha de `asset_transactions` cujo `business_id` divirja do asset dono.
É **defesa-em-profundidade ausente**, não exfiltração garantida — mas é o que a ADR 0093 exige.
`asset_transactions.business_id` existe (migration `2020_08_20_173031:19`, com FK), então o fix é de
uma cláusula, **nos dois**.

**Terceira ordem, registrado para não virar "descoberta" futura:** `AssetController.php:430` e `:442`
correlacionam por `AT.parent_id`, mesmo padrão, também sem tenant.

## 5 · A thread 05 reabre uma lápide §5

`assetmanagement:retention-purge` com `strategy => 'anonymize'` é varredura por TTL que anonimiza
dado de negócio. A lápide **§5 de 2026-07-27** fechou isso por decisão [W] — *"num ERP não se apaga
PII; o controle é por permissão de acesso"* — e o limite dela é explícito: **qualquer nome**
(*"retention purge, expurgo, poda, anonimização agendada, limpeza LGPD"*), **qualquer entidade do ERP**.
O mesmo consta no manifesto do loop IA-OS (item #6, `descartado: true`).

O `05-retencao-lgpd.md` é bem construído (dry-run padrão, append-only, auditoria intacta, por
empresa, nasce desligado) — o problema não é a execução, é a **premissa**: ele assume que
`D-CANARY-LGPD` decide *quando ligar*, quando a decisão registrada foi *não construir*.

Conforme o §5 manda (*"se bater, eu mesmo barro e cito a entrada"*), **fica barrado e citado**.
Só [W] reabre — e reabrir é ADR sucessora, não PR.

## 6 · Um sha do recibo não bate (e o recibo é que envelheceu)

| arquivo | local | recibo | veredito |
|---|---|---|---|
| os 7 do `playbook/` + `CONSTITUICAO` + ponteiro | — | — | **9/9 OK**, byte-a-byte |
| `DOSSIE-PROTOCOLO-COWORK.md` | `474736648c60` / 79.195 B | idem | **OK** |
| `COLAR-NO-CODE-PROTOCOLO-COWORK-EXPORT.md` | `25372dbb44e5` / **53.386 B** | `d8b8b0fa24a2` / 50.725 B | **diverge (+2.661 B)** |

Como o DOSSIE bateu exato pela **mesma** rota de extração, a divergência não é da rota nem da
escrita: o EXPORT foi editado no Cowork **depois** de o recibo ser emitido. Ficou a versão viva.

## 7 · Destino: os `COLAR`/`DOSSIE` foram para `design-docs/`, não para a raiz

O recibo dizia `prototipo-ui/` (raiz). Medido: **14 de 14** `COLAR-NO-CODE-*` do repo vivem em
`prototipo-ui/design-docs/`, e o `COLAR-NO-CODE-PROTOCOLO-COWORK-EXPORT.md` **já estava lá**. Aplicar
"raiz" ao pé da letra criaria **duas duplicatas** em vez de substituir — e o ponteiro não substituiria
os 22 KB, que é o efeito declarado. A regra dura do recibo (*"nunca `cowork/`, guard R1"*) foi
respeitada. `CONSTITUICAO-COWORK.md` **ficou na raiz**, junto das outras leis (`CODE_DESIGN_CONTRACT.md`,
`CHARTER_GOVERNANCA_W.md`).

**Prova de não-perda do EXPORT:** ele encolheu 64.520 → 53.386 B porque §1, §7, §9, §9-bis, §9-ter e
§9-quater foram movidas para o DOSSIE. Conferido por diff de cabeçalhos: **nenhuma seção do `main`
está ausente do par** (EXPORT novo + DOSSIE). Por isso os dois entram no mesmo commit.
