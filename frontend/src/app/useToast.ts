import { useCallback, useState } from 'react'

import type { ToastMessage } from '@/components/ui/Toast'

export interface ToastController {
  toast: ToastMessage | null
  notify: (text: string, tone?: ToastMessage['tone']) => void
  dismiss: () => void
}

/**
 * Aviso curto do resultado de uma ação. Um por vez: dois avisos empilhados
 * competiriam pela atenção logo depois de a tela já ter mudado atrás deles.
 */
export function useToast(): ToastController {
  const [toast, setToast] = useState<ToastMessage | null>(null)

  return {
    toast,
    notify: useCallback((text: string, tone: ToastMessage['tone'] = 'sucesso') => {
      setToast({ id: Date.now(), text, tone })
    }, []),
    dismiss: useCallback(() => setToast(null), []),
  }
}
