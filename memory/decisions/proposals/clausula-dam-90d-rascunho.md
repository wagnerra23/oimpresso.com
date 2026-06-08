# Cláusula contratual DAM em 90d — rascunho commercial

> ⚠️ **AVISO LEGAL CRÍTICO**: Este documento **NÃO É CONSELHO JURÍDICO**. É rascunho commercial preparado por Claude (assistente IA) pra Wagner usar como ponto de partida em conversa com **advogado real** (preferencialmente OAB/SP especializado em SaaS/contratos de TI). Nenhuma das versões abaixo deve ser incluída em contrato com cliente sem revisão jurídica formal. A oimpresso assume risco zero por uso direto deste rascunho.

**Data:** 2026-05-09
**Autor draft:** Claude (sessão 2026-05-09)
**Refs:** [ADR proposta DAM waiting-list](dam-roi-mubisys-decision.md), [pricing tiers](../../sales/2026-05/06-pricing-tiers.md), [ADR 0094 Constituição v2](../0094-constituicao-v2-7-camadas-8-principios.md)

---

## Contexto

A oimpresso adotou a estratégia **DAM (Digital Asset Management) nativo pós-3-contratos-Enterprise-pagos** (cenário D da decisão DAM-ROI-Mubisys, alinhado a ADR 0105 — cliente como sinal qualificado). Pra contornar o gap competitivo vs MubiDrive (Mubisys) durante a janela waiting-list, a oimpresso oferece **cláusula contratual SLA com penalidade financeira automática**: o cliente Enterprise contrata sabendo que o módulo DAM será entregue em até 90 dias corridos a partir da ativação; em caso de atraso, ativa-se desconto progressivo na mensalidade. Esta cláusula vira **selling point publicável** (governança formal materializada — diferencial Constituição v2), além de garantir que o build só dispara com sinal de receita comprometida.

---

## Versão A — Conservadora (oimpresso protegida, cliente menor incentivo)

> ⚠️ NÃO é conselho jurídico. Validar com advogado.

**Cláusula X — Entrega do Módulo DAM (Digital Asset Management)**

X.1 A CONTRATADA compromete-se a disponibilizar à CONTRATANTE o módulo DAM — Digital Asset Management, em sua versão MVP funcional, no prazo de **90 (noventa) dias corridos** contados da data de **ativação do plano Enterprise** (entendida como o primeiro acesso autenticado da CONTRATANTE ao ambiente de produção).

X.2 Entende-se por "MVP funcional" o conjunto mínimo de funcionalidades descrito no **ANEXO I — Especificação Técnica DAM MVP**, contemplando: (i) upload de arquivos; (ii) listagem e busca por nome/tag; (iii) retenção mínima de 12 (doze) meses; (iv) controle de acesso por usuário vinculado ao plano da CONTRATANTE.

X.3 Em caso de atraso na entrega imputável **exclusivamente** à CONTRATADA, será aplicado desconto de **30% (trinta por cento) sobre a mensalidade a partir do 4º (quarto) mês**, mantido até a efetiva disponibilização do módulo.

X.4 O desconto previsto na cláusula X.3 fica limitado a **6 (seis) mensalidades**. Persistindo o atraso após este período, a CONTRATANTE poderá optar pela rescisão contratual sem multa rescisória, mediante notificação por escrito com 30 (trinta) dias de antecedência.

X.5 **Eventos exonerantes**: não se aplicará a penalidade da cláusula X.3 nos casos de (i) caso fortuito ou força maior nos termos do art. 393 do Código Civil; (ii) atraso causado por demora da CONTRATANTE em fornecer informações, acessos ou dependências técnicas necessárias; (iii) alterações regulatórias supervenientes que exijam reanálise do escopo; (iv) indisponibilidade prolongada (>72h) de provedores de infraestrutura terceiros (AWS, Cloudflare, Hostinger e similares).

X.6 O reajuste anual previsto na cláusula [Y — Reajuste] permanece aplicável de forma integral, independentemente do andamento da entrega do módulo DAM.

X.7 As partes reconhecem que a presente cláusula configura **cláusula penal compensatória** nos termos dos arts. 408 a 416 do Código Civil, sendo esta a única e exclusiva indenização devida pela CONTRATADA em razão do atraso, salvo dolo ou culpa grave comprovada.

---

## Versão B — Equilibrada (recomendada pra começar)

> ⚠️ NÃO é conselho jurídico. Validar com advogado.

**Cláusula X — Entrega do Módulo DAM (Digital Asset Management) com SLA**

X.1 A CONTRATADA compromete-se a disponibilizar à CONTRATANTE o módulo DAM — Digital Asset Management — em sua versão MVP funcional, conforme **ANEXO I — Especificação Técnica DAM MVP**, no prazo máximo de **90 (noventa) dias corridos** contados da data de ativação do plano Enterprise.

X.2 O MVP DAM contempla: (i) upload via URL assinada para infraestrutura S3-compatível com armazenamento em **território brasileiro** (atendimento à LGPD — art. 33 da Lei 13.709/2018); (ii) listagem, filtragem e busca por nome/tag; (iii) preview básico (imagens e PDF); (iv) soft-delete com lixeira de 30 dias; (v) controle multi-tenant com isolamento por `business_id`; (vi) cota inicial de 50 GB com possibilidade de upgrade comercial.

X.3 Em caso de atraso superior a 7 (sete) dias além do prazo previsto na cláusula X.1, será aplicado desconto de **30% (trinta por cento)** sobre as mensalidades **retroativamente desde o 1º (primeiro) mês contratado**, sob a forma de crédito em fatura, mantido até a efetiva entrega do módulo.

X.4 O desconto previsto na cláusula X.3 fica limitado a **12 (doze) mensalidades** acumuladas. Caso o atraso exceda **120 (cento e vinte) dias**, a CONTRATANTE poderá rescindir o contrato sem incorrer em multa rescisória, sendo restituídos eventuais valores adiantados a título de setup proporcionalmente ao tempo não usufruído.

X.5 Durante o período de atraso, fica **suspenso o reajuste anual** previsto na cláusula [Y — Reajuste], retomando-se a contagem de 12 meses a partir da data efetiva de entrega do módulo.

X.6 **Eventos exonerantes** (afastam a aplicação do desconto): (i) caso fortuito ou força maior (art. 393 CC); (ii) atraso causado **comprovadamente** pela CONTRATANTE no fornecimento de dados, acessos ou validações requeridas — devendo a CONTRATADA notificar formalmente a pendência por escrito com 5 dias úteis de antecedência; (iii) indisponibilidade prolongada de provedores de infraestrutura terceiros (>72h consecutivas), comprovada por evidências públicas.

X.7 As partes reconhecem que a presente cláusula configura **cláusula penal compensatória** (arts. 408 a 416 CC), sendo a forma exclusiva de indenização pelo atraso, vedada cumulação com perdas e danos, salvo dolo comprovado.

X.8 A CONTRATADA enviará à CONTRATANTE relatório de progresso **mensal** durante o período dos 90 dias, contendo status das entregas, eventuais riscos identificados e replanejamento, se houver.

---

## Versão C — Agressiva (cliente protegido, incentiva oimpresso entregar)

> ⚠️ NÃO é conselho jurídico. Validar com advogado.

**Cláusula X — Entrega do Módulo DAM (Digital Asset Management) com SLA reforçado**

X.1 A CONTRATADA compromete-se a disponibilizar à CONTRATANTE o módulo DAM — Digital Asset Management — conforme **ANEXO I**, no prazo de **90 (noventa) dias corridos** contados da ativação do plano Enterprise.

X.2 O MVP contempla as funcionalidades descritas no ANEXO I, incluindo armazenamento em S3-compatível em território brasileiro, isolamento multi-tenant, política de retenção e backup conforme **ANEXO IV — Política de Retenção e Backup**.

X.3 Em caso de atraso na entrega:
(a) Será aplicado desconto de **50% (cinquenta por cento)** sobre todas as mensalidades **retroativamente** desde o 1º mês contratado, sob forma de crédito em fatura;
(b) A cada **30 (trinta) dias adicionais** de atraso, a CONTRATANTE faz jus a **1 (uma) mensalidade adicional gratuita** após a entrega, a título de bonificação;
(c) Fica suspenso integralmente o reajuste anual durante e até 12 meses após a entrega.

X.4 A CONTRATANTE terá direito a **auditoria quinzenal** do progresso de desenvolvimento, mediante relatório técnico enviado pela CONTRATADA contendo: percentual concluído, tarefas em andamento, riscos identificados e replanejamento. A CONTRATANTE poderá indicar 1 (um) representante técnico para reunião de revisão a cada 30 dias.

X.5 Caso o atraso ultrapasse **60 (sessenta) dias** além do prazo da cláusula X.1, a CONTRATANTE poderá rescindir o contrato sem qualquer multa, com restituição integral de valores adiantados não usufruídos, **incluindo a taxa de setup**.

X.6 Caso o atraso ultrapasse **150 (cento e cinquenta) dias**, a CONTRATADA reembolsará à CONTRATANTE **integralmente o valor de R$ [redacted Tier 0] (cinco mil reais)** referente à taxa de setup, independentemente da rescisão.

X.7 **Eventos exonerantes**: somente caso fortuito ou força maior (art. 393 CC) **comprovados documentalmente**. Atrasos por dependência da CONTRATANTE devem ser notificados em até 48 horas após identificação, com prazo de 5 dias úteis pra correção, sob pena de não suspensão do prazo.

X.8 As partes reconhecem cláusula penal compensatória (arts. 408 a 416 CC), sem prejuízo do direito da CONTRATANTE pleitear perdas e danos suplementares mediante prova de prejuízo concreto (art. 416, parágrafo único, CC — exige previsão expressa, **a presente cláusula expressamente prevê esta possibilidade**).

X.9 A presente cláusula constitui **obrigação essencial** do contrato; seu descumprimento autoriza resolução nos termos do art. 475 do Código Civil.

---

## Comparativo trade-off

| Aspecto | A conservadora | B equilibrada | C agressiva |
|---|---|---|---|
| Atratividade pro cliente Enterprise | baixa | média | alta |
| Risco financeiro pra oimpresso | baixo | médio | alto |
| Sinal de "selling point" no marketing | fraco | forte | muito forte |
| Probabilidade fechar 1º cliente Enterprise | ~20% | ~60% | ~80% |
| Risco churn se atrasar | baixo | médio | alto |
| Exposição máxima oimpresso (12m) | ~R$ [redacted Tier 0] (6m × 30% × R$ [redacted Tier 0]) | ~R$ [redacted Tier 0] (12m × 30%) | ~R$ [redacted Tier 0] + R$ [redacted Tier 0]k setup ≈ **R$ [redacted Tier 0]** |
| Pressão interna pra entregar no prazo | baixa | média | alta |
| Defensável em mediação/arbitragem | muito | razoável | exige documentação rigorosa |

---

## Recomendação

**Começar com Versão B (equilibrada).**

Justificativa:
1. Versão A é conservadora demais pra prospect Mubisys cético — cliente que migra de fornecedor com 23+ anos de mercado precisa enxergar **comprometimento real** da oimpresso, não promessa morna. Desconto retroativo (B) sinaliza "skin in the game" sem expor a oimpresso a risco existencial.
2. Versão C tem risco de exposição financeira **R$ [redacted Tier 0]/contrato** que, multiplicado por 3 contratos atrasados simultaneamente, ultrapassa R$ [redacted Tier 0]k — capaz de quebrar fluxo de caixa de uma empresa de 5 pessoas se a entrega derrapar (cenário plausível dado histórico de crashes documentados em `proibicoes.md`). Fica como **opção pra prospect estratégico específico** (ex: cliente farol que vira case público com autorização).
3. Versão B é **defensável em arbitragem ou mediação** sem exigir documentação rigorosa que C demanda. Linguagem comercial razoável + cláusula penal explícita (arts. 408-416 CC) + eventos exonerantes proporcionais.

Recomenda-se **oferecer C apenas pro 1º cliente farol** que aceite virar case público (vira marketing). Demais prospects: começar oferta em B; se prospect pedir mais agressivo, abrir C como concessão negociada.

---

## Riscos jurídicos a validar com advogado (top 7)

1. **Cláusula penal vs perdas e danos** (arts. 408-416 CC) — confirmar se o desconto de 30% configura cláusula penal compensatória válida. Especialmente Versão C (que prevê dano suplementar): art. 416, parágrafo único exige **previsão expressa** — confirmar redação está adequada.
2. **CDC aplicável?** Cliente PJ (gráfica) usa o ERP como **ferramenta de atividade-fim**. Embora STJ historicamente afaste CDC em B2B com finalidade lucrativa (Súmula 297 STJ tem nuances), há decisões recentes aplicando "consumidor relacional" ou inversão do ônus. Validar exposição.
3. **LGPD (Lei 13.709/2018)** — DAM armazena artes do cliente final (que podem conter dados pessoais: campanhas com fotos, conteúdos personalizados). Definir explicitamente: oimpresso é **operadora** (art. 5º, VII LGPD); cliente Enterprise é **controlador**. Anexo de tratamento de dados (DPA) obrigatório. Localização do storage: S3 BR ou Cloudflare R2 com region BR — documentar.
4. **Marco Civil da Internet (Lei 12.965/2014)** — guarda de registros de acesso (art. 15) por 6m mínimo; comunicações em log.
5. **Cláusula de auditoria (Versão C, X.4)** — direito do cliente de auditar progresso é renunciável? Como limitar pra evitar abuso (cliente exigir reuniões diárias). Versão atual restringe a 1 reunião/30d — validar suficiência.
6. **Foro de eleição** — sugerir comarca de **Joinville/SC ou São Paulo/SP**? Câmara de arbitragem (CAM-CCBC, CIESP-FIESP) pra contratos > R$ [redacted Tier 0]k? Custo arbitragem >> contrato ainda — provavelmente foro comum melhor pra ticket Enterprise inicial.
7. **Reajuste anual** — IPCA (mais comum SaaS BR), IGP-M (descontinuado pra muitos contratos pós-2021), INCC (não aplicável). Recomendar **IPCA acumulado 12m** como índice padrão.

---

## Anexos sugeridos ao contrato

- **ANEXO I — Especificação Técnica DAM MVP** (3 páginas): escopo mínimo viável, o que ESTÁ e o que NÃO ESTÁ no MVP (gestão clara de expectativa). Baseado em escopo MVP da decisão DAM-ROI.
- **ANEXO II — SLA pós-entrega**: uptime ≥99,5% mensal; RTO 4h; RPO 24h; canal de suporte e SLA de resposta por severidade.
- **ANEXO III — Termo de Confidencialidade Mútuo**: arte do cliente é confidencial; código-fonte e métricas de uso da oimpresso também. Vigência 5 anos pós-término.
- **ANEXO IV — Política de Retenção e Backup**: backup diário incremental; retenção 30d soft-delete + 90d backup adicional; procedimento de restore.
- **ANEXO V — DPA (Data Processing Agreement)**: papéis LGPD (operadora/controlador), localização dado, sub-operadores, transferência internacional (se houver), prazo, eliminação pós-término.

---

## Checklist pré-assinatura (Wagner usa antes de mandar pra cliente)

- [ ] **Advogado validou** cláusula penal (arts. 408-416 CC) na versão escolhida
- [ ] **Advogado opinou** sobre risco CDC aplicável B2B atividade-fim
- [ ] **LGPD**: DPO definido (Wagner ou terceirizado), registro de operações de tratamento, DPA (Anexo V) revisado
- [ ] **Política de privacidade** pública e atualizada em `oimpresso.com/privacidade`
- [ ] **Cliente assinou ciência** do que é "MVP DAM" — ANEXO I anexado e rubricado pelo cliente folha a folha
- [ ] **Capacidade técnica**: Wagner confirmou com [F] Felipe que MVP é entregável em 16h wallclock IA-pair (cenário D do ROI)
- [ ] **Eliana[E]** (financeiro) revisou cláusula de desconto/reembolso e impacto fluxo de caixa
- [ ] **Limite de exposição agregado**: contar quantos contratos com cláusula DAM podem estar atrasados simultaneamente sem quebrar caixa (recomendado: máx 3 simultâneos com cláusula B; 1 simultâneo com cláusula C)
- [ ] **Foro/arbitragem** definido junto ao advogado
- [ ] **Reajuste anual** índice escolhido (sugestão: IPCA)
- [ ] **Storage BR confirmado** (Wasabi BR, Cloudflare R2 com region BR ou similar) — sem dado em US-only
- [ ] **Plano de mitigação documentado** caso entrega atrase: comunicação proativa cliente em D-30 do prazo, replanejamento, notificação formal de gatilho do desconto antes da fatura

---

**Última atualização:** 2026-05-09 — rascunho commercial inicial. Aguarda revisão de advogado real antes de qualquer uso em proposta comercial.
