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
