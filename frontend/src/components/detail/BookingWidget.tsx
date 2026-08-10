import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { catalogue, diner } from '../../lib/services'
import { fieldError, toApiError } from '../../lib/api'
import { useAuth } from '../../context/AuthContext'
import { useUiStore } from '../../context/UiContext'
import type { ApiError, Restaurant } from '../../lib/types'

const PARTY_SIZES = [1, 2, 3, 4, 5, 6, 7, 8]

/**
 * The template's `.box_booking` sidebar, wired to real availability.
 *
 * Time slots come from the restaurant's opening hours via
 * `/restaurants/{slug}/availability`, so the widget can never offer a sitting
 * the kitchen is closed for.
 */
export function BookingWidget({ restaurant }: { restaurant: Restaurant }) {
  const { user, isAuthenticated } = useAuth()
  const { notify, openSignIn } = useUiStore()
  const navigate = useNavigate()

  const today = useMemo(() => new Date().toISOString().slice(0, 10), [])
  const [date, setDate] = useState(today)
  const [time, setTime] = useState('')
  const [partySize, setPartySize] = useState(2)
  const [openDropdown, setOpenDropdown] = useState<'time' | 'people' | null>(null)
  const [error, setError] = useState<ApiError | null>(null)
  const [guest, setGuest] = useState({ name: '', email: '', phone: '', notes: '' })

  const availability = useQuery({
    queryKey: ['availability', restaurant.slug, date],
    queryFn: () => catalogue.availability(restaurant.slug, date),
    enabled: Boolean(date),
  })

  // The chosen time may not exist on a newly picked date — drop it if so.
  useEffect(() => {
    if (!time || !availability.data) return

    const stillOffered = availability.data.services.some((service) => service.times.includes(time))
    if (!stillOffered) setTime('')
  }, [availability.data, time])

  const booking = useMutation({
    mutationFn: () =>
      diner.book({
        restaurant_id: restaurant.id,
        guest_name: isAuthenticated ? (user?.name ?? '') : guest.name,
        guest_email: isAuthenticated ? (user?.email ?? '') : guest.email,
        guest_phone: isAuthenticated ? undefined : guest.phone || undefined,
        booking_date: date,
        booking_time: time,
        party_size: partySize,
        notes: guest.notes || undefined,
      }),
    onSuccess: ({ data, message }) => {
      notify(message)
      navigate(`/booking/${data.reference}?email=${encodeURIComponent(data.guest.email)}`)
    },
    onError: (err) => {
      const apiError = toApiError(err)
      setError(apiError)
      notify(apiError.message, 'error')
    },
  })

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)

    if (!time) {
      setError({ message: 'Choose a time for your table.' })

      return
    }

    booking.mutate()
  }

  const closed = availability.data?.is_closed ?? false
  const hasSlots = (availability.data?.services ?? []).some((s) => s.times.length > 0)

  return (
    <div className="box_booking">
      <div className="head">
        <h3>Book your table</h3>
        {restaurant.discount_percent ? (
          <div className="offer">Up to -{restaurant.discount_percent}% off</div>
        ) : null}
      </div>

      <div className="main">
        <form onSubmit={submit}>
          {/*
            Deliberately NOT id="datepicker_field": detail-page.css hides that
            element, because in the template it was a hidden proxy input for
            the jQuery inline calendar. This is a real native date picker.
          */}
          <input
            type="date"
            className="form-control foogra-date"
            value={date}
            min={today}
            onChange={(e) => { setDate(e.target.value); setTime('') }}
            aria-label="Booking date"
          />

          {/* ------------------------------------------------- time dropdown */}
          <div className={`dropdown time${openDropdown === 'time' ? ' show' : ''}`}>
            <a
              href="#0"
              onClick={(e) => { e.preventDefault(); setOpenDropdown(openDropdown === 'time' ? null : 'time') }}
            >
              Hour <span id="selected_time">{time || '—'}</span>
            </a>

            <div className={`dropdown-menu${openDropdown === 'time' ? ' show' : ''}`}>
              <div className="dropdown-menu-content">
                {availability.isPending && <p className="foogra-muted">Checking availability…</p>}

                {closed && <p className="foogra-muted">Closed on this date. Please pick another day.</p>}

                {!closed && !availability.isPending && !hasSlots && (
                  <p className="foogra-muted">No sittings left today — try tomorrow.</p>
                )}

                {(availability.data?.services ?? []).map((service) =>
                  service.times.length === 0 ? null : (
                    <div key={service.service}>
                      <h4>{service.service === 'lunch' ? 'Lunch' : 'Dinner'}</h4>
                      <div className="radio_select add_bottom_15">
                        <ul>
                          {service.times.map((slot) => (
                            <li key={slot}>
                              <input
                                type="radio"
                                id={`time_${slot}`}
                                name="time"
                                value={slot}
                                checked={time === slot}
                                onChange={() => { setTime(slot); setOpenDropdown(null) }}
                              />
                              <label htmlFor={`time_${slot}`}>
                                {slot}
                                {restaurant.discount_percent ? <em>-{restaurant.discount_percent}%</em> : null}
                              </label>
                            </li>
                          ))}
                        </ul>
                      </div>
                    </div>
                  ),
                )}
              </div>
            </div>
          </div>

          {/* ----------------------------------------------- people dropdown */}
          <div className={`dropdown people${openDropdown === 'people' ? ' show' : ''}`}>
            <a
              href="#0"
              onClick={(e) => { e.preventDefault(); setOpenDropdown(openDropdown === 'people' ? null : 'people') }}
            >
              People <span id="selected_people">{partySize}</span>
            </a>

            <div className={`dropdown-menu${openDropdown === 'people' ? ' show' : ''}`}>
              <div className="dropdown-menu-content">
                <h4>How many people?</h4>
                <div className="radio_select">
                  <ul>
                    {PARTY_SIZES.map((size) => (
                      <li key={size}>
                        <input
                          type="radio"
                          id={`people_${size}`}
                          name="people"
                          value={size}
                          checked={partySize === size}
                          onChange={() => { setPartySize(size); setOpenDropdown(null) }}
                        />
                        <label htmlFor={`people_${size}`}>{size}</label>
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            </div>
          </div>

          {/* Guests book without an account, so ask for contact details inline. */}
          {!isAuthenticated && (
            <div className="foogra-guest-fields">
              <input
                type="text"
                className="form-control"
                placeholder="Your name"
                required
                value={guest.name}
                onChange={(e) => setGuest({ ...guest, name: e.target.value })}
              />
              {fieldError(error, 'guest_name') && (
                <small className="foogra-field-error">{fieldError(error, 'guest_name')}</small>
              )}

              <input
                type="email"
                className="form-control"
                placeholder="Your email"
                required
                value={guest.email}
                onChange={(e) => setGuest({ ...guest, email: e.target.value })}
              />
              {fieldError(error, 'guest_email') && (
                <small className="foogra-field-error">{fieldError(error, 'guest_email')}</small>
              )}

              <input
                type="tel"
                className="form-control"
                placeholder="Phone (optional)"
                value={guest.phone}
                onChange={(e) => setGuest({ ...guest, phone: e.target.value })}
              />

              <p className="foogra-muted foogra-signin-nudge">
                <a href="#0" onClick={(e) => { e.preventDefault(); openSignIn() }}>Sign in</a> to
                keep all your bookings in one place.
              </p>
            </div>
          )}

          <textarea
            className="form-control foogra-notes"
            placeholder="Anything the restaurant should know? (optional)"
            rows={2}
            value={guest.notes}
            onChange={(e) => setGuest({ ...guest, notes: e.target.value })}
          />

          {error && !error.errors && <div className="foogra-alert error">{error.message}</div>}
          {fieldError(error, 'booking_time') && (
            <div className="foogra-alert error">{fieldError(error, 'booking_time')}</div>
          )}

          <button type="submit" className="btn_1 full-width mb_5" disabled={booking.isPending || !time}>
            {booking.isPending ? 'Reserving…' : 'Reserve Now'}
          </button>
        </form>

        <div className="text-center"><small>No money charged at this step</small></div>
      </div>
    </div>
  )
}
