import type { ButtonHTMLAttributes, ReactNode } from 'react'

import { cn } from '@/lib/cn'

type Variant = 'primary' | 'ghost' | 'subtle'

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant
  isLoading?: boolean
  children: ReactNode
}

const variants: Record<Variant, string> = {
  primary:
    'bg-green text-[#04140C] font-bold shadow-[0_6px_20px_rgba(53,214,138,0.22)] hover:bg-green-light',
  ghost:
    'border border-hairline bg-surface-raised text-[#C3C9D2] font-semibold hover:border-hairline-strong',
  subtle: 'text-ink-soft font-semibold hover:text-ink hover:bg-white/5',
}

export function Button({
  variant = 'primary',
  isLoading = false,
  className,
  children,
  disabled,
  ...props
}: ButtonProps) {
  return (
    <button
      type="button"
      disabled={disabled ?? isLoading}
      className={cn(
        'inline-flex items-center justify-center gap-2 rounded-[11px] px-4 py-[9px] text-[12.5px] transition-colors',
        'disabled:cursor-not-allowed disabled:opacity-60',
        variants[variant],
        className,
      )}
      {...props}
    >
      {isLoading ? <span className="size-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" /> : null}
      {children}
    </button>
  )
}
