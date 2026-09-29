import { useLocalSearchParams } from "expo-router";

import ProdutoWizard from "../_wizard";

export default function EditarProdutoRoute() {
  const { id, startStep } = useLocalSearchParams<{
    id: string;
    startStep?: string;
  }>();
  const initialStep = startStep ? parseInt(startStep, 10) || 0 : 0;
  return <ProdutoWizard produtoId={id} initialStep={initialStep} />;
}
