---
sessao: "_saida-07"
thread: "07 · Linha do tempo por máquina (arrastar e Alt+setas) + gargalo por regra com sugestão aplicável"
dono: "[CL]"
data: 2026-10-22
tipo: recibo
entregue_em: "3 PRs: #9763 (protótipo da Linha ajustado às regras de Fluxos), #9766 (backend) e o PR deste recibo (tela)"
base_lida: "wagnerra23/oimpresso.com@main b20b92e0c1"
---
# _saida-07

## Entregue

| PR | O quê |
|----|-------|
| #9763 | Protótipo da Linha (`producao-cv-page/data.jsx`) seguindo as regras de Fluxos que entraram no `main` entre 19 e 21/10, pedido por [W] antes da thread. Subiu ao Cowork e foi conferido de volta byte a byte. |
| #9766 | `LinhaDaProducao` (blocos do dia, carga, travas, estouro, entrega prometida, sugestão de gargalo) e `PUT /producao/etapas/{id}/reprogramar`. |
| este | Tela `/producao/linha`, aba "Linha do tempo", charter e casos, eventos das máquinas, âncora da US-PCP-015. |

### Por UC
- **UC-PCV-08:** uma linha por máquina, com a carga "X de Y h" pela mesma conta do Painel (`PainelDaProducao::carga`, agora pública). Arte não aprovada ou OS em "Aguardando material" deixam o bloco tracejado. Terminar depois do fim do turno, ou da entrega prometida, dá borda de estouro. "Próximo dia útil" segue o `CalendarioDeProducao`.
- **UC-PCV-09:** uma sugestão por dia, para a máquina mais estourada. Vai a menor etapa pendente que resolve, para outra máquina do mesmo setor com folga e cujo responsável a opera, no mesmo horário ou no primeiro livre depois. "Aplicar" e "Desfazer" são duas reprogramações, ambas no histórico.
- **UC-PCV-30:** arrastar (15 em 15 min) e Alt+setas reprogramam só a etapa pendente. Outro setor e choque de horário são recusados, com a OS do choque. Na troca para máquina com operadores cadastrados, a tela pergunta quem faz (UC-FLX-08). Passar do turno move e avisa. Evento `etapa_alocada` (origem `linha`) e aviso no app ao responsável (`aviso_reprogramada`).

## Decisões
- **[W] 2026-10-22:** antes da thread, o protótipo foi ajustado às regras de Fluxos (6 pontos, #9763). Na troca de máquina, vale a recomendação "pergunta o operador do destino".
- **Reprogramar é um método novo, não o `alocar`:** a Linha é a agenda da máquina e recusa choque de horário. O `alocar` do Quadro ficou intocado (o teste dele compara o evento campo a campo).

## Divergência com o caso do Cowork (registrar no Cowork)
`Producao.casos.md` UC-PCV-30 fala em "o operador da máquina de destino" receber o aviso. Com operadores por posto (UC-FLX-08), quem recebe é **o responsável da etapa**, que precisa operar o destino. Esse é o comportamento do protótipo ajustado no #9763.

## O que NÃO foi feito
- "Mesma mídia: dá para agrupar", que está no protótipo: encaixe de mídia é versão 2 (D-ENCAIXE). Ausente.
- Abrir a ficha da OS ao clicar no bloco: a ficha é a thread 08.
- Medição `design-diff` contra o alvo `producao--linha--index`: depende da tela no ar. Fica para depois do deploy.

## Como conferi
- `php -l` nos PHP tocados (Herd local); parse TSX com `@babel/parser`.
- **Pest e `tsc` não rodados localmente** (regra do CT 100, e o `node_modules` do worktree não existe). O veredito vem das lanes do PR: `verticais-pest` (`ProducaoLinhaContratoTest`, 9 casos, tenant 98 × 99) e typecheck.

## Placar
`node scripts/qa/placar.mjs --indice prototipo-ui/cowork/Wagner/cowork-inbox/producao/playbook/00-INDICE.md --thread 07`
