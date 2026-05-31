# receita-drift-rule.tests.ps1 - TESTE DE DESVIOS do Loop Fechado Anti-Drift.
#
# Responde Wagner 2026-05-31 "vai teste de desvios?": prova que a regra de deteccao
# DISTINGUE drift de saudavel. "Aperta o botao do alarme E simula fumaca."
#
# Roda standalone (sem servidor/DB): dot-source a regra + matriz de cenarios + assert.
# Exit 0 = todos os cenarios pegos corretamente. Exit 1 = mecanismo quebrado (algum desvio passou).
#
# Uso: powershell -NoProfile -ExecutionPolicy Bypass -File .claude/hooks/receita-drift-rule.tests.ps1

$ErrorActionPreference = 'Stop'
. "$PSScriptRoot\receita-drift-rule.ps1"

$cases = @(
    @{ nome='Cycle de engenharia (nao-Receita) -> SILENT (loop nao incomoda)'; cycle='Fundacoes pos-4.8'; novos=0;  pct=50; esperado='SILENT' }
    @{ nome='Receita recem-aberto (0% decorrido, 0 novos) -> FRAME (cedo demais p/ drift)'; cycle='Receita - Onda A'; novos=0; pct=0;  esperado='FRAME' }
    @{ nome='*** DESVIO ***: Receita 50% decorrido, 0 clientes novos -> DRIFT (forcador)'; cycle='Receita - Onda A'; novos=0; pct=50; esperado='DRIFT' }
    @{ nome='Receita 50% decorrido COM movimento (3 novos) -> FRAME (nao e drift)'; cycle='Receita - Onda A'; novos=3; pct=50; esperado='FRAME' }
    @{ nome='*** DESVIO no limite ***: Receita 25% decorrido, 0 novos -> DRIFT'; cycle='Receita'; novos=0; pct=25; esperado='DRIFT' }
    @{ nome='Receita logo abaixo do limite (20% decorrido, 0 novos) -> FRAME'; cycle='Receita'; novos=0; pct=20; esperado='FRAME' }
    @{ nome='*** DESVIO grave ***: Receita 90% decorrido, 0 novos -> DRIFT'; cycle='Receita - Onda A'; novos=0; pct=90; esperado='DRIFT' }
    @{ nome='Cycle vazio/nulo -> SILENT'; cycle='';  novos=0; pct=50; esperado='SILENT' }
)

Write-Host ""
Write-Host "=== TESTE DE DESVIOS - Loop Fechado Anti-Drift (Receita) ==="
Write-Host ""
$fail = 0
foreach ($c in $cases) {
    $got = Get-ReceitaDriftLevel -CycleName $c.cycle -Novos7d $c.novos -PctElapsed $c.pct
    if ($got -eq $c.esperado) {
        Write-Host ("  PASS   {0}" -f $c.nome)
    } else {
        Write-Host ("  FAIL   {0}  [esperado={1} veio={2}]" -f $c.nome, $c.esperado, $got)
        $fail++
    }
}
Write-Host ""
if ($fail -gt 0) {
    Write-Host ("TESTE DE DESVIOS FALHOU: {0} de {1} cenarios nao pegos. LOOP QUEBRADO." -f $fail, $cases.Count)
    exit 1
}
Write-Host ("TESTE DE DESVIOS OK: {0} de {0} cenarios -- o loop distingue DRIFT de saudavel." -f $cases.Count)
exit 0
