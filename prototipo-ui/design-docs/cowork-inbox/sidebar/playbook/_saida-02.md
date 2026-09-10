---
sessao: "_saida-02"
thread: "02 · Seção MODOS — auto-rail UI-0030 + persistir só escolha manual + corte do estado morto"
dono: "[CC]"
data: 2026-09-10
prefixo_tocado: prototipo-ui/cowork/app.jsx · (host: bump de ?v=)
base_lida: wagnerra23/oimpresso.com@main — Components/cockpit/shared.ts + Layouts/AppShellV2.tsx (árvore af09f7c3a0fd)
---
# _saida-02

## Feito
1. **Limiar corrigido pro valor do vivo.** `AUTO_RAIL_MQ = "(max-width: 1280px)"` — **1280 inclusive**, lido em `shared.ts` (`AUTO_RAIL_MAX_W = 1280`). Era `innerWidth < 1280`.
2. **Só a escolha manual persiste.** O `useEffect([sbMode])` que gravava **todo** valor saiu; entrou `escolherModo(v)`, chamada pela alça, por ⌘\\, por ⌘⇧\\ e pela alça de reabrir. Aceita valor ou updater.
3. **Listener de largura:** sem chave persistida, o modo acompanha o `change` do mq ao vivo.
4. **Estado morto cortado:** `tab`/`setTab`, `activeConvId`/`setActiveConvId`, `handleSelectConv`, os dois `useEffectA` que gravavam `oimpresso.sidebar.tab` e `oimpresso.conv`, a linha `if (r === "chat") setTab("chat")`, as props passadas ao `<Sidebar>`, e as funções `ChatPage` + `ConvTabsBar` (62 linhas).

## Medição
- **Ciclo completo dos atalhos** (reload + 400ms de re-render entre cada tecla — o teste síncrono da 1ª rodada não distinguia):

| passo | classe | largura | localStorage |
|---|---|---|---|
| inicial | `sb` | 260px | expanded |
| ⌘\\ #1 | `sb sb--rail` | 56px | rail |
| ⌘\\ #2 | `sb` | 260px | expanded |
| ⌘\\ #3 | `sb sb--rail` | 56px | rail |
| ⌘⇧\\ #1 | *(sem aside)* | — | hidden |
| ⌘⇧\\ #2 | `sb` | 260px | expanded |

- Clique na alça → a chave muda: **a escolha manual grava**. Estado do [W] devolvido a `expanded` ao fim (não limpei nenhuma chave).
- `setSbMode` direto restou **2×**, ambas legítimas: dentro de `escolherModo` e no listener de largura (o caminho automático, que por definição não grava).
- Grep de regressão em `app.jsx`: `oimpresso.sidebar.tab` **0** · `oimpresso.conv` **0** · `setTab(` **0** · `handleSelectConv` **0** · `function ChatPage` **0**.

## Regressão que eu introduzi e não peguei sozinho
A 1ª versão de `escolherModo` resolvia o updater contra `sbMode` direto:
```
const proximo = typeof v === "function" ? v(sbMode) : v;
```
O `useEffect` do teclado tem deps `[]` e captura o **primeiro** `escolherModo`, com `sbMode` congelado em `"expanded"`. Resultado: `(m) => m === "rail" ? "expanded" : "rail"` recebia sempre `"expanded"` e devolvia sempre `"rail"` — **⌘\\ virou mão única**, ia pra rail e nunca voltava; `hidden` idem (o tooltip prometia "Mostrar sidebar (⌘⇧\\)" e o teclado não cumpria). O código antigo era imune por usar `setSbMode((m) => …)`, o updater do próprio React; troquei por uma função minha e **removi a imunidade sem repor equivalente**. Conserto: `sbModeRef` — resolve o updater contra o valor corrente sem gravar em fase de render (que duplicaria em StrictMode).

**Por que a minha prova não pegou:** testei **um clique numa direção** e li o `localStorage` — que estava certo. Toggle só se prova pelo **ciclo**, e mudança de estado em React só se lê **depois do re-render**, nunca na mesma volta síncrona.

## Não feito, e por quê
- **A matriz de 4 casos (sem chave × com chave) × (estreito × largo) NÃO foi medida.** A sessão do [W] **já tem** `oimpresso.sidebar.mode` gravada, e a chave vence a largura — que é exatamente o comportamento correto, mas torna o ramo "sem chave" inobservável sem apagar storage do [W], o que não faço. **Fica para conferência em perfil limpo.** O que medi cobre metade: com chave, a escolha vence; a escolha grava.
- **Chave não renomeada.** O vivo usa `LS.SB_MODE = "oimpresso.sb.mode"`; mantive `oimpresso.sidebar.mode`. A paridade exigida é de comportamento, e renomear invalidaria a preferência atual do [W] sem ganho. Divergência **declarada, não corrigida**.
- `Thread` e `LinkedAppsPanel` (usados só pela `ChatPage` removida) vivem em outro arquivo — **fora do prefixo**. Podem ter virado órfãos lá; não conferi o arquivo deles.

## Descobertas
1. **O `?v=` do host é parte do ciclo, não detalhe.** Editar `app.jsx`/`sidebar.jsx`/`styles.css` sem bumpar o `?v=` no `oimpresso.com.html` entrega uma tela que **parece não ter mudado** — e uma medição feita nesse estado vira um veredito falso. Foi o que quase aconteceu aqui. Nenhum guard cobre isso (o `cowork-ssot-guard` não olha `?v=`).
2. **A ordem 01→02 não era preferência, era dependência dura:** cortar as props em `app.jsx` antes de a `01` remover o JSX que as consome quebraria o `Sidebar`. O índice anunciava as duas como paralelas na vaga 1 — estava errado, e já foi corrigido.
3. **Trocar um updater do React por função própria é mudança de semântica, não refactor.** `setSbMode((m)=>…)` carregava uma garantia (valor corrente) que `escolherModo(v)` não herdou. Toda vez que uma função minha passar a intermediar um `setState` **lido por listener de deps `[]`**, o valor corrente tem de vir de ref — e a prova tem de ser o ciclo, com espera entre as teclas.
