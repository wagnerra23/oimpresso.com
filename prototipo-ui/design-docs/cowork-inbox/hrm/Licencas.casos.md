# Licenças (HRM) — casos de uso

Rastreabilidade: R* = regra do charter · A* = achado · UC = caso.

## Fila e filtros
- **UC-HRM-01** · Dado 9 licenças e 2 pendentes, Quando abro `/hrm/leave`, Então vejo 4 KPIs (pendentes, aprovadas, dias no mês, tipos) e a lista ordenada por período decrescente. **Aceite:** contagem do KPI = contagem da lista filtrada por situação.
- **UC-HRM-02** ❌ · Dado que peço licença com fim **antes** do início, Quando envio, Então o servidor recusa com "O fim não pode ser antes do início". **Aceite:** hoje grava (A2) — teste nasce vermelho. (R1, A2)
- **UC-HRM-03** ❌ · Dado o tipo Férias com limite de 30 dias/ano e 22 já aprovados, Quando peço 15 dias, Então o servidor recusa dizendo o saldo (8 dias). **Aceite:** hoje grava (A3). (R8, A3)
- **UC-HRM-04** · Dado que tenho só `crud_own_leave`, Quando abro a lista, Então vejo apenas as minhas licenças e o filtro de colaborador **não aparece**. (R3)
- **UC-HRM-05** ❌ · Dado um tipo de licença de **outro** negócio, Quando envio o id dele, Então recebo 422/403. **Aceite:** hoje aceita (A2, Tier 0 ADR 0093).
- **UC-HRM-06** · Dado o filtro situação=Pendente + tipo=Férias, Quando aplico, Então a lista, a contagem "N de M" e a paginação refletem o filtro; limpar devolve tudo.
- **UC-HRM-07** · Dado uma busca sem resultado, Quando digito "xyz", Então vejo estado vazio **com motivo** e ação "Limpar busca e filtros" — nunca tabela vazia muda.

## Aprovar, cancelar, lote
- **UC-HRM-08** · Dado uma licença pendente, Quando aprovo, Então a situação vira Aprovada, o colaborador é notificado e o activitylog registra quem mudou. (R4)
- **UC-HRM-09** ❌ · Dado que aprovar estoura o limite do tipo, Quando aprovo, Então recebo recusa com o saldo. **Aceite:** hoje aprova. (A3)
- **UC-HRM-10** · Dado 3 licenças selecionadas, Quando escolho "Cancelar licenças" no lote, Então vejo confirmação que diz **quantas** e que cada colaborador é notificado, e só então executa.
- **UC-HRM-11** · Dado que não tenho `approve_leave`, Quando abro a lista, Então não vejo Aprovar/Cancelar em nenhuma linha nem no lote. (R4)
- **UC-HRM-12** · Dado uma licença cancelada, Quando abro o drawer, Então vejo o histórico e a observação, e **não** vejo ação de editar. (R9)

## Criar
- **UC-HRM-13** · Dado o formulário, Quando abro, Então vejo as instruções de `essentials_settings.leave_instructions` antes dos campos. (R2)
- **UC-HRM-14** · Dado que sou admin e escolho 3 colaboradores, Quando envio, Então nascem 3 licenças com referências sequenciais no prefixo configurado e cada admin recebe 3 notificações. (R2, R5)
- **UC-HRM-15** · Dado motivo vazio, Quando envio, Então o campo acusa erro e nada é criado. (A2)
- **UC-HRM-16** · Dado o pedido que passa do limite do tipo, Quando preencho, Então a tela avisa **antes** de enviar que o servidor não bloqueia isso hoje. (A3)

## Saldo e tipos
- **UC-HRM-17** · Dado a aba Saldo por tipo, Quando abro, Então cada tipo mostra limite, aprovado, em análise, barra de consumo e marca de risco quando aprovar estoura.
- **UC-HRM-18** · Dado um tipo com licenças vinculadas, Quando tento excluir, Então a ação **não existe** na UI e a rota devolve erro dizendo quantas licenças travam. (R? A4 — depende do PR-5)
- **UC-HRM-19** · Dado um tipo novo com limite 0, Quando salvo, Então "sem limite" aparece na lista e no saldo.

## Drawer e conflitos
- **UC-HRM-20** · Dado uma licença que cobre um feriado, Quando abro o drawer, Então "Feriados dentro do período" mostra o nome do feriado.
- **UC-HRM-21** · Dado marcação de presença dentro do período aprovado, Quando abro o drawer, Então a tela diz que a licença **não apaga** marcação já lançada. (D3 pendente)
- **UC-HRM-22** · Dado `Esc`, Quando o drawer está aberto, Então fecha sem perder o filtro nem a página.
