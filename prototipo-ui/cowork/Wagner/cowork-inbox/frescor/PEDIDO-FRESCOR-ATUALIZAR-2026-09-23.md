# PEDIDO — Atualizar `FRESCOR-PRODUCAO-vs-PROTOTIPO.md` (2026-09-23)

> **Dono:** [CL]. **Alvo:** `memory/reference/prototipo-ui/FRESCOR-PRODUCAO-vs-PROTOTIPO.md` (8.274 B, lido no `main` hoje).
> **Por quê:** o quadro de 23/06 estava velho em 4 das 5 telas que eu conferi hoje. O [CC] leu os 🔵, seguiu e teria refeito trabalho que já estava feito. O arquivo é o passo 2 do read-order do Cowork, então um quadro velho custa uma sessão inteira.
> **Evidências lidas no `main` neste turno** (`ae3c4d92b479` → `cd78c7a10f66`). Nenhuma medição de runtime minha; onde digo "medido", cito a medição de outro.

## Linhas a mudar

| Tela | Hoje no FRESCOR | Novo | Evidência |
|---|---|---|---|
| **Atendimento/CaixaUnificada** | 🔵 À FRENTE · caminho `resources/js/Pages/Atendimento/CaixaUnificada/` | ✅ **paridade** (resta 1 item de fundação) · caminho **`Modules/Whatsapp/Resources/js/Pages/Atendimento/CaixaUnificada/`** | O caminho antigo não existe (`github_get_tree` → not found). O protótipo (`inbox-page.jsx` + `inbox-{extras,ai,cur,out}.jsx`) cobre todos os `_components/` da tela viva: Filas, Canais, Broadcast, Nova conversa, Templates Jana+HSM, Reconnect, Guia, CheatSheet, MobileTabs, Contexto em drawer, `linkifyMessage`, comentários, transcript, apresentação, favoritos. `governance/design/targets/medidas/Atendimento--CaixaUnificada--Index/resultado.json` (18/09): **IGUAL** em D2/D6/D8/D9. Único item aberto: h1 14px (prod) × 22px (protótipo), já decidido por [W] (22px) → `cowork-inbox/pageheader/PEDIDO-PAGEHEADER-DECISOES-W-2026-09-23.md` item 2. |
| **Cliente (Crm) — drawer 760** | 🟠 revisado (o drawer "à frente") | drawer 760: ✅ **paridade** · listagem/Import/Map: sem mudança (dono segue `PARIDADE-area-cliente-…md`) | Única lacuna achada: `_drawer/EnderecosEntregaList.tsx` (US-CRM-078 f2). Já trazida para o protótipo, no `CliEnderecoSection` (`clientes-page.jsx?v=end3`): editar, remover exceto o principal, CEP → autopreenche, UF por lista, entrega = nota fiscal. |
| **PageHeader (fundação)** | ⚪ ~85% | ⚪ **decidido [W] 2026-09-23** — execução no pedido | 7 decisões (h1 600 · subtítulo 12px · aba 36px · setas + `role="banner"` no DS · Caixa 22px · ADR 0395 aprovada · matriz arquivada) → `cowork-inbox/pageheader/PEDIDO-PAGEHEADER-DECISOES-W-2026-09-23.md`. Descartar `index.html`/`3-familias.html` continua valendo. |
| **Sidebar/Shell (fundação)** | ⚪ empate · 3 itens de catch-up, 1 travado | ⚪ **sem pendência de puxar** | Tema: travado por "dark × light UI-0014" → **resolvido pela UI-0023** (dark fixo nos dois modos). ⌘K: existe nos dois (`AppShellV2.tsx:89/735`). "Fixados": a produção não tem na sidebar (o `fixadas` de `AppShellV2.tsx:132` é da lista de conversas do chat). Os itens eram catch-up **para a produção**, não para o protótipo. |
| **Compras grade-matrix** | 🟠 ATRÁS · componente órfão | ⏳ **implementado, aguarda smoke [W]/[W2]** | `Purchase/Create.tsx` importa e usa `GradeMatrixInput` (`:26`, `:459`, lido em 05/09 — `cowork-inbox/compras/playbook/05-grade-smoke-bloqueada.md`). O que falta é o canary biz=4, não código. |
| **Financeiro/Dre/Index** | 🟠 ATRÁS, pouco | ✅ **quase** — resta a 1ª coluna em fonte mono | O rótulo "Novo título" e os tokens do tema já entraram com o FIN-1 (`Dre/Index.tsx:131/220/224/261`, `Index.charter.md:32`). A fonte mono na coluna Conta não aparece no `Dre/Index.tsx`: busquei `font-mono` e só encontrei em `_components/BalanceteView.tsx:147`. |
| **Financeiro/Fluxo/Index** | 🟠 ATRÁS | ✅ **paridade de forma** (FIN-2) | Errata e resultado FIN-2 em `memory/requisitos/Financeiro/fluxo-visual-comparison.md`: faixa de 4 KPIs em 28px, título "Financeiro · Fluxo de caixa", primário "Novo título" (`Fluxo/Index.tsx:590`). O `div × table` era estado vazio (dado), não forma. |
| **OficinaAuto/Vehicles/*** | 🟠 SEM FONTE | 🟠 **sem mudança** — pedido aberto | `Vehicles/Index.tsx:398` ainda em texto puro; 0 `MercosulPlate` nos `.tsx` de `Vehicles/`. Pedido: `cowork-inbox/oficina-auto/PEDIDO-VEHICLES-MERCOSULPLATE-2026-09-23.md`. |

## Acrescentar ao "Como o design usa isto"

4. **Um 🔵 ou 🟠 não substitui ler o `main` no turno.** Em 23/09, 4 de 5 veredictos tinham mudado sem que o quadro registrasse. Todo veredicto deve levar a data e o sha da leitura, e o leitor trata como pista qualquer linha com mais de 14 dias.

## O que este pedido NÃO faz
- Não mede runtime. As linhas marcadas ✅ citam a medição de outro (resultado.json de 18/09, FIN-2) ou a leitura do código; nada disso passou pelo T7.
- Não cobre Ponto nem os outros módulos no "RESTO DO WORKSPACE — não assuma".

## Prova
- `grep -n "Modules/Whatsapp/Resources/js/Pages/Atendimento/CaixaUnificada" memory/reference/prototipo-ui/FRESCOR-PRODUCAO-vs-PROTOTIPO.md` ≥ 1.
- `grep -c "resources/js/Pages/Atendimento/CaixaUnificada" …` = 0 (o caminho morto some).
- As 8 linhas acima presentes com data 2026-09-23.
