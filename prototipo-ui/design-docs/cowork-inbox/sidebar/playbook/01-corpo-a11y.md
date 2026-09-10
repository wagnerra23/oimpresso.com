---
sessao: "01"
titulo: Seção CORPO — `nav` acessível, bateria a11y A1–A12 e fim da aba Chat no protótipo
dono: "[CC]"
base: af09f7c3a0fd
prefixo: prototipo-ui/cowork/sidebar.jsx · prototipo-ui/cowork/styles.css (bloco Sidebar)
nao_toca: app.jsx · data.jsx · Components/cockpit/** (esta thread não escreve no vivo)
depende: RESÍDUO-2 (só a parte "remover Chat"); o resto anda sozinho — vaga 1
---
# 01 · Seção CORPO (menu + grupos + ghosts)

## A · Identidade — ancoragem dupla
- **alvo (layout):** o próprio `sidebar.jsx` — `SidebarMenu` (l.256) · `MenuGroup` (l.208) · `ItemRow` (l.160) · `GhostList` (l.180) · `ItemEnd`/`Kbd` (l.25-39). Layout **não muda** nesta thread: ela conserta semântica, não desenho.
- **âncora (código):** `resources/js/Components/cockpit/Sidebar.tsx` + o wrapper em `AppShellV2.tsx`, onde o corpo é `<nav className="sb-body" aria-label="Navegação principal">`. O protótipo usa `<div className="sb-body">` (`sidebar.jsx:566`). **O vivo está certo; copiar dele.**

## B · O que fazer
1. **`sb-body` vira `<nav aria-label="Navegação principal">`** no `Sidebar` do protótipo (l.566-570), nos dois modos (expandido e rail). Uma `<nav>` só — não duplicar landmark.
2. **Bateria a11y A1–A12 no alvo.** O protocolo é explícito: *o alvo não é sagrado* — o que falhar **corrige-se aqui**, não vira pedido pro [CL]. Pontos já visíveis na leitura (confirmar medindo, não de cabeça):
   - `ItemRow`/`GhostList`/`MenuGroup`: elemento clicável é `div`? → precisa ser `button`/`a` real, não `div` com `onClick` (exportar `DIV` clicável é exportar dívida com selo).
   - grupo accordion: `aria-expanded` + `aria-controls` na cabeça do grupo.
   - ícone do item: `aria-hidden` no glyph + nome acessível vindo do texto (nunca ícone anônimo).
   - `.sb-kbd`: `aria-hidden="true"` (é dica visual; o listener real é global) — igual ao vivo (`Sidebar.tsx:537`).
   - "⋯ mais N" do `GhostList`: `button` com nome acessível literal ("Mostrar mais N telas"), e o estado aberto/fechado anunciado.
   - contraste do texto dim sobre a sidebar preta ≥ 4.5:1 — medir com `getComputedStyle`, nunca pela classe declarada.
3. **Aposentar `SidebarTabs` (l.77) · `SidebarChat` (l.95) · `ConvRow` (l.150)** — o vivo os removeu em 2026-05-05 (UI-0011 single-pane; conv switcher vive em `Pages/Copiloto/Chat.tsx`). **Só executar com RESÍDUO-2 respondida.** Se [W] mandar manter, não remover: marcar com comentário `// fora do canon (UI-0011) — demo` e registrar no `_saida`.

## C · Não inventar
- Sem token novo: `--sb-text`, `--sb-hover`, `--sb-active`, hue por grupo via `--gh`. **Sem cor crua.**
- **Sidebar é PRETA nos dois modos** (UI-0023). `.sb-item.is-open` **não clareia** — só hover ilumina.
- Copy PT-BR, sem emoji. Nada de `rounded-xl+`.
- Não tocar em `WipMark`, `podeVer`, `countOf` — instrumentos do protótipo, ficam.

## Execução
```
ARQUIVOS A EDITAR : prototipo-ui/cowork/sidebar.jsx (nav + semântica dos clicáveis + aria dos grupos)
                    prototipo-ui/cowork/styles.css (só se o :focus-visible da linha não existir/estiver fraco)
MEDIR ANTES       : tema dark · esperar __oiLazyDone · DUAS leituras iguais de querySelectorAll('*').length
                    sonda de a11y roda um caso de sanidade de valor conhecido ANTES de qualquer veredito
                    escopo do seletor = a <aside class="sb">, NUNCA document (erro catalogado 2026-09-05)
PASSO A PASSO     : 1) medir A1–A12 e registrar a tabela no _saida  2) nav+aria  3) clicáveis viram button/a
                    4) foco visível em toda linha  5) remeasure (contagem de nós ± esperado)  6) _saida-01.md
PARAR SE          : (a) trocar div→button mudar o layout medido → parar e reportar (é mudança de alvo, precisa [W])
                    (b) RESÍDUO-2 sem resposta → fazer 1 e 2, deixar 3 fora e dizer isso no _saida
```

## Prova
- `sidebar.jsx` contém `aria-label="Navegação principal"` e **um** `<nav>` no corpo.
- Tabela A1–A12 no `_saida-01.md` com valor medido (não "ok"): nome acessível de cada linha, `aria-expanded` do grupo, contraste em número.
- Se RESÍDUO-2 = remover: `sidebar.jsx` sem `function SidebarChat` / `SidebarTabs` / `ConvRow`, e `app.jsx` **ainda monta** (as props `tab`/`onTab`/`activeConvId` viram no-op documentado — quem as remove é a thread 02, não esta).
- Não verificável daqui: axe em prod · T7.
