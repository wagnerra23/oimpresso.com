# _SESSAO-FRIA.md — 1 thread = 1 chip = 1 PR

> **Pedido de [W] 2026-09-14:** *"eu gostaria de sessões em chip fresco, isso ajudaria a colocar em thread e economizar tokens"*.
> **É a §2-quater da norma virada em ferramenta:** uma onda = uma sessão limpa. O que esta folha acrescenta é o **prompt de abertura por thread**, com o read-order **mínimo** — e, principalmente, **o que NÃO ler**.
> **A economia real não está em ler pouco: está em não reconstituir a conversa.** Cada thread abaixo já traz o que foi lido, o que não foi, e o `PARAR SE`.

---

## Prompt de abertura — cole isto no chip novo, trocando as duas linhas do topo

```
THREAD: <arquivo>            ← ex.: 22-gap-escalas.md
MÓDULO: Ponto

Leia, nesta ordem, e só isto:
1. prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/ATA-DECISOES-2026-09-14.md
   → é o ÚNICO dicionário dos ids D-* que a thread cita. Sem ela as siglas ficam órfãs.
2. a THREAD acima, inteira.
3. o que a própria thread listar em "lido no turno" — releia no main, não confie no texto dela.
4. o charter da tela (resources/js/Pages/Ponto/<Tela>.charter.md), se a thread tocar tela.

NÃO leia: as outras threads do playbook · o 00-INDICE.md · o histórico deste chat.
Cada thread é 1 PR e o contexto das outras não é pré-requisito.

Regras que valem sem eu repetir (estão na ata):
R1 Non-Goal ratificado vira guard E sai do protótipo no mesmo pacote.
R2 Onde [W] decidiu contra o protótipo, quem muda é o protótipo.
R3 Onde a resposta era técnica, é "faça", não "escolha".

Termine escrevendo _saida-<NN>.md na mesma pasta e PARE.
Não edite 00-INDICE.md, github.md nem memory/**.
```

---

## As 15 threads, e o que cada chip precisa saber

| thread | o que faz | 1 PR? | pré-requisito | ids que usa |
|---|---|---|---|---|
| **16** Aprovações · gap | proposta de `aprovacoes-index-gap.md` (6 regiões) | sim | — | — |
| **17** `data-contract` no `.tsx` | grava no vivo os 26 ids que o protótipo criou | sim | 16 e 20–26 (nomeiam as regiões) | — |
| **18** contratos órfãos | **BLOQUEADA** — fecha sozinha com os PRs da 30 | — | 30 | — |
| **20** Intercorrências · gap | 4 telas, 2 símbolos | sim | — | `D-INTERC-ACOES` · `D-INTERC-DRAWER` · `D-INTERC-ANEXO` |
| **21** Banco de Horas · gap | 2 telas, 1 símbolo | sim | — | `D-BH-KPI` · `D-BH-ROTA` |
| **22** Escalas · gap | 2 telas, 2 símbolos | sim | — | `D-ESC-DESTROY` · `D-ESC-TURNOS` |
| **23** Colaboradores · gap | 2 telas | sim | — | `D-COLAB-CPF` · `D-COLAB-COLUNAS` · `D-PONTO-ATOMO-BOOLEANO` |
| **24** Importações · gap | 3 telas, 1 símbolo | sim | — | `D-IMP-EXTRAS` · `D-IMP-FILTRO` |
| **25** Relatórios · gap | 1 tela | sim | — | `D-REL-FLUXO` · `D-REL-FILA` |
| **26** Configurações · gap | 2 telas, 1 símbolo | sim | — | `D-CFG-IA` · `D-CFG-IA-CAMINHO` · `D-CFG-POR-BUSINESS` (**ADIADA**) |
| **27** emendas + guards | 8 emendas de charter, 3 UC, 2 Pest guards | **não — 3 PRs** (charters · casos · testes) | 16 e 20–26 | vários |
| **28** rota própria | 8 páginas viram rota | **não — 5 PRs**, 1 por símbolo | — | `D-PONTO-DETALHE` |
| **29** defaults das retidas | **já respondida** — leitura de contexto | — | — | as 5 da proposal |
| **30** proposal retida | 5 PRs (Conformidade → Fechamento → ValidacaoMobile → REP-P → AFD/AEJ) | **não — 5 PRs** | 29 (ratificada) | D0–D4 |
| **31** bateria B1–B8 | recibos de comportamento | leitura + 2 dívidas | — | — |

**Fora do Ponto:** `ds-atomos/playbook/06-widget-nivel-titulo.md` — chip próprio, dono é o primitivo (`Components/ui/card.tsx`), não o módulo.

---

## Ordem que [W] determinou (não é sugestão)

```
1º   28 (rota própria) + a passada de ALVO 1280   ← sem isso nada de pixel
2º   30 PR 1 (Conformidade) · 22 e 20 (os Non-Goals ratificados) · 23 (LGPD)
3º   27 como UMA onda: emenda de charter + protótipo juntos
4º   21 · 24 · 25 · 26
—    D-CFG-POR-BUSINESS ADIADA, fora da fila
```

## Por que "chip fresco" é mais barato aqui, medido neste ciclo

- A conversa que produziu estas 15 threads passou de **60% do orçamento** de contexto. Um chip que abre **1 thread + a ata** gasta **~8 KB de leitura obrigatória**, não 60%.
- **O caro não é ler — é reconstituir.** Cada thread já carrega: o que foi lido no turno, **o que NÃO foi lido** (e portanto não pode ser afirmado), a âncora por símbolo com faixa de linha, e o `PARAR SE`.
- **Sessão que lê 15 threads produz pedido médio.** Sessão que lê 1 produz PR.

## A trava que faz isso funcionar

**Ninguém escreve estado.** Cada chip escreve **só** o seu `_saida-NN.md`; o estado do módulo é **derivado** pelo `placar-indice.mjs`. Chip que edita o `00-INDICE.md` quebra o próximo chip — é o mesmo motivo pelo qual o zip **não** pousa o índice (a cópia do Cowork está 518 B atrás do `main`).
