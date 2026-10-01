---
id: modules-whatsapp-resources-js-pages-atendimento-channels-index-casos
casos: Canais do atendimento — lista e cadastro · /atendimento/canais
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do gestor + critério de aceite verificável (Dado/Quando/Então)
por_que: é a porta de entrada do atendimento — sem canal pareado a caixa fica vazia; e é tela de gestão, então mutar canal de outro business é vazamento Tier 0.
owner: wagner
last_run: "2026-09-30"
---

# Casos de Uso & Aceite — Canais (lista)

> Fonte: `Index.charter.md` (Mission · Goals · Non-Goals · Anti-hooks) · ADR 0093 (multi-tenant
> Tier 0) · ADR 0135 (omnichannel). **Não derivados do `.tsx`** (lápide §5 2026-06-05).
>
> **Teste:** `tests/Feature/Whatsapp/AtendimentoChannelsContratoTest.php` — lane sqlite
> (`.github/ci-sqlite-pest.list`). Em MySQL o arquivo pula por desenho: skip não é verde (LC-13).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC e passa (manifesto não regravado) ·
> ⬜ não verificado · ❌ quebrou.

---

## UC-CNL-01 · Vejo só os canais da minha empresa
- **Persona:** gestor do atendimento — abre `/atendimento/canais` pra conferir os números da empresa.
- **Aceite:** Dado canais no meu business e no de outro business · Quando abro a lista · Então vejo só os meus, e a lista vem sob demanda (não bloqueia a abertura da tela).
- **Teste:** `UC-CNL-01 · lista só os canais do business da sessão (Tier 0) e carrega a lista sob demanda`.
- **Regressão que defende:** charter Goal *"Listar todos os canais do `business_id` atual"* + Anti-hook *"mutar canal alheio"*. Uma query sem o filtro não dá erro — mostra o canal do vizinho, com o número dele.
- **Status: 🧪** — passou na lane sqlite do PR #8327 (run 36801547031, `PHP / Pest (Unit)`, 2026-10-01); ✅ quando `casos:results` regravar o manifesto.

---

## UC-CNL-02 · Canais em pré-visualização aparecem, mas não podem ser escolhidos
- **Persona:** gestor cadastrando um canal novo.
- **Aceite:** Dado o formulário de canal novo · Quando abro a lista de tipos · Então Instagram, Messenger e Email aparecem desabilitados, e o WhatsApp oficial (Meta) aparece habilitado.
- **Teste:** `UC-CNL-02 · canais em pré-visualização aparecem no cadastro mas não podem ser escolhidos`.
- **Regressão que defende:** charter Mission *"IG/FB/Email preview-only"*. Habilitar um tipo sem driver cria canal que nunca recebe mensagem.
- **Status: 🧪** — passou na lane sqlite do PR #8327 (run 36801547031); ✅ com o manifesto regravado.

---

## UC-CNL-03 · Canal novo nasce pendente de configuração, na minha empresa
- **Persona:** gestor cadastrando o WhatsApp do comercial.
- **Aceite:** Dado um cadastro com tipo e telefone · Quando salvo · Então o canal fica no meu business, com status `setup`, o telefone vira o identificador exibido e, por ser driver não-oficial, o aceite LGPD é gravado com o meu usuário.
- **Teste:** `UC-CNL-03 · novo canal nasce em setup, no business da sessão, com aceite LGPD se não-oficial`.
- **Regressão que defende:** charter Goal *"criar canal novo apontando driver + display_identifier"*. O business vem da sessão, nunca do formulário.
- **Status: 🧪** — passou na lane sqlite do PR #8327 (run 36801547031); ✅ com o manifesto regravado.

---

## UC-CNL-04 · Não consigo remover canal de outra empresa
- **Persona:** gestor do business 98 tentando apagar um id que não é dele.
- **Aceite:** Dado um canal de outro business · Quando peço a remoção pelo id · Então recebo 404 e o canal continua existindo.
- **Teste:** `UC-CNL-04 · não remove canal de outro business — 404 e o canal continua lá`.
- **Regressão que defende:** charter Anti-hook *"Mutar canal alheio (`business_id != session`)"* · Tier 0.
- **Status: 🧪** — passou na lane sqlite do PR #8327 (run 36801547031); ✅ com o manifesto regravado.

---

## Backlog (prosa, sem UC — não há teste que os cite)

- [BACKLOG] **Remoção: charter × código discordam.** O charter diz *"NÃO permite deletar canal (apenas soft-disable)"*; `ChannelsController::destroy` chama `$channel->delete()` e `Channel` não usa `SoftDeletes`. Não virou UC porque o teste reprovaria contra a lei ou consagraria o código contra ela — é decisão [W] qual dos dois está certo.
- [BACKLOG] **Lista de tipos:** o charter proíbe *"Hardcode driver list — vem de `ChannelDriverFactory::availableDrivers()`"*; hoje a lista vem de `availableTypesForUi()`, escrita no controller. Mesma natureza: decisão [W].
- [BACKLOG] Badge de `driver_health` com cor semântica e empty state *"Nenhum canal cadastrado"* (charter UX targets) — sem teste de render nesta onda.
