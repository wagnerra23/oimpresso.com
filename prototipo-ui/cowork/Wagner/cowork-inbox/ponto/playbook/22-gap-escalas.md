<!-- SESSÃO FRIA · abra esta thread sozinha. Read-order mínimo e prompt de abertura: `_SESSAO-FRIA.md` (linha "Escalas · gap").
     Os ids de decisão (D-*) só existem em `ATA-DECISOES-2026-09-14.md` — leia a ata antes, ou as siglas ficam órfãs.
     Não leia as outras threads: cada uma é 1 PR e o contexto delas não é pré-requisito desta. -->

# 22 · gap.md de Escalas — onda 4 (2 telas, 2 símbolos)

> **Entrega:** propostas de `memory/requisitos/Ponto/escalas-{index,form}-gap.md`.
> **Lido no turno:** `Escalas/Index.charter.md` (2.221 B) · `Escalas/Form.charter.md` (2.529 B) · protótipo `ponto-telas.jsx :395-491` (símbolos `Escalas` e `EscalaForm`, inteiros). **NÃO lido:** `Index.tsx` (6.465 B) · `Form.tsx` (9.022 B) ⇒ lado vivo **TODO**.

## A · `escalas-index-gap.md` — 5 regiões

| # | região | protótipo | `data-contract` | status |
|---|---|---|---|---|
| 1 | Barra — nota de carga ("480 = 8h, 2.640 = 44h CLT") + `Nova escala` | `:412-417` | *falta* | a medir |
| 2 | Lista — 8 colunas: Código (mono 110px), Nome (+1º turno na sub-linha), Tipo (pill), Carga diária, Carga semanal, Turnos, Banco de horas (pill Sim/Não), Ação (150px) | `:418-443` | ✅ `escalas-escalas-cadastradas` | a medir |
| 3 | Paginação | `:442` | — | **corrigido: 15 → 20/pág** |
| 4 | Nota de rodapé — turnos são leitura aqui, edição em fase posterior | `:445` | — | ✅ **bate com o Non-Goal** do Form (*"a UI de turnos é read-only"*) |
| 5 | **Empty state com CTA "criar a primeira escala"** | só texto seco (*"Nenhuma escala cadastrada."*) | — | 🟠 **região a nascer** — o `EmptyState` do DS tem `variant="first"` + `action` |

### ⛔ Violação de Non-Goal — e ela já é pendência aberta do charter

O charter Index declara: *"❌ **Não exclui escala nesta lista** (rota destroy existe no resource, mas a UI não expõe — confirmar com Wagner)"*, e repete na lista de pendências: *"Definir se exclusão de escala (destroy) entra na UI e com quais guardas"*.
**Meu Index tem `Remover`** (`:438`), com `window.confirm("Remover esta escala? Colaboradores vinculados perderão a referência.")`. Ou seja: **o protótipo já respondeu uma pergunta que é de [W]** — e respondeu com `window.confirm`, que não é o `Modal` (PT-04) do DS.
**Não removi** (mesma regra do `D-INTERC-ACOES`: Non-Goal não ratificado não autoriza apagar função). Vira `D-ESC-DESTROY`, e ela tem **duas** perguntas: entra na UI? e **com qual guarda** (a minha frase já nomeia o risco real — colaborador vinculado perde a referência).

### Outras divergências

1. **Ordem das colunas:** charter lista *"nome, código, tipo…"*; eu começo por **Código**. Forma, não informação.
2. **Sub-linha do Nome** com o 1º turno (`entrada–saída` ou "sem turno configurado") — **extra meu**, não está no charter. É útil e resolve "por que turnos = 0"; declaro, não peço.
3. **"Editar" abre form na mesma tela;** o charter manda `/ponto/escalas/{id}/edit`. **3ª ocorrência da mesma pergunta no módulo** ⇒ consolidada em `D-PONTO-DETALHE`.

## B · `escalas-form-gap.md` — paridade alta, 2 divergências

| # | região | protótipo | status |
|---|---|---|---|
| 1 | Cabeçalho com Voltar + título dual (Nova × Editar) | `:459-461` | a medir |
| 2 | Nome (req, 120) · Código (30) · Tipo (enum) | `:464-471` | ✅ bate |
| 3 | Carga diária (**60–600**) · Carga semanal (**0–3600**) · permite BH | `:472-476` | ✅ **os dois limites batem com o charter, número a número** |
| 4 | Turnos configurados **read-only** (5 colunas: dia, entrada, saída almoço, retorno, saída) — só no modo edit | `:477-486` | ✅ bate com o Goal |
| 5 | Nota CLT — interjornada (Art. 66) e intrajornada (Art. 71) validadas na apuração, não aqui | `:487` | ✅ **acerto de fronteira** (a lei citada literalmente, como a norma exige) |
| 6 | Rodapé Cancelar + Criar/Atualizar (disabled com `title` = erro) | `:488-491` | a medir |

**Divergência 1 · fluxo pós-salvar.** O charter declara: *"`store` injeta `business_id` e **redireciona pro edit** ('configure os turnos')"*. Eu volto para a **lista** com o toast *"Escala criada — vincule colaboradores na tela de Colaboradores."* Dois destinos e duas frases diferentes. **O charter manda** (é hook declarado do vivo) ⇒ meu toast e meu destino estão errados. **Não corrigi ainda**: mudar o destino sem ter a tela de turnos editável só troca um beco por outro — entra junto com `D-ESC-TURNOS`.

**Divergência 2 · validação de cliente.** Meu `erro` bloqueia o submit com `title`; o charter fala de *"validação com feedback inline"*. O DS tem `error` inline nos campos (`Input`/`Select`). **Defeito de forma meu** — o `title` de botão desabilitado não é lido por leitor de tela no estado disabled.

```json
[
  {
    "id": "D-ESC-DESTROY",
    "pergunta": "Excluir escala entra na UI? Com qual guarda?",
    "medido": "charter: Non-Goal + pendência aberta. Protótipo: botão Remover com window.confirm, e a própria frase nomeia o risco ('colaboradores vinculados perderão a referência').",
    "opcoes": ["ratificar o Non-Goal e eu removo o botão", "entrar com guarda de dependência (bloquear se houver colaborador vinculado) + Modal do DS em vez de window.confirm"],
    "dono": "[W]"
  },
  {
    "id": "D-ESC-TURNOS",
    "pergunta": "A UI de turnos vira editável (e quando)? O redirect pós-criar depende disso.",
    "medido": "charter Form: 'a UI de turnos é read-only (iteração futura)' + pendência 'definir escopo do CRUD de turnos'. O redirect declarado ('vá pro edit configurar os turnos') aponta para uma tela que hoje só lê.",
    "dono": "[W]"
  },
  {
    "id": "D-PONTO-DETALHE",
    "pergunta": "CONSOLIDA D-INTERC-DRAWER + D-BH-ROTA + Escalas: no módulo Ponto, detalhe/edição é Drawer-ou-form na mesma tela (protótipo) ou ROTA própria (charters e .tsx existentes)?",
    "medido": "3 de 3 telas com detalhe seguem o padrão in-page no protótipo, e os 3 charters mandam rota. Existem Show.tsx/Form.tsx/Edit.tsx no main para todas.",
    "consequencia": "uma decisão resolve 6 telas; seis decisões separadas resolvem zero.",
    "dono": "[W]"
  }
]
```

**PARAR SE** — o `Index.tsx` vivo já tiver empty state com CTA ⇒ região 5 é catch-up meu · `D-PONTO-DETALHE` aberta ⇒ sem thread de layout para o Form.
