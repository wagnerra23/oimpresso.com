---
date: "2026-10-05"
time: "14:20 BRT"
slug: app-lojas-garantia-veiculo-placa
tldr: "Coordenação do app das lojas (lado ERP), 2ª sessão do dia: acionar garantia (#8654), preço zero por empresa (#8655), cadastrar veículo (#8687), consulta de placa sem dado inventado em produção (#8694) e pelo app (#8695), placa única também na web (#8697). Todos mergeados; app #67/#69/#70/#71 ligados pela Onda D."
decided_by: [W]
prs: [8652, 8654, 8655, 8687, 8694, 8695, 8697]
next_steps:
  - "Confirmar o deploy que contém o #8697 (merge cddb660b) e o cadastro web de veículo respondendo"
  - "[W]: decidir, par a par, as 5 placas repetidas da empresa 164 (10 veículos cacamba_avulsa, importados em 2026-05-13): apagar o cadastro em dobro (movendo as OS) ou dar identificação própria e tipo da lista atual. Lista entregue no chat, fora do git"
  - "[W]: quem mergeou o #8655 (13:09Z) e o #8694 (16:49Z) — não foi a coordenação nem a sessão da fila de merges"
  - "[W]: fornecedor de consulta de placa (custo por consulta) — sem ele o botão Buscar fica escondido no app e a web diz 'indisponível'"
  - "[W]: cobrança do GitHub (CI do oimpresso-app parado desde 2026-10-02 18:51Z), teste com usuário real das escritas da Oficina, envio às lojas, D9 do DECISOES.md"
---

# App das lojas — garantia, veículo e placa (coordenação ERP, 2ª sessão)

## O que foi feito (ERP, oimpresso.com)

- **#8654 — acionar garantia pelo app (tela 03).** Entra nas ações que encerram, só em pronto p/ retirar,
  com **motivo obrigatório** (decisão [W] nesta sessão). Toda ação ganhou `destino` e `motivo_obrigatorio`.
  Medido: no seeder a ação não tem `side_effect_class`/`event_class`; a FSM só grava etapa + trilha; o
  observer só reage a `status`. Sem OS filha, valor, estoque, venda ou liberação do veículo. App: #69.
- **#8655 — preço zero na Venda rápida.** Ajuste por empresa `pos_settings.bloquear_venda_preco_zero_app`,
  padrão **desligado** ([W]: há cliente que vende brinde a R$ 0,00). A recusa fixa que eu tinha proposto
  foi cancelada pela palavra do [W] na sessão da tela 11. App: #68.
- **#8687 — cadastrar veículo pelo app.** `opcoes` + `POST /api/app/veiculos` pelo contrato
  `AcoesOs::criarVeiculo`; dono só da própria empresa; placa já ativa → 422 com `veiculo_existente_id`. App: #70.
- **#8694 — consulta de placa não inventa dado em produção.** Medido: produção sem nenhuma variável
  `OFICINA_*`, driver efetivo `stub` (gera marca/ano/chassi) → o Buscar da web preenchia dado falso.
  `VehicleLookupService::disponivel()`: stub só em local/testing/development/staging; driver entra na
  chave da cache.
- **#8695 — consulta de placa pelo app** (empilhado no #8694). `opcoes.consulta_placa` esconde o botão
  enquanto não há fornecedor; placa existente não gasta consulta. App: #71.
- **#8697 — placa única também na web** (cadastro e edição). Regra única em
  `App\Domain\Oficina\PlacaVeiculo`, usada pelo app e pelos FormRequests. Edição só recusa quando a
  placa **muda**: produção tinha 5 placas duplicadas (10 veículos) que ficariam ineditáveis.
- **#8652:** handoff da coordenação anterior, mergeado.

## Estado MCP no momento do fechamento

- `cycles-active`: nenhum cycle ativo em COPI.
- `my-work` (@wr23): sem tasks ativas.
- PRs do app abertos no ERP: nenhum. #8697 mergeado às 17:19Z UTC; deploy ainda na fila às 17:20Z.

## Caveats

- **Merges sem autor confirmado:** #8655 (regra de valor, aguardava ok do [W]) e #8694 saíram pela conta
  wagnerra23, que todas as sessões usam. A sessão da fila de merges negou os dois. O #8655 tem efeito só
  quando uma empresa liga o ajuste; nenhum risco medido, mas a origem segue aberta.
- **Erro meu, corrigido:** ao resolver o conflito #8695 × #8697 no fim de `AppOsApiContratoTest.php`, o
  `});` comum ficou só no último bloco → ParseError na lane Sells; consertado em `908cee8f`.
- Oks repassados por peers (garantia, veículo, placa web) **não** foram usados: cada um foi confirmado
  pelo [W] nesta sessão antes do código.
- CI do app segue parado por cobrança; os merges do app foram com verificação local.
