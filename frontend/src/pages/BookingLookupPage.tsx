import { useState } from 'react'
import { useNavigate } from 'react-router-dom'

/** Lets a guest who booked without an account find their reservation again. */
export function BookingLookupPage() {
  const navigate = useNavigate()
  const [reference, setReference] = useState('')
  const [email, setEmail] = useState('')

  return (
    <div className="container margin_60_40">
      <div className="row justify-content-center">
        <div className="col-lg-6">
          <div className="admin-panel">
            <h3>Find a booking</h3>
            <p className="foogra-muted">
              Enter the reference from your confirmation email, plus the address you booked with.
            </p>

            <form
              onSubmit={(e) => {
                e.preventDefault()
                navigate(`/booking/${reference.trim().toUpperCase()}?email=${encodeURIComponent(email.trim())}`)
              }}
            >
              <div className="admin-form-grid">
                <div className="full">
                  <label htmlFor="lookup_ref">Booking reference</label>
                  <input
                    id="lookup_ref"
                    type="text"
                    placeholder="FG-XXXXXX"
                    value={reference}
                    onChange={(e) => setReference(e.target.value)}
                    required
                  />
                </div>
                <div className="full">
                  <label htmlFor="lookup_email">Email address</label>
                  <input
                    id="lookup_email"
                    type="email"
                    placeholder="you@example.com"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    required
                  />
                </div>
              </div>

              <div className="foogra-form-actions">
                <button type="submit" className="btn_1">Find my booking</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  )
}
