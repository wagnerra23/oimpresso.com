# Martinho Mecânica — abordagem personalizada — 2026-05-09

> **Status:** prospect REAL reportado por Wagner — cliente do sistema atual há 26 anos pagando R$ 830/mês, **trocando de sistema agora**. Provavelmente cliente histórico OfficeImpresso (Delphi WR Sistemas) — a confirmar com Wagner.
> **Sinal qualificado:** ✅ ([ADR 0105](../../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) — cliente paga + reportou intenção de trocar.
> **Autor:** Claude (SDR + analyst)
> **Inputs:** pesquisa pública Google/Solutudo/Infoisinfo + auto-mem `reference_clientes_ativos`/`reference_delphi_wr_comercial` + `01-cold-email-persona-oficina.md`.
> **Restrições:** sem PII pessoa física, sem inventar dados, sem commit/push.

---

## Quem é (pesquisa pública — sinais fracos, ambíguo)

Pesquisa pública **NÃO retornou um "Martinho Mecânica" claramente único** no Brasil. Resultados Google/Solutudo/Infoisinfo dominados por homônimos em Portugal (Martinho Auto, Jorge Martinho Carcavelos, Auto Martinho & Mestre Lisboa). Em PT-BR só apareceram pistas fracas:

### 3-5 candidatos prováveis (a confirmar com Wagner)

1. **Auto Mecânica Avenida LTDA — São Martinho/RS** (CNPJ 11.112.047/0001-63)
   - Fundada 02/09/2009. Nome do município "São Martinho" pode ter virado apelido. **Pouco provável** se cliente tem 26 anos de sistema (empresa só tem ~17 anos).

2. **Martinelli Auto Mecânica — São Bernardo do Campo/SP**
   - Página Facebook ativa. Nome contém "Martin-" e pode ter sido apelidado de "Martinho" no boca-a-boca. **Possível**, é Grande SP (alinha com base WR Sistemas).

3. **Oficinas em Martinho Campos/MG**
   - Cidade pequena (~12k hab) com várias oficinas. Wagner teria que dar mais sinal — nome do dono ou bairro.

4. **Oficina de R. Martinho Lutero, Canela/RS**
   - Endereço (não nome) — descartável a menos que Wagner confirme.

5. **Oficina local sem presença web** (mais provável)
   - Persona típica do João Mecânico em [01-cold-email-persona-oficina.md](01-cold-email-persona-oficina.md): galpão de bairro, 5-12 funcionários, R$ 50-200k/mês. **80% das oficinas BR não têm site nem ficha LinkedIn.** Se for cliente OfficeImpresso há 26 anos, é exatamente esse perfil — Wagner provavelmente tem o cadastro completo no banco WR Sistemas (`HKCU\Software\Rocha\Office Comercial\Banco\Caminhos` por auto-mem `reference_bancos_firebird_wr2`).

> **Recomendação imediata:** antes de qualquer outbound, Wagner consulta a base interna OfficeImpresso (banco Firebird WR2 ou MySQL oimpresso `business`) e confirma:
> - Razão social + CNPJ
> - Cidade/UF
> - Nome dono (titular do contrato)
> - Volume histórico (OS/mês, ticket médio se tiver)
> - Última interação (suporte, upgrade, queixa)
>
> Sem isso, abordagem cega gera ruído — e perde o trunfo "fornecedor histórico".

### Tamanho estimado (sinais indiretos)
- Paga R$ 830/mês há 26 anos = OfficeImpresso plano base/médio. Provável **5-12 funcionários** (sweet spot persona João Mecânico).
- 26 anos no mesmo sistema = dono **estável, conservador, ~50-65 anos** OU sucessão geracional acabou de assumir.

### Segmento mecânica
- Provável **mecânica geral** (motor + suspensão + freio) ou **multimarca** — OfficeImpresso é genérico, não tem skin especialista (motor diesel, ar-condicionado, retífica).

### Tempo de mercado
- Mínimo 26 anos (tempo do sistema). Provável **30-40 anos de operação**.

### Cliente típico
- PF + PJ frota local (mecânicas históricas costumam ter 1-2 contratos PJ recorrentes — taxi, locadora pequena, serviço público municipal).

---

## Por que está trocando? (5 hipóteses)

### H1 — Sistema legacy 26 anos = obsoleto fiscal/tech
- OfficeImpresso é Delphi/Firebird, instalado local. Sem nuvem, sem mobile, sem app cliente.
- NFS-e reforma tributária 2027 vai exigir adaptação grande no legacy — pode não ter roadmap.
- **Probabilidade: alta.** Mais óbvia.

### H2 — Suporte do fornecedor atual deteriorou
- Wagner é o próprio fornecedor (WR Sistemas) — se ele decidiu **não evoluir mais OfficeImpresso** e migrar todo mundo pro oimpresso, esse cliente sentiu o afastamento.
- **Probabilidade: alta** se Wagner é o trigger emocional. Conflito: se Martinho está saindo **por queixa do Wagner**, abordagem precisa abrir com humildade.

### H3 — Crescimento exige funcionalidades novas
- App mobile, integração WhatsApp, gestão multi-loja se abriu filial.
- **Probabilidade: média.** 26 anos no mesmo sistema sugere baixa pressão de crescimento.

### H4 — Pressão fiscal (NFS-e Reforma 2027, eSocial completo)
- OfficeImpresso pode não cobrir SPED/eSocial moderno.
- **Probabilidade: média-alta.** Contador sempre é o porta-voz dessa pressão.

### H5 — Sucessão geracional (filho assumiu)
- Filho 25-35 anos não aceita sistema Delphi de 1999. Quer cloud, mobile, dashboard.
- **Probabilidade: alta.** Padrão recorrente em oficinas BR 2025-2026.

---

## Por que oimpresso é fit (se for)

- **Modules/Repair** Kanban drag-drop (PR #363) — vocabulário automotivo já vazado: `placa`, `vehicle`, `brand`, `km`, `box`, `mecanico`. Estrutura serve mecânica com 80-90% de cobertura visual já hoje.
- **55-60% cobertura técnica atual** vs Mecânico/Auto Manager (gap analysis R32 do mercado auto).
- **IA Jana** — diferencial real vs Mecânico/Auto Manager/OficinaMaster (nenhum tem IA com memória persistente).
- **Multi-tenant Tier 0** — isolamento garantido ([ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md)).
- **NFC-e SEFAZ pronta** (US-NFE-002 fechada biz=1, Wagner WR2 SC). NFS-e em roadmap.
- **Vínculo histórico Wagner-Martinho** se confirmado: 26 anos = relação > qualquer concorrente conseguir vender em 2 visitas. Trunfo.

---

## Por que oimpresso pode NÃO ser fit (confronto honesto)

Faltam **12 essenciais auto** (~38h dev IA-pair = 10 dias Felipe pra MVP, fator 10x [ADR 0106](../../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)):

1. Cadastro veículo persistente (placa+km+VIN)
2. Consulta CRLV/Detran (placa → marca/modelo/ano)
3. NFS-e municipal (gap crítico — atual 0)
4. WhatsApp aprovação OS com PIN
5. Tabela tempária Sindirepa (tempo padrão por serviço)
6. Catálogo OEM (Molicar/Audatex)
7. App mecânico mobile com 3 botões (PWA existe; nativo não)
8. Histórico veículo (km anterior, peças trocadas, garantia)
9. Tabela de tempos por marca/modelo
10. Integração financeira boleto + PIX automático (Asaas-style)
11. Galeria foto antes/depois OS
12. Relatório fechamento contador (mensal automático)

**Sem cliente piloto auto = primeiro = pioneer pain.** Risco real: oimpresso quebra/atrasa, Martinho fala mal em grupo WhatsApp Donos de Oficina (200-800 membros) — queima a marca em 48h ([01-cold-email-persona-oficina.md §Risco 2](01-cold-email-persona-oficina.md)).

---

## Abordagem proposta (3 fases)

### Fase 0 — Prep (Wagner faz ANTES de qualquer contato)
1. Consultar base OfficeImpresso (Firebird WR2 ou MySQL): razão social, CNPJ, cidade, dono, histórico
2. Decidir tom: **fornecedor histórico que evoluiu** (acolhedor) vs **frio, "ouvi falar"** (improvável dado vínculo)
3. Confirmar se há queixa/conflito pendente — se sim, abrir com mea culpa

### Fase 1 — Discovery (call/visita 25min, presencial > zap > telefone)

**Tom recomendado se vínculo histórico:** abre acolhedor, reconhece os 26 anos.

> "Seu [Dono], aqui é o Wagner. 26 anos juntos no OfficeImpresso, né? Sei que você tá olhando alternativas. Em vez de te empurrar coisa, queria te ouvir 20 minutos: o que travou, o que tu precisa que o sistema atual não dá. Se o oimpresso (que é o que eu tô construindo agora) servir, a gente conversa preço; se não servir, te indico o melhor da praça honestamente. Topas?"

**Perguntas SPIN:**
1. Por que decidiram trocar **agora**? (timing — mudou imposto, contador cobrou, filho assumiu?)
2. O que o OfficeImpresso **não fazia** que virou deal-breaker?
3. Quanto tempo/h por semana o sistema atual rouba do dono no fechamento?
4. Quem decide compra (dono solo, sócio, filho-sucessor, esposa-contadora)?
5. Prazo de troca (já está em negociação com outro fornecedor? mês? trimestre?)
6. Budget real — R$ 830 atual está confortável, apertado, ou queria pagar menos?
7. App mobile pros mecânicos é must-have ou nice-to-have?
8. NFS-e + NFC-e: faz hoje? quem faz? qual prefeitura?
9. Tabela tempária / catálogo de peças usa qual hoje?
10. Aceitaria ser piloto-pioneer com desconto + acesso direto Wagner?

### Fase 2 — Proposta diferenciada

- **Pricing pioneer**: R$ 599/m (vs R$ 830 atual = economia R$ 2.772/ano) por 12 meses, sem setup, sem fidelidade
- **Compromisso explícito DAM-style**: 12 essenciais auto entregues em 90d ou desconto 50% até entrega
- **Acesso direto Wagner via WhatsApp** — fundador é o suporte L1 (não call center)
- **Migração de dados OfficeImpresso → oimpresso incluso** — Wagner conhece o schema (Delphi WR Sistemas é dele), exportação Firebird → MySQL é trivial pra ele (auto-mem `reference_bancos_firebird_wr2`)
- **Garantia de retorno**: 90 dias com devolução 100% se não rolar

### Fase 3 — Cláusula contratual pioneer

- Contrato 12m com cláusula DAM-style pros 12 essenciais auto
- Métrica de sucesso explícita: NPS ≥40 em 90d
- Caso churn: relatório de aprendizados público (anonimizado) → ADR amendment
- Cláusula "não falar mal em grupo": se reclamação, Wagner resolve em 48h direto OU contrato libera o cliente sem multa + compensação financeira (proteção reputacional setor)

---

## Cold WhatsApp pra Martinho (≤120 palavras, tom Wagner-direto, vínculo histórico)

**Versão A — vínculo histórico (recomendada se Wagner confirmar relação)**

> Fala-aí, [Seu Dono], beleza?
>
> Wagner aqui, da WR Sistemas. Soube que você tá pensando em trocar o OfficeImpresso. Antes de qualquer coisa: 26 anos juntos, qualquer caminho que você seguir, fica tranquilo, **eu mesmo te ajudo na migração dos dados pro próximo sistema** — seja o meu novo (oimpresso) ou de qualquer concorrente.
>
> Mas queria te pedir 20 minutos antes de fechar com outro: tô construindo o **oimpresso**, sucessor moderno do OfficeImpresso, com IA + app mobile + nuvem. Se servir pra ti, faço pioneer R$ 599/m (economiza R$ 230/mês vs hoje) com compromisso escrito de entregar tudo em 90d.
>
> **Posso passar aí semana que vem ou tu prefere uma call rápida?**
>
> Wagner — oimpresso

**Versão B — frio (se Wagner NÃO tem relação direta)**

> [Seu Dono], boa! Wagner aqui, oimpresso. Soube por terceiros que vocês tão olhando alternativa de sistema — e que tão num atual há 26 anos. Respeito demais essa fidelidade.
>
> Sem empurrar nada: tô construindo um ERP pra oficina mecânica, mais leve que os de mercado, com IA pra atendimento e app mobile pros mecânicos. **Quer 15min pra eu te mostrar o que tá pronto e o que ainda falta — e tu decide se faz sentido ou não?**
>
> Sem custo, sem amarração. Wagner — oimpresso

---

## Riscos

### R1 — Não fechar se Martinho já está em negociação avançada
- 26 anos de fidelidade + tá saindo = vendedor de Mecânico/Auto Manager/OficinaMaster já farejou e tá em cima
- **Mitigação:** velocidade. Wagner contata em ≤48h da conversa que originou o sinal.

### R2 — Capacidade de entregar 12 essenciais em 90d
- Felipe + Wagner pareados, IA-pair fator 10x = teoricamente factível (~38h dev). Mas 90d corridos exige zero distração de outros clientes.
- **Mitigação:** se fechar, Wagner congela parcialmente roadmap ROTA LIVRE por 60d. Trade-off real — discutir antes de assinar.

### R3 — Reputacional se atrasar
- Martinho fala mal no setor — grupos WhatsApp regionais amplificam.
- **Mitigação:** cláusula contratual proteção reputacional (Fase 3). Wagner pessoalmente envolvido toda semana primeiro 60d.

### R4 — Conflito emocional se Wagner é o trigger da saída
- Se Martinho saiu **por queixa do Wagner** (suporte deteriorou nos últimos 2-3 anos), reabordar pode reabrir ferida.
- **Mitigação:** Wagner abre com mea culpa explícito antes de pitch. Se queixa for grave, talvez não seja o momento — indicar concorrente honestamente preserva relação 26 anos.

### R5 — Pioneer pain real
- Sem outro cliente auto = Martinho é cobaia. Bug aparece, falta feature, frustração acumula.
- **Mitigação:** desconto vitalício 50% como recompensa pioneer + compromisso "1 melhoria/semana baseada no feedback" + canal direto Wagner WhatsApp 24/7 primeiro 60d.

---

## Cuidado: este caso valida ou invalida vertical auto?

**1 cliente ≠ vertical inteira ([ADR 0105](../../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))**:
- Se Martinho fechar = 1 sinal qualificado, ainda exploratório
- Se 3+ outras oficinas seguirem em 90d via indicação Martinho = vertical real, abre roadmap auto formalmente
- Se Martinho não fechar (escolher Mecânico/Auto Manager) = sinal forte que vertical auto **não é prioridade** vs com.visual/gráfica (ICP atual)

**Risco anti-pattern:** Wagner se anima com 1 sinal e desvia o roadmap inteiro. ADR 0105 existe pra prevenir isso. Se fechar, **manter ROTA LIVRE como cliente-âncora** (99% volume) e Martinho como **piloto experimental ringfenced** com Felipe dedicado parcialmente.

---

## Métricas pós-fechamento (se rolar)

- **D+30:** NPS, # tickets suporte, tempo médio resposta WhatsApp Wagner, # OS criadas
- **D+60:** quantos dos 12 essenciais entregues (meta: 6+)
- **D+90:** cliente recomendaria? topa virar caso público (nome real + foto)?
- **D+180:** indicações geradas (potencial canal — ver auto-mem `reference_concorrentes_com_visual` sobre boca-a-boca regional)
- **D+365:** churn ou renovação? se renovar com upgrade de plano = vertical validada

---

## Próximo passo

1. **Wagner consulta base OfficeImpresso** e confirma identidade Martinho (cidade, dono, histórico)
2. **Wagner decide tom**: vínculo histórico (preferido) vs frio
3. **Wagner aprova cold WhatsApp** (publication-policy: outbound externo escala pra Wagner)
4. Wagner envia versão escolhida em ≤48h da conversa que originou o sinal (urgência: concorrente já pode estar negociando)
5. Se Martinho responder positivo → agendar call/visita 25min Discovery
6. Após Discovery → Wagner decide se vale piloto pioneer ou indicar concorrente honestamente

---

**Sources pesquisa pública:**
- [Solutudo — Oficinas Martinho Campos/MG](https://www.solutudo.com.br/empresas/mg/martinho-campos/oficinas+mecanicas+para+carros)
- [Auto Mecânica Avenida LTDA — São Martinho/RS (CNPJ 11.112.047/0001-63)](https://cnpj.biz/11112047000163)
- [Martinelli Auto Mecânica — São Bernardo do Campo/SP](https://www.facebook.com/martinelliautomecanica/)
- [Infoisinfo SP — centros automotivos](https://sao-paulo.infoisinfo-br.com/busca/centro-automotivo)

> **Disclaimer pesquisa:** Google/Solutudo/Infoisinfo não retornaram um "Martinho Mecânica" claramente identificável no Brasil. Hipótese mais forte: oficina local sem presença web, cadastrada na base OfficeImpresso de Wagner. **Confirmação interna pré-abordagem é mandatória.**
