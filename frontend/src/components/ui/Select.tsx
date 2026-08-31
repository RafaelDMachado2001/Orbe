import type { SelectHTMLAttributes } from 'react'
import { forwardRef } from 'react'

import { cn } from '@/lib/cn'

interface Option {
  value: string
  label: string
}

interface SelectProps extends Omit<SelectHTMLAttributes<HTMLSelectElement>, 'children'> {
  label?: string
  error?: string
  options: Option[]
  /** Primeira opcao neutra, para o campo poder ficar vazio. */
  placeholder?: string
}

export const Select = forwardRef<HTMLSelectElement, SelectProps>(function Select(
  { label, error, options, placeholder, className, id, ...props },
  ref,
) {
  const selectId = id ?? props.name ?? label

  return (
    <div className="flex min-w-0 flex-col gap-1.5">
      {label ? (
        <label htmlFor={selectId} className="text-[11.5px] font-semibold text-ink-soft">
          {label}
        </label>
      ) : null}
      <select
        ref={ref}
        id={selectId}
        aria-invalid={error !== undefined}
        aria-describedby={error ? `${selectId}-error` : undefined}
        className={cn(
          'w-full appearance-none rounded-[11px] border bg-surface-alt px-3.5 py-2.5 text-[13px] text-ink transition-colors',
          error ? 'border-orange/60' : 'border-hairline focus:border-purple/60',
          className,
        )}
        {...props}
      >
        {placeholder ? <option value="">{placeholder}</option> : null}
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      {error ? (
        <p id={`${selectId}-error`} role="alert" className="text-[11px] font-medium text-orange-light">
          {error}
        </p>
      ) : null}
    </div>
  )
})
