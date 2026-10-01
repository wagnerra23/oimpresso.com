---
sessao: "A1"
titulo: "ALVO lote — 5 de 9 telas medidas; 4 sem rota no protótipo — saída da thread"
autor: "[CL]"
data: 2026-09-30
base: origin/main 164688e98
thread: 02-alvos.md §A1
veredito: "entregue parcial — 5 alvos medidos (20 seções, 0 ausentes), cada um 2× byte-idêntico; 4 telas sem rota medível no protótipo, listadas abaixo sem alvo (não inventado)."
---

# _saida-A1 · Alvos do lote trio + medida

## O que saiu

| tela (Page) | slug | rota no protótipo | seções |
|---|---|---|---|
| `Auditoria/Index` | `auditoria--index` | `auditoria` → `MockupPage` (corpo estático `MOCKUP_BODIES.auditoria`) | 3 · header · acoes · log |
| `ConsultaOs/Index` | `consultaos--index` | `portalos` → `MockupPage` (`MOCKUP_BODIES.portalos`) | 2 · busca · os |
| `Nfse/Index` | `nfse--index` | `fiscal-nfse` → `fiscal-page.jsx` `FxNotasPage` (NFS-e) | 7 · header · tabs · toolbar · visoes · tabela · paginacao · debitos |
| `Settings/PaymentGateways/Index` | `paymentgateways--index` | `payment-gateways` → `pg-payment-gateways-page.jsx` (related_prototype do charter) | 5 · header · kpis · aviso · tabela_titulo · tabela |
| `Vestuario/Etiquetas/Index` | `vestuario--etiquetas--index` | `vest-etiquetas` → `vestuario-page.jsx` | 3 · cabecalho · config · lote |

Arquivos: `governance/design/targets/<slug>.{secoes,alvo}.json` para os 5 slugs. Os `.alvo.json` são saída do `alvo:medir`, não editados à mão.

## Sem alvo — a tela não tem rota medível no protótipo

| tela | por quê |
|---|---|
| `Auditoria/Detail` | o protótipo só tem a lista (`MOCKUP_BODIES.auditoria`, estático); não há detalhe de evento. `gov-auditoria` é a auditoria do MCP (Governança), outra tela |
| `Nfse/Emitir` | o botão **Emitir → NFS-e** do `FxEmitir` só navega para `fiscal-nfse` (a lista); não existe formulário de emissão de NFS-e no protótipo |
| `Nfse/Show` | o detalhe da nota no protótipo é **drawer** (`FxNotaDrawer`, após clicar numa linha); a produção é página. Medir o drawer como alvo da `Show` decidiria a forma da tela — **decisão [W]**, não medida |
| `Settings/PaymentGateways/CnabRetorno` | nenhuma rota do protótipo; o charter declara `n/a (herda PT-02)`. O `SheetRemessaRetorno` de `boletos-page.jsx` é da tela de Boletos, não desta |

## Como foi medido

- Espelho servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`, raiz = `MIRROR_DIR` do `protocolo.config.mjs`) na porta **5561**.
- Seletores colhidos com `alvo:mapa --rota <r> --raiz <raiz>` no DOM vivo (as raízes resolveram — o JSON trouxe `raiz: div`, não o fallback `body`).
- `node scripts/design-sync/alvo.mjs --alvo http://127.0.0.1:5561/ --tela <slug> --rota <r> --secoes governance/design/targets/<slug>.secoes.json --quieto-ms 2000`, **duas vezes** por tela; sha256 (16 primeiros) idêntico nas duas:
  auditoria `82e071027890e63c` · consultaos `82f79c03cb7c751b` · nfse `c5f9876e077d3be0` · paymentgateways `f4a4430412d169ec` · vestuario `7bc6d34da6082221`.
- Viewport 1280×900, tema dark (default do `alvo.mjs`, que espera `__oiLazyDone` e janela de 2000 ms sem mudança no nº de nós). `nos_totais`: 466 · 437 · 557 · 651 · 1019. `ausentes: []` nos cinco.

## Provas do json conferidas

- `governance/design/targets/auditoria--index.alvo.json` com a chave `secoes` — existe.
- `governance/design/targets/vestuario--etiquetas--index.alvo.json` com a chave `secoes` — existe.
- Nota do índice ("as outras 7 provas entram no recibo com o nome final de cada slug"): 3 delas existem (`consultaos--index`, `nfse--index`, `paymentgateways--index`); as outras 4 estão na tabela "Sem alvo" acima.

## Pendente (não inventado)

1. **Auditoria/Index e ConsultaOs/Index medem um mockup estático.** As duas rotas renderizam `MockupPage` com HTML de `mockup-bodies.js` (eventos de gráfica, `OS::delete`, banner Acme…), e os dois charters declaram `related_prototype: n/a`. É a única rota do protótipo para esses módulos, por isso foi medida — mas se o [W] não quiser esse mockup como alvo, os dois arquivos saem sem custo. **Decisão [W].**
2. **Vestuário: seção `previa` fora do alvo.** O protótipo abre com a prévia ligada (tweak `vstPrevia: true`), e ela mesma se marca "proposta F1 · pendente [W]" (D-2). Medir faria o `secao-check` cobrar uma seção que a produção não tem por decisão em aberto.
3. **`design.json` por tela e `tableRow` em `__DD_ROLES`** (pedido da ficha) são do `design-diff` (`governance/design/targets/medidas/` e `roles/`), fora do `prefixo` desta thread; o `alvo.mjs` não recebe `__DD_ROLES`. Não feito aqui.
4. A tabela "Alvos exportados" do `governance/design/targets/README.md` não foi atualizada: o README está fora do `prefixo`.

## Errata ao índice

- O título da thread diz "9 telas"; 4 não têm rota medível no protótipo (tabela acima). A prova declarada no json cobre só as duas que existem.

## Placar

entregue 5 de 9 alvos · 20 de 20 seções medidas · ausentes 0 · 4 telas sem fonte de protótipo.

## PR

O PR que adiciona este arquivo — branch `claude/lote-trio-medida-thread-A1`.
