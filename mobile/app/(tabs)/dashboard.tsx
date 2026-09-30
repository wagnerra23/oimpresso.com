import React from 'react';
import { ActivityIndicator, View, Text, ScrollView } from 'react-native';
import { ScreenContainer } from '@/components/screen-container';
import { MetricCard, KanbanCard } from '@/components/erp-ui';
import { useProdutos, usePedidos } from '@/lib/erp-queries';
import { useRouter } from 'expo-router';

export default function DashboardScreen() {
  const produtosQuery = useProdutos();
  const pedidosQuery = usePedidos();
  const router = useRouter();

  const produtos = produtosQuery.data ?? [];
  const pedidos = pedidosQuery.data ?? [];

  const isLoading = produtosQuery.isLoading || pedidosQuery.isLoading;
  const isError = produtosQuery.isError || pedidosQuery.isError;
  const errorMessage =
    produtosQuery.error?.message ??
    pedidosQuery.error?.message ??
    'Erro ao carregar dados';

  // Calcular métricas
  const totalProdutos = produtos.length;
  const totalVendas = pedidos.length;
  const vendasPendentes = pedidos.filter((p) => p.status !== 'entregue').length;
  const totalReceita = pedidos.reduce((sum, p) => sum + p.valor, 0);

  // Pedidos recentes (últimos 5)
  const pedidosRecentes = [...pedidos]
    .sort((a, b) => new Date(b.data).getTime() - new Date(a.data).getTime())
    .slice(0, 5);

  const formatarMoeda = (valor: number) => {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
      minimumFractionDigits: 0,
    }).format(valor);
  };

  return (
    <ScreenContainer className="bg-background">
      <ScrollView className="flex-1" contentContainerStyle={{ paddingBottom: 20 }}>
        {/* Header */}
        <View className="px-4 py-6">
          <Text className="text-3xl font-bold text-foreground mb-2">Manus ERP</Text>
          <Text className="text-sm text-muted">Visão geral do negócio</Text>
        </View>

        {isLoading ? (
          <View className="py-12 items-center">
            <ActivityIndicator />
          </View>
        ) : isError ? (
          <View className="px-4">
            <View className="bg-error/10 rounded-lg p-4">
              <Text className="text-error text-sm">{errorMessage}</Text>
            </View>
          </View>
        ) : (
          <>
            {/* Métricas */}
            <View className="px-4 mb-6">
              <View className="flex-row gap-3 mb-3">
                <MetricCard label="Produtos" value={totalProdutos} />
                <MetricCard label="Vendas" value={totalVendas} />
              </View>
              <View className="flex-row gap-3">
                <MetricCard label="Pendentes" value={vendasPendentes} />
                <MetricCard label="Receita" value={formatarMoeda(totalReceita)} />
              </View>
            </View>

            {/* Pedidos Recentes */}
            <View className="px-4">
              <View className="mb-4">
                <Text className="text-lg font-bold text-foreground">Pedidos Recentes</Text>
              </View>

              {pedidosRecentes.length > 0 ? (
                pedidosRecentes.map((pedido) => (
                  <KanbanCard
                    key={pedido.id}
                    id={pedido.id}
                    title={pedido.cliente}
                    subtitle={`${pedido.produto} • ${pedido.data}`}
                    status={pedido.status}
                  />
                ))
              ) : (
                <View className="bg-surface rounded-lg p-4 items-center">
                  <Text className="text-muted text-sm">Nenhum pedido cadastrado</Text>
                </View>
              )}
            </View>
          </>
        )}
      </ScrollView>
    </ScreenContainer>
  );
}
