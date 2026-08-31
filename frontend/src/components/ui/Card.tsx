import type { HTMLAttributes, ReactNode } from 'react'

import { cn } from '@/lib/cn'

interface CardProps extends HTMLAttributes<HTMLDivElement> {
  children: ReactNode
}

/** Superficie padrao: borda de 1px, raio 18px e fundo #0F1216. */
export function Card({ className, children, ...props }: CardProps) {
  return (
    <div
      className={cn('rounded-[18px] border border-hairline bg-surface p-5', className)}
      {...props}
    >
      {children}
    </div>
  )
}

interface CardHeaderProps {
  title: string
  subtitle?: string
  action?: ReactNode
  className?: string
}

export function CardHeader({ title, subtitle, action, className }: CardHeaderProps) {
  return (
    <div className={cn('flex items-start gap-4', className)}>
      <div>
        <h2 className="text-[14.5px] font-bold tracking-[-0.2px] text-ink">{title}</h2>
        {subtitle ? (
          <p className="mt-[3px] text-[11.5px] font-medium text-ink-dim">{subtitle}</p>
        ) : null}
      </div>
      {action ? <div className="ml-auto">{action}</div> : null}
    </div>
  )
}
