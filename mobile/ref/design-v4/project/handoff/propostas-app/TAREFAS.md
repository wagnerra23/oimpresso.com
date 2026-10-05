# Propostas P1–P9 — lista de tarefas para o Claude Code

Fonte visual: `Mobile Propostas.dc.html` (este projeto). Telas atuais: `Oimpresso Mobile.dc.html` (chips "proposta" nas telas 02, 03, 04, 10, 13, 16, 22, 35).
Conferido em `wagnerra23/oimpresso.com@main` e `wagnerra23/oimpresso-app@main` em 2026-10-05.

Dois repos:
- **ERP** = `wagnerra23/oimpresso.com` — rotas em `routes/api/app/<area>.php` (grupo `auth:api` + prefixo `app`), controllers em `app/Http/Controllers/Api/App/`, contrato em `memory/requisitos/AppMobile/API-CONTRATO-v1.md`.
- **App** = `wagnerra23/oimpresso-app` — telas em `src/telas/`, cliente HTTP em `src/api.ts`.

## Regras que valem para toda tarefa

- **Tier 0 (ADR 0093):** `business_id` do usuário do token em toda consulta, explícito. O global scope lê a sessão, que não existe com token. Teste cross-tenant no tenant 98 (ADR 0358).
- **Códigos de erro**, os mesmos de `OficinaController`:
  - `403 sem_permissao`
  - `404 nao_encontrado`
  - `409 etapa_mudou`
  - `422 validacao {campos}` / `422 bloqueado`
  - `503 sem_configuracao`
  - `429` (throttle)

  O app trata cada um como já faz em Pagamentos e FilaGestor.
- **Throttle de escrita:** 20–30/min, como em `os.php` e `pagamentos.php`.
- **Regra mestre** em tudo que mexe em valor ou estoque (P1 se a etapa baixar estoque; P4): dupla prova, mostrar antes → depois e aprovação [W].
- **Offline:** escrita trava, nada fica em fila (README do app, §3).
- **PT-BR**, toque ≥ 44 px, tokens de `src/styles/oi-v4.css`.

## Arquivos desta pasta
- `api/tela-*.md`: contrato de cada rota nova (corpo, 200, erros), no formato do `API-CONTRATO-v1`. No ERP, vira `memory/requisitos/AppMobile/api/` (um arquivo por tela, regra do §9).
  - P1: `tela-22-avancar-etapa.md`
  - P2: `tela-03-anexos.md`
  - P3: `tela-22-entrega.md`
  - P4: `tela-04-orcamento-rapido.md`
  - P5: `tela-41-atendimento.md`
  - P6: `tela-13-lucratividade.md`
  - P8: `tela-02-apontamento.md`
  - P7 e P9 não têm rota nova.
- `demo-propostas.ts`: respostas simuladas no mesmo formato, para juntar ao `src/demo.ts` do app (modo demonstração).
- `AJUSTES-DA-EMPRESA.md`: as decisões de comportamento viram ajuste da empresa (`pos_settings.app_*`, lido em `/api/app/inicio → ajustes`). **Nada de decisão fixa no código.**

## Antes de tudo (papel)

| # | Tarefa | Repo | Por quê |
|---|---|---|---|
| 0.1 | README do app, §1 "Permissões": trocar "Nunca adicionar permissão de câmera" por câmera permitida (anexos e QR) e biometria só no login. Adicionar `CAMERA`, `NSCameraUsageDescription`, `USE_BIOMETRIC` e `NSFaceIDUsageDescription` com textos em PT-BR. | App | Decisão [W] de 2026-10-05. A ADR 0383 cobre só o ponto. |
| 0.2 | ADR nova: "Biometria só no login do app". A biometria nativa destrava o token, nada vai ao servidor, base no Art. 11 da LGPD; o ponto continua sem biometria. | ERP | A 0383 manda abrir ADR para biometria fora do ponto. |
| 0.3 | Atualizar o BRIEFING do AppMobile, "Regras que não mudam": "Ponto sem câmera/biometria (ADR 0383)" continua igual; acrescentar a linha do app. | ERP | Evitar que o próximo agente leia "sem câmera" como regra do app inteiro. |
| 0.4 | Textos de privacidade das lojas (`docs/lojas-app/textos/privacidade-lojas.md`): câmera e biometria. | ERP | A revisão das lojas exige. |

## Onda 1 — menor risco

### P7 · Contador no ícone — só no app
- [ ] Plugin de badge do Capacitor.
- [ ] Atualizar o número ao abrir (`/api/app/inicio` → `nao_lidas`), ao receber push (`src/push.ts`) e ao ler (Notificacoes: `/notificacoes/{id}/lida` e `/notificacoes/lidas`).
- ERP: nada (as rotas já existem).

### P1 · Avançar etapa do pedido
- **OS:** a rota já existe (`POST /api/app/os/{id}/acoes/{chave}`, `AcoesOs`). Conferir que o `OsDetalhe.tsx` usa `acoes[]`.
- [ ] ERP: `POST /api/app/pedidos/{id}/acao { acao, motivo, etapa_esperada }` via `ExecuteStageActionService` (nome do §2 do contrato). `GET /pedidos/{id}` já devolve `acoes[]`. Contrato: `api/tela-22-avancar-etapa.md`.
- [ ] App: rodapé do Detalhe (Pedidos.tsx) com a ação, confirmação em folha e histórico. Sem permissão, o botão some; com 409, recarrega o pedido.
- [ ] Ajuste `app_acoes_fsm_liberadas`: a empresa escolhe, mas só dentro da lista **segura** (sem efeito em valor ou estoque). Ação com estoque fica fora até a regra mestre ter o ok do [W].

### P8 · Apontamento por QR
- **ERP hoje:** `/comunicacao-visual/api/apontamentos/iniciar | {id}/finalizar | {id}/cancelar | em-andamento`. É sessão web, não token; e a US-COMVIS-004 aponta por spool.
- [ ] ERP: `/api/app/producao/apontamentos/*` com token, por cima do `ApontamentoController`. **Sem pausa**: só iniciar, finalizar e cancelar.
- [ ] ERP: QR na etiqueta da OS/pedido com o id.
- [ ] App: leitor de QR (câmera, depois da 0.1) + número digitado como alternativa + cronômetro.

## Onda 2

### P2 · Fotos e anexos
- **ERP hoje:** tabela `arquivos` (morph `arquivable_type`/`arquivable_id`); `GET /os/{id}` só devolve `fotos_laudo` (contagem).
- [ ] ERP: `GET` e `POST /api/app/os/{id}/arquivos` (multipart, jpg/png/pdf ≤ 10 MB, throttle). Depois, o mesmo para o pedido.
- [ ] App: aba Anexos na OS e no pedido; "Tirar foto" e "Da galeria".

### P3 · Protocolo de entrega
- **ERP hoje:** não há rota de entrega em `routes/api/app`.
- [ ] Ajustes `app_entrega_exige_assinatura` (padrão `false`, permite "sem assinatura" com motivo) e `app_entrega_exige_localizacao` (padrão `false`). Contrato: `api/tela-22-entrega.md`.
- [ ] ERP: `POST /api/app/pedidos/{id}/entrega { itens[], recebido_por, assinatura_png | sem_assinatura_motivo, lat, lng }`. Fecha a etapa Entrega pela FSM e gera o protocolo.
- [ ] App: tela de entrega (conferência, nome, assinatura em canvas), comprovante.

### P6 · Lucratividade e comissões (só leitura)
- **ERP hoje:**
  - comissão do UltimatePOS: `users.cmmsn_percent`, mais `pos_settings.cmmsn_calculation_type` (valor da venda ou pagamento recebido)
  - relatório no `ReportController`
  - permissão `commission_agent.view`
- A regra é **% por vendedor**, não margem nem valor fixo. A tela foi corrigida para isso.
- [ ] ERP: `?aba=lucratividade` e `?aba=comissoes` no `RelatoriosController` que já existe. Vendedor vê só a própria comissão; lucratividade exige `dashboard.data`.
- [ ] App: aba nova em Relatórios e cartão no Dashboard.

### P9 · Entrar com biometria
- [ ] Depende da 0.2.
- [ ] App: plugin de biometria, opção em Conta; três falhas pedem a senha.
- ERP: nada.

## Onda 3 — depende de decisão no ERP

### P4 · Orçamento rápido
- **ERP hoje:** `POST /comunicacao-visual/api/calcular` e `/orcamentos`, só sessão web; preço em `comvis_materiais.preco_venda_m2`.
- [ ] ERP: `POST /api/app/orcamentos/calcular` e `POST /api/app/orcamentos` sobre o mesmo `OrcamentoController`. O app **nunca** calcula sozinho: mostra "calculando no ERP…" e o valor devolvido.
- [ ] Regra mestre (valor).

### P5 · Atendimento WhatsApp
- **ERP hoje:**
  - `Modules/Whatsapp` tem a caixa inteira na web: `/inbox`, `/inbox/{id}/send`, `send-media`, `conversations`, `contacts/search`, macros e IA (`suggest-reply`)
  - consentimento em `contacts.whatsapp_consent` (+ `whatsapp_opt_in_at`), que já vem em `GET /api/app/pessoas/{id}/cadastro`
- [ ] ERP: `/api/app/atendimento/*` por cima do `InboxController`, com token e `business_id` explícito.
- [ ] App: área "Atendimento" em Mais, só quando o canal está ligado.
  - Sem consentimento: só responde dentro da janela de 24 h, envio ativo bloqueado.
  - Atalhos: orçamento, status do pedido e link de arte.

## Como conferir cada tarefa
- `vendor/bin/pest` com o teste cross-tenant da rota nova (tenant 98).
- `npm test` no app: regras puras em `*-regras.ts`, como `pagamento-regras.test.ts`.
- Build de demonstração (`npm run build:demo`) com o dado simulado em `src/demo.ts`.
- Tela conferida contra `Mobile Propostas.dc.html`, com os simuladores de rede, resposta do ERP e canal.
