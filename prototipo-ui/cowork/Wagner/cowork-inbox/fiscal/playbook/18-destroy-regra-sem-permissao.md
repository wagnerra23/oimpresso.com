---
sessao: "18"
titulo: Apagar regra tributária exige a permissão fiscal (UC-NFRF-04)
dono: "[CL]"
base: wagnerra23/oimpresso.com@main 794f606dcecc (2026-10-06)
prefixo: Modules/NfeBrasil/Http/Controllers/TributacaoController.php (destroy) · Http/Requests/<DestroyRegraTributariaRequest novo, se for o padrão>
nao_toca: Modules/NfeBrasil/Services/MotorTributarioService.php · resources/js/
depende: — . 🔴 T0 (multi-tenant/permissão): sozinho no PR, com o teste
implementa: UC-NFRF-04 (já existe no main — fica verde)
us: UC-NFRF-04 (RegraForm.casos.md) · US-NFE-010
---
# 18 · Apagar regra tributária exige a permissão fiscal (UC-NFRF-04)

**Já catalogado no `main` como falha esperada** (o teste é failing-first; o vermelho é o recibo). Antes de abrir PR: `gh pr list --state open` — pode já haver conserto em curso.

`TributacaoController@destroy` recebe `Illuminate\Http\Request` e o arquivo tem 0 `can(`/`abort` (medido no casos). Um usuário de caixa apaga uma regra NCM e a nota passa a usar a regra geral, errada e com aparência de certa. Gatear com `nfe.tributacao.manage`, no mesmo padrão do store/update.

## Casos de uso que esta thread implementa
Nenhum UC novo: o caso já está escrito no `main` e esta thread só o tira de ❌. Atualizar o status na tabela de rastreabilidade do casos.md.

## Prova
`TributacaoGatesContratoTest` › UC-NFRF-04 passa de ❌ para verde · UC-NFRF-01..03 seguem verdes · casos.md atualiza o status.

Terminou: `_saida-18.md`. Pare.
