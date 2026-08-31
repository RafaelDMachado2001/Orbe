import { cn } from '@/lib/cn'

/** Placeholder de carregamento. Nunca usamos spinner de tela inteira. */
export function Skeleton({ className }: { className?: string }) {
  return <div className={cn('skeleton rounded-lg', className)} aria-hidden="true" />
}

export function SkeletonText({ className }: { className?: string }) {
  return <Skeleton className={cn('h-3 w-24', className)} />
}
