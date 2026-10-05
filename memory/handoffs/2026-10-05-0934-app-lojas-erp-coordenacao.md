---
date: "2026-10-05"
time: "09:34 BRT"
slug: app-lojas-erp-coordenacao
tldr: "Lado ERP do app das lojas (D16) completo em produção: produtos/estoque (19, 05, 29, 20), reorganização das rotas e do contrato por arquivo (#8612), histórico do veículo (#8635) e escritas da Oficina (avançar #8637, nova OS #8639, cancelar/recusar #8646). Coordenação continua em sessão nova."
decided_by: [W]
prs: [8574, 8577, 8581, 8582, 8583, 8606, 8612, 8635, 8637, 8639, 8646]
next_steps:
  - "Confirmar o deploy que contém o #8646 (a rota já respondia 401, só o deploy prova) e avisar a sessão do app da Onda D para ligar cancelar/recusar"
  - "[W]: cobrança do GitHub — CI do oimpresso-app (privado) não inicia desde 2026-10-02 18:51Z; merges do app foram com verificação local"
  - "[W]: D9 do DECISOES.md ainda cita 'as 7 áreas (D13)'"
  - "[W]: envio às lojas (Vaultwarden, pacote Demo lojas, login real, tipo da conta Play Console) e venda de conferência na empresa 235"
  - "[W]: teste com usuário real de produção das escritas da Oficina"
---

# App das lojas — lado ERP completo; coordenação muda de sessão

## O que foi feito (ERP, oimpresso.com)

- **Produtos e estoque (Onda B):** #8574 (19 produtos), #8577 (05 estoque), #8581 (29 movimentações —
  o controller invertia de novo o histórico que `getVariationStockHistory` já devolve invertido; o teste
  de contrato pegou), #8582 (20 novo produto, sem preço). Todos em produção (401 sem token, controle 404).
- **#8606:** fixture de teste dava `location.{id}` pelo papel; `permitted_locations()` só lê permissão
  direta (como a web grava). A lane Acessos estava vermelha no main por isso.
- **#8612 — rotas e contrato por arquivo:** `routes/api.php` carrega `routes/api/app/<área>.php`;
  contrato em `memory/requisitos/AppMobile/api/tela-NN-*.md`, com tabela fixa da numeração antiga no v1.
  Motivo: todo PR do app editava as mesmas linhas e cada merge derrubava os irmãos (15 num dia).
  Refutação GT-G5 por subagente fable em contexto novo: 308 itens, 1 erro leve, PII 0 — evidência em
  `memory/sessions/2026-10-02-refutacao-gt-g5-lote-8612-r1.md`. Regenerado 4× até a cadeia da Onda C entrar.
- **#8635 — histórico do veículo** (`GET /veiculos/{id}/os`, tela 08).
- **Escritas da Oficina (tela 03/07):** #8637 avançar etapa, #8639 nova OS, #8646 cancelar/recusar.
  Contrato `App\Contracts\Oficina\AcoesOs` implementado pela OficinaAuto (núcleo não importa o módulo).
  Medido no código: a FSM só grava `current_stage_id` + trilha; o `ServiceOrderObserver` só age quando
  `status` muda (orcamento → WhatsApp, concluida → venda), e nenhuma dessas rotas mexe em `status`;
  ação com `side_effect_class`/`event_class` no banco é recusada. Fora da regra mestre. Veículo não é
  liberado ao cancelar (igual à web).

## Estado MCP no momento do fechamento

- `cycles-active`: nenhum cycle ativo em COPI.
- `my-work` (@wr23): sem tasks ativas.
- PRs do app abertos no ERP: nenhum (#8646 mergeado 12:24Z UTC; deploy ainda não confirmado às 12:34Z).

## Caveats

- O CI do oimpresso-app não roda (cobrança). Todo merge no app desde 2026-10-02 18:51Z foi com
  verificação local (tsc + testes + build sem demo) — o build Android/iOS nunca rodou nesses merges.
- Peers repassaram oks do [W] (#8603, #8597, "todos autorizados"); não usei repasse como autorização —
  os merges de valor/estoque tiveram o ok dado na sessão dona.
- Coordenação continua na sessão "Continuar coordenação do app das lojas (ERP)" (chip task_3f84499a),
  com o prompt completo de estado e regras.
