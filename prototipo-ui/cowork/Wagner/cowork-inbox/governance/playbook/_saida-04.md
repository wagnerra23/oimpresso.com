---
thread: "04 · Meu build está atrás da produção"
dono: "[CC]"
estado: feito (fichas + recomendação) · termina em decisão [W]
base_lida: wagnerra23/oimpresso.com@main 68e071305601 (2026-09-24)
prefixo_tocado: nenhum arquivo de build · nenhum arquivo do main
---
# _saida-04 · As telas da Governança que o protótipo não tem

## A premissa mudou: são 3, não 4
Busca na árvore inteira (17.881 arquivos) por `ModuleGrades/Show` e por `(?i)module-?grade.*\.tsx` = **0**. O mesmo filtro achou `governance/Custos.tsx` e `Home/_components/GradesPainel.tsx`, então a busca funciona e a ausência vale. Os `Inertia::render('governance/…')` em `Modules/Governance/Http/Controllers/` hoje são: `Audit · Custos · Dashboard · DriftAlerts · DsRollout · Policies · QualidadeIa` (+1 não listado pelo corte da busca). **O `ModuleGrades/Show` de 28.806 B da thread saiu da produção** — o que sobrou de "nota de módulo" é o `GradesPainel` da Home.

## Fichas (lidas do charter — os `.tsx` não foram abertos, por teto)
| tela | `.tsx` | charter | o que é | dono do dado · permissão | estado do charter |
|---|---:|---|---|---|---|
| `Custos` | 13.884 B | 4.638 B | custo de IA do período: 4 KPIs, gráfico diário, tabela por usuário | `CustosController` · `jana.admin.custos.view` (nome legado) | **draft** — Non-Goals herdados nunca ratificados |
| `QualidadeIa` | 20.788 B | 5.205 B | 8 métricas + 3 RAGAS com gate, sparklines, 30 últimas runs | `QualidadeIaController` · `jana.mcp.usage.all` (plataforma, cross-business intencional) | **draft** — idem; dívidas: sparkline de escala local, HEX cru |
| `DsRollout` | 32.411 B | 5.003 B (+ `casos.md` 8.618 B) | plano de ondas do DS + **Ledger de Conformidade** (census estático no controller) | `DsRolloutController` | **draft** — aguarda aprovação visual [W] desde 2026-06-12 |

## Recomendação (custo · por tela)
- **Custos e QualidadeIa → (a) não trazer.** Leitura pura, vinda do Jana, com permissão própria; nenhuma onda de UI as nomeia; o próprio charter delas ainda espera [W] ratificar os Non-Goals. Trazer agora = espelhar tela que pode mudar de escopo. Custo 0; o índice passa a declarar que o protótipo **não** é espelho completo da Governança.
- **ModuleGrades/Show → encerrada.** A tela não existe mais no `main`.
- **DsRollout → depende da pergunta abaixo.** Se sim: 1 sessão, lendo o `.tsx` (32 KB) inteiro — é a maior das três.

## Registro pedido pela thread
O `DsRollout` nasceu de um handoff daqui (2026-06-12) e **não voltou**. O próprio charter mediu em 2026-09-09: o `.html` de origem **não existe** em nenhum dos dois lados — o handoff nunca pousou no espelho. Não há nada a "puxar de volta" do Cowork; só da produção.

## Pergunta para [W] — `D-GOV-ROLLOUT`
> O Ledger de Conformidade do `DsRollout` ainda é assunto vivo? Se sim, eu trago a tela para o protótipo (1 sessão) e ela vira alvo de onda. Se não, o charter dela deveria sair de `draft` para `superseded` — hoje ela é a única tela `tier: A` da Governança parada em draft há 3 meses.
