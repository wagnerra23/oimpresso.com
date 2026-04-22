import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/Types';

export function usePageProps() {
  return usePage<SharedProps>().props;
}

export function useAuth() {
  return usePageProps().auth;
}

export function useBusiness() {
  return usePageProps().business;
}

export function useAiFlags() {
  return usePageProps().ai;
}

export function useFlash() {
  return usePageProps().flash;
}
