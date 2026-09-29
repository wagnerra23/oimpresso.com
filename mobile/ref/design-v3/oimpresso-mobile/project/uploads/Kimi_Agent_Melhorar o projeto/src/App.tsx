import { TweaksProvider, useTweaks } from '@/contexts/TweaksContext';
import { AppStateProvider, useAppState } from '@/contexts/AppStateContext';
import { StageHero } from '@/components/canvas/StageHero';
import { TweaksPanel } from '@/components/canvas/TweaksPanel';
import { IOSDevice } from '@/components/devices/IOSDevice';
import { AndroidDevice } from '@/components/devices/AndroidDevice';
import { IOSTabBar } from '@/components/navigation/IOSTabBar';
import { AndroidBottomNav } from '@/components/navigation/AndroidBottomNav';
import { ToastStack } from '@/components/shared/ToastStack';

import { HomeScreen } from '@/screens/HomeScreen';
import { TasksScreen } from '@/screens/TasksScreen';
import { TaskDetailScreen } from '@/screens/TaskDetailScreen';
import { OrdersScreen } from '@/screens/OrdersScreen';
import { OrderDetailScreen } from '@/screens/OrderDetailScreen';
import { NewOrderScreen } from '@/screens/NewOrderScreen';
import { ProductionScreen } from '@/screens/ProductionScreen';
import { ProductionDetailScreen } from '@/screens/ProductionDetailScreen';
import { ClientsScreen } from '@/screens/ClientsScreen';
import { ClientDetailScreen } from '@/screens/ClientDetailScreen';
import { NewClientScreen } from '@/screens/NewClientScreen';
import { ProductsScreen } from '@/screens/ProductsScreen';
import { NewProductScreen } from '@/screens/NewProductScreen';
import { LoginScreen } from '@/screens/LoginScreen';

function ScreenRouter() {
  const { state } = useAppState();

  switch (state.screen) {
    case 'home': return <HomeScreen />;
    case 'tasks': return <TasksScreen />;
    case 'taskDetail': return <TaskDetailScreen />;
    case 'orders': return <OrdersScreen />;
    case 'orderDetail': return <OrderDetailScreen />;
    case 'newOrder': return <NewOrderScreen />;
    case 'production': return <ProductionScreen />;
    case 'productionDetail': return <ProductionDetailScreen />;
    case 'clients': return <ClientsScreen />;
    case 'clientDetail': return <ClientDetailScreen />;
    case 'newClient': return <NewClientScreen />;
    case 'products': return <ProductsScreen />;
    case 'newProduct': return <NewProductScreen />;
    case 'login': return <LoginScreen />;
    default: return <HomeScreen />;
  }
}

function MobileApp({ platform }: { platform: 'ios' | 'android' }) {
  const { tweaks } = useTweaks();
  const { state, setNavTab, removeToast } = useAppState();

  const NavBar = platform === 'ios' ? IOSTabBar : AndroidBottomNav;

  return (
    <div
      className="oi-app"
      data-theme={tweaks.theme}
      data-density={tweaks.density}
    >
      <div className="oi-screen relative">
        <ScreenRouter />
        <ToastStack toasts={state.toasts} onRemove={removeToast} />
      </div>
      <NavBar activeTab={state.navTab} onTabChange={setNavTab} />
    </div>
  );
}

function DeviceShell({ platform }: { platform: 'ios' | 'android' }) {
  const Device = platform === 'ios' ? IOSDevice : AndroidDevice;
  return (
    <Device>
      <MobileApp platform={platform} />
    </Device>
  );
}

function AppContent() {
  const { tweaks } = useTweaks();

  const loginShell = (
    <div className="oi-app" data-theme={tweaks.theme} data-density={tweaks.density}>
      <div className="oi-screen">
        <LoginScreen />
      </div>
    </div>
  );

  return (
    <div className="min-h-screen flex flex-col" style={{ fontFamily: '"IBM Plex Sans", system-ui, sans-serif' }}>
      <StageHero />

      <div
        className="flex-1 flex items-start justify-center flex-wrap"
        style={{ gap: 56, padding: '56px 32px 96px' }}
      >
        {/* iOS Device */}
        <div className="flex flex-col items-center" style={{ gap: 14 }}>
          <span className="text-[11px] font-mono font-semibold tracking-[0.16em] uppercase" style={{ color: 'oklch(0.68 0.02 240)' }}>
            iOS · iPhone 15
          </span>
          <AppStateProvider>
            <DeviceShell platform="ios" />
          </AppStateProvider>
        </div>

        {/* Android Device */}
        <div className="flex flex-col items-center" style={{ gap: 14 }}>
          <span className="text-[11px] font-mono font-semibold tracking-[0.16em] uppercase" style={{ color: 'oklch(0.68 0.02 240)' }}>
            Android · Pixel 8
          </span>
          <AppStateProvider>
            <DeviceShell platform="android" />
          </AppStateProvider>
        </div>

        {/* Login Device */}
        <div className="flex flex-col items-center" style={{ gap: 14 }}>
          <span className="text-[11px] font-mono font-semibold tracking-[0.16em] uppercase" style={{ color: 'oklch(0.68 0.02 240)' }}>
            Login · marca Oimpresso
          </span>
          <IOSDevice>
            {loginShell}
          </IOSDevice>
        </div>
      </div>

      <TweaksPanel />
    </div>
  );
}

export default function App() {
  return (
    <TweaksProvider>
      <AppContent />
    </TweaksProvider>
  );
}
