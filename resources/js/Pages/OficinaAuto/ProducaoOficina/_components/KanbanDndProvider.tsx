// O provider de arraste do kanban passou para `@/Components/shared/KanbanDndProvider` (US-CRM-092),
// para o funil do CRM usar o mesmo arraste do quadro da Oficina. Este arquivo só reexporta,
// para o `Board.tsx` não mudar e os casos dele não precisarem de nova prova por um caminho de import.
export { default } from '@/Components/shared/KanbanDndProvider';
