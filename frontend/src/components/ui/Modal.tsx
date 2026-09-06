import { X } from 'lucide-react'
import { useEffect, type ReactNode } from 'react'
import { createPortal } from 'react-dom'

import { cn } from '@/lib/cn'

interface ModalProps {
  isOpen: boolean
  onClose: () => void
  title: string
  subtitle?: string
  children: ReactNode
  footer?: ReactNode
  className?: string
}

/**
 * Diálogo em portal, para não herdar o empilhamento da linha que o abriu.
 * Fecha no Escape e no clique fora; a rolagem do fundo trava enquanto está
 * aberto, senão a página rola atrás do formulário.
 */
export function Modal({
  isOpen,
  onClose,
  title,
  subtitle,
  children,
  footer,
  className,
}: ModalProps) {
  useEffect(() => {
    if (!isOpen) {
      return
    }

    function handleKey(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        onClose()
      }
    }

    const previousOverflow = document.body.style.overflow

    document.body.style.overflow = 'hidden'
    document.addEventListener('keydown', handleKey)

    return () => {
      document.body.style.overflow = previousOverflow
      document.removeEventListener('keydown', handleKey)
    }
  }, [isOpen, onClose])

  if (!isOpen) {
    return null
  }

  return createPortal(
    <div
      className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/65 p-0 backdrop-blur-[2px] sm:p-6"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) {
          onClose()
        }
      }}
    >
      <div
        role="dialog"
        aria-modal="true"
        aria-label={title}
        className={cn(
          'flex h-full w-full flex-col rounded-none border-0 bg-surface shadow-[0_28px_70px_rgba(0,0,0,0.6)] sm:my-auto sm:h-auto sm:max-w-[560px] sm:rounded-[18px] sm:border sm:border-hairline-strong',
          className,
        )}
      >
        <header className="flex shrink-0 items-start gap-4 border-b border-hairline px-[22px] py-[18px]">
          <div>
            <h2 className="text-[15px] font-bold tracking-[-0.2px] text-ink">{title}</h2>
            {subtitle ? (
              <p className="mt-[3px] text-[11.5px] font-medium text-ink-dim">{subtitle}</p>
            ) : null}
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Fechar"
            className="ml-auto grid size-7 shrink-0 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-white/[0.06] hover:text-ink"
          >
            <X className="size-4" aria-hidden="true" />
          </button>
        </header>

        <div className="flex-1 overflow-y-auto px-[22px] py-[18px] sm:flex-none">{children}</div>

        {footer ? (
          <footer className="flex shrink-0 items-center justify-end gap-2.5 border-t border-hairline px-[22px] py-[15px]">
            {footer}
          </footer>
        ) : null}
      </div>
    </div>,
    document.body,
  )
}
