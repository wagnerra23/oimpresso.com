---
id: resources-js-pages-jana-memoria-casos
casos: Jana Memória · fatos aprendidos · LGPD Art. 18 · /ia/memoria
irmaos: Memoria.charter.md (lei) · memory/requisitos/Jana/RUNBOOK-memoria.md (runbook)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-08-17"
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

## Divergências protótipo × produção — decididas

- ✅ **Escala de relevância — FICA `/10`.** Decisão [W] 2026-08-07: a **produção é a fonte** e o
  protótipo (que desenha 1–5) é que se adapta. Mudar seria migração de `metadata.relevancia` já
  gravado, sem razão de domínio que a justifique. **Não re-propor** sem sinal novo: divergência
  protótipo×prod não é, por si, motivo pra mexer em dado persistido.
- ✅ **`origem` passou a ser renderizada** (decisão [W] 2026-08-07, PR à parte). O dado já vinha no
  payload e não aparecia — o titular via *o que* a Jana aprendeu, mas não *de onde*. Fecha o Goal 4
  do charter (*"Mostrar `origem` do fato (chat / brief auto / inserção manual) — transparência"*).

## Revalidação de 2026-08-17 — por que o `last_run` subiu

O G-6 acusou `stale:` porque o `Memoria.tsx` mudou depois do `last_run` de 08-08. O que mudou:
as 5 pills de categoria (`meta` `preferencia` `restricao` `contexto` `acao_pendente`) e o
fallback saíram de `bg-<cor>-100 text-<cor>-800` — **escala crua sem par `dark:`, ilegível no
tema escuro nas cinco** — para as variantes soft do `<Badge>`, que carregam light+dark no token.

**Interseção com os UCs desta tela: nenhuma.** Os cinco tratam de comportamento de servidor
(motivo obrigatório na edição, trilha em `activity_log`, redação de PII, trilha no esquecer);
o diff não toca controller, service, validação nem payload — só qual `variant` o chip recebe.
Por isso o bump é do `last_run`, e nada de `Status:` mudou: os cinco seguem `🧪`, como estavam.

Registrado porque o §5 de 2026-07-27 já cataloga esta classe: mudança semanticamente inerte
**não é inerte pro gate** — o G-6 mede data de git, não semântica. O `last_run` só sobe com o
motivo escrito ao lado; subir o número calado é o que ele existe pra impedir.

- `[BACKLOG]` a legibilidade das pills no tema escuro virou contrato de fato nesta tela, e hoje
  nenhum UC a cobre — ficaria como UC quando existir teste que a prove (visual-regression da
  aba Memória ou asserção de `variant`). Sem id de propósito: UC sem teste que o cite quebra o
  G-2, e prometer teste que não existe é pior que a ausência.

## Ainda ABERTAS (sem decisão)

- ❓ **Edição parcial** — o protótipo edita `categoria` e `relevância`; a produção só edita o texto.
- ❓ **Naming** — a tela ainda diz "O Copiloto lembra de você" (h1 + título do shell) enquanto o
  resto do módulo já é "Jana" (`RUNBOOK-chat.md`: *"Em texto novo sempre Jana"*). Mexer no título
  toca o breadcrumb do shell, então não é edição de 1 linha.
