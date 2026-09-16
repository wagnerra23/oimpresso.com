<!-- SESSÃO FRIA · abra esta thread sozinha. Read-order mínimo e prompt de abertura: `_SESSAO-FRIA.md` (linha "Aprovações · gap").
     Os ids de decisão (D-*) só existem em `ATA-DECISOES-2026-09-14.md` — leia a ata antes, ou as siglas ficam órfãs.
     Não leia as outras threads: cada uma é 1 PR e o contexto delas não é pré-requisito desta. -->

# 16 · gap.md de Aprovações — onda 1 das 18 telas sem âncora de região

> **O que esta thread entrega:** a **proposta de `memory/requisitos/Ponto/aprovacoes-index-gap.md`**, no formato que o `gerar-map.mjs` consome. Não é arquivo pronto pra commit por mim: `gap.md` é canon fora do `cowork/`, e a lei da estrutura diz que arquivo com dono canônico fora do espelho **não vai no export** — desce como proposta, o Code aplica.
> **Por que o gap e não o map:** o `_doc` do `dashboard-index.map.json` é explícito — o map é *"gerado por scripts/design/gerar-map.mjs a partir do gap_fonte"*, e *"TODO em arquivo/linhas = âncora ainda não preenchida (grep -n real, nunca fabricar)"*. Escrever o map à mão seria fabricar.
> **Lido no turno:** `Aprovacoes/Index.charter.md` (3.244 B, inteiro) · `ponto-telas.jsx :13-110` (o símbolo `Aprovacoes`, inteiro). **NÃO lido:** `Aprovacoes/Index.tsx` (23.762 B) ⇒ todo lado vivo sai **TODO**, por regra, não por esquecimento.

---

## Proposta de conteúdo — `memory/requisitos/Ponto/aprovacoes-index-gap.md`

**Tela:** `Ponto/Aprovacoes/Index` (`/ponto/aprovacoes`) · **Protótipo:** `prototipo-ui/cowork/Wagner/ponto-telas.jsx` símbolo `Aprovacoes` (13-110) · **Backend:** `AprovacaoController@index`, permissão `ponto.access` · **Charter:** `status: draft`, tier B, ADRs 114/101/93/182.

### Regiões (o denominador — 6)

| # | região | protótipo | `data-contract` | vivo | status |
|---|---|---|---|---|---|
| 1 | **Barra de filtros** — 2 selects (Estado · Tipo) + "Limpar" + contador de pendentes no filtro | `:51-62` (`PtBarra`) | *falta* (a barra não é Card) | TODO `grep -n` | **a medir** |
| 2 | **Fila de aprovações** — tabela de 7 colunas: seleção, Colaborador (nome + matrícula · cargo), Tipo, Data/intervalo (mono), Estado, Prioridade, Ação | `:64-100` | ✅ `aprovacoes-fila-de-aprovacoes` (criado 2026-09-14) | TODO | **a medir** |
| 3 | **Ação por linha** — pendente: `Aprovar` (primary) + `Rejeitar` (danger); decidida: `Ver` | `:88-97` | — (dentro da região 2) | TODO | **a medir** |
| 4 | **Paginação** — 15/pág no protótipo · **charter diz 20/pág** | `:101` (`Pager`) | — | TODO | **divergência declarada** |
| 5 | **Barra de lote** — contador + campo "Motivo único (obrigatório só para rejeitar)" + `Aprovar N` + `Rejeitar N` + `Limpar seleção` | `:103-112` (`.pt-bulk`) | *falta* | TODO | **a medir** |
| 6 | **Rodapé legal** — `Legal` | `:113` | — | TODO | **a medir** |

### Divergências que o gap JÁ fecha sem medir o vivo

1. **Paginação 15 × 20.** O protótipo usa `usePagina(lista.length, 15)`; o charter diz *"Lista paginada (20/pág)"*. **O charter manda** (é contrato) ⇒ **o defeito é meu**, e o conserto é no build, não pedido.
2. **KPIs por estado ausentes no protótipo.** O charter lista *"KPIs por estado (Pendente/Aprovada/Rejeitada/Aplicada/Rascunho/Cancelada) — clique no card filtra a lista"* como **Goal**. Meu símbolo `Aprovacoes` **não tem faixa de KPI** — filtra por `<select>`. ⇒ **região 7 a nascer**, e o padrão já existe: `KpiCard variant="filter"` (o mesmo do Painel).
3. **Rejeição individual usa `window.prompt`.** `:41` — prompt nativo. O charter exige *"motivo obrigatório (mín. 5 chars)"*, e o DS tem `Modal` (PT-04) para confirmação. ⇒ **defeito do protótipo**: `window.prompt` não valida mínimo, não é estilizável, não é acessível. Conserto no build.
4. **Estado inicial `PENDENTE`** no protótipo; o charter não declara filtro default. ⇒ **pergunta pro charter**, não divergência.
5. **`impacta_apuracao`** é Goal do charter (*"Alerta visual quando a intercorrência impacta_apuracao"*) e **não aparece** no meu símbolo — nem coluna, nem badge. ⇒ **região 8 a nascer**.

### O que NÃO é gap (guarda contra pedido inventado)

- **Non-Goals do charter:** não criar/editar intercorrência aqui · não alterar marcação (append-only, Portaria MTP 671/2021) · não editar campo em massa. Qualquer pedido que atravesse isso **se recusa**.
- **Anti-hooks:** sem polling · sem mutação em GET · sem notificar solicitante sem opt-in LGPD. O meu `decidirLote` avisa *"ele vai para todos os solicitantes"* — texto do protótipo, **não** promessa de notificação.
- **`Inertia::defer` + partial reload** (`only: ['aprovacoes','filtros']`) são hooks declarados do vivo: o protótipo não os modela e **não deve**.

### Pendências de medição (o que a próxima passada resolve)

- `grep -n` no `Aprovacoes/Index.tsx` para preencher as 6-8 linhas de `vivo`.
- `data-contract` no `.tsx` com os **mesmos strings** do protótipo (thread 17) — declarado e ausente = DRIFT.
- Vetor de estilo por região (bbox/linhas/overflow/cor/radius/borda) **por tema × estado × largura** — a largura depende de `D-ALVO-1280`.
- Contrato de comportamento (§5 do protocolo) das 5 ações: `Aprovar`, `Rejeitar`, `Aprovar N`, `Rejeitar N`, `Limpar seleção`.

---

## Contrato de comportamento — as 5 ações (§5, preenchido do protótipo)

| elemento | gatilho | pré-condição | resultado | foco depois | erro |
|---|---|---|---|---|---|
| `Aprovar` (linha) | clique | `estado === "PENDENTE"` | estado → APROVADA, aprovador + timestamp, toast *"apuração do dia será reprocessada"* | permanece na linha | — |
| `Rejeitar` (linha) | clique | idem | **hoje:** `window.prompt`; vazio cancela. Estado → REJEITADA + `motivo_rejeicao` | permanece | **falta** validar mín. 5 chars (charter) |
| checkbox da linha | clique | `estado === "PENDENTE"` (senão `disabled`, `title="Só pendentes entram no lote"`) | entra/sai de `marcadas` | próprio | — |
| checkbox do header | clique | há selecionáveis na página | marca/desmarca **os pendentes desta página** | próprio | — |
| `Aprovar N` (lote) | clique | `marcadas.length > 0` | todas → APROVADA, toast com contagem | barra fecha (seleção some) | — |
| `Rejeitar N` (lote) | clique | idem **+ motivo não vazio** | todas → REJEITADA com o **mesmo** motivo | barra fecha | toast `warn`: *"Rejeição em lote exige um motivo único"* |

---

## PARAR SE

- o `.tsx` vivo já tiver KPI-filtro e/ou alerta de `impacta_apuracao` ⇒ **as regiões 7/8 não são gap, são catch-up MEU** (minha hipótese "produção está atrás" já caiu 3 de 3 vezes — medir antes de pedir);
- a paginação do vivo for 15 e não 20 ⇒ o conflito é **charter × vivo**, e aí é [W], não PR;
- passar de 1 PR / 300 linhas.
