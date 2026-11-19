---
id: resources-js-pages-settings-payment-gateways-cnab-retorno-casos
casos: Retorno CNAB · /settings/payment-gateways/{id}/cnab-retorno
irmaos: CnabRetorno.charter.md (lei · draft)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-11-19"
---

# Casos de Uso & Aceite — Retorno CNAB

> ⛔ Tela Tier-0 **DINHEIRO** — o upload de retorno CNAB **reconcilia cobranças** (marca `paga`/`cancelada`/`vencida` + dispara baixa no Financeiro via `CnabRetornoProcessor`). Persona: **Wagner/operador financeiro**.
> Passo 3 do [template-onda-modulo] — complementa a [CAPTERRA-FICHA](../../../../../memory/requisitos/PaymentGateway/CAPTERRA-FICHA.md) (nota 67).
>
> **Status:** ✅ passa (UC-id no manifesto) · 🧪 comportamento tem teste Feature sem UC-id (débito G-2) · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **Onde roda (medido 2026-11-19).** O motor é coberto por `Modules/PaymentGateway/Tests/Feature/CnabRetornoProcessorTest.php`: **7 casos**, na lane **sqlite** do `ci.yml` (`.github/ci-sqlite-pest.list`; no MySQL o arquivo pula, schema sintético). Até 2026-11-19 este arquivo dizia "8 casos Feature/MySQL verdes no CT100" — eram 7, e não rodam no MySQL. Run [44509368899](https://github.com/wagnerra23/oimpresso.com/actions/runs/44509368899) (push em main, 2026-11-19): os 7 com `✓`, ainda com os títulos sem UC-id. A tela é coberta por `tests/Browser/PaymentGateway/CnabRetornoBrowserTest.php` (UC-PGCNAB-08..09, com axe CRITICAL) e o envio de arquivo por `tests/Feature/PaymentGateway/CnabRetornoUploadContratoTest.php` (UC-PGCNAB-10), no step advisory `E2E de comportamento + axe · PaymentGateway/CnabRetorno` do `visual-regression.yml`.
>
> 🔴 **Dente de cálculo (D1 · REGRA MESTRE valor):** o upload move VALOR (quita títulos) **sem preview antes→depois** — processa em background direto. É a exposição Tier-0 nº 1 desta tela. Fica no backlog como P0 (PR 4.3 da auditoria de 2026-11-19).

---

## Casos com id (promovidos do backlog em 2026-11-19)

> **Fonte de cada UC:** [ADR 0170 (bancos nativos, drivers separados)](../../../../../../../memory/decisions/0170-bancos-nativos-top5-drivers-separados.md) §Ondas, Onda 4f.0 ("Job `CnabRetornoProcessor` (upload arquivo retorno → parse → dispatch `CobrancaPaga`/`CobrancaVencida`)" e "UI upload manual + histórico processamento") + §Tier 0 ("`business_id` global scope honrado") + charter G2/G3/G4. O `PaymentGateway/SPEC.md` não tem US de retorno CNAB e não há SDD com CU do tema; nenhum UC vem do `.tsx`.
>
> **Prefixo `UC-PGCNAB-`**: o irmão `Index.casos.md` usa `UC-PGSET-`; prefixo por tela evita que a prova de um credite o outro (§5 2026-09-04).
>
> **Status:** 🧪 = tem teste citando o id, mas o manifesto `scripts/casos-test-results.json` ainda não tem veredito dele (01..07 passaram no run [44509368899](https://github.com/wagnerra23/oimpresso.com/actions/runs/44509368899) com o título antigo; 08..09 nascem neste PR no step advisory de navegador; 10 na lane `paymentgateway-pest.yml`).

## UC-PGCNAB-01 · `💰` Liquidação (ocorrência 06) quita a cobrança
- **Aceite:** Dado um retorno com ocorrência 06 para um nosso número emitido · Quando o retorno é processado · Então dispara `CobrancaPaga` para a cobrança liquidada e `qtd_paga` sobe.
- **Teste:** `CnabRetornoProcessorTest` — `UC-PGCNAB-01 · dispatcha CobrancaPaga…`
- **Status: 🧪**

## UC-PGCNAB-02 · `💰` Baixa sem pagamento (ocorrência 09) cancela
- **Aceite:** Dado ocorrência 09 · Quando processo · Então dispara `CobrancaCancelada`.
- **Teste:** `CnabRetornoProcessorTest` — `UC-PGCNAB-02 · dispatcha CobrancaCancelada…`
- **Status: 🧪**

## UC-PGCNAB-03 · `💰` Entrada (02) com vencimento passado marca vencida
- **Aceite:** Dado ocorrência 02 com vencimento anterior a hoje · Quando processo · Então dispara `CobrancaVencida`.
- **Teste:** `CnabRetornoProcessorTest` — `UC-PGCNAB-03 · dispatcha CobrancaVencida…`
- **Status: 🧪**

## UC-PGCNAB-04 · `💰` Reprocessar não duplica a baixa
- **Aceite:** Dado cobrança com `paga_em` já preenchido · Quando o mesmo retorno é processado de novo · Então `CobrancaPaga` não é disparado outra vez.
- **Teste:** `CnabRetornoProcessorTest` — `UC-PGCNAB-04 · é idempotente…`
- **Status: 🧪**

## UC-PGCNAB-05 · `[T0]` Retorno não toca cobrança de outro business
- **Aceite:** Dado um nosso número que existe só no business 99 · Quando o retorno da credencial do 98 é processado · Então a cobrança do 99 não muda ([ADR 0093]).
- **Teste:** `CnabRetornoProcessorTest` — `[T0] UC-PGCNAB-05 · respeita business_id global scope…`
- **Status: 🧪**

## UC-PGCNAB-06 · O processamento grava as métricas do upload
- **Aceite:** Dado um upload registrado · Quando o Job termina · Então `cnab_retorno_uploads` recebe `processado_em` e `qtd_paga`/`qtd_cancelada`/`qtd_vencida`/`qtd_registrada` (charter G3).
- **Teste:** `CnabRetornoProcessorTest` — `UC-PGCNAB-06 · grava CnabRetornoUpload com métricas…`
- **Status: 🧪**

## UC-PGCNAB-07 · Arquivo ausente não derruba o Job
- **Aceite:** Dado um upload cujo arquivo não existe no disco · Quando o Job roda · Então termina sem exceção e registra o erro no upload (charter G4).
- **Teste:** `CnabRetornoProcessorTest` — `UC-PGCNAB-07 · não quebra quando arquivo de retorno não existe`
- **Status: 🧪**

## UC-PGCNAB-08 · O histórico mostra contadores, erros e pendentes
- **Persona:** operador financeiro, conferindo o que o banco devolveu.
- **Aceite:** Dado um upload processado (3 pagas, 1 cancelada, 2 vencidas, 4 registradas, 1 erro) e um não processado · Quando abro a tela · Então a linha do processado mostra `3 | 1 | 2 | 4` e "1 erro(s)", o erro só aparece depois de "Ver", e o outro aparece como "Pendente" (charter G3/G4).
- **Teste:** `tests/Browser/PaymentGateway/CnabRetornoBrowserTest.php` — `UC-PGCNAB-08 …` (com axe CRITICAL)
- **Status: 🧪** sem veredito (step advisory novo).

## UC-PGCNAB-09 · `[T0]` Tela de retorno de credencial alheia devolve 404
- **Aceite:** Dado uma credencial do business 99 · Quando a sessão do 98 pede `/settings/payment-gateways/{id}/cnab-retorno` · Então 404; a credencial do próprio 98 devolve 200 (controle) ([ADR 0093]).
- **Teste:** `CnabRetornoBrowserTest` — `[T0] UC-PGCNAB-09 …`
- **Status: 🧪** sem veredito.

## UC-PGCNAB-10 · Extensão fora da lista é recusada sem gravar upload
- **Aceite:** Dado um arquivo `.pdf` · Quando envio · Então a tela mostra "Extensão não aceita" e nenhum `cnab_retorno_uploads` é gravado (charter G2; a lista vem de `limites.extensoes`).
- **Teste:** `tests/Feature/PaymentGateway/CnabRetornoUploadContratoTest.php` — `UC-PGCNAB-10 …` (lane `paymentgateway-pest.yml`, MySQL; com controle positivo `.ret` aceito). Não é E2E: o servidor HTTP do Pest Browser não repassa arquivos ao Laravel (`LaravelHttpServer.php`, `[], // @TODO files...`) — medido no run 44542116020, o envio pela tela chegou sem arquivo ("O campo arquivo é obrigatório").
- **Status: 🧪** sem veredito.

---

## Backlog de casos (sem id — entram quando um teste citar o UC-id)

> Regra G-2: UC em heading `## UC-*` sem teste que o cite = órfão → quebra `casos-gate`. Bullets até o id ser wired.

- **[BACKLOG · ⬜ sem teste · UI] Validação de extensão/tamanho no front antes do envio** — G2 do charter fala em validar "no front"; hoje o front só põe `accept` no input e quem recusa é o servidor (UC-PGCNAB-10).
- **[BACKLOG · ⬜ sem teste · 🔴 P0 valor] Preview antes→depois antes de aplicar** — Dado arquivo carregado · Quando confirmo · Então vejo as cobranças e valores que serão baixados ANTES de aplicar (REGRA MESTRE). Hoje inexistente — PR 4.3 da auditoria de 2026-11-19.
- **[BACKLOG · charter draft] Non-Goals a resolver com Wagner** — reprocessamento (NG1), download do arquivo/relatório (NG2), upload em lote (NG3), AuditLog por processamento. Charter ainda `draft`.

## Como rodar a suíte
1. **Pest:** `CnabRetornoProcessorTest` (7 casos) na lane sqlite do `ci.yml`; `CnabRetornoUploadContratoTest` (1 caso) na `paymentgateway-pest.yml`; `CnabRetornoBrowserTest` (2 casos) no `visual-regression.yml`. Local só no CT100 ([ADR 0062]).
2. **Cadência:** rodar ao fim de toda mexida. UC ❌ = regressão → lição + conserto.

## Trilha do tempo
- 2026-11-19 · [CC] Auditoria PaymentGateway lote 4.2: 7 bullets do backlog promovidos a `UC-PGCNAB-01..07` (citados nos títulos do `CnabRetornoProcessorTest`) + `UC-PGCNAB-08..09` com E2E de navegador e axe, `UC-PGCNAB-10` em teste Feature (o Pest Browser não envia arquivo). Corrigido "8 casos MySQL" → 7 casos na lane sqlite.
- 2026-07-03 · [CC] criado no Passo 3 (régua por tela). Baseline de valor forte (8 casos Feature) mas 0 UC-id; charter ainda `draft` com Non-Goals abertos; falta preview antes→depois (D1 valor). Nota UX 76 (Advanced).

[template-onda-modulo]: ../../../../../memory/requisitos/_Governanca/programa-ondas/template-onda-modulo.md
[ADR 0264]: ../../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
[ADR 0062]: ../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md
