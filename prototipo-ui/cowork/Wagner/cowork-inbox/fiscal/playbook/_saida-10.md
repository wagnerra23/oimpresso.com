---
sessao: "10"
titulo: Sugestões da Jana na tributação (UC-NFTR-10..13)
autor: "[CL]"
data: 2026-10-07
base: wagnerra23/oimpresso.com@main (com #8941, #8973 e #8974)
prefixo_tocado: Modules/Jana/Ai/Agents/SugestaoFiscalAgent.php · Modules/Jana/Ai/Tools/Fiscal/ProdutoFiscalTool.php · Services/Tributacao/SugestaoFiscalService.php · migração 2026_10_07_000004 · Models/NfeSugestaoFiscal.php · Http/Controllers/SugestaoFiscalController.php · Http/Requests/SugestaoFiscalRequest.php · Routes · SugestaoFiscalTest · ProdutoFiscalToolTest · Index.casos.md · SCOPE · SUPERFICIE (Jana e NfeBrasil) · catalog.json · nfebrasil-pest.yml · este arquivo
---
# _saida-10 · Sugestões da Jana

**Resposta curta:** a Jana sugere NCM, natureza, regra incompleta ou inconsistência e **nunca aplica**.
A sugestão nasce `pendente`. Aceitar exige `nfe.tributacao.manage`; na regra, aplica criando versão
nova pelo caminho da thread 07, com `activity` e o autor. Risco alto só é aceito com
`confirmou_leitura`. Descartar também fica registrado. Com a Jana fora do ar, a resposta é
"Sugestões indisponíveis agora." e nada mais muda. Só backend (JSON); a tela é outra thread.

## 1 · Padrões seguidos (C12)

- Agente: `SugestaoFiscalAgent implements Agent, HasStructuredOutput` + `Promptable`, como o `SugestoesMetasAgent`.
- Tool: `ProdutoFiscalTool implements Tool, DeclaraPermissao`, `business_id` pelo construtor (ADR 0141), como a `NfeStatusTool`.
- O NfeBrasil chama a Jana; a Jana não escreve em tabela do NfeBrasil.

## 2 · Decisões de técnica

| decisão | por quê |
|---|---|
| saída do LLM validada antes de gravar | alvo de outra empresa, tipo desconhecido e NCM sem 8 dígitos são descartados |
| sugestão de regra só muda **códigos** (CFOP, CSOSN/CST, cClassTrib, CST IBS/CBS) | "nunca inventar alíquota": a alíquota que a Jana mandar é descartada |
| aceitar regra valida pelas regras do `UpsertRegraTributariaRequest` | o "caminho normal" da regra, sem uma 2ª validação divergente |
| aceitar `natureza`/`inconsistencia` só registra a decisão | não há onde escrever automaticamente sem inventar regra |
| "Perguntar à Jana" (10b) fora | chat livre; foi para o backlog do `Index.casos.md` |

## 3 · Prova (CT 100, worktree isolado, sha `17d286fdc`, migrações da 07 e da 10 aplicadas e revertidas)

| rodada | resultado |
|---|---|
| `SugestaoFiscalTest` | **3 passed · 39 assertions** (UC-NFTR-10 · 12 · 13) |
| `ProdutoFiscalToolTest` | **1 passed · 15 assertions** (UC-NFTR-11) |
| mutação: risco alto sem confirmação aceito | cai (*422 virou 200*) |
| mutação: alvo de outra empresa vira sugestão | cai (*3 em vez de 2*) |
| mutação: tool sem `business_id` | cai |
| mutação: Jana fora derruba o pedido | cai (*500*) |
| mutação: controller sem `business_id` | **não cai** — o escopo global `HasBusinessScope` do modelo também dá 404. O isolamento tem duas camadas, e o teste não separa uma da outra |
| PHPStan (6 arquivos) | sem erros |
| staging depois | tabelas, colunas e entradas em `migrations` da 07/10 = 0 · dados de teste = 0 |

A IA é o fake do `laravel/ai`: nenhuma chamada ao provedor nos testes.

## 4 · Fica para depois

- Tela da aba "Jana · sugestões" (consome os endpoints `GET sugestoes`, `POST gerar|aceitar|descartar`).
- "Perguntar à Jana" (UC-NFTR-10b).
- Gerar sugestões chama o provedor de IA de verdade em produção — custo por chamada; hoje só por pedido explícito.
