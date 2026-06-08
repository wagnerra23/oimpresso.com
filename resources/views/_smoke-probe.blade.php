<!DOCTYPE html>
{{-- Probe standalone pro gate visual (US-GOV-013 Fase A · ADR 0108). Zero deps
     (sem layout/$request/Inertia/DB) → 200 determinístico no test env minimal.
     Prova o pipeline: chromium → app boota (schema-squash) → rota → render →
     assert → screenshot. Rota só registra fora de produção (routes/web.php). --}}
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>visual-gate smoke probe</title>
</head>
<body>
    <main data-testid="smoke-probe">visual-gate-smoke-ok</main>
</body>
</html>
