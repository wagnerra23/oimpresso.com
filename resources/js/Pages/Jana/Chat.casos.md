---
id: resources-js-pages-jana-chat-casos
casos: Jana Conversa · histórico · teclado · acessibilidade · /ia/conversa
irmaos: Chat.charter.md (lei) · memory/requisitos/Jana/RUNBOOK-chat.md (runbook)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-08-17"
---

# Casos de uso — /ia/conversa (Chat da Jana)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Derivados do `Chat.charter.md` (§Goals/§Automation Anti-hooks) e do protótipo
> `prototipo-ui/cowork/jana-merge.jsx` §`JmConversa` (âncora de símbolo) — **não** do `Chat.tsx`.
> Derivar do código seria tautológico (§5 2026-06-05).
>
> **Por que este arquivo nasceu em 2026-08-17:** ele não existia. O Chat é `tier: A`, `status: live`
> e é — palavras do próprio charter — *"o único ponto de IA conversacional cliente-facing do
> oimpresso"*. Estava sem **um** UC escrito, enquanto o Painel vizinho já tinha nove. Os 4 UCs
> abaixo **não** foram inventados: eles carimbam 15 testes que **já existiam e já passavam**
> (`tests/jana-chat-conversas.test.tsx`), rodados nesta data — `15 passed`. O que faltava era o
> contrato por cima deles.

## UC-COPI-CHAT-01 — O filtro filtra de verdade, e são DUAS abas de propósito
Status: ✅ (`tests/jana-chat-conversas.test.tsx` — 4 casos sob o describe que cita este UC)

A lista de conversas tem **duas** abas: **Todas** (tudo que não está arquivado) e **Arquivadas**.
"Todas" **esconde** a arquivada; "Arquivadas" mostra **só** ela.

⚠️ **Duas abas é DECISÃO, não lacuna.** O protótipo desenha quatro (`todas` · `minhas` ·
`compartilhadas` · `arquivadas`); o charter v3 reduziu para duas, e um dos testes crava isso pelo
nome: *"só existem 2 abas — Minhas/Compartilhadas foram removidas"*. Havia uma **fachada** — abas
que abriam um empty state "Em breve" — e ela foi removida. Outro teste guarda a remoção.
**Restaurar as 4 abas é reintroduzir a fachada**, não ganhar paridade com a âncora.

**Pronto quando:** cada aba mostra exatamente o seu conjunto, existem 2 abas, e nenhuma exibe "Em breve".

## UC-COPI-CHAT-02 — `J`/`K` andam entre CONVERSAS, respeitando o filtro
Status: ✅ (mesmo arquivo — 3 casos; o describe cita este UC)

`J` desce e `K` sobe **na lista de conversas** — não entre mensagens da thread. O charter registra a
correção de rota da v3 (*"era 'entre mensagens'"*) e o motivo, que é de negócio e não de estilo:
*"Larissa/Wagner trabalham no teclado"* — trocar de conversa é o que se faz o dia todo.

A navegação **respeita o filtro ativo**: na aba Arquivadas, `J`/`K` não pulam para uma conversa ativa.

**Pronto quando:** `J`/`K` percorrem a ordem visual da lista e nunca saem do conjunto filtrado.

## UC-COPI-CHAT-03 — Os atalhos não sequestram o teclado de quem está digitando
Status: ✅ (mesmo arquivo — 3 casos sob o UC-02 + 4 sob o UC-03)

Três recusas explícitas, cada uma com teste:

- **digitando** (foco em `input`/`textarea`) → `J`/`K` **não** disparam. Sem isso, escrever a
  palavra "jaqueta" no composer trocaria de conversa duas vezes.
- **com modificador** (`⌘J`/`Ctrl+J`) → inertes: são atalhos do browser.
- **na ponta da lista** → `K` no primeiro item é inerte, não dá a volta.

E o recolher tem **duas** teclas, não uma: `⌘⇧H` **e** `Ctrl+⇧H`. O teste nomeia o porquê:
*"Windows — a Larissa não usa Mac"*. A dica dos atalhos aparece na tela, e **o rail recolhido
mantém o atalho** — a lista nunca fica inalcançável.

**Pronto quando:** os três casos de recusa são inertes, as duas teclas recolhem, e a dica é visível
tanto expandido quanto recolhido.

## UC-COPI-CHAT-04 — Trocar de conversa é anunciado a leitor de tela
Status: ✅ (mesmo arquivo — 1 caso; o describe cita este UC)

Uma região `aria-live="polite"` anuncia `Conversa: <título>` quando a conversa ativa muda — por
clique **ou** por `J`/`K`. O anúncio é guardado por id, então re-render não re-anuncia a mesma
conversa.

**Pronto quando:** mudar a conversa ativa escreve o título na região viva; re-render sem troca não escreve.

## UC-COPI-CHAT-05 — Thread de outro business NUNCA é devolvida (Tier 0)
Status: 🧪 (`Modules/Jana/Tests/Feature/Chat/ChatAntiHooksTier0Test.php` — cita o UC no título; aguarda run verde na lane MySQL)

Um usuário de outro business pede a conversa pelo id e **não recebe 200**. Vale 403 (negado) ou 404
(nem existe pra ele); o que não pode é conteúdo alheio na tela.

Âncora: charter §Automation Anti-hooks *"⛔ Não acessa thread de outro `business_id`"* +
[ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md). Tenant fictício 98 e
um vizinho ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) — **nunca biz=4**.

⚠️ **Este UC pode nascer vermelho, e isso é o achado.** O `ChatController::show()` guarda por
`user_id` (`abort_unless($conversa->user_id === auth()->id(), 403)`), **não** por `business_id` — o
charter promete isolamento por BUSINESS. Se passar, o `user_id` cobre o caso na prática; se falhar,
o teste achou o buraco que o anti-hook descreve. Os dois desfechos são informação.

**Pronto quando:** o status não é 200 **nem 302** (anti-vácuo: redirect de login faria o assert
passar sem provar isolamento nenhum) e está em `[403, 404]`.

## UC-COPI-CHAT-06 — Abrir a thread é leitura PURA
Status: 🧪 (mesmo arquivo — cita o UC no título; aguarda run verde)

Abrir uma conversa **não dispara e-mail nem notificação**. Efeito colateral pertence ao POST de
mensagem, não à consulta.

Âncora: charter §Automation Anti-hooks *"⛔ Não dispara emails ao abrir (read da thread é puro)"* +
*"⛔ Não dispara SMS"*.

**Pronto quando:** `Mail::assertNothingSent()` e `Notification::assertNothingSent()` após o GET.

## UC-COPI-CHAT-07 — O render inicial não escreve no banco
Status: 🧪 (`ChatAntiHooksTier0Test` — cita o UC no título; aguarda run verde)

Abrir a conversa **não acrescenta linha** em `jana_mensagens`. Escrita pertence ao POST.

Âncora: charter §Anti-hooks *"⛔ Não escreve no banco no render inicial (só no POST de mensagem)"*.

O teste conta **antes e depois** em vez de assertar zero: a conversa pode nascer com mensagem de
sistema, e o contrato é sobre o GET **não acrescentar** — não sobre a thread estar vazia. Assertar
zero passaria a depender de um detalhe de seed, não do comportamento.

**Pronto quando:** a contagem depois do GET é idêntica à de antes.

## UC-COPI-CHAT-08 — O render não chama o Brain B nem vaza credencial
Status: 🧪 (`ChatAntiHooksTier0Test` — cita o UC no título; aguarda run verde)

Abrir a conversa **não faz chamada HTTP de saída** (`Http::preventStrayRequests()`), e o corpo
servido **não contém** o nome nem o valor da credencial do Brain B.

Âncora: charter §Anti-hooks *"⛔ Não chama Brain B no render (só após user submit)"* + *"⛔ Não
persiste credencial Brain B no client (token vive no backend)"*.

Testa **os dois**: o nome (`ANTHROPIC_API_KEY`, que denunciaria a prop trafegando) e o valor
configurado (que é o vazamento de fato). Só o nome não bastaria — um token servido sob outra chave
passaria batido.

**Pronto quando:** a resposta é 200 sem request de saída, e nenhuma das duas strings aparece no corpo.

---

## Inventário de cobertura — cada Goal e Anti-hook do charter, medido

> Os 4 UCs acima cobrem **o painel de histórico e o teclado**. O charter promete muito mais. Esta
> tabela é o retrato honesto de 2026-08-17: **o que está implementado** (medido por varredura em
> `Pages/Jana/**`) × **o que tem contrato** (UC + teste que o cite).
>
> A coluna que importa é a terceira. Implementado sem contrato significa: funciona hoje, e nada
> impede de sumir amanhã sem ninguém notar.

### §Goals — features

| Goal do charter | implementado | tem UC + teste |
|---|---|---|
| Layout 2-col: histórico + thread | ✅ | ✅ UC-01/02/03 |
| Filtro `Todas`/`Arquivadas` (2 abas, v3) | ✅ | ✅ UC-01 |
| Histórico recolhível (`⌘⇧H` · `Ctrl+⇧H` · chevron) | ✅ | ✅ UC-03 |
| Sobreposição ≤1100px com scrim clicável | 🟡 overlay sim, **scrim não** | ❌ |
| `aria-live` anuncia troca de conversa | ✅ | ✅ UC-04 |
| `J`/`K` navega conversas | ✅ | ✅ UC-02 |
| Bubbles por papel (`user` direita / `assistant` esquerda) | ✅ | ❌ |
| Bloco `tool_use` (chip da ferramenta acionada) | ✅ (6 refs) | ❌ |
| Bloco `data_table` (tabela inline read-only) | ✅ (2 refs) | ❌ |
| Bloco `action_card` (confirmação de ação) | ✅ (3 refs) | ❌ |
| Bloco `markdown` (fallback) | ✅ | ❌ |
| Composer multi-line com `⌘+Enter`/`Ctrl+Enter` | ✅ | ❌ |
| "Jana está pensando…" durante stream | ✅ (5 refs) | ❌ |
| Streaming token-a-token | ✅ (18 refs) | ❌ |
| `/` foca o composer | ✅ | ❌ |
| Persistência `localStorage` prefix `oimpresso.jana.*` | ✅ (7 refs) | 🟡 só o recolhido, via UC-03 |
| Multi-tenant Tier 0 (`business_id` em thread/mensagem/ação) | ✅ | 🧪 UC-05 |
| Aviso de PII no composer (CPF/CNPJ/cartão) | ✅ (`PiiRedactor`, 4 refs) | ❌ |

**16 Goals implementados · 5 com contrato · 11 sem.**

### §Automation Anti-hooks — o que a tela NUNCA dispara

O charter diz literalmente *"Vira Pest GUARD"*. **Seis dos oito viraram em 2026-08-17** (UC-05 a UC-08); sobram DOIS sem guarda — *tool sem auth check do registry* e *PII em plain text no `jana_audit_log`*. Os dois exigem exercitar o POST de mensagem, não o GET, e por isso ficam pra leva própria. Medido: o módulo tem
apenas `BriefDiarioChatTriggerTest.php` e `Chat/ChatTokensTurnoTest.php` — nenhum guarda estes.

| Anti-hook | Pest GUARD |
|---|---|
| ❌ Não dispara emails ao abrir | 🧪 **UC-06** |
| ❌ Não dispara SMS | 🧪 **UC-06** |
| ❌ Não escreve no banco no render inicial | 🧪 **UC-07** |
| ❌ Não chama Brain B no render | 🧪 **UC-08** |
| ❌ Não acessa thread de outro `business_id` (**Tier 0**) | 🧪 **UC-05** |
| ❌ Não persiste credencial Brain B no client | 🧪 **UC-08** |
| ❌ Não roda tool sem auth check do registry | ausente |
| ❌ Não loga PII em plain text | ausente |

### O que este inventário quer dizer

O charter tem uma seção chamada **"Métricas vivas (Pest GUARD — a escrever em F1.5)"**. O parêntese
é literal e continua verdadeiro: **as guardas nunca foram escritas**.

Dois desses anti-hooks não são estética — são **Tier 0**: o de `business_id` (isolamento
multi-tenant, [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)) e o de
PII em plain text (LGPD). Eles estão implementados, e a ausência de guarda significa que uma
refatoração futura pode removê-los com o CI verde.

**Nenhum desses UCs foi escrito aqui de propósito.** UC declarado sem teste que o cite reprova o
G-2 ([ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md)), e
prometer teste inexistente é pior que a ausência declarada. O caminho é escrever o Pest **primeiro**
e o UC junto — e os dois Tier 0 são a fila de prioridade óbvia, porque não dependem do desbloqueio
do visreg: são teste de servidor, não de tela.

---

## Ainda sem UC — prosa honesta, porque UC sem teste quebra o G-2

> O `Chat.tsx` é Page **fora** do manifesto `tests/Browser/visreg-screens.json`, então qualquer
> edição nele é **fail-closed** no `visual-regression` (reproduzido com `ui-impact.mjs` em
> 2026-08-17). Os itens abaixo dependem de a tela entrar no manifesto — por isso entram como
> `[BACKLOG]`, sem id e sem gate.

- `[BACKLOG]` **Metadados do card da conversa** — o protótipo mostra `preview`, *"última em X"* /
  *"criada agora"*, e *"com {pessoa}"* quando compartilhada. A tela viva mostra só o título.
- `[BACKLOG]` **Contador de conversas** no cabeçalho do histórico e no rail recolhido (`peek`).
- `[BACKLOG]` **Escopo no cabeçalho da thread** — *"só sua"* / *"da equipe"* / *"compartilhada com X"*.
- `[BACKLOG]` **Scrim clicável** ao abrir o histórico em sobreposição (≤1100px). O overlay já existe;
  o scrim, não.

## O que a tela viva tem e o protótipo NÃO tem

Registrado porque paridade não é via de mão única — apagar isto para "ficar igual" seria regressão:

- **Busca por texto** na lista, com `normalizeSearch` (ignora acento). O protótipo não tem busca.
- **Agrupamento fixadas + recentes**. O protótipo tem lista única.

## Limite honesto desta comparação

O cruzamento protótipo × tela viva que gerou este arquivo foi **estrutural** — leitura de código dos
dois lados, componente a componente. **Não é medição de fidelidade visual**: não houve
`cowork-mirror-freshness --compare --check` provando o espelho `SYNC`, nem sonda
`design-diff --probe` nos dois renders. Portanto **nenhum UC aqui afirma "fiel ao protótipo"** —
eles afirmam comportamento, que é o que os 15 testes provam.
