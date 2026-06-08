---
cliente: Extreme (EXTREMA LED)
gmv_ano: R$ 6,36M (snapshot oimpresso Insights)
priority: P0
canal_sugerido: whatsapp
timing_sugerido: Q3/26 set
versao_delphi: 1472 (build novo, chama backend Connector)
relacionamento_anos: estimar via banco (cliente saudável e atualizado)
status: draft — Wagner revisa
---

# Carta — Extreme (EXTREMA LED)

## Texto pra mandar (WhatsApp ou email)

> Oi `<NOME_DONO>`, tudo bem?
>
> Aqui é o Wagner, da WR Sistemas. A gente trabalha junto há um bom tempo no OfficeImpresso — vi aqui que vocês estão na versão 1472, das mais recentes do parque, então sei que vocês acompanham as atualizações de perto.
>
> Te escrevo porque tô construindo um sistema novo aqui, chamado **oimpresso**. Continua sendo nosso, não é parceria com terceiro nem nada parecido. A ideia é manter **tudo** que vocês já têm no OfficeImpresso de hoje (cadastro, histórico de pedidos, financeiro, banco de dados completo) e adicionar coisas que o Delphi não dá pra fazer com facilidade — emissão automática de NFe quando o boleto é pago, uma IA que conversa com seus dados ("quanto faturei em LED este mês?"), tela mais moderna pros operadores.
>
> **Importante:** não é "pega ou larga". O Delphi continua. As manutenções e releases do OfficeImpresso seguem normalmente — quem quiser ficar onde está, fica.
>
> O que eu queria era te mostrar de perto, sem compromisso. Uma call de uns 45min, ou se preferir e a logística der, passo aí em `<CIDADE_UF>` pessoalmente. Você vê e me diz o que acha — se faz sentido, a gente conversa em outra hora; se não faz, fechou também.
>
> Quando der pra você dar uma olhada?
>
> Abraço,
> Wagner — WR Sistemas
> WhatsApp: `<WAGNER_TEL>`

---

## Notas Wagner

### Histórico relacionamento
- Cliente saudável, **versão Delphi 1472** (build novo, chama backend Connector — sinal de cliente que paga upgrades e fica em dia)
- Razão social provável: **EXTREMA LED** — vertical comunicação visual + LED é encaixe **nativo** Modules/ComunicacaoVisual (sweet spot do produto novo)
- GMV ano anterior: **R$ 6,36M** (oimpresso Insights snapshot)
- Receita histórica WR Sistemas estimada: R$ 600-850/m (confirmar via skill `officeimpresso-financial-snapshot` antes de mandar)

### Pontos sensíveis
- 🟢 **Cliente atualizado** = receptivo a tecnologia nova SE valor evidente
- 🟢 **Vertical perfeito** — não precisa "encaixar" Modules/ComunicacaoVisual no negócio dele; já É o negócio dele
- 🟡 **Concorrência potencial:** Mubisys pode ter mapeado EXTREMA LED via AFACOM Centro-Oeste. Se Wagner detectar sinal de prospecção concorrente em call, **NÃO baixar preço por reflexo** — defender pelo diferencial NFe + Jana (battle card no post-mortem Gold)
- 🔴 **2º maior cliente do top** (atrás só de Vargas R$ 7,9M) — perda dói. Tom da carta tem que ser **respeitoso da relação**, não comercial-agressivo
- 🟡 **Placeholder `<NOME_DONO>`** — Wagner preenche; se não souber, cair pra "Oi pessoal" como fallback (mas perde personalização P0)

### Versão Delphi e sinal
- **1472** = top 5 das versões mais recentes do parque (49 clientes mapeados)
- **Build NOVO** que chama backend Connector — sinal forte de cliente engajado
- Última atualização presumivelmente recente (Wagner confirma via log de release WR Sistemas)

### Pacote a mencionar SE perguntarem em call (NÃO na carta)
- Setup R$ 0 (pioneer 2º) — janela limitada
- Enterprise R$ 1.499/m grandfathered 24m + 30% off primeiros 6m
- Migração full Migration Factory + parallel run 30d Delphi+oimpresso
- Modules/ComunicacaoVisual completo + Jana ilimitada
- Compromisso: depoimento escrito (mais leve que vídeo do Vargas)

### Plano B se recusar
- Manter no OfficeImpresso normalmente
- Re-tentar 12-18m com case Vargas+ROTA LIVRE já estabilizado
- **Não churnar por pressão de migração** ([ADR 0105](../../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))
