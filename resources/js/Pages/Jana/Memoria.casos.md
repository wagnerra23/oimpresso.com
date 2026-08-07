---
id: resources-js-pages-jana-memoria-casos
casos: Jana Memória · fatos aprendidos · LGPD Art. 18 · /ia/memoria
irmaos: Memoria.charter.md (lei) · memory/requisitos/Jana/RUNBOOK-memoria.md (runbook)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-08-07"
---

# Casos de uso — /ia/memoria (Memória da Jana)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Derivados do `Memoria.charter.md` (Goals/Non-Goals/Anti-hooks) + do protótipo `JmMemoria`
> (`prototipo-ui/cowork/jana-merge.jsx`, lido no DesignSync 2026-08-07) — **não** do `.tsx`.
> Derivar do código seria tautológico (§5 2026-06-05): passaria verde mesmo com o comportamento
> errado, que é exatamente o que estava acontecendo aqui.
>
> **Estado medido ANTES desta leva (2026-08-07):** o charter mandava, desde 2026-05-16, registrar
> "autor/quando/**motivo**" no activitylog e proibia "update direto sem activitylog". O código
> validava só `fato`, o `useForm` mandava só `fato` (0 hits de `motivo`) e **nenhum teste mordia**.
> A lei existia e valia zero — é o caso do charter que não é lei porque ninguém a executa.

## UC-MEM-01 — Editar um fato SEM motivo é rejeitado
Status: 🧪 (MemoriaEdicaoMotivoTest · lane `jana-pest.yml`, MySQL real)
O titular abre a edição de um fato e tenta salvar sem preencher o motivo. O servidor **reprova**
antes de tocar o driver de memória — a UI desabilitar o Salvar é conveniência, não garantia
(contornar a tela não contorna a regra). Âncora: charter Anti-hooks *"⛔ Update direto sem
`activitylog` — quebra audit trail LGPD Art. 18"* + protótipo (`disabled={!rascunho.fato.trim() || !rascunho.motivo.trim()}`).
**Pronto quando:** `PATCH /ia/memoria/{id}` sem `motivo` volta com erro de validação em `motivo`
e **não** grava nada. _(O teste checa também que a resposta é 302 — anti-vácuo: 403/404 fariam o
assert passar por engano.)_

## UC-MEM-02 — Motivo em branco ou curto demais não conta como motivo
Status: 🧪 (MemoriaEdicaoMotivoTest)
`''`, `'   '` e `'ok'` são reprovados. Espaço em branco satisfazer um campo obrigatório é a forma
mais barata de esvaziar a regra sem parecer que se esvaziou.
**Pronto quando:** os três payloads voltam com erro em `motivo` (mínimo 3 caracteres, `string`).

## UC-MEM-03 — Editar COM motivo grava a trilha com autor + motivo
Status: 🧪 (MemoriaEdicaoMotivoTest)
Com motivo preenchido a correção passa e nasce **uma** linha em `activity_log` sob
`log_name = jana_memoria_fato_editado`, com `causer_id` (quem editou) e `properties.motivo`.
Âncora: charter Goals *"Editar fato inline … com `activitylog` registrando autor/quando/motivo"*
+ copy do protótipo *"Toda alteração registra autor e motivo no log de auditoria"*.
**Pronto quando:** a contagem sobe exatamente 1, `causer_id` não é nulo e `properties.motivo`
bate o texto enviado.

## UC-MEM-04 — PII do motivo é redigida antes de persistir
Status: 🧪 (MemoriaEdicaoMotivoTest)
O motivo é prosa digitada pelo titular — pode conter CPF. Passa por `PiiRedactor` antes de ir pro
`activity_log`, que **nunca é purgado** (`Modules/Jana/Config/retention.php`: *"activity_log é
AUDITORIA — NUNCA purgada"*). Âncora: charter Anti-hooks *"⛔ Render texto fato sem `PiiRedactor`
se contém CPF/CNPJ — Tier 0 LGPD"* + a decisão já tomada na entity (`logOnly([...])`, comentário
*"NÃO logga `fato`/`metadata` (PII livre)"*).
**Pronto quando:** motivo com CPF é gravado como `[REDACTED:CPF]` e o número cru não aparece.

## UC-MEM-05 — Esquecer um fato também deixa trilha
Status: 🧪 (MemoriaEdicaoMotivoTest)
Apagar é "alteração" pro Alert da tela. Trilha que registra edição mas não exclusão é trilha
quebrada — daria pra apagar um fato sem deixar rastro, que é o oposto do Art. 18. **Não** exige
motivo: o protótipo confirma o apagar inline, sem campo.
**Pronto quando:** `DELETE /ia/memoria/{id}` acrescenta 1 linha sob `jana_memoria_fato_esquecido`.

---

## Fora do alcance do Pest de Controller (⬜ contrato visual / smoke real)

> Honestidade de escopo: os itens abaixo são do charter e do protótipo, mas são **client-side** —
> Pest de Controller não morde. Ficam pro contrato visual + smoke real (RUNBOOK-memoria.md passo 6).

- ⬜ **Salvar desabilitado** enquanto texto **ou** motivo estiverem vazios (o servidor já é travado por UC-MEM-01).
- ⬜ **Copy literal do Alert** — "Memória da Jana — LGPD Art. 18" + "Você vê, corrige e apaga qualquer fato que a Jana aprendeu sobre o seu negócio. Toda alteração registra autor e motivo no log de auditoria."
- ⬜ **Rodapé da edição** "Toda correção registra autor, horário e motivo."
- ⬜ **Busca** "Buscar em fatos…" e **filtro por categoria** — implementados; a lista agora FILTRA
  em vez de AGRUPAR. Os chips são **derivados do dado**, não da lista literal do protótipo: aquela
  (`preferência/operação/financeiro/…`) é a taxonomia do mock do Martinho, a de produção é outra
  (`CATEGORIA_LABELS`). Traduz-se o comportamento, não a lista (§5 2026-07-16).
- ⬜ **Confirmação inline** do apagar, no lugar do `confirm()` nativo — implementada.
- ⬜ **Os DOIS empty states** ("nada aprendido" × "nenhum fato com esse filtro" + Limpar filtro) —
  implementados com a copy literal. Produção tinha só um, com texto diferente.

## Divergências protótipo × produção ainda ABERTAS (decisão [W])

- ❓ **Escala de relevância** — protótipo usa **1–5**, produção exibe **/10**. Mudar é **migração de
  dado** (`metadata.relevancia` já gravado), então segue como está até [W] decidir. Ver §5 da regra
  mestre de valor: mudança que mexe em dado gravado exige apresentar o antes→depois primeiro.
- ❓ **`origem` não é renderizada** em produção, mas o charter a exige no Goal 4 (*"Mostrar `origem`
  do fato (chat / brief auto / inserção manual) — transparência"*) e o protótipo mostra.
- ❓ **Edição parcial** — o protótipo edita `categoria` e `relevância`; a produção só edita o texto.
- ❓ **Naming** — a tela ainda diz "O Copiloto lembra de você" (h1 + título do shell) enquanto o
  resto do módulo já é "Jana" (`RUNBOOK-chat.md`: *"Em texto novo sempre Jana"*).
