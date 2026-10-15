---
sessao: "_saida-04"
thread: "04 · Celular do operador (PWA): minhas etapas, QR, cronômetro, fechamento no fim do turno, fila offline"
emendas: ["E04"]
dono: "[CL]"
data: 2026-10-15
tipo: recibo
entregue_em: "3 PRs sequenciais: #9458 regra (claude/producao-thread-04) → tela (-04-tela) → comando agendado (-04-turno); mais o #9455 no Ponto, antes"
base_lida: "wagnerra23/oimpresso.com@main 0455845c32 + claude/producao-thread-02c (02 inteira)"
---
# _saida-04

## Entregue (thread 04 + emenda E04 incorporada)

| PR | O quê |
|----|-------|
| #9455 (Ponto) | `PresencaDoDiaService::doUsuario` — entrada, saída e presença do dia, **só leitura**, `business_id` explícito, anulação vale. US-PONTO-017. Porta da E04 A7. |
| #9458 (1/3) | `ApontamentoOperadorService` por cima do `ApontamentoService` da 02 + `ApontamentoRecusado` (um código por recusa) + `ProducaoApontamentoContratoTest` (um `it` por UC) |
| 2/3 | Tela `/producao/apontamento` (`Modules/Producao/Resources/js/Pages/Producao/Apontamento/Index.tsx`), fila offline, `ApontamentoController` (JSON), rotas em bloco próprio, charter + casos, `ProducaoApontamentoTelaTest` |
| 3/3 | `producao:fechar-turno` a cada 15 min (registrado no `ProducaoServiceProvider`) + `ProducaoFecharTurnoComandoTest` + este recibo |

Dois consumidores, uma regra: a PWA web (controller desta thread) e a API do app com token (#9447, PR 06 do app) chamam o mesmo `ApontamentoOperadorService`.

### Por UC
- **UC-PCV-19** — `minhasEtapasDeHoje`: só as do operador (`pcp_programacoes.user_id`), só do dia, `pendente|iniciada`, na ordem do previsto.
- **UC-PCV-20** — `resolverQr` abre a etapa dele na OS; `iniciar` / `pausar` / `concluir` gravam linhas append-only. Cronômetro com `role="timer"`, controles `min-h-11` (44px).
- **UC-PCV-21** — concluir marca a etapa `concluida` e grava `pcp_eventos.aviso_proximo` para o responsável da próxima etapa `pendente` da OS (D-AVISO: o sino que mostra é da thread 06).
- **UC-PCV-22** — fecha no fim do turno da empresa com origem `turno` e evento `fechado_automaticamente` (aviso ao PCP); correção com justificativa vira `correcao_solicitada` e o par original fica.
- **UC-PCV-23 · E04 A8** — vale a hora do aparelho; a mesma chave com o mesmo corpo devolve a linha (201 → 200); corpo diferente → 422.
- **UC-PCV-31** — máquina e pessoa abertos juntos; o 2º de pessoa → `pessoa_ocupada`.
- **UC-PCV-34 · E04 A2** — QR de etapa alheia devolve etapa e responsável e não grava nada (409 na escrita).
- **UC-PCV-35 · E04 A3** — seguir para outra etapa não grava pausa na máquina.
- **UC-PCV-36 · E04 A7** — pessoa fecha na saída do ponto (origem `ponto`); sem saída, no fim do turno; horas de pessoa > presença + 30 min → `pendencia_gestor`; o Ponto não muda (contagem de `ponto_marcacoes` igual).
- **E04 A1** — "Rodando agora": pessoa + máquinas iniciadas por ele + linha do ponto.
- **E04 A4** — pausar exige motivo de pausa ativo do cadastro daquele posto (`pcp_motivos` da thread 03, #9459); a tela só oferece os do posto. Enquanto a 03 não entra, só a presença do motivo é exigida.

## Decisões [W] citadas (via coordenação, 2026-10-15)
- **D-PONTO:** *"Confirmo a D-PONTO como está: o ponto é a fonte legal e nunca é alterado pelo apontamento; horas de pessoa ≤ presença; diferença acima de 30 min/dia vira pendência do gestor; a saída no ponto fecha o cronômetro de pessoa e, sem saída, fecha no fim do turno."*
- **Fila offline:** *"Apontamento pode ficar numa fila sem internet e ser enviado depois, sem duplicar. A regra 8 do playbook do app ganha essa exceção só para o apontamento; o resto continua travando sem internet."*

Limites que defini para a hora do aparelho: até **5 min à frente** e até **72 h atrás** do servidor (fora disso, `momento_invalido`); a hora é convertida para o fuso do servidor e sem fração de segundo. Conclusão offline que chega depois de um fechamento automático e é anterior a ele vira correção.

## Como conferi
- `php -l` em todos os arquivos PHP, no container do CT 100 (stdin); controle: arquivo quebrado de propósito acusou `Parse error`.
- `tsc --noEmit`: zero erro nos arquivos da Produção (o projeto tem 299 erros anteriores em outras pastas).
- **Testes não rodados local nem no CT 100** (o banco do staging não tem as `pcp_*`). Veredito vem da lane `verticais-pest` dos PRs. Em SQLite os testes pulam: leia assertions (LC-13).
- `casos-coverage-guard`, `uc-id-lint`, `required-always-run`: verdes local.

## O que NÃO foi feito, e por quê
- **Medição `design-diff` da tela contra o alvo:** não rodou. Sem servidor Laravel local (sem PHP nesta máquina) e o staging roda outro checkout. Fica para depois do deploy, contra `producao--apontamento--index-390`. Mapa de seções: `header` → `PageHeader`; `celular` → conteúdo da tela; `abas` (navegação do módulo) é da thread 06; `texto` é o explicativo da demonstração e não vai para o produto.
- **Forma nova do Cowork** (terminal fixo na máquina, sobra de material): o espelho `producao-cv-campo.jsx` ainda não tem essa versão (último commit `be7603623b`, sem "terminal" nem "sobra"). Construído pelo alvo atual; a forma nova é das threads 12 e 16.
- **QR:** o formato é da thread 05. A tela lê os dois últimos segmentos como `os_tipo:os_id`; o código digitado sem `:` é tratado como OS de comunicação visual.
- **Apontamento iniciado sem internet** fica guardado sem id do servidor: pausar ou concluir esse apontamento espera o envio.

## Perguntas (para o [W], pela coordenação)
1. **Correção de horário e custo.** A correção do UC-22 hoje é pedido ao gestor (`correcao_solicitada`) e não muda o custo, porque o `CustoRealService` soma o par início→fim. Fazer a correção valer no custo é mudar cálculo de VALOR (regra mestre: dupla prova + antes→depois). Quem aplica (gestor numa tela da 06/08?) e como o custo passa a ler a correção?
2. **Junção de trilhas** (UC-21 "ou para a junção"): depende dos roteiros da 03. Hoje a próxima etapa é a próxima `pendente` da OS na ordem do previsto.
3. **Máquina no fim do turno:** a regra fecha também o apontamento de máquina às 18h. Impressão que roda à noite precisa de exceção por posto?

## Placar
`node scripts/qa/placar.mjs --indice prototipo-ui/cowork/Wagner/cowork-inbox/producao/playbook/00-INDICE.md --thread 04`
