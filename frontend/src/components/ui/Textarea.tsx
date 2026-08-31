import type { TextareaHTMLAttributes } from 'react'
import { forwardRef } from 'react'

import { cn } from '@/lib/cn'

interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  label: string
  error?: string
}

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(function Textarea(
  { label, error, className, id, ...props },
  ref,
) {
  const fieldId = id ?? props.name ?? label

  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={fieldId} className="text-[11.5px] font-semibold text-ink-soft">
        {label}
      </label>
      <textarea
        ref={ref}
        id={fieldId}
        aria-invalid={error !== undefined}
        aria-describedby={error ? `${fieldId}-error` : undefined}
        className={cn(
          'resize-y rounded-[11px] border bg-surface-alt px-3.5 py-2.5 text-[13px] text-ink transition-colors',
          'placeholder:text-ink-faint',
          error ? 'border-orange/60' : 'border-hairline focus:border-purple/60',
          className,
        )}
        {...props}
      />
      {error ? (
        <p id={`${fieldId}-error`} role="alert" className="text-[11px] font-medium text-orange-light">
          {error}
        </p>
      ) : null}
    </div>
  )
})
