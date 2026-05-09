# Cold email personalizado — ACM Visual

**Empresa:** ACM Visual
**Site/IG:** [acmvisual.com.br](https://acmvisual.com.br/)
**Cidade:** Campinas — Jd. do Trevo
**Sinais públicos usados:**
- Desde 1983 — 43 anos de mercado (uma das mais antigas Tier 1)
- Clientes citados publicamente: **Unicamp, Petrobras, IBM, GE, Banco do Brasil** — enterprise grade
- Atende nacional + internacional
- Porte estimado 30+ funcionários (ceiling do ICP — abordar como case escalado)
- Vertical: identificação visual + fachadas + envelopamento + adesivos decorativos + sinalização de segurança + totens

**Concorrente provável atual:** Calcgraf (40 anos, multi-deploy, perfil mid-large industrial) é o match natural. Possível ERP enterprise (TOTVS Protheus customizado) dado o cliente Petrobras/BB.

**Ângulo escolhido:** Jana IA + multi-tenant Tier 0 — porte enterprise vai apreciar governança formal e auditabilidade. NFe automática é commodity nesse porte; diferencial real é IA + arquitetura.

---

**Assunto:** ACM Visual + Petrobras/IBM/BB: governança que aguenta auditoria

**Corpo:**

ACM Visual tem 43 anos e clientes que exigem auditoria de fornecedor — Petrobras, IBM, GE, BB. Operação séria, processo formal.

Pergunta: hoje, quando a auditoria de um cliente grande pede "me mostra a trilha de como esse pedido foi precificado, produzido, faturado", o sistema entrega rastreabilidade ponta-a-ponta? Ou o financeiro monta uma planilha pra cada auditoria?

oimpresso foi construído com governança formal (Constituição v2, ADRs públicas, multi-tenant Tier 0 — isolamento por business como princípio duro). Toda decisão arquitetural está documentada e versionada. E a Jana IA (com memória persistente + SQL auditável) responde no chat "qual cliente A faturou mais esse ano" sem abrir relatório.

Não estou propondo trocar tudo. Tô propondo 30min pra você ver a tela e julgar se faz sentido pro porte da ACM. Manda **um cenário típico de auditoria de cliente grande** e te respondo como oimpresso entregaria a trilha.

Abraço,
Wagner

PS: stack moderna (Laravel 13.6 + Inertia v3 + React 19) — não é desktop legado em migração. Posso passar GitHub das ADRs públicas.

---

## Versão alternativa (B test)

**Assunto:** ACM Visual: 43 anos + cliente Petrobras = quanto custa cada relatório?

**Corpo:**

Cliente enterprise gera relatório custoso: trimestral, semestral, ad-hoc por compliance. Cada um é horas do financeiro/comercial montando Excel.

Pergunta: na ACM hoje, quem responde "qual o faturamento por contrato com Petrobras nos últimos 12 meses" — e quanto tempo leva?

oimpresso tem Jana IA: você pergunta no chat, ela busca os dados na hora, devolve com link auditável pras NFes. É o WhatsApp do diretor financeiro falando com o próprio ERP.

Me manda **uma pergunta típica de relatório recorrente** e te devolvo print da Jana respondendo (dados de demo, não os seus). Em 24h.

Abraço,
Wagner

---

## Notas pra Wagner antes de mandar

- **Melhor destinatário:** porte 30+ → tem comercial/diretor formal. Site + LinkedIn da empresa devem identificar diretoria. Site é sofisticado — empresa investe em apresentação.
- **Risco/cuidado:** ACM Visual é o **ceiling do ICP** — porte maior pode estar acima do oimpresso atual (7 clientes ativos, 1 dominante). Não prometer "vamos atender Petrobras". Tom: "30min pra julgar se faz sentido". Honestidade > promessa.
- **Próximo passo se responder:** vai ser reunião formal — Wagner + Felipe juntos. Levar GitHub das ADRs públicas (auditabilidade vende em enterprise).
- **Cuidado:** se mencionar Calcgraf, **não atacar diretamente** — Calcgraf é respeitado nesse porte. Diferenciar por stack moderna + IA, não por "Calcgraf é ruim".
- **Capabilities gap:** oimpresso pode não ter coleta de dados de máquina IoT (Zênite tem, ACM Visual pode esperar). Wagner avalia se cita ou evita.
