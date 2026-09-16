<!-- SESSÃO FRIA · abra esta thread sozinha. Read-order mínimo e prompt de abertura: `_SESSAO-FRIA.md` (linha "Colaboradores · gap").
     Os ids de decisão (D-*) só existem em `ATA-DECISOES-2026-09-14.md` — leia a ata antes, ou as siglas ficam órfãs.
     Não leia as outras threads: cada uma é 1 PR e o contexto delas não é pré-requisito desta. -->

# 23 · gap.md de Colaboradores — onda 5 (2 telas, 2 símbolos)

> **Entrega:** propostas de `memory/requisitos/Ponto/colaboradores-{index,edit}-gap.md`.
> **Lido no turno:** `Colaboradores/Index.charter.md` (2.417 B) · `Colaboradores/Edit.charter.md` (2.665 B) · protótipo `ponto-telas.jsx :492-634` (símbolos `Colaboradores` e `ColaboradorForm`, inteiros). **NÃO lido:** `Index.tsx` (8.276 B) · `Edit.tsx` (7.189 B) ⇒ lado vivo **TODO**.

## A · `colaboradores-index-gap.md` — 4 regiões

| # | região | protótipo | `data-contract` | status |
|---|---|---|---|---|
| 1 | **Barra de busca e filtros** — Buscar (nome/matrícula/CPF) + Limpar + select Escala + select Situação (5 opções) + contador "N de M" | `:519-536` | *falta* | ⚠️ **protótipo à frente** |
| 2 | **Lista — 9 colunas:** Matrícula (mono 92px), Nome (+e-mail · cargo), CPF/PIS, Escala (pill), **Último ponto**, **Saldo BH**, Controla ponto, Banco de horas, Ação (180px) | `:538-575` | ✅ `colaboradores-colaboradores` | ⚠️ **3 colunas além do charter** |
| 3 | Paginação | `:574` | — | **corrigido: 15 → 25/pág** |
| 4 | Nota "a vinculação é do Essentials/HRM" | `:576-578` | — | ✅ bate com o Non-Goal *"não cadastra colaborador — cadastro é no HRM"* |

### Onde eu estou à frente (declarar, não pedir)

O charter pede **busca** (debounce 350ms, partial reload) e **6 colunas**. Eu tenho:
- **2 filtros que o charter não declara:** `Escala` e `Situação` — e a opção **"Sem PIS cadastrado"** é operacionalmente forte, porque o próprio form avisa que *"sem PIS, a marcação do AFD é rejeitada na importação"*. Isso conecta duas telas (Colaboradores ↔ Importações) e é candidato a subir.
- **3 colunas extra:** `Último ponto` (derivada das marcações do mês), `Saldo BH` (com cor por sinal) e `Ação`. `Último ponto` responde "esse colaborador está batendo ponto?" sem sair da lista.
- **Linha de desligado com estado próprio** (`tr.folga`) e aviso inline `PIS não cadastrado`.
**Nada disso vira pedido** — vira proposta de emenda de charter, decisão de [W] (`D-COLAB-COLUNAS`).

### O ponto sensível: CPF e PIS em claro

O charter tem **pendência aberta**: *"[ ] Confirmar mascaramento de CPF na coluna (LGPD)"*, e Non-Goal *"❌ Não exporta CPF/PIS em massa — PII de colaborador (LGPD)"*.
**Meu Index mostra CPF inteiro e PIS inteiro** na mesma célula (`:562`). São dados **mock**, então não há incidente — mas o protótipo é o alvo que a produção copia, e exportar "CPF em claro na listagem" com selo de design aprovado é exportar dívida de LGPD.
**Não mascarei por conta:** máscara é decisão (quanto mostrar: `***.***.789-00`? só os 3 últimos? nada?) e a pergunta já está no charter. Vira `D-COLAB-CPF`, com a minha recomendação registrada: **mascarar por padrão na listagem e mostrar inteiro só no form**, que é onde o gestor edita.

### Divergências de mecanismo (artefato de mock, não gap)

- **Busca sem debounce:** o charter declara 350ms + `router.get` com `only: ['colaboradores','search']`. A minha filtra em memória a cada tecla — **não tem servidor aqui**. Declarado para não virar pedido de "tirar o debounce".
- **Empty states:** o charter pede **dois** distintos ("sem cadastro" × "busca sem resultado"). Eu tenho **os dois**, e a mensagem cita o termo buscado (`Nenhum colaborador encontrado para "q"`). ✅ bate.

## B · `colaboradores-edit-gap.md` — 2 regiões, paridade de campo

| # | região | protótipo | `data-contract` | status |
|---|---|---|---|---|
| 1 | **Configuração de ponto** — Matrícula (30) · CPF (14) · PIS (14, com help do AFD) · Escala atual (select, "— Sem escala vinculada —") · Admissão (req) · Desligamento (help "deixar em branco se ativo") · 2 flags · Cancelar/Salvar | `:594-620` | ✅ `colaboradorform-configuracao-de-ponto` | ✅ **os 6 campos + 2 flags batem com o charter** |
| 2 | **Dados do HRM** (read-only) — Nome · E-mail · Cargo · ID HRM (mono) + nota "para alterar, vá em Funcionários" | `:621-632` | ✅ `colaboradorform-dados-do-hrm` | ✅ **materializa o Non-Goal** *"não edita nome/email — vêm do HRM"* |

### 2 defeitos meus, declarados e NÃO corrigidos neste turno (com o motivo)

1. **Charter diz "Switches", eu uso checkbox.** *"Switches: 'controla ponto' … e 'usa banco de horas'"*. O DS tem `Switch` (com label + sublabel). Meu `PtCheck` é checkbox.
   **Por que não troquei agora:** `PtCheck` foi migrado para o `Checkbox` do DS numa onda anterior e é usado em **8 sítios** do módulo; trocar só aqui cria dois átomos para o mesmo papel. É thread de **papel** (`PAPEL = alternar estado booleano persistido` → `Switch`; `PAPEL = marcar item numa lista` → `Checkbox`), com prova de guarda nos 8 sítios.
2. **Admissão e Desligamento são texto livre.** O charter marca admissão como obrigatória e declara validação server-side `desligamento > admissao`. Meu form usa `PtCampo` de texto (o mock guarda `dd/mm/aaaa`), sem `type="date"` e **sem validar a ordem**.
   **Por que não troquei agora:** `type="date"` exige `aaaa-mm-dd` e o `data.jsx` inteiro usa `dd/mm/aaaa` — a troca é de **formato de dado**, não de campo, e o DS tem `DatePicker` PT-BR (`dd/mm/aaaa`) que resolve os dois. Entra como thread própria.

```json
[
  {
    "id": "D-COLAB-CPF",
    "pergunta": "CPF e PIS aparecem mascarados na listagem?",
    "medido": "charter: pendência aberta de mascaramento + Non-Goal de exportação em massa (LGPD). Protótipo: CPF e PIS inteiros na coluna (:562).",
    "recomendacao_CC": "mascarar na listagem, inteiro só no form de edição.",
    "dono": "[W]"
  },
  {
    "id": "D-COLAB-COLUNAS",
    "pergunta": "As 3 colunas e os 2 filtros que o protótipo acrescentou entram no contrato?",
    "medido": "charter declara busca + 6 colunas; protótipo tem 9 colunas (Último ponto, Saldo BH, Ação) e 2 filtros (Escala, Situação com 'Sem PIS cadastrado').",
    "nota": "'Sem PIS' liga esta tela à de Importações (sem PIS o AFD rejeita a marcação) — é o filtro de maior valor operacional dos dois.",
    "dono": "[W]"
  },
  {
    "id": "D-PONTO-ATOMO-BOOLEANO",
    "pergunta": "Switch × Checkbox: qual átomo para flag booleana persistida no Ponto?",
    "medido": "charter Colaboradores/Edit diz 'Switches'; o build usa Checkbox do DS em 8 sítios do módulo.",
    "papel": "alternar estado booleano persistido = Switch · marcar item numa lista = Checkbox",
    "dono": "[W] + DS (prova de guarda nos 8 sítios)"
  }
]
```

**PARAR SE** — o `Index.tsx` vivo já mascarar CPF ⇒ `D-COLAB-CPF` morre e o defeito é **só meu** · o `Edit.tsx` já usar `Switch` ⇒ a thread de átomo é catch-up meu, não pedido · qualquer um tentar portar minha busca sem debounce ⇒ **parar** (é artefato de mock).
