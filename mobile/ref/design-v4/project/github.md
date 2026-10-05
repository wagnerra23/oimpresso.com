repo: wagnerra23/oimpresso-app
branch: main
path: /

> **Alvo mudou em 2026-10-01 (decisão [W]).** O app das lojas é o `oimpresso-app` (Capacitor + React/Vite, telas próprias, dados pela API Passport do ERP). O app Expo (`wagnerra23/oimpresso.com` · `mobile/`) saiu das lojas. As ondas em `handoff/` foram escritas para o Expo e valem só como referência.

## Last sync
date: 2026-10-05T14:55:00Z

### Updated in this project
- Contratos por rota (`handoff/propostas-app/api/`), fixtures (`demo-propostas.ts`) e decisões como ajuste da empresa (`AJUSTES-DA-EMPRESA.md`, `pos_settings.app_*`); P1 alinhado ao §2 do API-CONTRATO-v1 (`POST /pedidos/{id}/acao`)
- Propostas conferidas contra o `oimpresso.com@main` (routes/api/app, OficinaController, ComunicacaoVisual, Whatsapp, comissão UltimatePOS); lista para o Code em `handoff/propostas-app/TAREFAS.md`
- Propostas: câmera (QR em P8, foto em P2) e biometria no login (P9) liberadas por [W]; a ADR 0383 vale só para o ponto (texto da própria ADR). Pendente no repo: README do oimpresso-app ainda diz "nunca adicionar permissão de câmera"
- Novo: `Mobile Propostas.dc.html` — 9 telas propostas (P1–P9) a partir da comparação com Mubisys, HoldApp e shopVOX Go; base: `memory/research/2026-05-prospeccao/33-grade-producao-concorrentes.md` (oimpresso.com)
- Navegação e Mais como produção: barra Início · Tarefas · Pedidos · Produção · Mais, 18 módulos, Meu menu (até 3), "Abrir o oimpresso completo"; sem Venda rápida, perfis nem troca de empresa
- Telas viram só leitura onde produção é só leitura: Produção (etapas da venda), Pedidos, Estoque, Movimentações, Orçamentos, Fiscal, Financeiro, Relatórios, OS; ações ficam "no computador"
- Ponto: sem REP-P (D9), 8 motivos do ERP, espelho com banco de horas/escala/justificativas, "Ponto não liberado", fila do gestor com confirmação de anulação
- Banner offline sem fila ("Bater ponto precisa de internet"); Login sem OAuth/empresa; Assistente = Jana
- Cópia anterior: `Oimpresso Mobile v1 (antes da sync 05-10).dc.html`

## Sync history
- 2026-10-05T14:20Z · Início, Ponto 36–38, Meu menu (1ª parte desta sync)
- 2026-10-01T20:23:45Z · alvo trocado para oimpresso-app

## Screen map (oimpresso-app)
| Tela | Arquivos do repo |
| --- | --- |
| 00 Login | src/telas/Login.tsx |
| 01 Início | src/telas/Inicio.tsx |
| 02 Produção | src/telas/Producao.tsx |
| 04 Orçamentos | src/telas/Orcamentos.tsx |
| 05 Estoque · 29 Movimentações | src/telas/Estoque.tsx, src/telas/Movimentacoes.tsx |
| 06 Financeiro | src/telas/Financeiro.tsx |
| 07 Ordens de serviço | src/telas/OrdensServico.tsx |
| 10 Mais · 30 Meu menu | src/telas/Mais.tsx, src/telas/PerfilMenu.tsx, src/navegacao.ts, src/App.tsx |
| 12 Tarefas | src/telas/Tarefas.tsx |
| 13 Relatórios | src/telas/Relatorios.tsx |
| 14 Fiscal | src/telas/Fiscal.tsx |
| 15 Pagamentos | src/telas/Pagamentos.tsx, src/pagamento-regras.ts |
| 16 Notificações | src/telas/Notificacoes.tsx |
| 17 Pessoas · 18 Ficha | src/telas/Pessoas.tsx |
| 21 Pedidos · 22 Detalhe | src/telas/Pedidos.tsx |
| 25 Assistente | src/telas/Assistente.tsx |
| 26 Equipe | src/telas/Equipe.tsx |
| 35 Dashboard | src/telas/Dashboard.tsx |
| 36 Bater ponto · 37 Meu espelho · 38 Justificar | src/telas/Ponto.tsx, src/ponto-regras.ts |
| 39 Marcações a validar | src/telas/FilaGestor.tsx |
| Não comparados ainda | 03 OsDetalhe, NovaOs, 08 Veiculos, 09 NovaPessoa, 11 VendaRapida, 20 NovoProduto, 28 TarefaDetalhe, 34 PessoaCadastro, Conta |
| Fora do app | 23 Manutenção, 24/31/32 Equipamentos, 33 Locais (sem tela no oimpresso-app) |
