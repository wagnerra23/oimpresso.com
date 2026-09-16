<!-- SESSÃO FRIA · abra esta thread sozinha. Read-order mínimo e prompt de abertura: `_SESSAO-FRIA.md` (linha "Configurações · gap").
     Os ids de decisão (D-*) só existem em `ATA-DECISOES-2026-09-14.md` — leia a ata antes, ou as siglas ficam órfãs.
     Não leia as outras threads: cada uma é 1 PR e o contexto delas não é pré-requisito desta. -->

# 26 · gap.md de Configurações — onda 8, a última (2 telas, 1 símbolo)

> **Entrega:** propostas de `memory/requisitos/Ponto/configuracoes-{index,reps}-gap.md`. **Fecha a fila das 18 telas de `ponto-telas.jsx`.**
> **Lido no turno:** `Configuracoes/Index.charter.md` (2.635 B) · `Configuracoes/Reps.charter.md` (2.632 B) · protótipo `ponto-telas.jsx :858-987` (símbolo `Configuracoes`, inteiro). **NÃO lido:** `Index.tsx` (6.471 B) · `Reps.tsx` (8.061 B) ⇒ lado vivo **TODO**.
> **1 símbolo, 2 telas:** o `tela === "reps"` (`:868`) é a segunda tela.

## A · `configuracoes-index-gap.md` — 6 regiões

| # | região | protótipo | `data-contract` | status |
|---|---|---|---|---|
| 1 | Nota "somente leitura" + atalho pro cadastro de REPs | `:916-918` | *falta* | **corrigido: a fonte do config estava errada** (abaixo) |
| 2 | **Regras CLT / Reforma Trabalhista** — 9 parâmetros, cada um com o artigo | `:921-933` | ✅ `configuracoes-regras-clt-reforma-trabalhista` | ✅ **bate e supera** |
| 3 | **Banco de Horas** — 7 parâmetros | `:934-944` | ✅ `configuracoes-banco-de-horas` | ✅ bate |
| 4 | **REP e imutabilidade** — 7 parâmetros + botão "Gerenciar REPs cadastrados" | `:945-961` | ✅ `configuracoes-rep-e-imutabilidade-de-marcacoes` | ✅ bate |
| 5 | **AFD / eSocial** — 7 parâmetros | `:962-972` | ✅ `configuracoes-afd-importacao-esocial` | ✅ bate |
| 6 | **IA do Ponto** — 5 flags (master switch + 3 recursos + modelo) | `:973-981` | ✅ `configuracoes-ia-do-ponto` | ⚠️ **5º bloco: o charter só declara 4** |

### O acerto: a lei citada parâmetro por parâmetro

O charter pede *"cita o artigo legal aplicável em cada parâmetro CLT"*. O protótipo cita **Art. 58 §1º** (tolerância por marcação e máxima diária) · **Art. 66** (interjornada) · **Art. 71** (intrajornada) · **Art. 73 §1º** (hora noturna ficta) · **Art. 73** (adicional noturno) · **Art. 59** (limite de HE) · **Art. 7º XVI CF/88** (adicional de HE) · **Lei 605/49** (DSR) — e ainda marca o prazo de compensação como *"Reforma Trabalhista — acordo individual"*. **Vai além do charter** (que fala só de CLT) e obedece a regra de copy de conformidade: **artigo literal, nunca parafraseado**.

### 1 defeito factual MEU, corrigido neste turno

A nota dizia que as configurações vêm de **`Modules/Ponto/Config/config.php`**. O charter declara a fonte real: **`config/pontowr2.php`**, lida no controller via `config('pontowr2')`. **Eu afirmei um caminho que nunca li** — é exatamente a falha que a regra "não afirmar caminho de memória" nomeia, e ela estava **escrita na tela**, não só no meu raciocínio. **Corrigido no build** (texto passa a citar `config/pontowr2.php` + `config('pontowr2')`).

### Divergência: o 5º bloco (IA)

O charter lista **4 blocos**; eu tenho **5** — o extra é **IA do Ponto** (master switch, classificação de intercorrência, explicação de divergência, geração de justificativa, modelo). **Não é invenção solta:** o charter de `Intercorrencias/Create` declara `ai_enabled` e o endpoint `aiClassify`, então as flags existem no sistema. O que falta é o charter de Configurações reconhecer que elas **têm superfície**. Proposta: **o 5º bloco entra** (`D-CFG-IA`).

## B · `configuracoes-reps-gap.md` — 3 regiões

| # | região | protótipo | `data-contract` | status |
|---|---|---|---|---|
| 1 | Cabeçalho — Voltar + "Dispositivos REP" + contagem | `:874-875` | *falta* | a medir |
| 2 | **REPs cadastrados** — 5 colunas: Tipo (pill `REP-P`), Identificador (mono), Descrição, Local, **CNPJ** | `:878-892` | ✅ `configuracoes-reps-cadastrados` | ⚠️ **falta a coluna `Ativo`** que o charter declara; e eu mostro CNPJ, que o charter põe só no form |
| 3 | **Cadastrar novo REP** — Tipo (3 opções com o nome completo) · Identificador (17) · Descrição (120) · Local · CNPJ (14) · botão com `disabled`+`title` | `:893-910` | ✅ `configuracoes-cadastrar-novo-rep` | **corrigido: o formato do identificador estava errado** (abaixo) |

### 2º defeito factual MEU, corrigido neste turno — e este era de CONFORMIDADE

O charter é explícito: *"identificador = **CNPJ 14 + sequencial 3**"* (Anexo I da Portaria). O meu campo dizia `AAAAMMDDHHMMSSNNN` no placeholder, no help e na mensagem de erro — ou seja, **eu inventei um formato de data-hora para um identificador que é CNPJ+sequencial**. Os dois têm 17 caracteres, então a validação de comprimento passava e o erro ficava invisível.
**Corrigido:** help → *"CNPJ (14) + sequencial (3), conforme Portaria MTP 671/2021 Anexo I"* · placeholder → `00000000000191001` · mensagem de erro reescrita com a composição certa.
**Por que isso é grave e não cosmético:** é **copy de conformidade legal**. Um operador que preenchesse pelo meu placeholder cadastraria um REP com identificador inválido, e a rejeição só apareceria na importação do AFD.

### Outras divergências

1. **Coluna `Ativo` ausente** — o charter a declara na lista. Meu mock de `REPS` não tem o campo. 🟠 região a nascer (dado + coluna).
2. **Unicidade do identificador não validada** — o charter pede *"identificador único de 17 chars"*; eu valido só o comprimento. Artefato de mock (não há backend), **declarado para não virar pedido**.
3. **Non-Goal concordante:** *"não edita nem inativa REP na UI (só cadastra e lista) — confirmar com Wagner"* — o protótipo **também** não edita. Os dois lados concordam; a pendência é de escopo, não de paridade.

```json
[
  {
    "id": "D-CFG-IA",
    "pergunta": "O bloco 'IA do Ponto' entra no contrato da tela de Configurações?",
    "medido": "charter declara 4 blocos (CLT, Banco de Horas, REPs/Imutabilidade, AFD/eSocial); o protótipo tem 5 — o extra expõe as flags de IA (master switch + 3 recursos + modelo), que EXISTEM no sistema (o charter de Intercorrencias/Create declara ai_enabled e o endpoint aiClassify).",
    "recomendacao_CC": "entra — flag que muda o comportamento da tela de Intercorrências precisa de superfície de consulta.",
    "dono": "[W]"
  },
  {
    "id": "D-CFG-POR-BUSINESS",
    "pergunta": "Os parâmetros passam a ser editáveis por business?",
    "medido": "é pendência ABERTA do próprio charter ('decidir se parâmetros passam a ser editáveis por-business — hoje é config de arquivo global') e Non-Goal declarado ('config é de arquivo, não escopada por business_id').",
    "nota": "se virar por-business, a tela deixa de ser read-only e muda de arquétipo (painel → formulário) — não é ajuste de layout, é outra tela.",
    "dono": "[W]"
  },
  {
    "id": "D-REP-ATIVO",
    "pergunta": "Inativar REP entra no escopo? (a coluna Ativo depende disso)",
    "medido": "charter Reps: Non-Goal 'não edita nem inativa REP na UI' + pendência 'definir se editar/inativar entra'. A coluna Ativo é declarada nos Goals, mas sem ação que a mude ela é informativa.",
    "dono": "[W]"
  }
]
```

**PARAR SE** — o `Reps.tsx` vivo já tiver a coluna Ativo ⇒ é catch-up meu · `D-CFG-POR-BUSINESS` for "sim" ⇒ **esta tela sai da fila de layout** e volta como tela nova (arquétipo diferente).

---

## FIM DA FILA — as 18 telas de `ponto-telas.jsx` estão cobertas

| onda | thread | telas | defeitos meus corrigidos |
|---|---|---|---|
| 1 | 16 | Aprovações Index | paginação 20 · motivo ≥ 5 chars |
| 2 | 20 | Intercorrências ×4 | paginação 25 |
| 3 | 21 | BancoHoras ×2 | paginação 30 · ordenação desc · observação ≥ 5 chars |
| 4 | 22 | Escalas ×2 | paginação 20 |
| 5 | 23 | Colaboradores ×2 | paginação 25 |
| 6 | 24 | Importações ×3 | paginação 20 |
| 7 | 25 | Relatórios | — |
| 8 | 26 | Configurações ×2 | **fonte do config** · **formato do identificador do REP** |

**Placar honesto:** 18 telas · 8 threads · **11 defeitos meus corrigidos no build** (7 de paginação/ordenação, 2 de validação auditada, 2 factuais) · **17 decisões de [W] abertas** · **0 thread de layout emitida** — porque `D-ALVO-1280` e `D-PONTO-DETALHE` são pré-requisito de qualquer pixel.
