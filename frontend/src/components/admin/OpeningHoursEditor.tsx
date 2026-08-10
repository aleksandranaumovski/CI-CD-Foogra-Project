import { useMemo, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { admin } from '../../lib/services'
import { toApiError } from '../../lib/api'
import { useUiStore } from '../../context/UiContext'
import type { OpeningHour } from '../../lib/types'

const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
const SERVICES = ['lunch', 'dinner'] as const

interface Slot {
  day_of_week: number
  service: 'lunch' | 'dinner'
  is_closed: boolean
  opens_at: string
  closes_at: string
}

/**
 * Edits the whole timetable and saves it in one PUT, which is what the API's
 * sync endpoint expects — fourteen separate row updates would be far worse.
 */
export function OpeningHoursEditor({ slug, hours }: { slug: string; hours: OpeningHour[] }) {
  const { notify } = useUiStore()
  const queryClient = useQueryClient()

  const initial = useMemo<Slot[]>(() => {
    return DAYS.flatMap((_, day) =>
      SERVICES.map((service) => {
        const existing = hours.find((h) => h.day_of_week === day && h.service === service)

        return {
          day_of_week: day,
          service,
          is_closed: existing?.is_closed ?? true,
          opens_at: existing?.opens_at ?? (service === 'lunch' ? '11:00' : '18:00'),
          closes_at: existing?.closes_at ?? (service === 'lunch' ? '15:00' : '23:00'),
        }
      }),
    )
  }, [hours])

  const [slots, setSlots] = useState<Slot[]>(initial)

  const save = useMutation({
    mutationFn: () => admin.syncOpeningHours(slug, slots),
    onSuccess: () => {
      notify('Opening hours saved.')
      void queryClient.invalidateQueries({ queryKey: ['admin', 'restaurant', slug] })
      void queryClient.invalidateQueries({ queryKey: ['restaurant', slug] })
    },
    onError: (error) => notify(toApiError(error).message, 'error'),
  })

  const patch = (day: number, service: string, changes: Partial<Slot>) =>
    setSlots((current) =>
      current.map((slot) =>
        slot.day_of_week === day && slot.service === service ? { ...slot, ...changes } : slot,
      ),
    )

  /** Copies Monday's two sittings across Tuesday to Saturday. */
  const copyMondayToWeek = () => {
    const monday = slots.filter((slot) => slot.day_of_week === 1)

    setSlots((current) =>
      current.map((slot) => {
        if (slot.day_of_week < 2 || slot.day_of_week > 6) return slot

        const source = monday.find((m) => m.service === slot.service)

        return source ? { ...slot, ...source, day_of_week: slot.day_of_week } : slot
      }),
    )
  }

  return (
    <div className="admin-panel">
      <h3>Opening hours</h3>
      <p className="foogra-muted" style={{ marginBottom: 16 }}>
        These drive the “Now Open” badge and the bookable slots on the detail page. A sitting that
        closes after midnight (18:00–01:00) is handled correctly.
      </p>

      <div className="hours-grid" style={{ marginBottom: 10 }}>
        <div className="head">Day / sitting</div>
        <div className="head">Opens</div>
        <div className="head">Closes</div>
        <div className="head">Closed</div>
      </div>

      {slots.map((slot) => (
        <div className="hours-grid" key={`${slot.day_of_week}-${slot.service}`} style={{ marginBottom: 8 }}>
          <div>
            <strong>{DAYS[slot.day_of_week].slice(0, 3)}</strong>{' '}
            <span className="foogra-muted">{slot.service}</span>
          </div>
          <input
            type="time"
            value={slot.opens_at}
            disabled={slot.is_closed}
            onChange={(e) => patch(slot.day_of_week, slot.service, { opens_at: e.target.value })}
          />
          <input
            type="time"
            value={slot.closes_at}
            disabled={slot.is_closed}
            onChange={(e) => patch(slot.day_of_week, slot.service, { closes_at: e.target.value })}
          />
          <label style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12 }}>
            <input
              type="checkbox"
              checked={slot.is_closed}
              onChange={(e) => patch(slot.day_of_week, slot.service, { is_closed: e.target.checked })}
            />
            Closed
          </label>
        </div>
      ))}

      <div className="foogra-form-actions">
        <button type="button" className="btn_1" onClick={() => save.mutate()} disabled={save.isPending}>
          {save.isPending ? 'Saving…' : 'Save opening hours'}
        </button>
        <button type="button" className="btn_1 outline" onClick={copyMondayToWeek}>
          Copy Monday to Tue–Sat
        </button>
        <button type="button" className="btn_1 outline" onClick={() => setSlots(initial)}>
          Reset
        </button>
      </div>
    </div>
  )
}
