# Arquivos idênticos neste projeto — RETRATADO e EXECUTADO

> ⚠️ **Este documento foi refeito em 21/09/2026.** A versão anterior afirmava que 16 dos 32 arquivos
> da lista de limpeza não eram duplicatas, e recomendava **não** executar a limpeza. Estava errado,
> por erro de método meu. A limpeza foi executada e funciona. O texto abaixo é o corrigido; o
> registro do erro está no §1.

---

## §1 Retratação — o erro foi comparar bytes sem normalizar quebra de linha

Na primeira medição comparei os arquivos **byte a byte** e tratei diferença de bytes como diferença
de conteúdo. Os arquivos de `erp-shell-v2/` estão em **CRLF** (quebra de linha do Windows); os da
raiz, em **LF**. O delta que reportei como "conteúdo diferente" era exatamente o número de linhas:

| Arquivo | delta em bytes | nº de CRLF | conteúdo normalizado |
| --- | --- | --- | --- |
| `chat.jsx` | 224 | 224 | **idêntico** |
| `tasks.jsx` | 244 | 244 | **idêntico** |
| `data-clientes.jsx` | 19 | 19 | **idêntico** |
| `mockup-pages.jsx` | 23 | 23 | **idêntico** |
| `data-vendas.jsx` | 405 | 405 | **idêntico** |
| `ds-behavior.js` | 207 | 207 | **idêntico** |

Não testei a hipótese mais banal antes de publicar a conclusão. A regra "toda afirmação sai com
medição na hora" não protege contra medir a coisa errada. O Claude Code apontou o erro, remedi
normalizando CRLF→LF, e retratei.

**Regra adotada daqui em diante:** comparação de conteúdo **sempre** em bytes, **ignorando quebra de
linha**. E a mensagem de recusa da importação diz os dois caminhos, os dois tamanhos e **se a
diferença é só de quebra de linha**.

---

## §2 Resultado correto

Dos 21 pares que eu havia disputado:

**Idênticos módulo CRLF — 19.** Os 11 de `erp-shell-v2/` (`chat.jsx`, `chat-v1-legacy.jsx`,
`data-clientes.jsx`, `data-orc-prod.jsx`, `laravel-panel.jsx`, `linked-apps.jsx`, `mockup-bodies.js`,
`mockup-pages.jsx`, `orc-page.jsx`, `tasks.jsx`, `viewers.jsx`), os 7 de `importado_telas/vendas/`
e o `ds-behavior.js`.

**Diferentes de verdade — 2.** Estes continuam diferentes **depois** de normalizar:

| Arquivo | `erp-shell-v2/` | raiz | diferença real |
| --- | --- | --- | --- |
| `styles.css` | 222.556 | 214.958 | **7.598 bytes** |
| `tweaks-panel.jsx` | 25.050 | 23.964 | **1.086 bytes** |

Os dois estavam na lista original de 32 como texto igual. Foram retirados da lista e **ficam onde
estão**.

**Mesmo nome, conteúdo diferente — o caso que nenhuma regra de conteúdo pega.** Medi quatro do
shell, normalizados:

| Arquivo | `erp-shell-v2/` | raiz | delta |
| --- | --- | --- | --- |
| `app.jsx` | 39.697 | 74.179 | −34.482 |
| `data.jsx` | 17.808 | 34.168 | −16.360 |
| `sidebar.jsx` | 17.700 | 26.127 | −8.427 |
| `icons.jsx` | 10.069 | 11.071 | −1.002 |

São duas gerações do shell: `erp-shell-v2/` é o publicado (veio de `public/cowork-preview/`), a raiz
é o vivo (veio de `prototipo-ui/cowork/`). **Não foram tocados** — continuam os dois no projeto.

---

## §3 Executado em 21/09/2026

### 3.1 Unificação do design system

O gerador recusou o pacote por conter três design systems sem indicação de qual valia. Resolvido:

| Ação | Detalhe |
| --- | --- |
| `_ds/…019dd02f…/` apagada | era o bundle do `49a36f` + shim de 11 linhas. Mesmo conteúdo, mesmo namespace — nunca foi um segundo DS |
| `_ds/office-impresso-atual-d7f88676-…/` apagada | **esta era diferente**: namespace `OfficeImpressoDesignSystem_d7f886`, 287.322 B × 295.062 B. Nenhuma página carregava |
| `handoff_*/design/_ds/` apagadas | as duas páginas apontam para a raiz |
| shim de alias | saiu do bundle, virou `<script>` declarado no `oimpresso.com.html` |
| `cockpit_domains.css` | **removido, não substituído** — era stub vazio. 41 tokens de domínio seguem definidos por outras folhas |

Resta **um** design system: `_ds/wagner-office-impresso-design-system-49a36f76-…/`.

### 3.2 Limpeza — 30 apagados, 14 religações

Todos os 30 conferidos idênticos (normalizado). As religações foram feitas **antes** das exclusões.

| Página | Religações |
| --- | --- |
| `erp-shell-v2/Oimpresso ERP - Chat.html` | 10 `src` para `../` |
| `handoff_fabricacao/design/Fabricacao - Guia de Producao.html` | 4 (`styles.css`, `otimiza-ondas.css`, `manufacturing-data.jsx`, `icons.jsx`) |

`screenshots/grade.png` — 25.434 bytes, igual ao `tela.png`. Medido antes de apagar (é binário e
ficou fora da primeira varredura).

### 3.3 O que a lista não previa, e foi tratado

A lista mandava apagar `handoff_produtos_consulta/design/Consulta de Produtos.dc.html` e
`support.js`, **sem dar instrução de religação** para esse pacote. Apagar sem mais nada deixaria o
handoff com README e ADRs e nenhuma tela — e o README dizia "duplo-clique em
`design/Consulta de Produtos.dc.html`".

Os dois README do pacote foram atualizados para apontar para a raiz, com o aviso de que
**descompactar só a pasta `handoff_produtos_consulta/` não abre mais a tela**.

---

## §4 O que mudou de princípio (e por que a versão anterior deste documento estava errada)

A versão anterior recomendava **não** apagar as cópias dos pacotes de handoff, porque um handoff é
auto-contido e o .zip sairia quebrado. O argumento era válido **sob a premissa errada** de que o
.zip é a pasta do handoff.

O Claude Code mediu e informou: **o .zip é sempre o projeto inteiro.** Sob essa premissa, apontar
para a raiz funciona, e a auto-contenção da pasta deixa de ser requisito. O pacote deixou de ser
auto-contido **por decisão de processo**, e isso está escrito no README dele.

A regra também mudou, e é a formulação do Claude Code, não a minha:

> **Arquivo compartilhado existe num lugar só; os outros apontam pelo caminho.** As pastas de
> entrega são geradas na exportação, não guardadas.

Ela substitui "conteúdo igual é proibido", que pegava os 23 casos inofensivos e deixava passar os
35 perigosos (mesmo nome, conteúdo diferente).

---

## §5 Continua válido da versão anterior

A instrução **"use o styles.css do shell erp-shell-v2 do Design System"** não corresponde a caminho
nenhum. O DS tem um `styles.css`, que é outro arquivo — é o CSS do DS, não o do cockpit. Um agente
de código em dúvida aponta para o plausível e a página abre sem o CSS do shell. Foi corrigido no
pedido novo, que não tem mais essa linha.
