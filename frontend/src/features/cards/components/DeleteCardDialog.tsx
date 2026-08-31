import { AlertTriangle } from 'lucide-react'

import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import { formatBRLCompact } from '@/lib/format'
import type { CardDetail } from '@/types/api'

interface Props {
  card: CardDetail | null
  isDeleting: boolean
  onCancel: () => void
  onConfirm: () => void
}

/**
 * Excluir só passa em cartão sem compra alguma; com histórico, a API recusa e
 * manda arquivar. O diálogo já diz isso antes do clique, para o usuário não
 * descobrir pelo erro.
 */
export function DeleteCardDialog({ card, isDeleting, onCancel, onConfirm }: Props) {
  const hasHistory = card !== null && card.used_amount > 0

  return (
    <Modal
      isOpen={card !== null}
      onClose={onCancel}
      title="Excluir cartão"
      className="max-w-[440px]"
      footer={
        <>
          <Button variant="ghost" onClick={onCancel}>
            Manter
          </Button>
          <Button
            onClick={onConfirm}
            isLoading={isDeleting}
            className="bg-danger text-white shadow-[0_6px_20px_rgba(244,81,95,0.24)] hover:bg-danger/85"
          >
            Excluir
          </Button>
        </>
      }
    >
      {card ? (
        <div className="flex gap-3.5">
          <span className="grid size-9 shrink-0 place-items-center rounded-[11px] bg-danger/[0.12] text-danger">
            <AlertTriangle className="size-4" aria-hidden="true" />
          </span>
          <div className="flex flex-col gap-2">
            <p className="text-[13px] font-semibold text-ink">
              {card.nickname} ···· {card.last_four}
            </p>
            <p className="text-[12px] leading-relaxed text-ink-soft">
              {hasHistory
                ? `Este cartão tem ${formatBRLCompact(card.used_amount)} em parcelas não pagas. Cartão com histórico não se exclui — arquive-o para tirá-lo da tela sem apagar os lançamentos já registrados.`
                : 'Só é possível excluir cartão que nunca foi usado. Se houver qualquer compra registrada, a exclusão é recusada e o caminho passa a ser arquivar.'}
            </p>
          </div>
        </div>
      ) : null}
    </Modal>
  )
}
