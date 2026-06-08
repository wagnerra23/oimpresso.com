// MercosulPlate — PROMOVIDO pra @/Components/shared/MercosulPlate (ADR 0251).
//
// Este arquivo agora é só um re-export pra não quebrar os imports existentes do
// OficinaAuto (CacambaCard, ServiceOrderRichSheet, ServiceOrderKanbanCard).
// Fonte canônica única lá — Sells (venda com veículo) usa a MESMA plaquinha.
export { default } from '@/Components/shared/MercosulPlate';
