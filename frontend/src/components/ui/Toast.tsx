import { CheckCircle2, XCircle } from 'lucide-react'
import { useEffect } from 'react'

import { cn } from '@/lib/cn'

export interface ToastMessage {
  id: number
  text: string
  tone: 'sucesso' | 'erro'
}

interface Props {
  toast: ToastMessage | null
  onDismiss: () => void
}

/**
 * Aviso curto de que a ação foi para o servidor e voltou. Some sozinho: a
 * confirmação real é a lista já atualizada atrás dele.
 */
export function Toast({ toast, onDismiss }: Props) {
  useEffect(() => {
    if (toast === null) {
      return
    }

    const timer = window.setTimeout(onDismiss, 4000)

    return () => window.clearTimeout(timer)
  }, [toast, onDismiss])

  if (toast === null) {
    return null
  }

  const Icon = toast.tone === 'sucesso' ? CheckCircle2 : XCircle

  return (
    <div
      role="status"
      aria-live="polite"
      className={cn(
        'fixed bottom-6 right-6 z-50 flex max-w-[380px] items-start gap-2.5 rounded-[13px] border px-4 py-3 text-[12.5px] font-semibold shadow-[0_18px_44px_rgba(0,0,0,0.5)]',
        toast.tone === 'sucesso'
          ? 'border-green/25 bg-[#0F1A15] text-green-bright'
          : 'border-orange/30 bg-[#1A120C] text-orange-light',
      )}
    >
      <Icon className="mt-px size-4 shrink-0" aria-hidden="true" />
      {toast.text}
    </div>
  )
}
