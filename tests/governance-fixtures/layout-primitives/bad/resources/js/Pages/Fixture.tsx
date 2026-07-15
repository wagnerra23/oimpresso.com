// Fixture BAD do gate-selftest (layout-primitives-guard).
// Esta árvore GANHOU flex/grid solto A MAIS que o baseline (que registra Fixture.tsx = 1):
// agora há 1 `flex` cru + 1 `grid` cru = 2 → 2 > 1 → REGRESSÃO → a catraca DEVE morder (exit 1).
export default function Fixture() {
  return (
    <div className="flex items-center gap-2">
      <div className="grid gap-4">
        <span className="text-sm">olá mundo</span>
      </div>
    </div>
  );
}
