import type { InputHTMLAttributes } from 'react'
import { forwardRef } from 'react'

import { cn } from '@/lib/cn'

interface FieldProps extends InputHTMLAttributes<HTMLInputElement> {
  label: string
  error?: string
}

export const Field = forwardRef<HTMLInputElement, FieldProps>(function Field(
  { label, error, className, id, ...props },
  ref,
) {
  const inputId = id ?? props.name ?? label

  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={inputId} className="text-[11.5px] font-semibold text-ink-soft">
        {label}
      </label>
      <input
        ref={ref}
        id={inputId}
        aria-invalid={error !== undefined}
        aria-describedby={error ? `${inputId}-error` : undefined}
        className={cn(
          'rounded-[11px] border bg-surface-alt px-3.5 py-2.5 text-[13px] text-ink transition-colors',
          'placeholder:text-ink-faint',
          error ? 'border-orange/60' : 'border-hairline focus:border-purple/60',
          className,
        )}
        {...props}
      />
      {error ? (
        <p id={`${inputId}-error`} role="alert" className="text-[11px] font-medium text-orange-light">
          {error}
        </p>
      ) : null}
    </div>
  )
})
