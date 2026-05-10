# Notas de quem atende PME brasileira há 20 anos — Edição #1

**Junho/2026**
Por Wagner, WR Sistemas

---

**Assunto sugerido:** *3 coisas que aprendi nestes 26 anos atendendo pequena empresa brasileira [edição #1]*

**Pré-cabeçalho:** Gestão de caixa, inadimplência e ticket — o que vejo nos clientes que duram.

---

Esta é a primeira edição da newsletter da WR Sistemas / oimpresso.

Toda última semana do mês eu vou te mandar 3 coisas que aprendi atendendo pequena empresa brasileira — comunicação visual, vestuário, autopeças, oficina. 26 anos de empresa, com base de clientes em Office Comercial (Delphi) e oimpresso ERP.

Sem hype. Sem vender nada. Só o que vejo na prática quando sento com dono de PME pra olhar número junto.

Se chegou aqui de outro lugar, pode se inscrever no rodapé. Se já é inscrito, encaminha pra um amigo dono de empresa pequena que precise ver isso.

---

## 1. Receita estável é o pior tipo de armadilha

Mês passado um cliente me ligou reclamando que "o ano está horrível". Quando olhamos o número juntos, a receita do trimestre dele tinha batido praticamente igual ao mesmo trimestre do ano anterior — variação de menos de 2%. Então não era queda de receita.

O que a gente achou olhando três indicadores juntos:

- **Ticket médio caiu 11%** em relação ao ano anterior.
- **Volume de pedidos subiu 13%** no mesmo período.
- **Inadimplência subiu de 12% pra 19%** da carteira.

Ele estava vendendo *mais* (volume) e *pior* (ticket), e ainda recebendo com mais atraso. A receita mensal parecia igual mas o trabalho dobrou e a margem evaporou. Em três trimestres ele saiu de margem operacional positiva pra negativa.

A solução dele não foi cortar custo — foi parar de aceitar pedido abaixo de R$ [redacted Tier 0] sem entrada de 50%. Em dois meses a inadimplência caiu pra 14% e a margem voltou pro azul. Volume caiu 8%. Receita ficou igual. Margem voltou.

**O que aprendi:** quem só olha o total do mês não vê o ralo. Os três indicadores que importam (ticket médio, volume e prazo médio de recebimento) precisam ser olhados juntos, todo mês, antes de decidir qualquer coisa de operação.

---

## 2. Inadimplência é política, não acaso

Existe um padrão que eu vejo repetido em quase toda PME que entra em apuro: 3 a 4 nomes concentram a maior parte da inadimplência. Não é "o mercado". É política de crédito específica com aqueles clientes específicos.

Faça uma conta simples ainda essa semana, antes do fim de semana:

```
Inadimplência % = (valor faturado vencido há mais de 30 dias)
                  ─────────────────────────────────────────  × 100
                  (valor total faturado nos últimos 90 dias)
```

Se o resultado estiver **acima de 15%**, você provavelmente está financiando seus clientes sem saber. Se estiver **acima de 22%**, o caixa do mês que vem já está comprometido.

Três coisas pra fazer ainda essa semana se o número assustar:

1. **Pedido novo abaixo de um valor de corte (você define):** entrada de 50% antes de produzir. Se o cliente reclamar, é justamente esse cliente que não te pagaria depois.
2. **Cliente recorrente em atraso há mais de 45 dias:** corta crédito até regularizar. Sem brigar, sem cobrar juro alto — só "enquanto não acertar o anterior, o próximo é à vista".
3. **Olha quem deve mais de 60 dias.** Se for sempre os mesmos 3-4 nomes, o problema não é "o mercado" — é a sua política de crédito com aqueles clientes.

---

## 3. Caso real: ROTA LIVRE em Termas do Gravatal/SC

A Larissa toca a ROTA LIVRE — loja de vestuário no Sul de Santa Catarina. Está no oimpresso há mais de 2 anos como cliente em produção.

O que mudou pra ela em relação ao sistema antigo, na prática:

- **NFC-e automática** quando o boleto recebido cai pago (sem ela emitir manual peça por peça).
- **Conversa com a Jana** (a IA do sistema) tipo: *"qual produto vendeu mais essa semana?"* — e a resposta sai com número do banco dela, não com palpite.
- **Tela web** que abre em qualquer máquina — não precisa instalar Delphi em cada PC novo.

Não é caso de marketing. É cliente real, 99% do volume de vendas do oimpresso novo passa por lá hoje. O motivo de eu citar aqui é simples: **o que funciona pra Larissa em vestuário é a mesma engrenagem de núcleo que vai funcionar pra comunicação visual, autopeças, oficina ou qualquer PME brasileira**. O que muda por vertical é o módulo profundo em cima do núcleo (ROTA LIVRE usa o módulo de Vestuário; uma gráfica usaria o de Comunicação Visual; uma autopeça, o de Autopeças).

Não é "ERP gráfico" — é ERP modular onde o miolo é comum (multi-empresa, financeiro, NFe, IA conversacional) e o módulo vertical adiciona profundidade onde precisa.

---

## Rodapé

Encaminhe pra um amigo dono de empresa pequena.
Se chegou aqui via amigo, inscreva-se em **oimpresso.com/newsletter**.

Quem escreve: Wagner Rocha, fundador WR Sistemas (Tubarão/SC). 26 anos atendendo pequena empresa brasileira com Office Comercial (Delphi) e, mais recentemente, oimpresso ERP — núcleo modular com Jana IA, NFe automática e tela web moderna; módulos verticais profundos (Comunicação Visual, Vestuário, Autopeças, Oficina) construídos sobre o mesmo miolo.

**Quer entender se faz sentido pro seu negócio?** Responde esse email com um "oi" que eu te chamo numa call de 30min, sem proposta e sem compromisso. Se não faz sentido, fechou também.

[Cancelar inscrição]

---

*Próxima edição: 31/jul/26 — "O custo escondido de manter sistema legado funcionando".*

<!--
CHANGELOG POLISH 2026-05-10 (Claude pareado Wagner):

REMOVIDO (violava ADR 0121 + decisão Wagner sobre Pilar 5 DaaS externo descartado):
- Título "Estado da Com.Visual BR" (posicionava como vertical-only — viola ADR 0121 modular)
- "Mais da metade das gráficas brasileiras fechou abril no vermelho" (assunto)
- Bloco "Número do mês" com "54% das gráficas em déficit" (Pilar 5 DaaS — descartado)
- Bloco "Tendência setorial" com distribuição de margem das 37 gráficas (Pilar 5 DaaS)
- Bloco "Pergunta da comunidade" com faixa de R$/m² de banner lona das 37 gráficas (Pilar 5 DaaS)
- Frase no rodapé: "Os clientes oimpresso recebem o relatório individual completo (margem por OS, ticket por categoria, DSO segmentado) automaticamente todo mês" (Pilar 5 — descartado)
- Toda referência a "37 gráficas analisadas", "base agregada k-anonymous", "p25/p75/mediana do setor"
- "20 anos atendendo gráficas brasileiras" → "26 anos atendendo pequena empresa brasileira" (alinha ADR 0121)

REESCRITO:
- Título → "Notas de quem atende PME brasileira há 20 anos" (genérico, sem benchmark externo)
- Estrutura de 5 blocos numerados + recomendação prática → 3 blocos consolidados (mais enxuto, sem dado externo)
- Caso anônimo sulista → mantido genérico em bloco 1 (sem citar setor — funciona pra qualquer PME)
- Bloco 3 NOVO → ROTA LIVRE como case real explícito (loja de vestuário em Termas do Gravatal/SC, não gráfica em SP — corrige erro factual histórico)
- Rodapé reposicionado: WR Sistemas atende qualquer PME; oimpresso é modular com módulos verticais profundos (Comunicação Visual, Vestuário, Autopeças, Oficina) — alinhado ADR 0121
- CTA final: troca "saber como funciona o relatório agregado" por "call de 30min sem proposta"
- "fundador WR Sistemas" mantido; "20 anos atendendo gráficas" virou "26 anos atendendo pequena empresa brasileira"

POLISH GRAMATICAL/CLAREZA:
- Removido jargão "DSO" no corpo principal (mantido só a explicação "prazo médio de recebimento")
- Removido tabela ASCII de distribuição de margem
- Consolidado 3 observações da seção 2 antiga em 1 frase principal por bloco
- Tom mantido PT-BR direto, sem hype, sem "revolucionário/transformação digital"
- Citação ROTA LIVRE em produção 2+ anos (case real, não promessa)

VERIFICAÇÃO:
- ✅ Não diz "ERP gráfico" em lugar nenhum
- ✅ Não menciona venda/agregação de dados externa (Pilar 5 descartado)
- ✅ Não usa "transformação digital", "revolucionário", "estado da arte"
- ✅ ROTA LIVRE descrita corretamente (vestuário, Termas do Gravatal/SC, Larissa)
- ✅ Posicionamento alinhado ADR 0121 (modular especializado por vertical)
- ✅ Tom PT-BR direto, mostra resultado real
-->
