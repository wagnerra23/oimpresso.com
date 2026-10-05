# Playbook de PRs — propostas P1–P9 (app das lojas)

> Para o Claude Code. Cada linha abaixo vira **um PR**. Leia antes: `TAREFAS.md` (o quê e por quê), `AJUSTES-DA-EMPRESA.md` (as decisões viram ajuste), `api/tela-*.md` (contrato de cada rota) e `demo-propostas.ts` (respostas simuladas).
> Visual: `Mobile Propostas.dc.html` (simuladores de rede, resposta do ERP, canal e ajustes) e `Oimpresso Mobile.dc.html` (selos "proposta" nas telas onde cada uma entra).
> Conferido em `oimpresso.com@main` e `oimpresso-app@main` em 2026-10-05. **Nada foi gravado nos repositórios.**

## Repos
- **ERP** `wagnerra23/oimpresso.com`:
  - rotas em `routes/api/app/<area>.php` (um arquivo por área; nunca rota solta em `routes/api.php`)
  - controllers em `app/Http/Controllers/Api/App/`
  - contrato em `memory/requisitos/AppMobile/api/tela-NN-*.md` (um arquivo por tela, regra do §9)
- **App** `wagnerra23/oimpresso-app`:
  - telas em `src/telas/`
  - cliente em `src/api.ts`
  - regras puras em `src/*-regras.ts` (com teste)
  - demonstração em `src/demo.ts`

## Regras de todo PR (não negociáveis)
1. **Um PR, uma coisa.** O PR de API (ERP) vem antes do PR de tela (app). O app pode subir antes contra o `demo.ts`, mas só mergeia quando a rota estiver ✅.
2. **Tier 0 (ADR 0093):**
   - `business_id` do usuário do token, explícito, em toda consulta.
   - Teste cross-tenant no tenant 98 × 2 (ADR 0358).
   - Outra empresa → 404, sem vazar que o registro existe.
3. **Erros:** os códigos do `OficinaController` (`403 sem_permissao`, `404 nao_encontrado`, `409 etapa_mudou`/`em_andamento`, `422 validacao{campos}`/`bloqueado`, `503 sem_configuracao`, `429`). Corpo `{erro, mensagem}` em PT-BR.
4. **Escrita:**
   - `Idempotency-Key` (tabela `app_idempotencia`, a mesma da venda §2.2)
   - throttle de 20–30/min
   - `etapa_esperada` quando muda etapa
5. **Regra mestre (valor ou estoque):** dupla prova + tabela antes → depois no PR + **ok do [W] antes do merge**. Sem isso o PR fica em rascunho.
6. **Ajuste da empresa:** comportamento que alguém poderia querer diferente vira chave `app_*` (`AJUSTES-DA-EMPRESA.md`), conferida no servidor. Nada de `if` de empresa no código.
7. **Contrato:** cada PR de API cria ou atualiza `memory/requisitos/AppMobile/api/tela-NN-*.md` (⬜ → ✅) e acrescenta a área em `areas` (§6), se for o caso.
8. **App:**
   - offline trava a escrita, sem fila
   - toque ≥ 44 px
   - tokens de `src/styles/oi-v4.css` (nunca editar à mão: `npm run tokens`)
   - PT-BR, sentence case
   - respeitar o tema do celular (D14)

## Gates
- **ERP:** `vendor/bin/pest` (lane `PHP / Pest` MySQL) com o teste de contrato da rota + cross-tenant; `vendor/bin/pint --test`.
- **App:**
  - `npm ci && npm test && npm run build && npm run build:demo`
  - `npx cap sync`
  - workflow `pr-check.yml` verde
  - tela conferida no build de demonstração contra o protótipo, em tema claro e escuro

## Ordem e dependências

| PR | Repo | Branch | O quê | Depende de | Regra mestre |
|---|---|---|---|---|---|
| 00a | App | `docs/permissoes-camera-biometria` | README §1: câmera (anexos e QR) e biometria só no login; textos `CAMERA`, `NSCameraUsageDescription`, `USE_BIOMETRIC`, `NSFaceIDUsageDescription` | — | — |
| 00b | ERP | `docs/adr-biometria-login-app` | ADR nova (biometria só no login, Art. 11; o ponto segue a 0383) + BRIEFING AppMobile + `docs/lojas-app/textos/privacidade-lojas.md` + os 7 `api/tela-*.md` como ⬜ | — | — |
| 01 | ERP | `feat/app-ajustes-empresa` | Aba "App das lojas" nas configurações; `pos_settings.app_*`; `ajustes{}` em `GET /api/app/inicio` (sempre completo, com padrão) | 00b | — |
| 02 | App | `feat/ajustes-empresa` | Tipo e padrão de `ajustes` em `src/api.ts`; fallback quando o ERP não manda | 01 | — |
| 03 | App | `feat/badge-icone` | **P7** — plugin de badge; atualiza em `/inicio`, push (`src/push.ts`) e ao ler (Notificacoes) | — | — |
| 04 | ERP | `feat/app-pedido-acao` | **P1** — `POST /api/app/pedidos/{id}/acao` via `ExecuteStageActionService`, **só a lista segura** + `app_acoes_fsm_liberadas` | 01 | só se entrar ação com estoque (fora deste PR) |
| 05 | App | `feat/pedido-avancar-etapa` | **P1** — rodapé do Detalhe (`Pedidos.tsx`), folha de confirmação, histórico, 409 recarrega | 04, 02 | — |
| 06 | ERP | `feat/app-apontamento` | **P8** — `/api/app/producao/apontamentos/*` (iniciar, finalizar, cancelar, em-andamento) sobre o `ApontamentoController` + `GET /producao/etiqueta/{codigo}` + `app_apontamento_exige_qr` | 01 | — |
| 07 | ERP | `feat/qr-etiqueta-pedido` | QR `oimpresso:p:<business_hash>:<pedido_id>` no PDF da OS/pedido | 06 | — |
| 08 | App | `feat/apontamento-qr` | **P8** — leitor de QR (câmera), número digitado, cronômetro (o tempo que vale é o do servidor) | 06, 00a | — |
| 09 | ERP | `feat/app-os-arquivos` | **P2** — `GET`/`POST /api/app/os/{id}/arquivos` sobre a tabela `arquivos`; URL assinada de 10 min; `app_anexos_camera` | 01 | — |
| 10 | App | `feat/anexos-os` | **P2** — aba Anexos na OS (`OsDetalhe.tsx`): "Tirar foto" e "Da galeria" | 09, 00a | — |
| 11 | ERP | `feat/app-pedido-entrega` | **P3** — `POST /api/app/pedidos/{id}/entrega`, protocolo `ENT-NNNN`, assinatura em `arquivos`, FSM numa transação; `app_entrega_exige_assinatura`/`_localizacao` | 04 | — |
| 12 | App | `feat/entrega-protocolo` | **P3** — conferência, quem recebeu, assinatura (canvas) ou "sem assinatura" com motivo; comprovante | 11 | — |
| 13 | ERP | `feat/app-relatorio-lucro-comissao` | **P6** — `?aba=lucratividade` e `?aba=comissoes` no `RelatoriosController`; comissão do UltimatePOS (`cmmsn_percent`, `cmmsn_calculation_type`); `app_comissao_vendedor_ve_propria` | 01 | — (só leitura) |
| 14 | App | `feat/relatorio-lucratividade` | **P6** — aba em Relatórios + cartão no Dashboard | 13 | — |
| 15 | App | `feat/biometria-login` | **P9** — plugin de biometria, opção em Conta, 3 falhas → senha; `app_biometria_login` | 00a, 00b, 02 | — |
| 16 | ERP | `feat/app-orcamento-rapido` | **P4** — `GET /orcamentos/materiais`, `POST /orcamentos/calcular`, `POST /orcamentos` sobre o `OrcamentoController`; dupla prova do total; `app_orcamento_rapido` | 01 | **sim** |
| 17 | App | `feat/orcamento-rapido` | **P4** — "+ Novo" em Orçamentos; o app mostra "calculando no ERP…" (debounce de 500 ms) e nunca soma | 16 | **sim** |
| 18 | ERP | `feat/app-atendimento` | **P5** — `/api/app/atendimento/*` sobre o `InboxController` (Modules/Whatsapp); bloqueio sem consentimento; área `atendimento` em `areas`; `app_atendimento_atalhos` | 01 | — |
| 19 | App | `feat/atendimento-whatsapp` | **P5** — tela 41 em Mais (só com o canal ligado); atalhos de orçamento, status e arte | 18 | — |

**Ondas:**
- Onda 1: 00a, 00b, 01, 02, 03, 04, 05, 06, 07, 08
- Onda 2: 09–15
- Onda 3: 16–19

Em cada onda, os PRs sem dependência entre si podem ir em paralelo, um agente por PR.

## Molde de descrição do PR

```
## O quê
<P# · tela NN> — <uma frase>.

## Contrato
memory/requisitos/AppMobile/api/tela-NN-<nome>.md (⬜ → ✅)

## Ajustes da empresa usados
app_<chave> (padrão <valor>) — conferido no servidor em <arquivo:linha>

## Como conferi
- [ ] Pest: <teste> (lane MySQL) — <n> passed
- [ ] Cross-tenant tenant 98 × 2: outra empresa → 404
- [ ] Idempotência: mesma chave + mesmo corpo → mesma resposta
- [ ] Erros: 403 / 404 / 409 / 422 / 429 cobertos
- [ ] (app) build:demo conferido contra Mobile Propostas.dc.html#p<N>, claro e escuro

## Regra mestre (só se mexe em valor/estoque)
| Campo | Antes | Depois |
|---|---|---|
Dupla prova: <onde> · Ok do [W]: <link>

## Fora deste PR
<o que ficou para depois>
```

## Ao terminar cada PR
- Marcar o item em `TAREFAS.md` e trocar ⬜ por ✅ no `api/tela-*.md`.
- No projeto de design: atualizar `github.md` (Last sync) e trocar o selo "proposta" da tela por nada. A tela passa a ser produção no `Oimpresso Mobile.dc.html`.
