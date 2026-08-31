import { AlertTriangle, Bell } from 'lucide-react'

import { cn } from '@/lib/cn'
import type { DashboardAlert } from '@/types/api'

export function AlertsStrip({ alerts }: { alerts: DashboardAlert[] }) {
  if (alerts.length === 0) {
    return null
  }

  return (
    <section aria-label="Alertas" className="flex flex-col gap-2.5">
      {alerts.map((alert) => (
        <div
          key={`${alert.level}-${alert.title}`}
          className={cn(
            'flex items-start gap-3 rounded-[14px] border px-4 py-3',
            alert.level === 'alerta'
              ? 'border-orange/25 bg-orange/[0.07]'
              : 'border-purple/25 bg-purple/[0.06]',
          )}
        >
          {alert.level === 'alerta' ? (
            <AlertTriangle className="mt-px size-4 shrink-0 text-orange" aria-hidden="true" />
          ) : (
            <Bell className="mt-px size-4 shrink-0 text-purple-light" aria-hidden="true" />
          )}
          <div>
            <p className="text-[12.5px] font-bold text-ink">{alert.title}</p>
            <p className="mt-0.5 text-[11.5px] leading-relaxed text-ink-soft">{alert.message}</p>
          </div>
        </div>
      ))}
    </section>
  )
}
