# Cláusula Opt-in + DPA + Termo de Uso — oimpresso Insights — 2026-05-09

> ⚠️ **DRAFT — advogado externo BR LGPD especialista DEVE validar antes de assinar com cliente.**
>
> Estes documentos são minutas técnico-jurídicas preparadas pelo time interno (in-house drafter). NÃO substituem parecer de advogado registrado na OAB com especialização em LGPD/Privacidade e Direito Digital.
>
> **Budget previsto Wagner:** R$ [redacted Tier 0]–15k counsel one-time (validação + ajustes regionais) + R$ [redacted Tier 0]–5k/mês DPO (interno ou retainer externo).

---

## Contexto do produto

**oimpresso Insights** é um produto vertical para clientes PJ do setor de comunicação visual (gráficas, plotters, fachadas, brindes) que processa dados financeiros operacionais (vendas, despesas, recebíveis, fluxo de caixa) com três finalidades distintas:

1. **Análise individual** — relatórios e dashboards do próprio negócio do cliente.
2. **Benchmark setorial anônimo** — agregação cross-cliente com k-anonimato ≥ 5, devolvida ao próprio cliente como referência setorial.
3. **API DaaS externo (opcional)** — disponibilização de dado anonimizado para terceiros (fintechs, seguradoras, indústria fornecedora) mediante contrapartida financeira ao cliente.

**Premissa LGPD:** mesmo sendo cliente PJ, dados operacionais frequentemente contêm CPF de sócios, funcionários, fornecedores PF e clientes finais PF. Logo, LGPD aplicável (Lei nº 13.709/2018).

---

## Doc 1 — Cláusula Opt-in (anexo ao Contrato de Assinatura oimpresso)

### ANEXO III — TRATAMENTO DE DADOS PARA O PRODUTO "oimpresso Insights"

**ENTRE:**
- **CONTRATANTE:** [Razão Social do Cliente], CNPJ [•], adiante denominado "**CLIENTE**" ou "**CONTROLADOR**".
- **CONTRATADA:** WR Sistemas Ltda. (oimpresso.com), CNPJ [•], adiante denominada "**OIMPRESSO**" ou "**OPERADORA**".

#### 1. Objeto

Este anexo regulamenta o tratamento de dados pessoais e dados de negócio do CLIENTE pela OIMPRESSO no escopo do produto **oimpresso Insights**, em estrita observância à Lei nº 13.709/2018 (Lei Geral de Proteção de Dados Pessoais — "**LGPD**").

#### 2. Definições

- **Dado pessoal:** informação relacionada a pessoa natural identificada ou identificável (LGPD, art. 5º, I).
- **Dado de negócio:** transações comerciais, financeiras e operacionais do CLIENTE registradas no oimpresso (vendas, despesas, recebíveis, fluxo de caixa, base de fornecedores e clientes finais).
- **Dado anonimizado:** dado tratado de modo que perde a possibilidade de associação com um indivíduo, observados meios técnicos razoáveis (LGPD, art. 5º, III e art. 12).
- **k-anonimato ≥ 5:** padrão técnico de anonimização em que cada registro agregado é indistinguível de pelo menos outros 4 (cinco no total) com mesmas características, impedindo singularização.

#### 3. Finalidades e bases legais — escolha do CLIENTE

O CLIENTE **DEVE** escolher granularmente, abaixo, quais finalidades autoriza. A primeira opção é **obrigatória** para uso do produto; as demais são facultativas e podem ser revogadas a qualquer tempo.

**3.1. ☑ OBRIGATÓRIA — Análise individual do meu negócio**

A OIMPRESSO processará dados do CLIENTE para gerar relatórios, dashboards, projeções e alertas restritos ao próprio negócio do CLIENTE, acessíveis apenas pelo CLIENTE e seus usuários autorizados.

- **Base legal:** LGPD, art. 7º, V — execução de contrato do qual o titular é parte.
- **Compartilhamento:** nenhum, exceto sub-operadores listados no DPA (Anexo IV).

**3.2. ☐ FACULTATIVA — Benchmark setorial anônimo**

A OIMPRESSO poderá agregar dados do CLIENTE, **após anonimização irreversível com k-anonimato ≥ 5**, juntamente com outros clientes da mesma vertical de mercado, para gerar referências setoriais (ex.: ticket médio do setor, margem média por região, sazonalidade). O CLIENTE recebe o benchmark agregado como benefício; nenhum cliente individual é identificável.

- **Base legal:** LGPD, art. 7º, IX — legítimo interesse do controlador, somado a manifestação afirmativa do CLIENTE neste opt-in.
- **Garantias técnicas:** k-anonimato ≥ 5 obrigatório; supressão de quasi-identificadores (CNPJ, razão social, geolocalização fina); validação automatizada antes da publicação de cada agregação.
- **Compartilhamento:** apenas com outros clientes que também optaram in (cross-share simétrico).

**3.3. ☐ FACULTATIVA — API DaaS externo (compartilhamento monetizável com terceiros)**

A OIMPRESSO poderá disponibilizar dados anonimizados (k-anonimato ≥ 5) a terceiros contratantes (instituições financeiras, seguradoras, indústria fornecedora, pesquisa de mercado) mediante contrato específico com tais terceiros, e repassará ao CLIENTE participação financeira na receita gerada, conforme tabela vigente em [URL].

- **Base legal:** LGPD, art. 7º, IX — legítimo interesse, somado a manifestação afirmativa do CLIENTE neste opt-in.
- **Garantias técnicas:** k-anonimato ≥ 5 + auditoria trimestral dos terceiros + obrigação contratual de não-reidentificação imposta a cada terceiro.
- **Lista de terceiros vigente:** publicada em oimpresso.com/lgpd/parceiros, atualizada com 30 dias de antecedência via e-mail ao CLIENTE.

#### 4. Direitos do titular e do CLIENTE

A qualquer tempo, e sem custos, o CLIENTE pode (em nome próprio e dos titulares cujos dados controla, conforme LGPD, art. 18):

- **Confirmar** a existência de tratamento;
- **Acessar** os dados tratados;
- **Corrigir** dados incompletos, inexatos ou desatualizados;
- **Anonimizar, bloquear ou eliminar** dados desnecessários, excessivos ou tratados em desconformidade;
- **Portar** os dados a outro fornecedor de serviço, em formato estruturado;
- **Eliminar** os dados pessoais tratados com consentimento, ressalvadas as hipóteses do art. 16 da LGPD (cumprimento de obrigação legal, estudo por órgão de pesquisa, transferência a terceiro mediante requisitos legais, uso exclusivo do controlador anonimizado);
- **Obter informação** sobre entidades públicas e privadas com as quais a OIMPRESSO compartilhou dados;
- **Revogar consentimento** (itens 3.2 e 3.3) a qualquer tempo, sem penalidade contratual e sem prejuízo da continuidade do produto na finalidade 3.1.

**Canal de exercício de direitos:** lgpd@oimpresso.com.br ou formulário em oimpresso.com/lgpd. Resposta em até **15 (quinze) dias** corridos (LGPD, art. 19).

#### 5. Retenção

- **Durante o contrato:** dados mantidos para execução do produto.
- **Após encerramento:** retenção de **5 (cinco) anos** a partir da data de encerramento do contrato, exclusivamente para cumprimento de obrigação legal/regulatória (fiscal, contábil, trabalhista) e exercício regular de direitos em processo judicial (art. 16, I e III, LGPD).
- **Pós-retenção:** eliminação ou anonimização irreversível, conforme política técnica documentada.

#### 6. Compartilhamento com terceiros

- **Sub-operadores técnicos** (cloud, processamento de pagamento, e-mail transacional): listados no DPA (Anexo IV), com obrigação contratual equivalente em LGPD.
- **Outros clientes oimpresso** (item 3.2): apenas dado anonimizado k≥5, somente em modo simétrico opt-in.
- **Terceiros DaaS** (item 3.3): apenas se o CLIENTE optar in explicitamente, e apenas dado anonimizado k≥5.
- **Autoridades públicas:** mediante ordem judicial, requisição da ANPD ou cumprimento de obrigação legal específica, com notificação ao CLIENTE quando legalmente permitida.

#### 7. Encarregado pelo Tratamento de Dados (DPO)

- **Nome / Função:** [a ser nomeado formalmente]
- **E-mail:** dpo@oimpresso.com.br
- **Telefone:** [•]
- **Endereço postal:** [•]

(LGPD, art. 41 — divulgação pública obrigatória do canal de comunicação.)

#### 8. Vigência da cláusula

Esta cláusula entra em vigor na assinatura e permanece vinculada ao contrato principal de assinatura oimpresso. As escolhas dos itens 3.2 e 3.3 podem ser alteradas a qualquer tempo pelo CLIENTE em oimpresso.com/lgpd ou pelo canal do DPO.

**[Local], [Data].**

____________________________________
**[CLIENTE]**

Opt-in 3.1: [obrigatório, marcado por força do contrato]
Opt-in 3.2: ☐ ACEITO ☐ NÃO ACEITO
Opt-in 3.3: ☐ ACEITO ☐ NÃO ACEITO

____________________________________
**WR SISTEMAS LTDA. (oimpresso.com)**

---

## Doc 2 — DPA (Data Processing Agreement) — Anexo IV

### ACORDO DE TRATAMENTO DE DADOS (DPA)

**ENTRE** o CLIENTE (CONTROLADOR) e a OIMPRESSO (OPERADORA), conforme contrato principal e Anexo III.

#### 1. Papéis e responsabilidades

- **CONTROLADOR (CLIENTE):** define as finalidades e os meios essenciais do tratamento dos dados que carrega na plataforma oimpresso (cadastro de clientes finais, fornecedores, transações).
- **OPERADORA (OIMPRESSO):** trata os dados em nome e conforme instruções do CONTROLADOR (art. 5º, VII, LGPD), no escopo do contrato e dos opt-ins ativos.
- Para os benchmarks setoriais (item 3.2 do Anexo III) e o DaaS externo (item 3.3), a OIMPRESSO atua como **CONTROLADORA** dos dados anonimizados resultantes (já fora do escopo de "dado pessoal", mas mantendo as garantias éticas e contratuais aqui assumidas).

#### 2. Categorias de dados pessoais tratados

| Categoria | Origem | Finalidade |
|---|---|---|
| Dados cadastrais de PF (nome, CPF, e-mail, telefone, endereço) | Clientes finais e fornecedores cadastrados pelo CLIENTE | Execução de transações e relatórios |
| Dados de sócios/funcionários do CLIENTE | Cadastro de usuários do sistema | Autenticação, controle de acesso, log de auditoria |
| Dados transacionais (vendas, despesas, recebíveis) | Operação do CLIENTE | Análise individual (3.1); anonimizados para 3.2/3.3 |
| Dados de pagamento (boletos, PIX, cartão) | Integração Asaas | Processamento financeiro (sub-operador) |
| Dados fiscais (NF-e, CST, NCM) | Emissão fiscal | Conformidade tributária |

**Não tratamos:** dados sensíveis (LGPD art. 5º, II — origem racial, convicção religiosa, opinião política, saúde, vida sexual, biometria) salvo se o CLIENTE expressamente os carregar, hipótese em que o CLIENTE assume responsabilidade exclusiva pela base legal.

#### 3. Sub-operadores autorizados

A OPERADORA contrata os seguintes sub-operadores, todos com contrato com cláusulas equivalentes em LGPD:

| Sub-operador | Função | Localização do dado |
|---|---|---|
| Hostinger International Ltd. | Hospedagem app web | Brasil / EUA |
| Hetzner / AWS / equivalente | Hospedagem complementar (CT 100, backups) | UE / Brasil |
| Asaas Gestão Financeira S.A. | Processamento de pagamentos (boleto, PIX, cartão) | Brasil |
| BrasilAPI / Receitaws | Consulta CNPJ pública | Brasil |
| Meilisearch (self-hosted CT 100) | Indexação de busca interna | Infra própria oimpresso |
| Anthropic / OpenAI (via laravel/ai SDK) | Inferência de IA (Jana) — apenas com dado redatado por PiiRedactor | EUA |

**Adição/remoção de sub-operadores:** comunicada ao CLIENTE com **30 dias** de antecedência por e-mail e em oimpresso.com/lgpd/sub-operadores. CLIENTE pode objetar; em caso de objeção razoável não solucionável, CLIENTE pode rescindir sem multa proporcional.

#### 4. Medidas técnicas e organizacionais

A OPERADORA aplica e mantém:

- **Criptografia em repouso** (encryption-at-rest) em todos os bancos de dados de produção.
- **TLS 1.3** em todo o tráfego entre cliente e plataforma e entre componentes internos.
- **Controle de acesso baseado em função** (RBAC) com isolamento multi-tenant via `business_id` (Tier 0 — ADR 0093 da governança interna).
- **Log de auditoria imutável** (`mcp_audit_log`) cobrindo acesso a dados pessoais e operações privilegiadas.
- **Anonimização k≥5** validada automaticamente antes de qualquer publicação cross-cliente.
- **Redação de PII** (`PiiRedactor`) antes de qualquer envio a sub-operadores de IA externa.
- **Backup criptografado** com retenção rotativa.
- **Treinamento anual de equipe** em LGPD e segurança da informação.
- **Política de senhas e MFA** para acesso administrativo.
- **Política de gestão de vulnerabilidades** (patch crítico ≤ 30 dias).

#### 5. Notificação de incidente de segurança

Em caso de incidente que possa acarretar risco ou dano relevante aos titulares (LGPD, art. 48), a OPERADORA notificará o CLIENTE em **até 24 (vinte e quatro) horas** da detecção, contendo:

- Descrição da natureza do incidente;
- Categorias e quantidades aproximadas de dados e titulares afetados;
- Medidas técnicas adotadas para mitigação;
- Riscos relacionados ao incidente;
- Plano de comunicação à ANPD e aos titulares, se aplicável.

A comunicação à ANPD em prazo razoável é obrigação do CONTROLADOR; a OPERADORA fornecerá todas as informações necessárias.

#### 6. Auditoria pelo CLIENTE

O CLIENTE tem direito a auditar a conformidade da OPERADORA, **uma vez por ano**, mediante:

- Aviso prévio de 30 dias;
- Realização em horário comercial, sem prejuízo da operação;
- Auditoria por equipe própria do CLIENTE ou auditor independente sob NDA;
- Custos de mão de obra suportados pelo CLIENTE; documentação fornecida pela OPERADORA sem custo;
- Auditorias adicionais mediante incidente comprovado, sem limite de frequência.

A OPERADORA pode oferecer relatório SOC 2, ISO 27001 ou equivalente como substitutivo, se aceito pelo CLIENTE.

#### 7. Garantias da OPERADORA

A OPERADORA declara e garante que:

- Cumpre integralmente a LGPD e regulamentações da ANPD;
- Possui DPO formalmente designado;
- Mantém Registro das Operações de Tratamento (ROPA) atualizado;
- Realizou DPIA (Avaliação de Impacto à Proteção de Dados) para o produto Insights;
- Coopera plenamente com o CONTROLADOR e a ANPD em qualquer requerimento.

#### 8. Encerramento e devolução/eliminação

No encerramento do contrato, a OPERADORA:

- Disponibiliza, **dentro de 30 dias**, exportação completa dos dados do CLIENTE em formato estruturado (CSV/JSON), conforme direito de portabilidade (LGPD, art. 18, V);
- **Elimina ou anonimiza** todos os dados pessoais do CLIENTE em **até 90 dias** após o encerramento, ressalvada retenção do item 5 do Anexo III (5 anos para obrigação legal);
- Fornece **atestado formal de exclusão** assinado pelo DPO ao final do prazo de retenção;
- Os dados anonimizados resultantes de opt-ins 3.2/3.3 já em circulação permanecem em uso, por sua natureza desvinculada do titular (não são mais "dado pessoal" — LGPD, art. 5º, III).

#### 9. Transferência internacional

Caso ocorra transferência internacional (ex.: sub-operador EUA), a OPERADORA garante uma das hipóteses do art. 33 da LGPD (cláusulas-padrão, garantias específicas, certificação ANPD ou consentimento específico).

**[Local], [Data].**

____________________________________
**CLIENTE (CONTROLADOR)**

____________________________________
**OIMPRESSO (OPERADORA)**

DPO: ___________________ E-mail: dpo@oimpresso.com.br

---

## Doc 3 — Termo de Uso oimpresso Insights (público)

### TERMO DE USO — oimpresso Insights

**Última atualização:** [Data].
**Publicado em:** oimpresso.com/insights/termos.

#### 1. Definição do produto

**oimpresso Insights** é o conjunto de funcionalidades analíticas, de benchmark e de monetização de dados anonimizados oferecido pela WR Sistemas Ltda. ("OIMPRESSO") aos clientes contratantes ("USUÁRIO" ou "CLIENTE") da plataforma oimpresso.com, contemplando:

- (a) Relatórios, dashboards e projeções do próprio negócio do USUÁRIO;
- (b) Benchmarks setoriais anônimos (k-anonimato ≥ 5) entre clientes que tenham optado in;
- (c) API DaaS para terceiros, mediante opt-in adicional e contrapartida financeira ao USUÁRIO.

#### 2. Aceite e capacidade

O acesso ao Insights pressupõe (i) contrato de assinatura oimpresso vigente, (ii) Anexo III (cláusula opt-in LGPD) assinado e (iii) este Termo de Uso aceito eletronicamente. O USUÁRIO declara ter capacidade jurídica e poderes de representação da PJ contratante.

#### 3. Uso permitido

Ao USUÁRIO é permitido:

- Acessar, consultar e exportar relatórios do próprio negócio;
- Compartilhar relatórios com sócios, contadores e auditores próprios sob sua responsabilidade;
- Receber benchmarks setoriais anônimos, se opt-in 3.2 ativo;
- Receber participação financeira do programa DaaS, se opt-in 3.3 ativo, conforme tabela vigente.

#### 4. Uso vedado

É **expressamente vedado** ao USUÁRIO:

- Tentar reidentificar dados de outros clientes a partir de benchmarks ou outputs agregados;
- Extrair, copiar, raspar (scraping), realizar engenharia reversa, descompilar ou desmontar a plataforma, suas APIs, modelos analíticos ou bases de dados;
- Acessar áreas, dados ou funcionalidades de outros clientes (a tentativa configura ofensa ao art. 154-A do Código Penal — invasão de dispositivo informático);
- Revender, sublicenciar ou ceder o acesso à plataforma a terceiros, salvo expressa autorização escrita;
- Utilizar a plataforma para finalidade ilícita, contrária à moral, aos bons costumes ou que viole direitos de terceiros;
- Sobrecarregar, comprometer ou interferir no funcionamento da plataforma (DoS, brute-force, fuzzing não autorizado);
- Inserir dados sensíveis (LGPD art. 5º, II) sem base legal própria, ou dados de menores sem consentimento dos responsáveis.

#### 5. Propriedade intelectual

- **Dados do USUÁRIO** permanecem de titularidade do USUÁRIO. Concede-se à OIMPRESSO licença não-exclusiva, mundial, gratuita, pelo prazo do contrato, para tratá-los nas finalidades do Anexo III.
- **Plataforma, código, modelos, marcas, layouts, textos e bibliotecas** da OIMPRESSO permanecem de sua titularidade exclusiva. Nenhum direito de propriedade intelectual é transferido.
- **Dados anonimizados** (k≥5) resultantes de opt-ins 3.2/3.3, por já não constituírem dado pessoal e por força do anexo, podem ser usados pela OIMPRESSO conforme finalidades acordadas.

#### 6. Limitação de responsabilidade

- Os Insights são **ferramentas de apoio à gestão** e **não substituem** parecer contábil, jurídico, fiscal ou de auditoria.
- A OIMPRESSO emprega esforços técnicos razoáveis para manter exatidão, mas **não garante resultado financeiro** decorrente do uso dos Insights.
- A responsabilidade da OIMPRESSO, em qualquer hipótese e independentemente do fundamento, fica limitada ao valor pago pelo USUÁRIO nos **12 (doze) meses** anteriores ao evento, ressalvadas as hipóteses de dolo, culpa grave e violação à LGPD com dano comprovado, em que vigem os limites legais.
- Casos fortuitos, força maior, indisponibilidade de sub-operadores e ataques de terceiros excluem responsabilidade da OIMPRESSO, observada a tomada de medidas razoáveis de mitigação.

#### 7. SLA e disponibilidade

A OIMPRESSO empregará esforços razoáveis para manter disponibilidade de **99% mensal** do produto Insights. Janelas de manutenção programadas serão comunicadas com 48h de antecedência. Indisponibilidade superior gera crédito proporcional, conforme política em [URL].

#### 8. Encerramento por descumprimento

A OIMPRESSO pode suspender ou encerrar o acesso, mediante notificação prévia de 5 (cinco) dias úteis (ou imediatamente, em caso de violação grave ou risco à plataforma):

- Violação dos itens 3 ou 4 deste Termo;
- Inadimplência superior a 30 dias;
- Uso fraudulento, ilícito ou que prejudique terceiros;
- Decisão judicial ou ordem de autoridade competente.

Em qualquer hipótese, são assegurados ao USUÁRIO portabilidade dos dados (item 8 do DPA) e direitos LGPD.

#### 9. Alterações deste Termo

A OIMPRESSO pode atualizar este Termo, notificando o USUÁRIO por e-mail e em oimpresso.com/insights/termos com **30 dias** de antecedência. Continuação de uso após o prazo configura aceite. Discordância permite rescisão sem multa proporcional.

#### 10. Comunicações

Comunicações oficiais entre as partes serão feitas pelos e-mails cadastrados, considerando-se válidas e recebidas mediante confirmação de entrega ou após 5 dias da expedição.

#### 11. Foro

Fica eleito o **foro da comarca da sede da OIMPRESSO** para dirimir qualquer controvérsia decorrente deste Termo, com renúncia a qualquer outro, por mais privilegiado que seja, ressalvada a hipótese de o USUÁRIO ser equiparado a consumidor (art. 2º, CDC), caso em que prevalecerá o foro do domicílio do USUÁRIO.

#### 12. Disposições gerais

- A invalidade de uma cláusula não compromete as demais.
- Tolerância no exercício de direito não configura novação.
- Este Termo constitui, junto com o contrato principal, Anexo III e DPA, o acordo integral sobre o produto Insights, prevalecendo sobre quaisquer entendimentos prévios.

**oimpresso — WR Sistemas Ltda.**
CNPJ: [•]
Contato LGPD: lgpd@oimpresso.com.br
DPO: dpo@oimpresso.com.br

---

## Checklist pré-aprovação

- [ ] Advogado externo BR LGPD especialista validou as 3 minutas
- [ ] DPO formalizado (interno ou retainer) e divulgado em oimpresso.com/lgpd
- [ ] DPIA documentada (Data Protection Impact Assessment) — exigida para tratamento de risco (art. 38 LGPD; reforçado para opt-in 3.2/3.3)
- [ ] Política de privacidade pública atualizada e linkada do rodapé
- [ ] Audit log infrastructure (`mcp_audit_log` já existe — validar cobertura para acessos a dados pessoais e exports)
- [ ] Endpoint público de opt-out e exercício de direitos (`oimpresso.com/lgpd`)
- [ ] Procedure documentada de notificação de incidente em 24h (runbook + matriz de escalonamento)
- [ ] Sub-operadores listados, com contratos com cláusulas LGPD equivalentes (Asaas, Hostinger, BrasilAPI, Anthropic/OpenAI via PiiRedactor)
- [ ] Validador automatizado de k-anonimato ≥ 5 implementado e testado (Pest) antes do go-live de 3.2/3.3
- [ ] Mecanismo de opt-out granular auditável em UI (cliente pode desmarcar 3.2/3.3 e ver confirmação imediata + log)
- [ ] ROPA (Registro das Operações de Tratamento) atualizado contemplando Insights
- [ ] Treinamento LGPD do time interno antes do lançamento

## Riscos jurídicos a validar com counsel externo

- **Cláusula penal e indenização** (Código Civil arts. 408–416) em caso de vazamento — definir limites compatíveis com porte da operação e previsibilidade contratual.
- **CDC aplicável a PJ pequena** (consumidor relacional / vulnerabilidade técnica — jurisprudência STJ admite em alguns casos): impacta foro de eleição e cláusulas limitativas.
- **LGPD — sanções ANPD** (art. 52): advertência, multa simples até 2% do faturamento (limitado a R$ [redacted Tier 0] mi por infração), publicização, bloqueio/eliminação de dados, suspensão da atividade.
- **Direito do trabalhador** (CLT + LGPD art. 7º, V): dados de funcionário do CLIENTE têm proteção própria — escopo de uso pelo CLIENTE precisa ser auditado.
- **Compartilhamento internacional** (LGPD art. 33) — base legal para envio a sub-operadores nos EUA precisa de cláusulas-padrão ANPD ou equivalente.
- **Concorrência desleal** se benchmarks revelarem vantagem competitiva injusta — mitigado por k≥5, mas validar.
- **Marco Civil da Internet** (Lei nº 12.965/2014) — guarda de logs de acesso (6 meses) e logs de aplicação (6 meses) em paralelo à LGPD.
- **Tributação do DaaS** (item 3.3) — regime fiscal da contrapartida financeira ao CLIENTE (royalty? receita? participação? — impacto IR/PIS/COFINS/ISS).
- **Cláusula de não-reidentificação** imposta a terceiros DaaS — eficácia e remédios.
- **Foro do consumidor** vs foro da OIMPRESSO — ajustar conforme análise de hipossuficiência.

---

**Estado deste rascunho:** v0.1 (2026-05-09) — pronto para revisão de counsel externo.
**Próximo passo:** envio a escritório especializado em LGPD/Direito Digital para parecer formal e ajuste regional.
