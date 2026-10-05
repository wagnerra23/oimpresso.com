# Ajustes da empresa — App das lojas ⬜

As decisões de comportamento **não ficam no código**. Cada empresa escolhe nas configurações da web, no mesmo padrão do `bloqueia_preco_zero` da venda rápida (§2.2): uma chave em `pos_settings`, valor padrão quando a chave não existe, e o app lê sem precisar de versão nova.

## Onde fica
- **Web:** Configurações da empresa → aba "App das lojas", nova. Permissão `business_settings.access`.
- **Gravação:** `business.pos_settings` (JSON), chaves com prefixo `app_`.
- **Leitura no app:** `GET /api/app/inicio` passa a trazer `ajustes: { … }`, sempre com todas as chaves. O ERP completa com o padrão. Se o ERP for antigo e não mandar `ajustes`, o app usa o padrão desta tabela.
- **Validação:** o ERP aplica o ajuste **no servidor também**. O app só esconde ou mostra; quem recusa é a rota. Exemplo: com assinatura obrigatória, `POST /entrega` sem assinatura → `422 campos.assinatura_png`.

## As chaves

| Chave | Tipo | Padrão | O que muda | Proposta |
|---|---|---|---|---|
| `app_entrega_exige_assinatura` | bool | `false` | `true`: some "Recebido sem assinatura"; a rota exige `assinatura_png` | P3 |
| `app_entrega_exige_localizacao` | bool | `false` | `true`: sem GPS a entrega não é registrada | P3 |
| `app_acoes_fsm_liberadas` | string[] | `[]` = todas as **seguras** | Quais ações da FSM do pedido aparecem no app. Só aceita ações que o ERP marca como sem efeito em valor ou estoque | P1 |
| `app_anexos_camera` | bool | `true` | `false`: só galeria | P2 |
| `app_apontamento_exige_qr` | bool | `false` | `true`: some "Digitar número"; só vale o QR | P8 |
| `app_orcamento_rapido` | bool | `false` | Liga o "+ Novo" no app (depois do ok do [W] da regra mestre) | P4 |
| `app_atendimento_atalhos` | bool | `true` | Atalhos de orçamento, status e arte na conversa. O consentimento LGPD vale sempre | P5 |
| `app_comissao_vendedor_ve_propria` | bool | `true` | O vendedor vê a própria comissão; `false`: só quem tem `commission_agent.view` | P6 |
| `app_biometria_login` | bool | `true` | `false`: a opção some em Conta e o token não fica preso à biometria | P9 |

## O que NÃO é ajuste
- **Regra mestre:** uma ação que mexe em valor ou estoque só entra no código com dupla prova e ok do [W]. Uma empresa não liga o que o [W] não liberou. Por isso `app_acoes_fsm_liberadas` só aceita ações da lista segura.
- **Ponto sem biometria (ADR 0383):** não há chave. O ponto nunca usa biometria.
- **Consentimento LGPD no WhatsApp:** não há chave. Sem consentimento, não há envio ativo.
- **Ordem das ondas:** é decisão de entrega, não de empresa.

## Tarefa
- [ ] ERP: aba "App das lojas" nas configurações (Blade/React da tela de settings), gravação em `pos_settings`, `ajustes` em `/api/app/inicio` e checagem em cada rota de escrita.
- [ ] App: `ajustes` em `src/api.ts` (tipo + padrão), e cada tela lê dali.
- [ ] Contrato: entrada no §6 (Início) e nota em cada `api/tela-*.md` que usa a chave.
