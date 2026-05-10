# Cold email v3 — ACM Visual

**Empresa**: ACM Visual
**Site/IG**: [acmvisual.com.br](https://acmvisual.com.br/)
**Cidade**: Campinas — Jd. do Trevo
**Vertical**: comunicação visual (CNAE 1813-0/01) → Modules/ComunicacaoVisual
**Sinais públicos usados**:
- Desde 1983 (43 anos) — uma das mais antigas Tier 1
- Clientes: Unicamp, Petrobras, IBM, GE, BB — enterprise grade
- Atende nacional + internacional, porte 30+ funcionários
- Vertical: identificação visual + fachadas + envelopamento + adesivos + sinalização segurança + totens

**Concorrente atual provável**: Calcgraf (mid-large) ou TOTVS Protheus customizado
**Ângulo escolhido v3**: Governança + Jana (núcleo provado) + co-criar enterprise no módulo

---

**Assunto**: ACM Visual + Petrobras/IBM/BB: governança que aguenta auditoria

**Corpo**:

ACM Visual tem 43 anos e clientes que exigem auditoria de fornecedor — Petrobras, IBM, GE, BB. Processo formal.

Pergunta: quando a auditoria de cliente grande pede "trilha de como esse pedido foi precificado, produzido, faturado", o sistema entrega? Ou o financeiro monta planilha pra cada uma?

oimpresso foi construído com governança formal (Constituição v2, ADRs públicas, multi-tenant Tier 0 — isolamento por business como princípio duro). **Núcleo rodando 2+ anos em prod** (cliente outro vertical, 99% volume) — Jana IA com SQL auditável, NFe-de-boleto-pago, rastreabilidade ponta-a-ponta. Fundamentos testados.

Agora estou abrindo o **Modules/ComunicacaoVisual** com features específicas do setor (cálculo m², PCP por máquina, OS multi-etapa) e quero **3 pilotos**. ACM Visual seria o pioneer enterprise: porte e exigência forçam o módulo a ficar sério.

Pioneer: **50% off 12m + acesso direto a mim + voto na priorização**. ACM ajuda a desenhar enterprise-grade.

Vale 30min pra você ver o núcleo rodando e julgar?

Abraço,
Wagner

PS: stack Laravel 13.6 + Inertia v3 + React 19. ADRs públicas no GitHub — auditável.

---

## Versão alternativa (B test)

**Assunto**: ACM 43 anos + Petrobras = quanto custa cada relatório?

**Corpo**:

Cliente enterprise gera relatório custoso: trimestral, semestral, ad-hoc. Cada um é horas do financeiro montando Excel.

Pergunta: na ACM hoje, quem responde "faturamento por contrato Petrobras 12 meses" — quanto tempo leva?

oimpresso tem Jana IA no núcleo: pergunta no chat, busca os dados na hora, devolve com link auditável. **Núcleo rodando 2+ anos em prod** (cliente outro vertical, 99% volume).

Estou abrindo Modules/ComunicacaoVisual com 3 pilotos pioneer. ACM Visual seria o piloto enterprise. 50% off 12m + meu contato direto + voto em prio.

30min pra avaliar?

Abraço,
Wagner

---

## Notas pra Wagner antes de mandar

- **Risco**: ACM Visual é o **ceiling do ICP**. Pode estar acima do oimpresso atual. Tom honesto: "30min pra julgar se faz sentido". Não prometer "atender Petrobras agora".
- **Próximo passo**: reunião formal — Wagner + Felipe juntos. Levar GitHub das ADRs públicas (auditabilidade vende em enterprise).
- **Cuidado**: se mencionar Calcgraf, **não atacar** — Calcgraf é respeitado nesse porte. Diferenciar por stack moderna + IA + governança.
- **Capabilities gap**: não tem coleta IoT de máquina (Zênite tem). Wagner avalia se cita.
