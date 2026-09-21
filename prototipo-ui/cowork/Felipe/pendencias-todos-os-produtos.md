# Pendências — tela "Todos os produtos" (`prod-lista`)

Lista de entrega para quem implementa. Consolidada em 01/09/2026 a partir do §10, §20 e §21 de
[`absorcao-v2-em-todos-os-produtos.md`](absorcao-v2-em-todos-os-produtos.md), mais um defeito novo
medido hoje (P-1).

## §0 Contrato de leitura

**Autoridade.** A tela manda. Onde este documento e a tela divergirem, vale a tela — e a divergência
volta como pergunta, não como conserto.

**Escopo desta lista: fechada.** São os 8 itens abaixo. Nada além deles foi encontrado nas varreduras
de 28/08 (eixo 4 do §10) e 01/09 (§21 + varredura de overlays). **Não acrescentar item por conta**;
achado novo vira linha nova aqui, com medição.

**Procedência de todo valor citado.** `[TELA]` = medido em `produto-blade.jsx` / `produto-blade.css`,
com linha. `[DS]` = citado do design system, com arquivo e linha. `[TPL]` = template canônico
`templates/pt-01-lista`. **Nenhum número deste documento foi inferido** — todos foram lidos no
arquivo em 01/09/2026.

**Proibições que valem para a lista inteira.** Não substituir componente do DS por versão local. Não
"corrigir" o que está declarado conforme no §3. Não alterar `app.jsx` (ver §4). Não mexer em arquivo
fora de `produto-blade.jsx` / `produto-blade.css` — os itens que tocam o shell estão marcados e
esperam decisão.

**Ordem sugerida:** P-1 primeiro (defeito, isolado, baixo risco). Depois P-6 e P-7 (item por item).
P-2/P-3/P-4 juntos, numa onda só (é a "onda 0b"). P-5 por último.

---

## §1 P-1 · Defeito — o menu "Ações" fecha ao arrastar a própria barra de rolagem

**Eixo:** comportamento. Não é responsividade — nada aqui depende da largura da janela.

**Está** `[TELA]` — `produto-blade.jsx` L181:

```js
window.addEventListener("scroll", fecha, true);
```

O painel (`.pb-menu`, portal, `position:fixed`) tem `maxHeight` (L197) e rola por dentro. Com
`capture: true`, o listener recebe a fase de captura de **qualquer** scroll do documento —
inclusive o do próprio painel. Arrastar a barra dele dispara `scroll` ⇒ `fecha()` ⇒ o menu some.

**Não é o clique-fora.** O L175 já exclui o painel corretamente
(`!painel.current?.contains(e.target) && !botao.current?.contains(e.target)`). Só o `scroll` erra.

**Deve ficar** — filtrar a origem do evento, mantendo o listener:

```js
const fecha = (e) => { if (e && painel.current?.contains(e.target)) return; setPos(null); };
```

**Por que não remover o listener.** Ele existe por um motivo real: o painel é `position:fixed` em
portal (L195-197, portado para portal porque `td` do DS tem `overflow:hidden` e recortava o menu —
comentário L147-148). Sem fechar no scroll da página, o menu fica flutuando descolado da linha que o
abriu.

**Referência do padrão certo, no próprio protótipo:** `venda-v3.jsx` L596-600 — o calendário do
`DatePicker`, mesmo cenário de portal, registra `remede` (reposiciona) no scroll, não `fecha`.
Se preferirem reposicionar em vez de fechar, é essa a implementação a copiar.

**Como conferir.** Abrir "Ações" numa linha, arrastar a barra de rolagem do menu de cima a baixo: o
menu permanece aberto e rola. Em seguida rolar a **página**: o menu fecha (comportamento preservado).

**Varredura de vizinhos — fechada, nada mais tem isso.** Os três `createPortal` e todos os
`addEventListener('scroll', …)` do projeto foram medidos em 01/09:

| arquivo | linha | o que faz no scroll | veredito |
| --- | --- | --- | --- |
| `produto-blade.jsx` | L181 | `fecha` | **é este o defeito** |
| `venda-v3.jsx` | L600 | `remede` (reposiciona) | correto |
| `vendas-create-page.jsx` | L170 | scrollspy de seção | não é overlay |
| `manufacturing-print.jsx` | L115 | portal de impressão, sem listener | não se aplica |

O dropdown "Colunas" (`produto-blade.jsx` L713-723) é `absolute` inline dentro do `.pb-kebab`, sem
listener de scroll — o `contains` do clique-fora alcança o painel. **Conforme, não mexer.**

---

## §2 Onda 0b — os três itens de token do shell (fazer juntos)

Os três mexem no mesmo arquivo e no mesmo mecanismo. **Aviso de raio:** o `produto-blade.css` serve
**11 telas** do módulo Produto, não só a lista. Fazer os três de uma vez, com passada visual nas 11.
Separar um do outro entrega metade do efeito e dobra a conferência.

### P-2 · Padding dos slots está cravado em px

**Está** `[TELA]`: `.pb-body` `16px 20px 28px` (css L4) · `.pb-toolbar` `8px 12px` (L86) ·
`.pb-chips` `8px 12px` (L97) · `.pb-pag` `9px 12px` (L106).

**Deve ficar** `[TPL]`: `padding: var(--d-cpad-y, 12px) var(--d-cpad-x, 14px)` em todo slot de
conteúdo, como o `templates/pt-01-lista` faz. O fallback do `var()` preserva o valor atual quando o
token não estiver escrito.

**Como conferir.** Trocar a densidade no segmented: o padding da toolbar, dos chips e do corpo muda
junto com o da tabela. Hoje só a tabela responde.

### P-3 · Densidade é classe local, não contrato de token

**Está** `[TELA]`: `.pb-dense .pb-body{padding:12px 16px 22px}` e `.pb-tbl.densa tbody td{padding:3px 8px}`
(css L80-82 e L261-263) — o segmented liga/desliga classe.

**Deve ficar** `[TPL]`: o segmented escreve tokens na raiz do shell — `--d-fontsz`, `--d-cpad-x/y`,
`--d-tb-y`, `--d-td-y`, `--d-th-y` — e as regras leem `var(--d-*)`. As classes `.pb-dense` e
`.densa` saem.

**É mecanismo, não valor.** Os números atuais podem ser preservados como fallback do `var()`.

**Como conferir.** Com o DevTools na raiz do shell, trocar a densidade: as variáveis `--d-*` mudam de
valor e nenhuma classe é adicionada ou removida no DOM.

### P-4 · Rampa `--fs-*` usada só em parte

**Está** `[TELA]`: sobram tamanhos fixos — `12.5px` (css L23, L33, L49), `11.5px` (L55, L57, L99,
L106), `11px` (L8, L28, L40) e `20px` no valor do KPI (L179).

**Deve ficar** `[TPL]`: a rampa `--fs-1..9` (10,5 / 11,5 / 12,5 / 13,5 / 15 / 18 / 22 / 28 / 38px) e
`font-size: var(--d-fontsz)` na raiz. Os valores acima **já coincidem** com degraus da rampa:
`11px`→`--fs-2` (11,5), `11.5px`→`--fs-2`, `12.5px`→`--fs-3`, `20px`→ decisão entre `--fs-6` (18) e
`--fs-7` (22) — **não escolher sozinho, perguntar**.

**Já conforme, não mexer:** `.pb-tbl thead th` (L50), `.pb-chips-l` (L98), `.pb-chip span` (L101),
`.pb-kpi small` (L178) e `.pb-dt span` (L196) já usam `var(--fs-1)`.

**Como conferir.** `grep -nE 'font-size:\s*[0-9]' produto-blade.css` não devolve nenhuma linha fora
das que este item lista.

---

## §3 P-5 · Tabelas secundárias ainda usam `.pb-tbl` própria

**Está** `[TELA]`: `.pb-tbl` (css L48-65) com `thead` sticky, `th 8px/10px`, `td 7px/10px` — usada no
**Relatório de estoque** e nas tabelas **dentro do drawer**.

**Deve ficar** `[DS]`: `DataTable` / `DataTablePro`, com padding vindo de `--d-td-y` / `--d-th-y`.

**Escopo estreito:** a **lista principal já usa a grade do DS**. Este item é só sobre as secundárias.
**Não** reescrever a lista principal.

**Como conferir.** As tabelas do Relatório de estoque e do drawer respondem ao segmented de densidade
pelos mesmos tokens da lista principal.

---

## §4 P-6 e P-7 · Componentes do DS recriados na tela

### P-6 · Controles do widget "Filtros" são HTML cru

**Está** `[TELA]`: sete `<select>` e um `<input type="checkbox">` estilizados por `.pb-fld`
(css L21-34).

**Deve ficar** `[DS]`: `Select` (`_ds_bundle.js` L4546) e `Checkbox` (L2511) — já estão no bundle
carregado pela tela.

**O widget "Filtros" fica.** É decisão fechada da tela (§3 da absorção); este item é só sobre os
**controles** dentro dele. **Não** trocar o widget por outro contêiner.

**Como conferir.** Os oito controles do widget renderizam com o anel de foco e a altura do DS,
idênticos aos de outra tela que use `Select`.

### P-7 · `.pb-kpi` sobreviveu no CSS

**Está** `[TELA]`: `.pb-kpi` / `.pb-kpis` (css L176-182) continuam definidos, embora a onda 0c tenha
trocado as placas **da lista** pelo `KpiCard` do DS.

**Situação:** ele ainda serve **outras telas** do módulo. Enquanto servir, é código compartilhado,
não resíduo — **não apagar agora**. Sai junto com a onda 0b, quando as outras telas migrarem.

**Como conferir.** `grep -n 'pb-kpi' produto-blade.jsx` não devolve ocorrência na `TelaLista`.

---

## §5 P-8 · Pergunta em aberto — `.os-btn.danger` do shell (não é da tela)

**Está** `[TELA]`: `produto-blade.css` L264-268 corrige, dentro do módulo, um `.os-btn.danger` vindo
de `superadmin-page.css` que vencia `.os-btn.ghost.danger` e deixava texto vermelho sobre fundo
vermelho (**~1,6:1**). O contorno está declarado no próprio arquivo.

**Não é defeito da tela: é defeito de origem do shell.** Não remover o contorno enquanto a origem não
for corrigida. Pendente de decisão da Maiara: levar para `pauta-design-system.md` como defeito de
origem, ou manter o contorno declarado onde está.

---

## §6 O que **não** é pendência (decidido — não reabrir)

- **Sidebar em hue 295, ghosts no sidebar e slot do atalho `G X`.** A tela **já tem** (`styles.css`
  L3-11, L5173-5179, L6593, L6638-6647). A ADR UI-0028 copiou esses valores **daqui** para o
  `cockpit.css` do repo. Quem está atrás é o espelho do DS (hue 240), não a tela.
- **ADR 0386 (roxo é a única identidade de chrome).** Já conforme: `--accent oklch(0.55 0.15 295)`
  (`styles.css` L23), `--accent-h: 295` (L6342, L6452, L6582), zero ocorrências de `.oficina-scope`.
- **Tweak "Tom do accent" (`app.jsx` L1012-1017), slider `0-360`.** Fica como está — decisão da
  Maiara em 01/09. É ferramenta de exploração do protótipo, fora do invariante de hue 250-330 da
  ADR 0263. O valor de entrega é o default `295` (`app.jsx` L679). **Não** alterar a faixa do slider
  e **não** copiá-la para o repo.
- **Paginação da lista** (§2.10 da absorção): recusada por decisão da tela. O `.pb-pag` local segue
  servindo as telas de formulário.
- **Verificado conforme, não mexer:** foco visível `outline:2px solid var(--accent)` em 6 seletores
  (css L32) · alvos ≥44px em `pointer:coarse` (L127-136) · piso na região de dados +
  `overflow:auto` no shell (L3-4) · nenhuma cor nova, só token (`grep oklch` = 4 linhas, todas preto
  de sombra/scrim ou derivado de `--accent`) · moldura em `--radius-lg` + `--shadow-soft` (L7, L48) ·
  zero `cursor:default` em elemento clicável.
