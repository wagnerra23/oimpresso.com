# Superadmin (SaaS/licenciamento) — F1 [CC] · 2026-08-18

Pedido zero-toque pro [CL]. Origem: `Modules/Superadmin/Resources/views/**` (Blade/AdminLTE) lido no `main` (tree `9d99d9b2405f`).
Build F1 vive no projeto Cowork: `superadmin-page.jsx` + `superadmin-page.css`, rota `superadmin` + ghosts `sa-negocios · sa-assinaturas · sa-pacotes · sa-comunicador · sa-config` dentro de `oimpresso.com.html`.
**Não commitado.** Este arquivo é o trio (charter + casos) que falta pra tela contar como pronta no `prototipo-readiness`.

---

## 1. Charter — `Superadmin/Index`

**Dono da tela:** [W] · **Persona:** Wagner (escritório, 1440px) — nenhuma outra persona entra aqui.
**Permissão:** `superadmin` (Bouncer). Admin de negócio **não vê** o item na sidebar; rota responde 403.
**Vocabulário:** negócio (não "empresa", não "tenant"), assinatura, pacote, MRR, trial. Nunca "business", "subscription", "plan".

### Norte
Uma pergunta por tela. Visão geral responde *"a plataforma está crescendo ou vazando?"*; Negócios responde *"quem é este cliente e o que ele tem contratado?"*; Assinaturas responde *"o dinheiro entrou?"*; Pacotes responde *"o que estamos vendendo?"*; Comunicador responde *"como aviso todo mundo sem mentir no alcance?"*; Configurações responde *"o que vale pra plataforma inteira?"*.

### Seções por view (ordem trava no contrato)
1. **`visao`** — PageHeader · segmented de período (Hoje/Semana/Mês/Ano, janelas rolantes) · 4 KPI (novas assinaturas em R$, novos cadastros, sem assinatura, MRR) · funil trial→pago (4 etapas, perda por etapa) · churn 30 d (taxa + motivos declarados) · receita por pacote · fila "Vencendo ou vencido" com 1 ação por linha · tendência mensal (Chart bar, 12 meses) · "O que fazer primeiro" (3 itens navegáveis) · cadastros recentes (5 linhas).
2. **`negocios`** — PageHeader · busca (`/`) · 4 filtros (pacote · assinatura · status · última venda) · tabela 5 colunas + seleção · paginação 6/página · BulkBar (comunicar · exportar · desativar) · Drawer PT-02 (assinatura · uso contra o teto · dono e contato · histórico de assinaturas) · FormDrawer novo/editar.
3. **`assinaturas`** — PageHeader · 4 KPI de status · 3 filtros · tabela 6 colunas (vigência fundida `início → fim`) · kebab (status · datas · comprovante · cancelar) · FormDrawer status/vigência.
4. **`pacotes`** — grid de cards (preço · limites com "0 = ilimitado" · módulos liberados · tags privado/avulso/ativo · assinantes) · FormDrawer novo/editar/duplicar.
5. **`comunicador`** — grupos de destinatário como chips com contador · assunto · mensagem · agendamento · prévia do e-mail · histórico com taxa de abertura.
6. **`config`** — nav de 6 seções (Aplicação · SMTP · Gateways · Pusher · Rotinas/backup · JS-CSS extra) · Alert `danger` na seção de injeção de código.

### Regras de domínio (o que a UI promete)
- **R1** MRR só soma pacote recorrente com preço > 0. Gratuito e avulso entram no caixa do mês, nunca na recorrência.
- **R2** "Sem assinatura" = negócio cadastrado sem nenhuma assinatura — é o vazamento do funil, não inadimplência.
- **R3** Cancelar assinatura é append-only: para de renovar no fim da vigência, o acesso continua até lá, o registro fica.
- **R4** Desativar negócio corta o acesso na hora e preserva o dado; excluir é irreversível e leva vendas, OS e fiscal.
- **R5** Reduzir limite de pacote não corta quem já passou dele — só bloqueia novo cadastro acima do teto.
- **R6** Pacote inativo sai da tela de assinatura do cliente; quem já assinou segue cobrado.
- **R7** Pacote privado só o superadmin atribui.
- **R8** Comunicador conta alcance por negócio, não por pessoa; negócio em dois grupos recebe uma vez.
- **R9** Taxa de abertura conta 1 por negócio e só nos 14 dias seguintes ao envio.
- **R10** Churn declara apenas motivo respondido no cancelamento; saída sem motivo fica fora do gráfico e é dita em texto.
- **R11** Todo aviso é PT-BR, sentence case, sem emoji.

### Non-goals
- Não é BI: nenhum gráfico de série longa além dos 12 meses.
- Não faz cobrança: gateway é `Modules/PaymentGateway`; aqui só o registro e o status.
- Não edita dado operacional do cliente (produto, OS, venda) — para isso, "Entrar como este negócio".
- Sem exclusão em lote de negócios (só desativação).

### Decisões pendentes [W]
- **D1** "Entrar como este negócio" (impersonar) entra? Precisa de trilha no log de auditoria e banner permanente na sessão.
- **D2** `pages` e `pricing` do Blade ficam no Superadmin ou migram pro CMS?
- **D3** `subscription/pay` (tela que o cliente vê) é do Superadmin ou do módulo de assinatura do cliente?

---

## 2. Casos de uso — `Superadmin/Index.casos.md`

| UC | Como | Quero | Para | Critério de aceite |
|---|---|---|---|---|
| UC-SA-001 | superadmin | ver o pulso da plataforma no período | decidir onde mexer hoje | Trocar o período recalcula os 4 KPI; janela rolante dita em texto |
| UC-SA-002 | superadmin | saber onde o trial vaza | atacar a etapa certa | Funil mostra perda absoluta e % por etapa + conversão ponta a ponta |
| UC-SA-003 | superadmin | ver quem vence ou já venceu | cobrar antes de perder | Fila ordena atraso primeiro; ação por linha muda conforme risco |
| UC-SA-004 | superadmin | achar um negócio por biz # | atender um chamado | Busca por nome, dono, e-mail e número; `/` foca o campo |
| UC-SA-005 | superadmin | abrir a ficha do negócio | responder sem trocar de tela | Clique na linha abre Drawer; esc fecha; nada navega |
| UC-SA-006 | superadmin | saber se o cliente estourou o pacote | oferecer upgrade | Drawer mostra uso/teto com tom de alerta ≥ 70% e ≥ 90% |
| UC-SA-007 | superadmin | cadastrar negócio novo | subir cliente vendido no telefone | Salvar exige nome, dono e e-mail válido; e-mail de 1º acesso opcional |
| UC-SA-008 | superadmin | mudar o status de uma assinatura | registrar Pix pago fora do gateway | Exige motivo; nota diz o efeito de aprovar/cancelar |
| UC-SA-009 | superadmin | prorrogar vigência | segurar cliente em negociação | Editar fim sem gerar cobrança nova, dito na tela |
| UC-SA-010 | superadmin | criar/editar pacote | mudar a grade comercial | "0 = ilimitado" visível; R5 e R6 ditas no form |
| UC-SA-011 | superadmin | duplicar pacote | testar preço novo | Cópia nasce com 0 assinantes e sufixo "(cópia)" |
| UC-SA-012 | superadmin | avisar um grupo | comunicar manutenção | Alcance recalcula ao marcar grupo; prévia mostra o e-mail; teste vai só pra mim |
| UC-SA-013 | superadmin | agendar o aviso | não acordar cliente 22h | Data/hora aparecem só com agendamento ligado; CTA muda para "Agendar envio" |
| UC-SA-014 | superadmin | desativar vários de uma vez | encerrar piloto | BulkBar com confirmação citando a contagem |
| UC-SA-015 | superadmin | não apagar cliente por engano | evitar perda irreversível | Excluir passa por Modal dizendo o que vai embora |
| UC-SA-016 | admin de negócio | não ver esta tela | isolamento entre clientes | Item ausente na sidebar; rota 403 |

### Testes mínimos
- DQE: 1 negócio sem pacote, 1 pacote sem assinante, 1 assinatura pendente, 1 negócio inativo.
- Borda: filtro que zera a lista (vazio cita a busca digitada); paginação na última página ao filtrar; nome de negócio longo (elipse, sem quebra de token).
- Plural PT-BR: 1 local / 2 locais · 1 pagante / 2 pagantes · 6 meses (nunca "mêses").
- Mono nunca quebra: telefone, dd/mm/aaaa, `SUB-nnnn`, R$.
- Permissão: admin de negócio 403; superadmin 200.

---

## 3. Contrato de Tela — rascunho `prototipo-ui/contrato/superadmin.contract.json`

```json
{
  "screen": "Superadmin/Index",
  "related_prototype": "superadmin-page.jsx",
  "views": {
    "visao":       ["periodo", "kpis", "funil", "churn", "receita-pacote", "fila-vencimento", "tendencia", "prioridades", "recentes"],
    "negocios":    ["busca", "filtros", "tabela", "paginacao", "bulkbar", "drawer", "form"],
    "assinaturas": ["kpis", "filtros", "tabela", "paginacao", "form-status", "form-vigencia"],
    "pacotes":     ["grid-pacotes", "form-pacote"],
    "comunicador": ["destinatarios", "composicao", "agendamento", "previa", "historico"],
    "config":      ["nav-secoes", "campos", "aviso-codigo"]
  },
  "copy": {
    "kpi.mrr": "Receita recorrente (MRR)",
    "kpi.sem_assinatura": "Sem assinatura",
    "fila.titulo": "Vencendo ou vencido",
    "pacote.limite_zero": "0 = ilimitado",
    "excluir.aviso": "Não tem como desfazer.",
    "cancelar.aviso": "para de renovar no fim da vigência"
  },
  "states": ["carregando", "vazio-filtro", "vazio-busca", "sem-permissao", "lista", "selecao"],
  "forbidden": ["emoji", "ingles-em-ui", "cor-crua-fora-de-token", "modal-fullscreen-para-detalhe"]
}
```

---

## 4. Primitivos do DS usados (onda 4)

`PageHeader · KpiCard · StatusBadge (tone) · Chart (bar) · DataTable-like via os-table + Checkbox · Pagination · BulkBar · Drawer-like (sa-drawer, PT-02) · Modal · Input · Select · Textarea · Switch · Alert · Progress · Skeleton · EmptyState · Toast · RegistrationMark`.

Peças ainda caseiras, com motivo:
- **Tabela**: `os-table` do shell em vez de `DataTablePro` — as outras telas do app usam `os-table`; trocar só esta criaria dois padrões de grade. Decisão de [W] se o app migra inteiro.
- **Botões**: `os-btn` do shell (mesma razão).
- **Drawer**: casca `sa-drawer` própria porque o form precisa de rodapé fixo com 2 CTAs e o `Drawer` do DS assume seções de leitura.
- **Filtros**: `cli-fdrop-*` do shell — é o padrão que Clientes/CRM já usa.
