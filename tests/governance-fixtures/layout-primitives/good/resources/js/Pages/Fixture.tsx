// Fixture GOOD do gate-selftest (layout-primitives-guard).
// A contagem de flex/grid solto desta árvore BATE o baseline → 0 regressão → exit 0.
// Aqui há exatamente 1 `flex` cru (o baseline registra Fixture.tsx = 1).
export default function Fixture() {
  return (
    <div className="flex items-center gap-2">
      <span className="text-sm">olá mundo</span>
    </div>
  );
}
