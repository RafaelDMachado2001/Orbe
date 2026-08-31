import { CornerDownRight, Lock, Pencil, Plus, Trash2 } from 'lucide-react'

import { Button } from '@/components/ui/Button'
import { Card, CardHeader } from '@/components/ui/Card'
import { Money } from '@/components/ui/Money'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/ui/States'
import { cn } from '@/lib/cn'
import type { CategoryNode } from '@/types/api'

interface Props {
  title: string
  subtitle: string
  nodes: CategoryNode[]
  emptyDescription: string
  isRefreshing: boolean
  onEdit: (node: CategoryNode) => void
  onDelete: (node: CategoryNode) => void
  onAddChild: (parent: CategoryNode) => void
  action?: React.ReactNode
}

/**
 * A árvore de dois níveis. A mãe mostra o total dela somado ao das filhas —
 * quem separa "Casa" em "Aluguel" e "Condomínio" quer ver os três números, mas
 * é o total da casa que responde "quanto a moradia custou".
 */
export function CategoryTree({
  title,
  subtitle,
  nodes,
  emptyDescription,
  isRefreshing,
  onEdit,
  onDelete,
  onAddChild,
  action,
}: Props) {
  return (
    <Card className="flex flex-col gap-4">
      <CardHeader title={title} subtitle={subtitle} action={action} />

      {nodes.length === 0 ? (
        <EmptyState title="Nenhuma categoria aqui" description={emptyDescription} />
      ) : (
        <ul className={cn('flex flex-col gap-1.5 transition-opacity', isRefreshing && 'opacity-55')}>
          {nodes.map((node) => (
            <li key={node.id} className="flex flex-col gap-1.5">
              <Row node={node} onEdit={onEdit} onDelete={onDelete} onAddChild={onAddChild} />

              {node.children.length > 0 ? (
                <ul className="flex flex-col gap-1.5 pl-7">
                  {node.children.map((child) => (
                    <li key={child.id}>
                      <Row
                        node={child}
                        isChild
                        onEdit={onEdit}
                        onDelete={onDelete}
                        onAddChild={onAddChild}
                      />
                    </li>
                  ))}
                </ul>
              ) : null}
            </li>
          ))}
        </ul>
      )}
    </Card>
  )
}

interface RowProps {
  node: CategoryNode
  isChild?: boolean
  onEdit: (node: CategoryNode) => void
  onDelete: (node: CategoryNode) => void
  onAddChild: (parent: CategoryNode) => void
}

function Row({ node, isChild = false, onEdit, onDelete, onAddChild }: RowProps) {
  const hasChildrenTotal = node.total_with_children !== node.month_total

  return (
    <div className="group flex items-center gap-3 rounded-[13px] border border-hairline bg-surface-alt px-3.5 py-2.5 transition-colors hover:border-hairline-strong">
      {isChild ? (
        <CornerDownRight className="size-3.5 shrink-0 text-ink-faint" aria-hidden="true" />
      ) : null}

      <span
        aria-hidden="true"
        className="size-[10px] shrink-0 rounded-full"
        style={{ backgroundColor: node.color }}
      />

      <div className="flex min-w-0 flex-col gap-0.5">
        <span className="flex items-center gap-1.5 truncate text-[13px] font-semibold text-ink">
          {node.name}
          {node.is_system ? (
            <span title="Categoria do sistema: pode ser renomeada, não excluída">
              <Lock className="size-3 text-ink-faint" aria-hidden="true" />
            </span>
          ) : null}
        </span>
        <span className="truncate text-[10.5px] font-medium text-ink-muted">
          {node.entries_count === 0
            ? 'sem lançamentos'
            : `${node.entries_count} ${node.entries_count === 1 ? 'lançamento' : 'lançamentos'}`}
          {node.children.length > 0 ? ` · ${node.children.length} sub` : ''}
        </span>
      </div>

      <div className="ml-auto flex shrink-0 items-center gap-3">
        <div className="flex flex-col items-end gap-0.5">
          <Money
            value={node.total_with_children}
            className={cn(
              'text-[13px] font-semibold',
              node.total_with_children > 0 ? 'text-ink' : 'text-ink-faint',
            )}
          />
          {hasChildrenTotal ? (
            <span className="text-[10px] font-medium text-ink-faint">
              nela mesma: <Money value={node.month_total} className="text-[10px]" />
            </span>
          ) : null}
        </div>

        <div className="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
          {isChild ? null : (
            <IconButton label="Nova subcategoria" onClick={() => onAddChild(node)}>
              <Plus className="size-3.5" aria-hidden="true" />
            </IconButton>
          )}
          <IconButton label="Editar categoria" onClick={() => onEdit(node)}>
            <Pencil className="size-3.5" aria-hidden="true" />
          </IconButton>
          {node.is_system ? null : (
            <IconButton label="Excluir categoria" onClick={() => onDelete(node)} isDanger>
              <Trash2 className="size-3.5" aria-hidden="true" />
            </IconButton>
          )}
        </div>
      </div>
    </div>
  )
}

function IconButton({
  label,
  onClick,
  isDanger = false,
  children,
}: {
  label: string
  onClick: () => void
  isDanger?: boolean
  children: React.ReactNode
}) {
  return (
    <Button
      variant="subtle"
      aria-label={label}
      title={label}
      onClick={onClick}
      className={cn('size-7 rounded-lg p-0', isDanger && 'text-danger hover:text-danger')}
    >
      {children}
    </Button>
  )
}

export function CategoryTreeSkeleton() {
  return (
    <Card className="flex flex-col gap-4">
      <div className="flex flex-col gap-2">
        <Skeleton className="h-3.5 w-40" />
        <Skeleton className="h-2.5 w-64" />
      </div>
      <div className="flex flex-col gap-1.5">
        {Array.from({ length: 6 }).map((_, index) => (
          <Skeleton key={index} className="h-[52px] w-full rounded-[13px]" />
        ))}
      </div>
    </Card>
  )
}
