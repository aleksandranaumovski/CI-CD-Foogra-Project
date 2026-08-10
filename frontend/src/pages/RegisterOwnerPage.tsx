import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useMutation } from '@tanstack/react-query'
import { useAuth } from '../context/AuthContext'
import { useUiStore } from '../context/UiContext'
import { fieldError, toApiError } from '../lib/api'
import type { ApiError } from '../lib/types'

/**
 * The "Are you a Restaurant Owner?" call-to-action from the home page. Creates
 * an owner account and drops the new owner straight into the dashboard.
 */
export function RegisterOwnerPage() {
  const { register, isStaff } = useAuth()
  const { notify } = useUiStore()
  const navigate = useNavigate()

  const [form, setForm] = useState({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
  })
  const [error, setError] = useState<ApiError | null>(null)

  const submit = useMutation({
    mutationFn: () => register({ ...form, role: 'owner' }),
    onSuccess: (user) => {
      notify(`Welcome aboard, ${user.name.split(' ')[0]}. Add your first restaurant below.`)
      navigate('/admin/restaurants/new')
    },
    onError: (err) => setError(toApiError(err)),
  })

  if (isStaff) {
    return (
      <div className="container margin_60_40">
        <div className="admin-panel" style={{ textAlign: 'center' }}>
          <h3>You already have a business account</h3>
          <p className="foogra-muted">Head to the dashboard to manage your restaurants.</p>
          <Link to="/admin" className="btn_1">Open dashboard</Link>
        </div>
      </div>
    )
  }

  const set = (key: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setForm({ ...form, [key]: e.target.value })

  return (
    <div className="container margin_60_40">
      <div className="row justify-content-center">
        <div className="col-lg-8">
          <div className="foogra-page-head" style={{ textAlign: 'center', borderBottom: 'none' }}>
            <h1>List your restaurant on Foogra</h1>
            <p className="foogra-muted">
              Create a business account to publish your venue, build your menu, take bookings and
              reply to reviews — all from one dashboard.
            </p>
          </div>

          <div className="admin-panel">
            {error && !error.errors && <div className="foogra-alert error">{error.message}</div>}

            <form onSubmit={(e) => { e.preventDefault(); setError(null); submit.mutate() }}>
              <div className="admin-form-grid">
                <div>
                  <label htmlFor="own_name">Your name</label>
                  <input id="own_name" type="text" required value={form.name} onChange={set('name')} />
                  {fieldError(error, 'name') && (
                    <small className="foogra-field-error">{fieldError(error, 'name')}</small>
                  )}
                </div>

                <div>
                  <label htmlFor="own_email">Work email</label>
                  <input id="own_email" type="email" required value={form.email} onChange={set('email')} />
                  {fieldError(error, 'email') && (
                    <small className="foogra-field-error">{fieldError(error, 'email')}</small>
                  )}
                </div>

                <div>
                  <label htmlFor="own_phone">Phone</label>
                  <input id="own_phone" type="tel" value={form.phone} onChange={set('phone')} />
                </div>

                <div>
                  <label htmlFor="own_pw">Password</label>
                  <input
                    id="own_pw"
                    type="password"
                    required
                    minLength={8}
                    value={form.password}
                    onChange={set('password')}
                  />
                  {fieldError(error, 'password') && (
                    <small className="foogra-field-error">{fieldError(error, 'password')}</small>
                  )}
                </div>

                <div className="full">
                  <label htmlFor="own_pw2">Confirm password</label>
                  <input
                    id="own_pw2"
                    type="password"
                    required
                    minLength={8}
                    value={form.password_confirmation}
                    onChange={set('password_confirmation')}
                  />
                </div>
              </div>

              <div className="foogra-form-actions">
                <button type="submit" className="btn_1" disabled={submit.isPending}>
                  {submit.isPending ? 'Creating your account…' : 'Create business account'}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  )
}
