<!-- SESSÃO FRIA · abra esta thread sozinha. Read-order mínimo e prompt de abertura: `_SESSAO-FRIA.md` (linha "Intercorrências · gap").
     Os ids de decisão (D-*) só existem em `ATA-DECISOES-2026-09-14.md` — leia a ata antes, ou as siglas ficam órfãs.
     Não leia as outras threads: cada uma é 1 PR e o contexto delas não é pré-requisito desta. -->

# 20 · gap.md de Intercorrências — onda 2 (4 telas, 2 símbolos)

> **Entrega:** propostas de `memory/requisitos/Ponto/intercorrencias-{index,create}-gap.md`. `Edit` e `Show` entram pela mesma dupla de símbolos, com o charter **não lido** neste turno (declarado abaixo).
> **Lido no turno:** `Intercorrencias/Index.charter.md` (2.709 B) · `Intercorrencias/Create.charter.md` (3.340 B) · protótipo `ponto-telas.jsx :111-288` (símbolos `FormIntercorrencia` e `Intercorrencias`, inteiros). **NÃO lido:** os 4 `.tsx` vivos · `Edit.charter.md` · `Show.charter.md` ⇒ lado vivo **TODO**, por regra.
> **Muitos-para-um resolvido por símbolo:** `FormIntercorrencia :: 111-167` serve **Create e Edit** (o `registro` decide o modo e o rótulo do botão: `Salvar rascunho` × `Atualizar`). `Intercorrencias :: 168-288` serve **Index e Show** (o Show é o **Drawer** — ver a divergência D-INTERC-DRAWER).

---

## A · `intercorrencias-index-gap.md` — 7 regiões

| # | região | protótipo | `data-contract` | vivo | status |
|---|---|---|---|---|---|
| 1 | **Barra superior** — contador de registros + `Nova intercorrência` (primary, ícone `plus`) | `:246-251` | *falta* (barra não é Card) | TODO | a medir |
| 2 | **Lista** — 7 colunas: Código (mono, 128px), Colaborador (nome+matrícula), Tipo, Data (mono + intervalo), Estado, Prioridade, Ação (170px) | `:258-288` | ✅ `intercorrencias-intercorrencias` | TODO | a medir |
| 3 | **Linha clicável** — `tr.hit` abre o detalhe; a célula de Ação faz `stopPropagation` | `:266-285` | — | TODO | a medir |
| 4 | **Paginação** | `:287` | — | TODO | **corrigido no build: 15 → 25/pág** (Goal do charter) |
| 5 | **Form embutido** (Nova/Editar) — Card que aparece acima da lista | `:253-256` | ⚠️ `intercorrencias-card` (id feio, derivado sem título fixo) | TODO | a renomear quando a região tiver nome |
| 6 | **Filtros por estado e tipo** — `PageFilters` + chips removíveis + reset | **NÃO EXISTE** | — | TODO | 🟠 **região a nascer** |
| 7 | **Coluna "Criada" (humanizado)** | **NÃO EXISTE** | — | TODO | 🟠 **região a nascer** |

### Divergências fechadas sem ler o vivo

1. **⛔ VIOLAÇÃO DE NON-GOAL declarada — e eu NÃO corrigi de propósito.** O charter diz *"❌ Não aprova/rejeita/**submete** a partir da lista (essas ações vivem no `Show`/Aprovações)"*. Meu Index tem **`Submeter`** na célula de Ação (`:281`) e **`Editar`** (`:280`) — e o charter também diz *"❌ Não edita intercorrência inline"*. **Por que não mexi:** o charter é `status: draft` e ele mesmo declara *"Wagner aprova Non-Goals + Anti-hooks ANTES de virar status: live"* — o Non-Goal **não está ratificado**. Apagar 2 ações úteis com base em regra não ratificada é tão errado quanto ignorá-la. Vira **D-INTERC-ACOES**.
2. **Paginação 15 → 25** (charter declara 25/página): **defeito meu, corrigido no build** neste turno.
3. **Filtros ausentes (região 6).** O charter os declara como Goal, com `PageFilters` + chips + reset e partial reload `only: ['intercorrencias','filtros']`. Meu Index **não filtra nada** — a barra só conta. Gap real; o átomo existe (`PageFilters` no `main`, `FilterChip` no DS).
4. **Empty state único.** O charter pede **dois**: "sem itens" × "sem resultado do filtro". Meu `Vazio` tem só *"Nenhuma intercorrência registrada ainda."* — e sem filtro não há como ter o segundo. Cai junto da região 6. O `EmptyState` do DS já tem `variant` `first` e `no-results`.
5. **Coluna "Criada" ausente (região 7)** — o charter lista as colunas e inclui *"criada (humanizado)"*. Eu tenho `created_at` no **dado** (`:239`) e não na tabela.
6. **Badge de prioridade urgente:** o charter pede o badge **na coluna Estado**; eu tenho **coluna Prioridade própria**. Divergência de forma, não de informação — o vivo manda.

---

## B · `intercorrencias-create-gap.md` — 8 regiões (símbolo `FormIntercorrencia`)

| # | região | protótipo | vivo | status |
|---|---|---|---|---|
| 1 | Colaborador + Tipo (2 selects obrigatórios) | `:128-137` | TODO | a medir |
| 2 | Data + Dia todo + Início/Fim (time, desabilitados por "dia todo") | `:138-143` | TODO | a medir |
| 3 | Justificativa (textarea, `wide`, contador `n/2000`, mín. 10) | `:144-145` | TODO | a medir |
| 4 | Prioridade + 2 flags (`impacta_apuracao` default **true**, `descontar_banco_horas` default false) | `:146-152` | TODO | a medir |
| 5 | **Anexo** (file, `.pdf,.jpg,.jpeg,.png`, máx 5 MB no rótulo) | `:153-155` | TODO | ⚠️ **extra no protótipo** — o charter não lista anexo |
| 6 | Nota "nasce como rascunho / append-only" | `:157` | TODO | a medir |
| 7 | Rodapé: `Cancelar` + `Salvar rascunho`/`Atualizar` (disabled com `title` = erro) | `:158-163` | TODO | a medir |
| 8 | **Campo IA em texto livre** — classifica tipo/prioridade, reescreve a justificativa, badge "IA desligada no servidor", toast com % de confiança e origem cache | **NÃO EXISTE** | `POST /ponto/intercorrencias-ai/classify` (throttle 10/min, cache) | 🔴 **produção MUITO à frente** |

### O achado que muda o eixo desta onda

**A região 8 é a 4ª vez que a minha hipótese-padrão cairia.** O charter de `Create` declara um **classificador de IA** com endpoint dedicado (`aiClassify` → `IntercorrenciaAIClassifier`, OpenAI, throttle e cache), estado visível de `ai_enabled`, alerts de sucesso/erro e pré-preenchimento do form. **O meu protótipo não tem uma linha disso.** Então esta tela **não é catch-up de produção: é catch-up MEU**. Consequência prática: *não* emitir thread de layout para `Create` antes de modelar a região 8 no protótipo — exportar o form sem o campo de IA seria exportar uma tela que já não existe.

### Validação: onde eu estou à frente (declarar, não pedir)

Meu form valida **colaborador · tipo · data · data não futura · justificativa ≥ 10 chars** num único `erro` que desabilita o submit com `title`. O charter não declara validação de cliente. **Não vira pedido** — vira linha de ACERTO invertida (protótipo à frente), e quem decide se sobe é [W].

---

## C · Decisões que esta onda abre

```json
[
  {
    "id": "D-INTERC-ACOES",
    "pergunta": "A lista de Intercorrências pode ter Editar e Submeter na linha?",
    "medido": "O charter Index declara os dois como Non-Goal ('não submete a partir da lista', 'não edita inline'), MAS é status: draft e o próprio doc condiciona os Non-Goals à aprovação de [W]. O protótipo tem ambos (:280-281).",
    "opcoes": ["ratificar o Non-Goal e eu removo as 2 ações do build", "emendar o charter e as ações ficam"],
    "nao_fiz": "não removi — regra não ratificada não autoriza apagar função útil.",
    "dono": "[W]"
  },
  {
    "id": "D-INTERC-DRAWER",
    "pergunta": "O detalhe da intercorrência é Drawer (PT-02) ou página Show?",
    "medido": "O protótipo abre Drawer de 620px com 3 seções (Dados · Justificativa · Rastreio) e rodapé de ações (Editar · Submeter · Cancelar). O charter Index diz 'link Ver por linha → Show', e existe Pages/Ponto/Intercorrencias/Show.tsx (6.825 B).",
    "consequencia": "se for Show, o meu Drawer é pele paralela de uma página que já existe; se for Drawer, o Show vira rota morta.",
    "dono": "[W]"
  },
  {
    "id": "D-INTERC-ANEXO",
    "pergunta": "Anexo de comprovante entra no contrato da tela?",
    "medido": "Protótipo tem input de arquivo (pdf/jpg/png, rótulo 'máx 5 MB'); o charter Create NÃO lista anexo em Goals, e o dado tem campo (anexo_path, exibido no detalhe).",
    "nota": "é o padrão 'produção evoluiu além da âncora' invertido — aqui é o protótipo que promete algo que o contrato não declara. Ou o charter incorpora, ou o campo sai.",
    "dono": "[W]"
  }
]
```

---

## PARAR SE

- o `.tsx` vivo do Index **já tiver** `PageFilters` e a coluna "Criada" ⇒ regiões 6 e 7 **não são gap, são catch-up meu** (hipótese "produção atrás" já caiu 3 de 3 — e a região 8 seria a 4ª);
- `D-INTERC-ACOES` ou `D-INTERC-DRAWER` seguirem abertas ⇒ **nenhuma thread de layout** para Index/Show;
- o campo de IA exigir chave/limite que eu não modelo ⇒ **parar e reportar**, não simular resposta de IA no protótipo sem declarar que é mock.
