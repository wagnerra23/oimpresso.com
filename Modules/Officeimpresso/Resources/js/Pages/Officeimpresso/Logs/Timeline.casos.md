---
id: modules-officeimpresso-pages-logs-timeline-casos
casos: Timeline da máquina · /officeimpresso/licenca_log/timeline/{licenca_id}
irmaos: Timeline.charter.md (lei) · Timeline.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o recorte (200 acessos, source delphi_middleware, endpoint processa-dados-cliente) e os códigos 403/404 são contrato — não mudam quando a tela ganhar coluna.
owner: wagner
last_run: "2026-08-19"
---

# Casos de Uso & Aceite — Timeline da máquina

> Contrato derivado do **`LicencaLogController::timeline()`**, do Blade legado e do
> [logs-parity.md](../../../../../../../memory/requisitos/Officeimpresso/logs-parity.md) itens 38-51 —
> **não do `Timeline.tsx`**. Ancorado em **US-OI-005**
> ([SPEC](../../../../../../../memory/requisitos/Officeimpresso/SPEC.md)).
>
> ⚖️ **Onde rodam:** lane `officeimpresso-pest` (MySQL real, allowlist explícita). Tenant canônico
> **98** ([ADR 0358](../../../../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).
>
> **Status:** ✅ passa e com prova no manifesto · 🧪 escrito e na allowlist, **veredito ainda não
> emitido** · ⬜ não verificado · ❌ o teste prova comportamento indesejado.
>
> **Recibo honesto (2026-08-19):** todos **🧪**. O arquivo entrou na allowlist no mesmo PR que o
> criou, mas **a lane ainda não rodou** — Pest local é proibido e o CT 100 está com checkout de
> outra sessão. *"Está na allowlist"* não é *"passou"*.
>
> Os ids **UC-TL-01 a 04 estão reservados** para os `[BACKLOG]` do fim deste arquivo — não
> reutilizar.

## Rastreabilidade

| UC | Caso de uso | Prio | Teste | Status |
|----|-------------|------|-------|--------|
| UC-TL-05 | Autenticado sem permissão é barrado na timeline | must `[sec]` | `LogsBaselineTest` | 🧪 |
| UC-TL-06 | Máquina inexistente devolve 404 pra quem TEM permissão | must | `LogsBaselineTest` | 🧪 |
| UC-TL-07 | Com a flag ON a timeline responde Inertia com a máquina | must | `LogsBaselineTest` | 🧪 |
| UC-TL-08 | A timeline traz só os acessos daquela máquina | must | `LogsBaselineTest` | 🧪 |
| UC-TL-09 | O `was_blocked` do metadata é preservado | must | `LogsBaselineTest` | 🧪 |
| UC-TL-10 | A flag ON não afrouxa a guarda da timeline | must `[sec]` | `LogsBaselineTest` | 🧪 |

---

## UC-TL-05 · Autenticado sem permissão é barrado · `must [sec]`

**Status:** 🧪 — teste escrito e na allowlist da lane; a lane ainda não rodou.

**Dado** um usuário logado sem `superadmin` nem `officeimpresso.access`
**Quando** ele abre a timeline de qualquer máquina pela URL direta
**Então** recebe **403**.
**Por quê este caso existe:** a lista já era coberta pelo `LicencasAcessoPermissionTest`; a
**timeline não estava coberta por ninguém** até esta onda.

## UC-TL-06 · Máquina inexistente é 404, não 403 · `must`

**Status:** 🧪 — teste escrito e na allowlist da lane; a lane ainda não rodou.

**Dado** um usuário **com** `officeimpresso.access`
**Quando** ele abre a timeline de um `licenca_id` que não existe
**Então** recebe **404** — não 403, não redirect.
**Por quê:** a guarda roda **antes** do lookup. Os dois códigos dizem coisas diferentes
("você não pode ver" × "isso não existe") e a migração tem que preservar os dois.

## UC-TL-07 · Flag ON responde Inertia com a máquina · `must`

**Status:** 🧪 — teste escrito e na allowlist da lane; a lane ainda não rodou.

**Dado** a flag `useV2OfficeimpressoLogs` ligada e uma máquina cadastrada
**Quando** a timeline dela é aberta
**Então** a resposta é Inertia com componente `Officeimpresso/Logs/Timeline` e `maquina.id` igual
ao pedido.
**E** `logs` **não** vem no payload inicial — é `Inertia::defer`.

## UC-TL-08 · Só os acessos daquela máquina · `must`

**Status:** 🧪 — teste escrito e na allowlist da lane; a lane ainda não rodou.

**Dado** duas máquinas, uma com 2 acessos e outra com 1
**Quando** a timeline da primeira é aberta
**Então** vêm exatamente **2** registros, todos com o `licenca_id` dela.
**Por quê:** vazar acesso de outra máquina aqui é pior que mostrar de menos — o suporte usa esta
tela pra afirmar ao cliente o que aconteceu no computador **dele**.

## UC-TL-09 · O `was_blocked` do metadata é preservado · `must`

**Status:** 🧪 — teste escrito e na allowlist da lane; a lane ainda não rodou.

**Dado** um acesso registrado com `metadata.was_blocked = true`
**Quando** a timeline é aberta
**Então** aquele registro chega à tela com `was_blocked` verdadeiro.
**Por quê:** `metadata` chega como **objeto OU string JSON** conforme o caminho de leitura (o cast
`array` do model não vale pra consulta via `DB::table`). Ler errado faz a coluna "Estado no login"
afirmar que a licença estava liberada num acesso em que ela estava travada.

## UC-TL-10 · Flag ON não afrouxa a guarda · `must [sec]`

**Status:** 🧪 — teste escrito e na allowlist da lane; a lane ainda não rodou.

**Dado** um autenticado sem permissão **e** a flag `useV2OfficeimpressoLogs` ligada
**Quando** ele abre a timeline
**Então** recebe **403** — o caminho React não pode virar porta dos fundos.
**Por quê é caso próprio:** o UC-LOGS-10 cobre a lista. O render da timeline entra num PR
diferente (junto da page dela, pra não criar render órfão), então precisa da sua própria prova.

---

## `[BACKLOG]` — comportamento real, ainda sem teste que o cite

Sem id de propósito (G-2: UC sem teste que o cite é órfão). Os ids UC-TL-01 a 04 estão reservados
pra estes quando ganharem teste.

- `[BACKLOG]` O selo de estado no topo respeita a precedência empresa > máquina > ativa. *(parity 41, alta)*
- `[BACKLOG]` O recorte é de 200 registros, `source=delphi_middleware` **e** endpoint `processa-dados-cliente`, ordem desc. *(parity 43/44 — o 44 é alta e já tem teste parcial via UC-TL-08)*
- `[BACKLOG]` Status HTTP < 400 aparece como sucesso e ≥ 400 como falha.
- `[BACKLOG]` Data/hora no formato `d/m/Y H:i:s`, duração em `Nms`, e travessão quando vazio.
- `[BACKLOG]` O estado vazio explica que a máquina está cadastrada mas o Delphi nunca chamou dali.
- `[BACKLOG]` O botão Voltar leva à lista preservando a navegação.
