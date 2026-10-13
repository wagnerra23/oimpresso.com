---
sessao: "_saida-A1"
thread: "A1 · ALVO 5 Index: documentos · memorandos · lembretes · conhecimento · tarefas"
dono: "[CL]"
data: 2026-10-13
tipo: recibo
entregue_em: "PR desta thread (claude/essenciais-a1-alvos)"
base_lida: wagnerra23/oimpresso.com@main ce0e4dcb6a
---
# _saida-A1

## Entregue: os 5 alvos, medidos pela máquina
Os cinco foram gravados por `scripts/design-sync/alvo.mjs --alvo` contra o espelho
`prototipo-ui/cowork/Wagner/`, servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`)
em `http://127.0.0.1:5561/`. Tema dark (default do espelho, conferido no `base.theme` dos cinco),
viewport 1280×900, browser limpo. Nada foi escrito à mão nos `.alvo.json`. Os `.secoes.json` levam
seletores colhidos com `alvo:mapa`, e o resultado está registrado em `_mapa` dentro de cada um.

As rotas são as que a 00 fixou (`_saida-00`, mapa rota↔Page):

| slug | rota | Page viva | seções |
|---|---|---|---|
| `essenciais--documentos--index` | `ess-documentos` | `Essentials/Documents/Index` | cabecalho · abas · titulo · subtitulo · tabela |
| `essenciais--memorandos--index` | `ess-memorandos` | `Essentials/Documents/Index` (aba Memorandos) | cabecalho · abas · titulo · subtitulo · tabela |
| `essenciais--lembretes--index` | `ess-lembretes` | `Essentials/Reminders/Index` | cabecalho · abas · toolbar · calendario · legenda |
| `essenciais--conhecimento--index` | `ess-kb` | `Essentials/Knowledge/Index` | cabecalho · abas · toolbar · navegacao · artigo |
| `essenciais--tarefas--index` | `ess-tarefas` | `Essentials/Todo/Index` | cabecalho · abas · toolbar · filtros · tabela |

Comando, por tela: `node scripts/design-sync/alvo.mjs --alvo http://127.0.0.1:5561/ --tela <slug>
--rota <rota> --secoes governance/design/targets/<slug>.secoes.json --quieto-ms 2000`.
Saídas: `seções medidas: 5 · ausentes no DOM: 0` nas cinco.

Fica de fora de toda tela o `div.hrm-toast-host`: contêiner vazio de toast, sem filhos no render.

## Antes e depois de medir
- **Sanidade:** `alvo.mjs --selftest --browser` → `27/27 ok`.
- **Estável:** a medida espera `__oiLazyDone` e duas leituras iguais de `querySelectorAll('*').length`
  (dentro do `alvo.mjs`), mais `--quieto-ms 2000`.
- **Determinismo:** cada tela foi re-medida com `--saida` num arquivo temporário; `cmp` deu
  byte-idêntico nas cinco, rc=0.
- **A-LOTE:** um alvo por tela, hashes diferentes nas cinco — `abbb1db8` (documentos) · `cea39fcc`
  (memorandos) · `41efa90f` (lembretes) · `60fa1ef5` (conhecimento) · `bbb2fe74` (tarefas).
  `cabecalho` e `abas` coincidem nas cinco: é a mesma casca da `EssenciaisPage`, e a medida é de
  estilo e estrutura, sem texto. Documentos e Memorandos são a mesma Page em duas abas, por isso
  coincidem também `titulo` e `subtitulo` (mesmo estilo); a `tabela` difere. A `base.assinatura` de
  cada um traz o conteúdo da própria aba ("Arquivos compartilhados…" × "Avisos e memorandos…", o
  calendário de agosto, a árvore da base, o filtro "Atribuído a"). Não é a mesma tela medida duas vezes.

## `secao:check`
`node scripts/qa/secao-check.mjs --tela <slug> --servir-espelho`: `✓ 5 seção(ões) conforme`, rc=0,
nas cinco.

## Fora deste PR
- A tabela "Alvos exportados" do `governance/design/targets/README.md` não foi atualizada: o prefixo
  da thread é `governance/design/targets/essenciais--*`.
- Create/Edit/Show de Tarefas e de Conhecimento seguem sem alvo: pela D1 viram painel lateral da lista,
  e a A1 mede só as Index.
- As divergências "só no protótipo" da `_saida-00` (títulos h1 das Pages, "categoria" × livro → seção →
  artigo na base, ícone de compartilhar) entram no alvo como o protótipo as desenha. Decidir cada uma é
  [W]; a 03 deriva os contratos deste alvo.
