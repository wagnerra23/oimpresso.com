#!/usr/bin/env python3
"""
jana-ragas-runner.py — adapter Python pro canary daily 06:00 UTC (US-COPI-116).

Roda como subprocess no workflow .github/workflows/jana-ragas-canary.yml.
NÃO instala lib `ragas` Python (PHP-side já faz via RagasJudgeService).
Este runner usa apenas stdlib (json, subprocess, argparse, pathlib) pra evitar
pip install em cada CI run (boot rápido + zero supply-chain risk).

Fluxo:
  1. Invoca `php artisan jana:ragas-ci-eval --json` (fonte da verdade RAGAS)
  2. Lê baseline JSON (governance/jana-ragas-baseline.json)
  3. Calcula delta percentual por métrica vs baseline
  4. Detecta regressões > threshold (default 5%)
  5. Output JSON canônico pra workflow consumir + step summary

Output shape:
  {
    "gate_status": "pass" | "fail",
    "mode": "mock" | "real",
    "cost_usd": 0.0,
    "regression_threshold_pct": 5.0,
    "n_questions": 100,
    "baseline_n_questions": 100,
    "metrics_diff": [
      {"metric": "faithfulness", "current": 0.85, "baseline": 0.84, "delta_pct": +1.19, "status": "ok"},
      {"metric": "answer_relevancy", "current": 0.70, "baseline": 0.82, "delta_pct": -14.63, "status": "regression"}
    ],
    "regressions": [
      {"metric": "answer_relevancy", "current": 0.70, "baseline": 0.82, "delta_pct": -14.63}
    ]
  }

Exit code:
  0 = canary OK (nenhuma regressão > threshold)
  1 = pelo menos uma métrica regrediu > threshold
  2 = erro estrutural (artisan falhou, baseline ausente, etc)

US-COPI-116 · Refs: ADR 0094 §4 (loop fechado por métrica) · ADR 0035 (stack IA)
"""

from __future__ import annotations

import argparse
import json
import os
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

# Métricas avaliadas (subset do que jana:ragas-ci-eval emite — só essas 2 são gate).
# Context precision/recall ficam fora (W22 marca como info-only).
TRACKED_METRICS = [
    ("faithfulness", "faithfulness_avg"),
    ("answer_relevancy", "relevancy_avg"),
]


def run_artisan_eval(mode: str) -> dict[str, Any]:
    """Invoca `php artisan jana:ragas-ci-eval --json` e retorna o report parseado."""
    cmd = [
        "php",
        "artisan",
        "jana:ragas-ci-eval",
        "--json",
        f"--mode={mode}",
    ]
    print(f"[runner] Executando: {' '.join(cmd)}", file=sys.stderr)
    proc = subprocess.run(
        cmd,
        capture_output=True,
        text=True,
        cwd=os.getcwd(),
    )
    # Salva raw eval pra artifact (workflow upload)
    Path("ragas-eval.json").write_text(proc.stdout, encoding="utf-8")
    if proc.returncode not in (0, 1):
        # 0 = pass, 1 = fail-threshold (esperado). Qualquer outro = erro estrutural.
        print(f"[runner] artisan falhou rc={proc.returncode}", file=sys.stderr)
        print(proc.stderr, file=sys.stderr)
        sys.exit(2)
    try:
        return json.loads(proc.stdout)
    except json.JSONDecodeError as e:
        print(f"[runner] artisan output não-JSON: {e}", file=sys.stderr)
        print(proc.stdout[:500], file=sys.stderr)
        sys.exit(2)


def load_baseline(path: Path) -> dict[str, Any]:
    """Lê baseline JSON. Se ausente OU vazio, retorna estrutura zerada."""
    if not path.exists():
        print(f"[runner] Baseline ausente em {path} — usando zeros (primeiro run)", file=sys.stderr)
        return {}
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
        return data if isinstance(data, dict) else {}
    except json.JSONDecodeError as e:
        print(f"[runner] Baseline malformado: {e}", file=sys.stderr)
        sys.exit(2)


def calc_delta_pct(current: float, baseline: float) -> float:
    """Delta percentual relativo: (current - baseline) / baseline * 100.
    Trata baseline=0 como N/A (delta 0)."""
    if baseline <= 0:
        return 0.0
    return ((current - baseline) / baseline) * 100.0


def compute_diff(eval_report: dict, baseline: dict, threshold_pct: float) -> dict[str, Any]:
    """Calcula diff por métrica + lista de regressões."""
    metrics_diff = []
    regressions = []
    baseline_metrics = baseline.get("metrics", {})

    for metric_name, eval_key in TRACKED_METRICS:
        current = float(eval_report.get(eval_key, 0.0))
        baseline_val = float(baseline_metrics.get(metric_name, {}).get("value", 0.0))
        delta = calc_delta_pct(current, baseline_val)
        # Regressão = delta NEGATIVO maior que threshold (em magnitude)
        is_regression = baseline_val > 0 and delta < -threshold_pct
        status = "regression" if is_regression else "ok"
        entry = {
            "metric": metric_name,
            "current": round(current, 4),
            "baseline": round(baseline_val, 4),
            "delta_pct": round(delta, 2),
            "status": status,
        }
        metrics_diff.append(entry)
        if is_regression:
            regressions.append(entry)

    return {"metrics_diff": metrics_diff, "regressions": regressions}


def update_baseline_file(path: Path, eval_report: dict) -> None:
    """Sobrescreve baseline com scores atuais. Só chamado se --update-baseline + workflow_dispatch."""
    now_iso = datetime.now(timezone.utc).isoformat(timespec="seconds")
    new_baseline = {
        "_meta": {
            "schema_version": "1.0",
            "description": "Baseline RAGAS canary Jana — recriado via workflow_dispatch jana-ragas-canary.yml (US-COPI-116). Não editar à mão; usar update_baseline=true no dispatch.",
            "regression_alert_pct_default": 5.0,
        },
        "metrics": {
            "faithfulness": {
                "value": round(float(eval_report.get("faithfulness_avg", 0.0)), 4),
                "last_updated": now_iso,
                "evaluated_questions": int(eval_report.get("n_questions", 0)),
                "mode": eval_report.get("mode", "unknown"),
            },
            "answer_relevancy": {
                "value": round(float(eval_report.get("relevancy_avg", 0.0)), 4),
                "last_updated": now_iso,
                "evaluated_questions": int(eval_report.get("n_questions", 0)),
                "mode": eval_report.get("mode", "unknown"),
            },
        },
    }
    path.write_text(json.dumps(new_baseline, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"[runner] Baseline atualizada em {path}", file=sys.stderr)


def main() -> int:
    parser = argparse.ArgumentParser(description="Jana RAGAS canary runner (US-COPI-116)")
    parser.add_argument("--mode", default="mock", choices=["mock", "real"], help="Modo eval (mock=$0, real=~$0.06)")
    parser.add_argument("--baseline", required=True, type=Path, help="Path do baseline JSON")
    parser.add_argument("--threshold-pct", type=float, default=5.0, help="Tolerância regressão por métrica (default 5%%)")
    parser.add_argument("--output", required=True, type=Path, help="Path do output JSON canônico")
    parser.add_argument("--update-baseline", action="store_true", help="Sobrescreve baseline com scores deste run")
    args = parser.parse_args()

    # 1. Roda eval PHP-side
    eval_report = run_artisan_eval(args.mode)

    # 2. Lê baseline (se ausente, diff vira no-op informativo)
    baseline = load_baseline(args.baseline)

    # 3. Calcula diff
    diff = compute_diff(eval_report, baseline, args.threshold_pct)

    # 4. Monta output canônico
    output = {
        "gate_status": "pass" if not diff["regressions"] else "fail",
        "mode": eval_report.get("mode", args.mode),
        "cost_usd": float(eval_report.get("cost_usd", 0.0)),
        "regression_threshold_pct": args.threshold_pct,
        "n_questions": int(eval_report.get("n_questions", 0)),
        "baseline_n_questions": int(
            baseline.get("metrics", {}).get("faithfulness", {}).get("evaluated_questions", 0)
        ),
        "metrics_diff": diff["metrics_diff"],
        "regressions": diff["regressions"],
        "ran_at": datetime.now(timezone.utc).isoformat(timespec="seconds"),
    }
    args.output.write_text(json.dumps(output, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")

    # 5. Update baseline se solicitado
    if args.update_baseline:
        update_baseline_file(args.baseline, eval_report)

    # 6. Exit code
    if output["gate_status"] == "fail":
        print(
            f"[runner] FAIL — {len(diff['regressions'])} regressão(ões) > {args.threshold_pct}%",
            file=sys.stderr,
        )
        return 1
    print(
        f"[runner] PASS — sem regressões > {args.threshold_pct}% "
        f"({len(diff['metrics_diff'])} métricas comparadas)",
        file=sys.stderr,
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
