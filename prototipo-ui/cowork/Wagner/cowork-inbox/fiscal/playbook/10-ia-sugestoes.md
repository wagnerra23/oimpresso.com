---
sessao: "10"
titulo: Sugestões da IA na tributação
dono: "[CL]"
base: wagnerra23/oimpresso.com@main 3de6bdc5bf8b (2026-10-06)
prefixo: Modules/Jana/Ai/Agents/SugestaoFiscalAgent.php · Modules/Jana/Ai/Tools/Fiscal/ProdutoFiscalTool.php · Modules/NfeBrasil/Services/Tributacao/SugestaoFiscalService.php · Modules/NfeBrasil/Database/Migrations/<nfe_sugestoes_fiscais> · Modules/NfeBrasil/Http/Controllers/SugestaoFiscalController.php · Tests/Feature/<novos>
nao_toca: MotorTributarioService.php · RegraForm (a IA nunca escreve regra direto)
depende: 07. Motor de IA: Jana — confirmado [W] 2026-10-06
decisao: _DECISOES-W-2026-10-06.md
implementa: UC-NFTR-09 · UC-NFTR-10 · UC-NFTR-09b · UC-NFTR-09c · UC-NFTR-10b
us: Fiscal/SPEC §vocabulário: o mapa "Jana sugere" do cockpit é **receita determinística por cStat (substitui IA real, R#2 KB-9.75)**. A IA desta thread **não toca rejeição**; só NCM, natureza e regra incompleta.
---
# 10 · Sugestões da IA na tributação

**Acesso à Jana — seguir o padrão que já existe, não inventar** (lido 2026-10-06):
- Agent com saída estruturada, como `Modules/Jana/Ai/Agents/SugestoesMetasAgent.php`: `implements Agent, HasStructuredOutput` + `use Promptable`, `laravel/ai` (ADR 0034 · 0035). O schema devolve `[{tipo: ncm|natureza|regra|inconsistencia, alvo_id, valor_sugerido, confianca 0..1, risco: baixo|medio|alto, motivo}]`. Instruções: PT-BR, **só com os dados fornecidos, nunca inventar NCM ou alíquota**, citar a lei quando sugerir regra.
- Tool de leitura como `Modules/Jana/Ai/Tools/BriefDiario/NfeStatusTool.php`: `implements Tool, DeclaraPermissao`, `business_id` pelo construtor (ADR 0141 · Tier 0), `permission()` = `nfe.tributacao.manage`. Devolve só descrição, unidade, categoria, NCM atual e regras do produto, sem preço nem cliente.
- `NfeBrasil` chama a Jana; a Jana **não escreve** em tabela do NfeBrasil.

A sugestão nasce `pendente` com tipo (NCM · natureza · regra · inconsistência), alvo, valor sugerido, confiança, motivo e risco. Aceitar exige `nfe.tributacao.manage`, cria a versão nova pelo caminho normal (FormRequest + activity) e registra o autor. Risco alto exige `confirmou_leitura=true`. Descartar também é logado. Nenhuma sugestão aplica sozinha.

## Casos de uso que esta thread implementa
Formato do `main` (rastreabilidade + Dado/Quando/Então + controle positivo + teste que cita o UC). **Os números são provisórios:** confirmar o próximo livre no destino no seu turno. Colar cada UC no destino indicado, com a linha na tabela de rastreabilidade (G-2: UC só entra com o teste que o cita).

| UC | Título | Prioridade | Destino |
|---|---|---|---|
| UC-NFTR-09 | Sugestão da Jana nunca altera regra nem produto sozinha | `must` `[T0]` `[fiscal]` | `Index.casos.md` |
| UC-NFTR-10 | A Jana só lê o produto da própria empresa, sem CNPJ, cliente ou preço | `must` `[T0]` | `Index.casos.md` |
| UC-NFTR-09b | Aceitar ou descartar sugestão de outra empresa é 404 | `must` `[T0]` | `Index.casos.md` |
| UC-NFTR-09c | Jana fora do ar não afeta emissão nem cadastro | `must` `[fiscal]` | `Index.casos.md` |
| UC-NFTR-10b | "Perguntar à Jana" responde com fonte e não altera nada | `should` `[fiscal]` | `Index.casos.md` |

### UC-NFTR-09 · Sugestão da Jana nunca altera regra nem produto sozinha · `must` `[T0]` `[fiscal]`
- **Destino:** `Index.casos.md`
- **Persona:** qualquer usuário com a Jana ligada
- **Aceite:** Dado uma sugestão `pendente` · Quando ninguém age · Então `nfe_fiscal_rules` e `products` ficam intactos. Quando aceito **sem** `nfe.tributacao.manage` → 403. Quando aceito com risco alto sem `confirmou_leitura` → 422. Quando aceito corretamente → versão nova + `activity` com o autor. Controle positivo: descartar não muda nada e também gera `activity`.
- **Teste:** `SugestaoFiscalTest` — `UC-NFTR-09 · sugestão nunca aplica sozinha`
- **Contrato:** D-IA · Fiscal/SPEC (rejeição é determinística, a IA não toca) · ADR 0141
- **Regressão que defende:** IA mudando a tributação sem dono.

### UC-NFTR-10 · A Jana só lê o produto da própria empresa, sem CNPJ, cliente ou preço · `must` `[T0]`
- **Destino:** `Index.casos.md`
- **Persona:** —
- **Aceite:** Dado a ferramenta fiscal da Jana · Quando monta o contexto · Então só há descrição, unidade, categoria, NCM e regras do **business da sessão**. Controle positivo: produto de outro business não aparece.
- **Teste:** `ProdutoFiscalToolTest` — `UC-NFTR-10 · tool fiscal só lê o próprio tenant e sem PII`
- **Contrato:** ADR 0093 · `ContextoNegocio` (CNPJ mascarado)
- **Regressão que defende:** vazamento entre empresas via prompt.

### UC-NFTR-09b · Aceitar ou descartar sugestão de outra empresa é 404 · `must` `[T0]`
- **Destino:** `Index.casos.md`
- **Persona:** qualquer tenant
- **Aceite:** Dado sugestão do business A · Quando B aceita ou descarta · Então 404 e nada muda em A. Controle positivo: A aceita a própria.
- **Teste:** `SugestaoFiscalTest` — `UC-NFTR-09b · sugestão isolada por tenant`
- **Contrato:** ADR 0093
- **Regressão que defende:** aceite cruzado mudando regra de outra empresa.

### UC-NFTR-09c · Jana fora do ar não afeta emissão nem cadastro · `must` `[fiscal]`
- **Destino:** `Index.casos.md`
- **Persona:** dia em que o provedor de IA cai
- **Aceite:** Dado a Jana indisponível (timeout/erro) · Quando cadastro produto, salvo regra ou emito nota · Então tudo funciona igual e a tela diz "sugestões indisponíveis agora". Controle positivo: com a Jana de volta, as sugestões reaparecem.
- **Teste:** `SugestaoFiscalTest` — `UC-NFTR-09c · IA indisponível não bloqueia fiscal`
- **Contrato:** D-IA (IA é sugestão, nunca caminho crítico)
- **Regressão que defende:** emissão travada porque a IA não respondeu.

### UC-NFTR-10b · "Perguntar à Jana" responde com fonte e não altera nada · `should` `[fiscal]`
- **Destino:** `Index.casos.md`
- **Persona:** Eliana com dúvida de NCM ou CFOP
- **Aceite:** Dado uma pergunta fiscal · Quando a Jana responde · Então a resposta cita a fonte (regra, cadastro ou lei) e nenhuma tabela fiscal muda; se virar sugestão, entra como `pendente` (UC-NFTR-09). Controle positivo: pergunta sobre outra empresa não traz dado dela.
- **Teste:** `JanaFiscalPerguntaTest` — `UC-NFTR-10b · pergunta com fonte e sem escrita`
- **Contrato:** D-IA · protótipo aba "Jana · sugestões"
- **Regressão que defende:** chat que "resolve" mudando regra.

## Prova
Teste: aceitar sem permissão = 403 · risco alto sem confirmação = 422 · aceitar gera versão + activity · descartar não muda regra.

Antes de editar: confirmar no turno os símbolos citados (C12). Terminou: `_saida-10.md` com o sha e a saída dos testes. Pare.
