import { AlertTriangle } from 'lucide-react'

import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import type { AccountRow } from '@/types/api'

interface Props {
  account: AccountRow | null
  isDeleting: boolean
  /** Fica dentro do diálogo: fechar levaria embora a explicação da recusa. */
  error: string | null
  onCancel: () => void
  onConfirm: () => void
}

/**
 * Excluir só passa em conta que nunca recebeu movimento — o cascade do banco
 * levaria o extrato inteiro. O diálogo diz isso antes do clique, e conta quantos
 * lançamentos estão em jogo, para a pessoa não descobrir pelo erro.
 *
 * A API recusa também por referência: cartão que paga por esta conta e despesa
 * fixa que lança nela. Essas duas não dão para prever aqui, então a mensagem da
 * API aparece no próprio diálogo.
 */
export function DeleteAccountDialog({
  account,
  isDeleting,
  error,
  onCancel,
  onConfirm,
}: Props) {
  const movements = account?.movements_count ?? 0
  const hasHistory = movements > 0

  return (
    <Modal
      isOpen={account !== null}
      onClose={onCancel}
      title="Excluir conta"
      className="max-w-[460px]"
      footer={
        <>
          <Button variant="ghost" onClick={onCancel}>
            Manter
          </Button>
          <Button
            onClick={onConfirm}
            isLoading={isDeleting}
            disabled={hasHistory}
            className="bg-danger text-white shadow-[0_6px_20px_rgba(244,81,95,0.24)] hover:bg-danger/85"
          >
            Excluir
          </Button>
        </>
      }
    >
      {account ? (
        <div className="flex flex-col gap-4">
          <div className="flex gap-3.5">
            <span className="grid size-9 shrink-0 place-items-center rounded-[11px] bg-danger/[0.12] text-danger">
              <AlertTriangle className="size-4" aria-hidden="true" />
            </span>
            <div className="flex flex-col gap-2">
              <p className="text-[13px] font-semibold text-ink">
                {account.nickname} · {account.bank.name}
              </p>
              <p className="text-[12px] leading-relaxed text-ink-soft">
                {hasHistory
                  ? `Esta conta tem ${movements} ${movements === 1 ? 'lançamento' : 'lançamentos'} no extrato — excluí-la apagaria ${movements === 1 ? 'ele' : 'todos eles'}, inclusive o outro lado das transferências. Arquive a conta: ela sai dos seletores e dos totais, e o histórico fica.`
                  : 'Nenhum lançamento passou por esta conta — ela some sem deixar rastro. Se algum cartão paga a fatura por ela ou alguma despesa fixa lança nela, a exclusão é recusada.'}
              </p>
            </div>
          </div>

          {error ? (
            <p
              role="alert"
              className="rounded-[11px] border border-orange/25 bg-orange/[0.07] px-3.5 py-2.5 text-[11.5px] leading-relaxed text-orange-light"
            >
              {error}
            </p>
          ) : null}
        </div>
      ) : null}
    </Modal>
  )
}
