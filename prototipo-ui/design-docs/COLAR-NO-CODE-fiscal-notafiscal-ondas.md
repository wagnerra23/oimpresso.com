# COLAR NO CODE — Módulo Fiscal · pacote de EXPORT ancorado no código

> **De:** [CC] Claude Design (Cowork) · **Para:** [CL] Claude Code no `main` · **Data:** 2026-09-03
> **Substitui** a versão "plano em 9 ondas · 28 PRs" deste mesmo arquivo (anti-scatter §2-ter do `COLAR-NO-CODE-PROTOCOLO-COWORK-EXPORT.md`). As 11 perguntas ⛔ [W] foram **preservadas** no bloco 7; o que mudou é a forma: agora cada onda diz **qual arquivo editar, o que reusar, o que criar, o que não tocar e quando parar**.
> **Ancoragem dupla (§4-bis):** alvo de **layout** = protótipo medido aqui; âncora de **implementação** = arquivo real do `main`. O `main` responde *onde e com que dado*; o protótipo responde *como*.
> **Leitura do `main` NESTE turno** (árvores `1582cb14ca3a` · `8ce4de791752`, 2026-09-03T20:40–20:43Z): `resources/js/Pages/Fiscal/` (21 arquivos), `_components/` (11), `_lib/` (6), `Cockpit.tsx`, `Nfe.tsx`, `_components/FxShell.tsx`, `_lib/botao-fiscal.ts`, `_lib/chip-filtro.ts`, `Modules/Fiscal/**` (44 arquivos), `Modules/Fiscal/Routes/web.php`.
> **Ponte, não build.** Não exportar este `.md` para `prototipo-ui/cowork/` (guard R1). Eu **não escrevo no git**: desce por [W] colando 1× ou por Issue `cowork-intake`.

---

## 0 · Leis que não se renegociam

1. **A produção não se repinta "pro protótipo".** Frescor 🔵: as 7 telas do Fiscal são réplica viva e **estão à frente do meu alvo em 3 pontos medidos** (primitivas do DS já usadas, `aria-label` em busca e checkbox, `aria-pressed` na densidade). Onda é cirúrgica.
2. **Nenhuma onda escreve motor fiscal.** Cálculo é do `MotorTributarioService`; CC-e/inutilização/cancelamento/retransmissão são Services do `NfeBrasil` (o `AcoesController` já delega); emissão de serviço é do `NfseEmissaoService`; SPED é do `SpedIcmsIpiGeneratorService` (29 KB, com teste).
3. **Ledger de `Eventos` é append-only** — sem `UPDATE`/`DELETE`; correção é evento novo.
4. **Sem número fiscal sem lei citada literal** (`Ajuste SINIEF 07/2005`, janela 24h NFC-e / 168h NF-e, `cstat 102`, `tpEvento 110110/110111`, LC 116, CONFAZ Guia Prático v3.1.1 perfil A).
5. **Sem fonte de dado ⇒ `—` + linha no PR.** Nunca `rand()`, nunca literal disfarçado de dado.
6. **`501`/`NAO_IMPLEMENTADO` nunca se apresenta como sucesso** (XML, DANFE, TXT do SPED).
7. **1 assunto por PR · ≤ 8 arquivos · ≤ ~300 linhas de diff.** Migration nunca junto com UI. 🔴 (fiscal/legal/multi-tenant/schema) vai **sozinho**, com o teste no mesmo PR.
8. **Onda = sessão limpa** (§2-quater): quem executa lê o read-order no `main` + esta seção + o charter da tela. O pedido passa no teste do estranho.
9. **⛔ [W] trava o PR.** Abrir sem a decisão é inventar lei fiscal ou desenho.
10. **Placar obrigatório no corpo do PR** — sem ele, omitir é grátis.

---

## 1 · Ordem das ondas e âncora por onda

Granularidade: o Fiscal é **multi-view (7 telas)** ⇒ onda = **uma seção de uma tela**. Nenhuma onda passa de 1 PR ≤300 linhas.

| Onda | Seção (seletor do alvo) | Alvo (layout) | Âncora (código do `main`) | Trava |
|---|---|---|---|---|
| **1** | Alertas fiscais (`.fx-alerts`) — Cockpit | `fiscal-page.jsx` §`FxAlerts` | `Pages/Fiscal/Cockpit.tsx` (a prop `alerts` **chega e só é contada**) + `_components/` (criar `AlertasFiscais.tsx`) | — |
| **2** | Linha clicável operável por teclado (`.fx-table tbody tr`) — Cockpit + NF-e | `fiscal-page.jsx` §`FxNotasTable` | `Cockpit.tsx` (tabela unificada) · `Nfe.tsx` (tabela `fx-table[data-keyboard]`) | — |
| **3** | Paginação + rodapé de atalhos (`.fx-pager`) — Cockpit + NF-e | `fiscal-page.jsx` §`FxNotasPage` | `Cockpit.tsx` (hoje sem paginação) · `NfeCockpitController` (`rows.meta` **já pagina**) | — |
| **4** | Sparklines no ribbon (`.fx-ri svg`) — Cockpit | `fiscal-page.jsx` §`FxSpark`/`FxRibbon` | `Cockpit.tsx` — `interface Sparklines` existe, a prop **não é desestruturada** | ⛔ [W] 2 |
| **5** | Filtro por tipo + densidade (`.fx-select`, `.fx-density`) — NF-e e NFS-e | `fiscal-page.jsx` §`FxNotasPage` toolbar | `Nfe.tsx` (tem subtabs, **não tem** os dois) · `Nfse.tsx` | ⛔ [W] 1 |
| **6** | Manifestação em lote (`.fx-bulk[data-contract=lote-dfe]`) — DF-e | `fiscal-subpages.jsx` §`FxDfePage` | `Pages/Fiscal/Dfe.tsx` + `DfeController` + rota `POST /fiscal/acoes/dfe/{recebido}/{acao}` (hoje **por linha**) | ⛔ [W] 3 |
| **7** | Export CSV + chips de tipo de evento (`.fx-chips`) — Eventos | `fiscal-subpages.jsx` §`FxEventosPage` | `Pages/Fiscal/Eventos.tsx` + `EventosController` | — |
| **8** | Abas + gate de ambiente (`[data-contract=abas-config]`, `[data-contract=gate-ambiente]`) — Config | `fiscal-subpages.jsx` §`FxConfigPage` | `Pages/Fiscal/Config.tsx` (31 KB) + `ConfigController` | ⛔ [W] 4 |
| **9** | Validação de competência + prévia do TXT (`[data-contract=validacao-competencia]`, `[data-contract=previa-txt]`) — SPED | `fiscal-subpages.jsx` §`FxSpedPage` | `Pages/Fiscal/Sped.tsx` + `SpedController@gerar` + `SpedIcmsIpiGeneratorService` | — |
| **10** | Procedência por superfície (`.fx-proc`, 6 superfícies) — transversal | `fiscal-actions.jsx` §`FxProc` | `Cockpit.tsx` · `Dfe.tsx` · `Eventos.tsx` · `Config.tsx` | ⛔ [W] 5 (`CU-FISC-16`) |

**Ordem enxuta:** `1 → 2 → 3` (sem trava, nesta ordem) · `7 → 9` (sem trava, paralelas) · `4 · 5 · 6 · 8 · 10` **só depois** da decisão [W] correspondente.

**Fora deste pacote, por decisão já escrita:** emissão NF-e/NFS-e, contingência SEFAZ (`tpEmis` fixo em 1 — `NfeService.php:1198`), IBS/CBS, SPED completo, lane com `nfe_emissoes`. São **capacidade de backend**, não seção de tela — continuam como fila 🔴 no bloco 7, não viram onda de UI.

---

## 1-bis · Instrução de execução por onda (§4-ter)

### ONDA 1 — Alertas fiscais (`.fx-alerts`)
```
ARQUIVOS A EDITAR   : resources/js/Pages/Fiscal/Cockpit.tsx
                      resources/js/Pages/Fiscal/_components/AlertasFiscais.tsx  (novo)
                      resources/js/Pages/Fiscal/_components/FxShell.tsx  (1 linha — ver abaixo)
REUSAR (não recriar): Alert do DS (@/Components/ui) para a moldura tintada 6%/22%;
                      Button variant="cowork-ghost" para a ação do alerta;
                      a interface `Alert` que JÁ existe em Cockpit.tsx:44-52
                      (level 'crit'|'warn'|'info' · icon · title · sub · action · goto · focus?);
                      **o mapa `id → url` já existe**: `FX_PAGES` em FxShell.tsx:23-31
CRIAR               : só o AlertasFiscais.tsx (lista) — nenhum token, nenhuma classe nova
NÃO TOCAR           : o ribbon, a toolbar, a tabela, o WriteOffAuditoriaCard, e o RENDER/
                      comportamento do FxShell (a única mudança permitida lá é `const FX_PAGES`
                      → `export const FX_PAGES`)
PASSO A PASSO       : 1) desestruturar `alerts` no componente (hoje só entra no cálculo de
                         `totalRej`, Cockpit.tsx §`const totalRej`)
                      2) renderizar <AlertasFiscais alerts={alerts}/> ENTRE o `.fx-ribbon` e o
                         <WriteOffAuditoriaCard/> — a ordem do alvo (bloco 3)
                      3) cada item: ícone Lucide pelo nome de `icon` + <b>{title}</b> +
                         <small>{sub}</small> + botão {action} → router.visit(url)
                      3-bis) **`goto` é CHAVE de navegação, não path** ('nfe', 'fiscal_config',
                         'dfe'). Resolver por `FX_PAGES.find(p => p.id === goto)?.url` — o mapa
                         tem UM dono (FxShell), não se duplica em _lib (seria LC-19) e o backend
                         NÃO passa a mandar path nesta onda (seria 2º assunto no PR).
                         Chave sem correspondência ⇒ ver PARAR SE.
                      4) `data-contract="alertas-fiscais"` na raiz da lista
                      5) estado vazio: NÃO renderizar nada (0 alertas ⇒ nó ausente)
DADO                : `alerts` do CockpitController (já serializado). Nada novo no backend.
PARAR SE            : o `icon` vier com nome que não existe no Lucide (mapear no _lib, não
                      inventar glyph) · o `goto` não casar com nenhum `id` de `FX_PAGES` (então
                      o alerta renderiza SEM botão + linha no PR nomeando a chave órfã — nunca
                      chutar a url) · a url resolvida não existir em Modules/Fiscal/Routes/web.php
```

### ONDA 2 — Linha clicável com teclado (`.fx-table tbody tr`)
```
ARQUIVOS A EDITAR   : Pages/Fiscal/Cockpit.tsx · Pages/Fiscal/Nfe.tsx
REUSAR              : o próprio <tr onClick> que já existe nas duas telas; o `cursor`
                      de J/K que Nfe.tsx JÁ implementa (Nfe.tsx §useEffect keydown)
CRIAR               : nada
NÃO TOCAR           : filtros, chips, drawers, colunas, larguras
PASSO A PASSO       : 1) `tabIndex={0}` + `aria-label={"Abrir "+tipo+" "+num+" · "+cliente}` no <tr>
                      2) `onKeyDown`: Enter e Space abrem o mesmo drawer do clique
                         (e.preventDefault() no Space, senão a página rola)
                      3) manter o `stopPropagation` da célula de checkbox e da célula de ações
                      4) `:focus-visible` = anel de accent (token, sem outline:none)
DADO                : nenhum
PARAR SE            : a linha virar `role="button"` (destrói a semântica de tabela) — o alvo é
                      linha FOCÁVEL, não botão
```

### ONDA 3 — Paginação (`.fx-pager`)
```
ARQUIVOS A EDITAR   : Pages/Fiscal/Cockpit.tsx (+ CockpitController SE o corte for server-side)
REUSAR              : Pagination do DS; o padrão `rows.meta {current_page,last_page,total,
                      per_page}` que NfeCockpitController já devolve e Nfe.tsx já consome;
                      o `router.visit(..., {only:['rows'], preserveState:true})` do Nfe.tsx
CRIAR               : nada
NÃO TOCAR           : a tabela, os chips de visão salva, a densidade
PASSO A PASSO       : 1) decidir com [W] se o Cockpit pagina server-side (recomendado: igual
                         ao NF-e) ou client-side; 2) rodapé na ordem do alvo: meta "1–8 de N" ·
                         seletor por página (8/25/50) · Anterior · "p / total" · Próxima ·
                         dica "J/K navega · ↵ abre · N emite"; 3) filtro reseta para página 1
DADO                : contagem REAL do controller — nunca `rows.length` do cliente quando
                      houver paginação server-side (número mentiria)
PARAR SE            : não existir contagem total escopada por business (sem ela, "de N" é
                      invenção — renderiza só "Anterior/Próxima")
```

### ONDA 4 — Sparklines no ribbon ⛔ [W] 2
```
ARQUIVOS A EDITAR   : Pages/Fiscal/Cockpit.tsx (+ _components/RibbonSpark.tsx se virar peça)
REUSAR              : Chart do DS (type="line", height baixa) — NÃO desenhar <polyline> à mão;
                      a `interface Sparklines` que já existe em Cockpit.tsx:54-59
CRIAR               : nada além do wrapper
NÃO TOCAR           : os 6 `.fx-ribbon-item` existentes (rótulo/valor/delta ficam)
PASSO A PASSO       : 1) desestruturar `sparklines`; 2) um Chart por KPI que o alvo tem
                      (emitidas · autorizadas · rejeitadas) — os outros 3 NÃO ganham;
                      3) `aria-hidden` no gráfico (o número ao lado já é o dado)
DADO                : `sparklines` do CockpitController — 4 séries chegam, 3 são usadas
PARAR SE            : [W] não decidir (decisão 2). O charter promete no Goal #2, a prop chega
                      e não é usada: construir OU o Goal cede, por escrito.
```

### ONDA 5 — Filtro por tipo + densidade (NF-e · NFS-e) ⛔ [W] 1
```
ARQUIVOS A EDITAR   : Pages/Fiscal/Nfe.tsx · Pages/Fiscal/Nfse.tsx
REUSAR              : Select do DS e o bloco `.fx-density` (role="radiogroup" + aria-pressed)
                      que o Cockpit.tsx JÁ tem — copiar de lá, não reinventar
CRIAR               : nada
NÃO TOCAR           : as subtabs de modelo (NF-e 55 · NFC-e 65 · Entrada) — são do vivo e o
                      protótipo não as tem; a competência da Nfse
PASSO A PASSO       : 1) Select de tipo à direita da busca; 2) densidade no fim da toolbar;
                      3) persistir densidade na MESMA chave do protótipo (bloco 4);
                      4) filtro entra na query string junto dos existentes
DADO                : contadores por tipo do controller (o `counts` já existe no Nfe)
PARAR SE            : [W] não decidir (decisão 1) — ou constrói, ou vira Non-Goal no charter
```

### ONDA 6 — Manifestação em lote (DF-e) ⛔ [W] 3
```
LER NO TURNO        : Pages/Fiscal/Dfe.tsx · Modules/Fiscal/Http/Controllers/DfeController.php
                      (declarados, NÃO lidos por mim neste turno — ver bloco 8)
ARQUIVOS A EDITAR   : Dfe.tsx (+ AcoesController e web.php SE [W] aprovar a rota de lote)
REUSAR              : BulkBar do DS; Checkbox do DS; a rota por linha que já existe
                      (`POST /fiscal/acoes/dfe/{recebido}/{acao}`, throttle 30/min) e o
                      ManifestacaoService do NfeBrasil
CRIAR               : rota `POST /fiscal/acoes/dfe/lote` SÓ se [W] aprovar (decisão 3)
NÃO TOCAR           : a aba Histórico, o prazo (vem da SEFAZ, não de 90 dias no código)
PASSO A PASSO       : 1) checkbox só nas linhas manifestáveis (pendente|ciencia);
                      2) BulkBar com ciência · confirmar · desconhecer(danger);
                      3) modal exige justificativa ≥15 chars quando ≠ confirmar;
                      4) sem endpoint de lote ⇒ N requisições sequenciais, uma por nota, com
                         falha parcial nomeada (nunca "erro 500")
DADO                : `NfeRecebido` escopado por business
PARAR SE            : [W] não decidir a rota · a manifestação em lote não tiver como reportar
                      falha por nota (manifestação é definitiva: lote silencioso é inaceitável)
```

### ONDA 7 — Export CSV + chips de tipo (Eventos)
```
LER NO TURNO        : Pages/Fiscal/Eventos.tsx · EventosController.php (não lidos por mim)
ARQUIVOS A EDITAR   : Eventos.tsx (+ EventosController se o CSV for server-side)
REUSAR              : FilterChip/TagChip do DS para os 5 tipos; Button ghost para "Exportar CSV"
CRIAR               : nada no cliente; 1 endpoint só se [W] pedir server-side
NÃO TOCAR           : o append-only da timeline; a ordem cronológica
PASSO A PASSO       : 1) chips Todos · CC-e (110110) · Cancelamento (110111) · Inutilização (102)
                      · Manifesto destinatário, com contador; 2) filtro reversível (clicar no
                      ativo volta a Todos); 3) CSV com as 7 colunas do alvo (bloco 3), BOM
                      UTF-8 e `;` como separador (Excel pt-BR)
DADO                : `NfeEvento` escopado; cstat real, nunca derivado na tela
PARAR SE            : o período (7/30/90) não existir como filtro no controller — sem ele o
                      rótulo "últimos N dias" mente
```

### ONDA 8 — Abas + gate de ambiente (Config) ⛔ [W] 4
```
LER NO TURNO        : Pages/Fiscal/Config.tsx (31 KB) · ConfigController.php · Config.charter.md
ARQUIVOS A EDITAR   : Config.tsx (+ permissions do módulo SE [W] aprovar o gate novo)
REUSAR              : TabBar do DS para as 4 abas; Modal do DS (PT-04) para confirmação;
                      Alert do DS para o aviso de risco; Input/Switch do DS nos campos
CRIAR               : permissão `fiscal.config.ambiente` — SÓ com decisão [W] (é soberania)
NÃO TOCAR           : a senha do certificado (fica `$hidden`, nunca em payload nem log)
PASSO A PASSO       : 1) 4 abas: Certificado e regime · Séries · Ambiente e certificado · SPED;
                      2) trocar ambiente exige o nome do destino DIGITADO + motivo ≥15 chars;
                      3) toda troca grava evento de auditoria com autor e horário;
                      4) sem o gate, campos travados com o motivo visível no title
DADO                : certificado/regime/séries reais do emissor — a filial do mock não volta
PARAR SE            : [W] não ratificar (decisão 4: card "Envio de documentos", região
                      desancorada, e os 2 pontos já decididos virando ADR)
```

### ONDA 9 — Validação de competência + prévia do TXT (SPED)
```
LER NO TURNO        : Pages/Fiscal/Sped.tsx · SpedController.php · Sped.charter.md
ARQUIVOS A EDITAR   : Sped.tsx (+ SpedController se a prévia for server-side)
REUSAR              : SpedIcmsIpiGeneratorService (dono do arquivo); a rota
                      `GET /fiscal/sped/icms-ipi/{ano}/{mes}` (throttle 3/min) que já existe;
                      Alert do DS para a régua de validação; Progress do DS no "Gerando…"
CRIAR               : nada
NÃO TOCAR           : a trava `sped_simples_only_lock` (fail-secure) — só superadmin libera
PASSO A PASSO       : 1) régua com as 4 checagens do alvo (ano ≥ 2020 · não-futura ·
                      competência fechada · trava), cada uma com ✓/✕ e motivo;
                      2) botão Gerar desabilitado enquanto qualquer uma reprovar;
                      3) prévia = AMOSTRA declarada, com o nº de registros e o perfil;
                      4) validação legal continua sendo do PVA — a tela não promete conformidade
DADO                : blocos/registros do gerador. Sem golden file ⇒ dizer que não existe.
PARAR SE            : a prévia exigir gerar o arquivo inteiro em request síncrono (então é job)
```

### ONDA 10 — Procedência por superfície ⛔ [W] 5
```
ARQUIVOS A EDITAR   : Cockpit.tsx · Dfe.tsx · Eventos.tsx · Config.tsx (1 PR por 2 telas)
REUSAR              : StatusBadge do DS (kind="frescor"/"tipo") como selo; Tooltip do DS
                      para o "por quê"
CRIAR               : 1 helper em _lib/ com o mapa superfície → procedência
NÃO TOCAR           : o dado em si — o selo NÃO esconde nem substitui número
PASSO A PASSO       : 1) as 6 superfícies do CU-FISC-16 (lista unificada · eventos do
                      cabeçalho · contadores das visões salvas · situação SEFAZ · pacote da
                      contabilidade · write-off); 2) a marcação é IGUAL nas 6;
                      3) alternável e persistente (chave no bloco 4)
DADO                : a própria procedência (real | demonstração) declarada pelo controller
PARAR SE            : [W] não escolher entre marcar na UI, esconder atrás de flag ou Non-Goal
                      — e é esta decisão que destrava filtro/visão/densidade como contrato
```

---

## 2 · Onda 0a — a11y do ALVO (A1–A12): o que eu **corrigi no build hoje**

O alvo não é sagrado (§5-bis). Bateria rodada no protótipo servido, tema dark, após `__oiLazyDone`:

| # | erro | medido ANTES | ação |
|---|---|---|---|
| A1 | falso interativo | 8 `.fx-table tbody tr` clicáveis, **0 com `tabindex`** — lista inteira inoperável por teclado | ✅ **corrigido no build**: `tabIndex={0}` + `aria-label` + Enter/Space (`fiscal-page.jsx`, `fiscal-subpages.jsx` SPED) |
| A3 | ícone sem nome | **4 de 4** svg em botão sem `aria-hidden` nem nome | ✅ **corrigido no build**: `FxI`/`FsI` embrulham em `<span aria-hidden="true">` |
| A4 | overlay sem foco/trap | drawer com `role="dialog"` mas `aria-modal` ∅ e foco no `BODY` | ✅ **corrigido no build**: `aria-modal="true"`, foco inicial no "Fechar", Tab preso, foco devolvido ao fechar |
| A10 | dinâmico sem `aria-live` | `[aria-live]` = **0** na carga (o container nascia junto do 1º toast ⇒ leitor não anuncia) | ✅ **corrigido no build**: `.fx-toasts` sempre montado, `pointer-events:none` |
| A5 | ARIA de estado | subnav = TabBar do DS (7 botões) · chips com `aria-pressed` · densidade `role="radiogroup"` | ✅ passa |
| A6 | estado só por cor | `.fx-sefaz` e `.fx-timepill` têm **texto** (não só tom) | ✅ passa |
| A12 | estado vazio | `.fx-empty` com por-quê + o que fazer, nas 3 grades | ✅ passa |
| A2 | foco removido | não auditado por seção nesta rodada | 🟠 declarado (bloco 8) |
| A7 | alvo de toque | **18 de 47** botões < 24×24 (ações de linha, densidade) | ⚪ **decisão [W]**: ERP denso 1280px × WCAG 2.5.8 |
| A11 | skip link | fundação do shell, não do módulo | 🟠 fora de escopo |

**Verificado depois da correção, no DOM vivo:** `svg` sem nome **0/4** · `tr[tabindex="0"]` **8** · `[aria-live]` **1** · drawer `aria-modal="true"` com `activeElement` = botão "Fechar" e `esc` fechando · **0 erro de console**.

**Dívida do build que NÃO virou pedido** (é minha, não do Code): 13 controles nativos (`<input>`/`<select>`) na página do Fiscal. O `main` **já está à frente** aqui — `Cockpit.tsx` usa `Select`/`Checkbox`/`Input` do DS. Portanto o pedido especifica primitivas do DS **mesmo onde meu alvo usa nativo**; regredir para o meu markup seria exportar defeito.

---

## 3 · ALVO medido por seção (o que reprova)

**T1 · estabilidade:** três leituras iguais de `querySelectorAll('*').length` = **1099 / 1099 / 1099**, rota `fiscal`, dark, após `__oiLazyDone`. Tokens medidos por `getComputedStyle` no elemento — nunca pela classe declarada.

### Cockpit (`.fx-page`) — 10 filhos NESTA ordem
`.fx-h` · `.ds-tabbar` · `.fx-ribbon` · `.fx-alerts` · `.fx-writeoff` · `.fx-toolbar` · `.fx-chips` · `.fx-table.fx-d-comfort` · `.fx-pager` · `.fx-toasts`

| seção | alvo |
|---|---|
| `.fx-h` | 2 filhos: bloco `h1`+`p` · `.fx-h-r` (situação SEFAZ · Procedência · `⌘K` · Eventos · Enviar p/ contabilidade · Emitir) |
| `.ds-tabbar` | **7** botões (TabBar do DS via `CliTabs`), contadores mono nas 4 com número |
| `.fx-ribbon` | `display:flex` · **7 filhos** = 6 `.fx-ri` + `.fx-ribbon-cta`; `.fx-ri` padding **12px 18px**, `border-right 1px oklch(0.31 0.008 240)`; valor **18px/600**; rótulo **10.5px** uppercase, `letter-spacing .63px`, `oklch(0.58 0.005 90)` |
| `.fx-alerts` | **4** `.fx-alert`; cada um `flex`, gap **10px**, padding **10px 14px**, radius **10px**, bg `oklch(0.3252 0.01712 248.7)`; filhos na ordem: `.fx-alert-ic` · `.fx-alert-t` (b+small) · botão |
| `.fx-writeoff` | 4 filhos: `.fx-alert-ic` · `.fx-writeoff-t` · `.fx-btn` (Revisar) · `.fx-btn` (Depois) |
| `.fx-toolbar` | `flex`, gap **8px**, **4 filhos**: `.fx-search` · `.fx-select` (tipo) · `.fx-select` (status) · `.fx-density` |
| `.fx-chips` | **6** `.fx-chip`; altura **28.1px**, radius **999px**, 12.5px, cor ativa **`oklch(0.7 0.15 295)`** (accent dark) |
| `.fx-table` | `th` **10.5px** uppercase `oklch(0.58 0.005 90)` padding **9px 14px**; **7 colunas na ordem**: ⃞ · Tipo · Número · Cliente / chave · Status · Prazo · Valor; **8 linhas**, células `[td, td, .num, .cli, td, td, .val]`; linha **112.8px** em `comfort` |
| pílulas | `.fx-sefaz` 11.5px, padding **2px 9px**, radius 999px, ok = `oklch(0.74 0.14 150)` sobre o mesmo tom a **8%** · `.fx-timepill` 11.5px, padding **2px 8px** |
| `.fx-pager` | **6 filhos na ordem**: meta "1–8 de N" · `.fx-select` (8/25/50) · Anterior · `.fx-pager-n` · Próxima · `.fx-pager-hint` |
| drawer | `role="dialog"` + **`aria-modal="true"`**, foco inicial no "Fechar", `esc` fecha 1 nível, rodapé [Baixar XML · Baixar DANFE/PDF · (CC-e) · (Cancelar) · (Retransmitir)] |
| `--accent` (dark) | **`oklch(0.70 0.15 295)`** — não o `0.55` do light. `--fis` do port **não existe mais** no protótipo (string vazia) |

### Sub-páginas — filhos na ordem + nós do documento
| rota | `h1` | filhos de `.fx-page` | nós |
|---|---|---|---|
| `fiscal-nfe` | NF-e · NFC-e | `.fx-h` · `.ds-tabbar` · `.fx-toolbar` · `.fx-chips` · `.fx-table` · `.fx-pager` · `.fx-card` · `.fx-debitos` | 1021 |
| `fiscal-dfe` | Manifesto DF-e | `.fx-h` · `.ds-tabbar` · `.ds-tabbar` · `.fx-toolbar` · `.fx-chips` · `.fx-table` · `.fx-nota-rodape` | 853 |
| `fiscal-eventos` | Eventos fiscais | `.fx-h` · `.ds-tabbar` · `.fx-chips` · `.fx-card` · `.fx-nota-rodape` · `.fx-debitos` | 838 |
| `fiscal-config` | Certificado e configuração fiscal | `.fx-h` · `.ds-tabbar` · `.fx-chips` · `.fx-grid` | 828 |
| `fiscal-sped` | SPED e livros fiscais | `.fx-h` · `.ds-tabbar` · `.fx-validacao` · `.fx-table` · `.fx-grid` · `.fx-nota-rodape` · `.fx-debitos` | 888 |

**Âncoras `data-contract` que o alvo publica** (o Code preserva o nome, não inventa): `procedencia` · `kpi-ribbon` · `alertas-fiscais` · `write-off` · `toolbar-notas` · `visoes-salvas` · `tabela-notas` · `drawer-nota` · `acoes-mutacao` · `abas-dfe` · `filtros-dfe` · `tabela-dfe` · `lote-dfe` · `historico-dfe` · `abas-config` · `cert-regime` · `gate-ambiente` · `troca-ambiente` · `series-config` · `validacao-competencia` · `panorama-sped` · `previa-txt` · `blocos-arquivo` · `validacao-externa`. No `main` já existe `fiscal-nfe-filters` (Nfe.tsx) e `fiscal-cockpit-kpis` (Cockpit.tsx) — **esses dois mandam**; onde divergir, o nome do vivo vence.

---

## 4 · Comportamento (EARS) + invariantes

Uma linha por elemento interativo. `QUANDO <gatilho> O SISTEMA DEVE <efeito>`.

| elemento (TAG) | gatilho | efeito | persistência | reversível | prova |
|---|---|---|---|---|---|
| `.fx-table tbody tr` (**TR** focável) | clique · Enter · Space | abre drawer da nota | não persiste | `esc` fecha | `tr[tabindex="0"]` = nº de linhas; Enter abre `[aria-modal]` |
| checkbox da linha (**INPUT**→`Checkbox` do DS) | clique | entra na seleção; **`stopPropagation`** (não abre drawer) | não persiste | clicar de novo sai | selecionar 1 linha ⇒ BulkBar com "1 nota selecionada" e drawer fechado |
| `.fx-chip` visão salva (**BUTTON**) | clique | aplica tipo+status, limpa busca e cliente, volta à pág. 1 | não persiste | escolher outra visão | `aria-pressed` migra; contador do chip = linhas filtradas |
| `.fx-select` tipo/status (**SELECT**→`Select` do DS) | change | filtra e marca a visão como `custom` | query string (vivo) | voltar a "Todos" | chip "Filtro manual" aparece com a contagem |
| `.fx-density` (**BUTTON** ×3, `role=radiogroup`) | clique | troca a densidade da tabela | **`localStorage["oimpresso.fiscal.densidade"]`** | escolher outra | reload mantém a escolha |
| botão Procedência (**BUTTON**) | clique | mostra/esconde os selos das 6 superfícies | **`localStorage["oimpresso.fiscal.procedencia"]`** = `"1"`/`"0"` | clicar de novo | `.fx-proc` aparece/desaparece nas 6 |
| `.fx-link` do cliente (**A**) | clique | filtra por aquele cliente; **`stopPropagation`** | não persiste | `×` no `.fx-active-filter` | filtro ativo visível com o nome |
| `.fx-row-act` XML/PDF/↻ | clique | baixa / retransmite; **`stopPropagation`** | — | — | ação dispara sem abrir drawer |
| `.fx-pager` Anterior/Próxima | clique | troca de página; desabilitado nos extremos | não persiste | — | `disabled` na 1ª/última |
| teclado da lista | `j` `k` `↵` `n` | move cursor · abre · vai pra emissão. **Inibido** em input/textarea, com modal ou drawer aberto | — | — | cursor não anda com foco na busca |
| `⌘K` (global) | atalho | abre a paleta cross-fiscal (notas · DF-e · 7 telas) | — | `esc` | `[aria-modal]` com input focado |
| mutação (cancelar · CC-e · inutilizar · manifestar) | clique | **modal PT-04** com justificativa **≥15 caracteres**; confirmar desabilitado até validar | — | `esc` cancela sem efeito | afrouxar o mínimo derruba `UC-FNFE-04` (`AcoesContratoTest`) |
| troca de ambiente | clique | exige o nome do destino **digitado** + motivo; grava evento de auditoria | servidor | — | confirmação errada ⇒ nada muda, toast de aviso |

**Invariantes do módulo** (valem sem repetir por seção): 1 clique aninhado declara `stopPropagation` · 2 filtro é reversível · 3 `esc` fecha **um** nível · 4 teclado escopado à seção montada (`⌘K` e `?` globais) · 5 chave de persistência vem no pedido, ninguém inventa nome · 6 estado vazio diz por que e o que fazer · 7 `focus-visible` accent em tudo clicável · 8 mutação fiscal é irreversível ⇒ confirmação com justificativa e auditoria · 9 evento fiscal é append-only · 10 sem fonte ⇒ `—` + linha no PR.

---

## 5 · NÃO INVENTAR (o Design System é lei)

**Componentes — reusar o que existe, nesta ordem de precedência:**
1. **`_lib/` do próprio Fiscal** (já resolveram o mapeamento, com o motivo escrito): `botao-fiscal.ts` (`btnProps(kind)`: `fx-btn` → `Button` do DS — `ghost`→`cowork-ghost`, `primary`→`cowork-primary`, `danger`→`destructive+size:cowork`, `warn`→`outline`+tokens, default→`secondary`) · `chip-filtro.ts` (`chipProps(active,tone)`/`chipCount(active)`) · `sefaz-codes.ts` · `sefaz-actions.ts` · `fiscal-helpers.ts` (`brl`, `truncKey`, `formatDoc`, `prazoCancel`) · `linkify.tsx`.
2. **Componentes de tela do Fiscal**: `FxShell` · `NotaDrawer`/`NotaDrawerV2` · `NFSeDrawer` · `EventosDrawer` · `SendToContabilDrawer` · `InutilizacaoModal` · `SavedViewsChips` · `WriteOffAuditoriaCard` · `CmdKPalette` · `_shared/DrawerBase`.
3. **Primitivas do DS** (`@/Components/ui`): `Button` · `Input` · `Select` · `Checkbox` · `Alert` · `Modal` · `Drawer` · `TabBar` · `Pagination` · `BulkBar` · `FilterChip` · `StatusBadge` · `Tooltip` · `Progress` · `DataGrid` · `Chart` · `KpiCard`. **Nunca hand-roll**, nunca `role`/estado à mão em cima de `DIV`.

**Proibido explicitamente:** classe nova `fx-*` (a camada hand-rolled está sendo aposentada — `botao-fiscal.ts`/`chip-filtro.ts` existem exatamente pra isso) · `var(--fis)` rosa herdado do port · hex ou paleta crua do Tailwind (`ds/no-raw-palette-color`) · `rounded-xl+` · `select`/`checkbox`/`radio` nativo · componente ou token **novo** do DS (é soberania [W], `proibicoes.md`) · inglês em UI cliente-facing · emoji no app · modal full-screen para detalhe (detalhe é drawer, PT-02; modal é confirmação, PT-04).

**Tokens:** accent **`oklch(0.55 0.15 295)`** light / **`oklch(0.70 0.15 295)`** dark (ADR 0190/0235) · status por token semântico (`--ok`/`--warn`/`--bad`, pílula = 5–10% de fundo tintado + borda 20% + texto, **nunca** fill sólido nem pastel) · type RAMP `--fs-1..9` (rótulo de KPI 10.5px, corpo 13.5px, valor 18px, `h1` 22px) · tabular-nums em valor e prazo · IBM Plex Sans/Mono.

**Dados — Model/Service/coluna real, nomeada:**
| slot | fonte no `main` |
|---|---|
| lista unificada de notas | `NotasUnifiedService` (`Modules/Fiscal/Services`, com `NotasUnifiedServiceTest`) |
| KPIs · alertas · visões salvas · situação SEFAZ | `CockpitController` (cache invalidado por `InvalidaCockpitCacheListener`, `CockpitCacheTest`) |
| notas NF-e/NFC-e paginadas | `NfeCockpitController` → `Modules/NfeBrasil/Models/NfeEmissao` (`rows.meta`) |
| NFS-e | `NfseCockpitController` + `Modules/NFSe` (`NfseEmissao`, `NfseEmissaoService`) |
| DF-e recebidos e manifestação | `DfeController` + `ManifestacaoService` (NfeBrasil) |
| eventos | `EventosController` → `NfeEvento` (**sem `updated_at`** — append-only) |
| certificado · regime · séries | `ConfigController` · `CertHealthCheckCommand` |
| SPED | `SpedController` + `SpedIcmsIpiGeneratorService` |
| ⌘K | `PaletteSearchController` (`GET /fiscal/palette/search`, throttle 60/min) |
| mutações | `AcoesController` (`cancelarNfe` · `cartaCorrecao` · `inutilizar` · `retransmitir` · `manifestarDfe`, throttle 30/min) |

**Rotas (lidas em `Modules/Fiscal/Routes/web.php` neste turno):** `/fiscal` `fiscal.cockpit` · `/fiscal/nfe` · `/fiscal/nfse` · `/fiscal/eventos` · `/fiscal/dfe` · `/fiscal/config` · `/fiscal/sped` · `/fiscal/sped/icms-ipi/{ano}/{mes}` (throttle 3/min) · `POST /fiscal/acoes/*`. Middleware: `web, auth, SetSessionData, language, timezone, AdminSidebarMenu`.

**Copy:** literal do protótipo, PT-BR, sentence case. Vocabulário fechado: marcação · intercorrência · OP · PDV · cálculo por m² · DF-e · CC-e · inutilização · competência. `R$ 12.480,00`, `dd/mm/aaaa`, `08:42`.

---

## 6 · DoD e placar (recibo do PR)

1. Contagem **e ordem** dos filhos = alvo do bloco 3 (sonda da seção no PR).
2. Cada linha do bloco 4 com o teste que a prova (Pest/E2E ou sonda), citando o UC do `casos.md`.
3. `design-diff --compare --check` → **0 `DIVERGE(bug)`** no seletor da seção (nos **dois** renders).
4. Screenshot de produção autenticado · dark · 1280px.
5. `casos.md` da seção com **≥1 UC citado por teste**, no MESMO PR (`casos-gate` G-2 reprova órfão).
6. **PLACAR no corpo do PR:** *"entregue X de Y; os Y−X ausentes são `<nome>` por `<motivo>`"*.
7. Bloco de contrato destilado no `<Tela>.charter.md`, no MESMO PR.
8. `github.md`: linha do ciclo + `bundle regenerado (<data> · N arquivos)`.
9. Lanes verdes: `Casos-coverage · ratchet` · `Unit` · lane Fiscal · `cowork-ssot-guard` · `cowork-mirror-freshness` · `prototipo-readiness`.
10. **T6 vizinhança:** a sonda da onda anterior roda no PR da seguinte.

Nenhuma onda é "0 bug"/"igual ao design" antes do **T7** (paridade pareada com produção deployada).

---

## 7 · O que a ancoragem NÃO resolve

**a) Decisões ⛔ [W] — preservadas da versão anterior deste arquivo (5 continuam travando onda de UI):**

| # | pergunta | trava |
|---|---|---|
| 1 | NF-e/NFS-e: constrói o select de tipo + densidade, ou rejeita por escrito (Non-Goal)? | Onda 5 |
| 2 | Cockpit: as 3 sparklines entram (o charter promete e **a prop já chega sem ser usada**), ou o Goal #2 cede? | Onda 4 |
| 3 | DF-e: manifestação em lote vira `POST /fiscal/acoes/dfe/lote`, ou fica só no F1? Export CSV de eventos: server ou cliente? | Ondas 6 e 7 |
| 4 | Config: constrói "Envio de documentos" e reancora a região? Os 2 pontos já decididos (tela editável · séries reais) viram ADR quando? | Onda 8 |
| 5 | `CU-FISC-16` procedência das 6 superfícies: marcar na UI, flag, ou Non-Goal? **É o que destrava filtro/visão/densidade como contrato.** | Onda 10 |
| 6 | Emissão: sai pelo cockpit Fiscal (novo) ou continua na UI legada `NfeBrasil/Transactions`? | fila 🔴 (fora deste pacote) |
| 7 | IBS/CBS `US-FISCAL-021`: nesta rodada ou espera a régua de 2027? | fila 🔴 |
| 8 | Lane com migrations do NfeBrasil: schema na CT 100 ou fixture no CI? Destrava o único 🔴 Tier 0 (`UC-FNFE-01`). | fila 🔴 |
| 9 | NFS-e: emissão de serviço sai pelo cockpit Fiscal ou pela UI própria do `Modules/NFSe`? | fila 🔴 |
| 10 | Contingência: quais modos o piloto suporta (SVC-AN/SVC-RS por UF, NFC-e offline)? | fila 🔴 |
| 11 | Telemetria/Jana (`viewed_fiscal_nfe`, mapa SEFAZ com uma fonte só) entram, ou Non-Goal escrito? | fila 🔴 |

**b) Capacidade que não existe — não é seção de tela, não vira onda de UI:** emissão NF-e/NFC-e (o **motor existe**: `NfeService::emitirParaInvoice`, `DanfeService`, listener de pagamento; falta superfície + gate) · emissão NFS-e (`Modules/NFSe` inteiro e testado, sem tela) · **contingência SEFAZ** (`tpEmis` **hardcoded em 1**, `NfeService.php:1198` — SEFAZ fora do ar = balcão parado) · IBS/CBS (serialização testada, **cálculo** não) · SPED PIS/COFINS · importação de XML de entrada (contador de entrada é literal `0`).

**c) Cadeia de rastreabilidade:** 8 CU sem UC (`CU-FISC-02 · 03 · 08 · 09 · 10 · 11 · 15 · 16`) · **0 contrato `fiscal-*`** em `prototipo-ui/contrato/` (os 4 que o F1 declara nascem no PR) · `US-FISCAL-022` marcada `todo` com `CertHealthCheckCommand` + teste **vivos** (é divergência SPEC×código, não dívida de implementação).

**d) Verificação bloqueada daqui:** T7 exige deploy + sessão autenticada. VRT/E2E do Fiscal não medidos (ver bloco 8). Sem baseline, "não mudei layout" é opinião — **o primeiro PR de cada onda com risco visual cria a baseline**.

---

## 8 · Não medido — declarado, não afirmado

1. **Não li neste turno:** `Dfe.tsx` · `Eventos.tsx` · `Config.tsx` · `Sped.tsx` · `Nfse.tsx` e os controllers correspondentes. As ondas 6–9 abrem com `LER NO TURNO` justamente por isso. Os **caminhos e tamanhos** vêm da árvore lida (`1582cb14ca3a`); o **conteúdo**, não.
2. **`screen-coverage` do Fiscal** (E2E · a11y · VRT) — não medido. Primeira tarefa de quem abrir a Onda 1; se vier `0`, a baseline é pré-requisito.
3. **A2 (139 `outline:none` medidos no app inteiro numa rodada anterior)** — não auditado por seção do Fiscal nesta rodada.
4. **Fidelidade visual** — nada aqui prova que a produção vai ficar igual. T1–T6 provam que o pedido é **reprovável**.
5. **Pacote de sync** — a Onda 0a mexeu em 4 arquivos do build (`fiscal-page.jsx`, `fiscal-subpages.jsx`, `fiscal-actions.jsx`, `fiscal-page.css`) e **eu não regenerei o pacote**: o `gerar-payload-partes.mjs` exige os arquivos em disco (ADR 0374). Comando pro lado que tem disco:
   ```
   node scripts/design-sync/gerar-payload-partes.mjs --root <dir> --out sync/ --previous sync/bundle.manifest.json
   ```
6. **Correção do meu diagnóstico anterior:** a versão passada deste arquivo pedia "matar a camada `fx-*` e o `var(--fis)` rosa" como Onda 1. Medido hoje: `Cockpit.tsx` e `Nfe.tsx` **já usam** `Button`/`Input`/`Select`/`Checkbox` do DS via `botao-fiscal.ts`/`chip-filtro.ts`, com o motivo documentado no próprio `_lib`. Aquele PR-A1 está, em boa parte, **entregue**. O que sobra de `fx-*` é **CSS de layout** (`fiscal-cockpit.css`), não pele de controle — e isso é varredura, não onda.

---

## 9 · Recibo

- **Saídas deste ciclo:** ① build corrigido (a11y do alvo) em `prototipo-ui/cowork/`: `fiscal-page.jsx` · `fiscal-subpages.jsx` · `fiscal-actions.jsx` · `fiscal-page.css` · ② **este pedido** → `prototipo-ui/` (root, nunca `cowork/`) · ③ contrato destilado → `<Tela>.charter.md` no PR de cada onda · ④ `github.md` atualizado.
- **Nada disto está commitado.** As tools de GitHub daqui são read-only: eu leio e importo, não crio branch, não commito, não abro PR. Ponte = [W] cola 1× ou Issue `cowork-intake`.
- **Ciclo fechado SEM pacote regenerado** — ver bloco 8 item 5. Não afirmo que regenerei.
