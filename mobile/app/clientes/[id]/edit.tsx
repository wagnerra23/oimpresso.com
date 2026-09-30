import { useLocalSearchParams } from "expo-router";

import ClienteWizard from "../_wizard";

export default function EditarClienteRoute() {
  const { id, startStep } = useLocalSearchParams<{
    id: string;
    startStep?: string;
  }>();
  const initialStep = startStep ? parseInt(startStep, 10) || 0 : 0;
  return <ClienteWizard customerId={id} initialStep={initialStep} />;
}
