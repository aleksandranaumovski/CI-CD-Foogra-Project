import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'
import { useUiStore } from '../../context/UiContext'
import { fieldError, toApiError } from '../../lib/api'
import { auth as authApi } from '../../lib/services'
import type { ApiError } from '../../lib/types'

type Mode = 'sign-in' | 'sign-up' | 'forgot'

/**
 * The template's magnific-popup sign-in dialog, rebuilt as a React modal that
 * reuses the same `#sign-in-dialog` markup and classes.
 */
export function SignInModal() {
  const { signInOpen, closeSignIn, notify } = useUiStore()
  const { login, register } = useAuth()

  const [mode, setMode] = useState<Mode>('sign-in')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<ApiError | null>(null)
  const [form, setForm] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'customer' as 'customer' | 'owner',
  })

  // Reset to a clean slate whenever the dialog reopens.
  useEffect(() => {
    if (signInOpen) {
      setMode('sign-in')
      setError(null)
      setForm({ name: '', email: '', password: '', password_confirmation: '', role: 'customer' })
    }
  }, [signInOpen])

  useEffect(() => {
    if (!signInOpen) return

    const onEscape = (e: KeyboardEvent) => e.key === 'Escape' && closeSignIn()
    window.addEventListener('keydown', onEscape)

    return () => window.removeEventListener('keydown', onEscape)
  }, [signInOpen, closeSignIn])

  if (!signInOpen) return null

  const set = (key: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setForm((current) => ({ ...current, [key]: e.target.value }))

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setError(null)

    try {
      if (mode === 'sign-in') {
        const user = await login({ email: form.email, password: form.password })
        notify(`Welcome back, ${user.name.split(' ')[0]}.`)
        closeSignIn()
      } else if (mode === 'sign-up') {
        const user = await register({
          name: form.name,
          email: form.email,
          password: form.password,
          password_confirmation: form.password_confirmation,
          role: form.role,
        })
        notify(`Welcome to Foogra, ${user.name.split(' ')[0]}.`)
        closeSignIn()
      } else {
        const { message } = await authApi.forgotPassword(form.email)
        notify(message)
        setMode('sign-in')
      }
    } catch (err) {
      setError(toApiError(err))
    } finally {
      setBusy(false)
    }
  }

  const heading = mode === 'sign-in' ? 'Sign In' : mode === 'sign-up' ? 'Create account' : 'Reset password'

  return (
    <div className="foogra-modal-backdrop" onMouseDown={(e) => e.target === e.currentTarget && closeSignIn()}>
      <div id="sign-in-dialog" className="zoom-anim-dialog foogra-modal" role="dialog" aria-modal="true">
        <button type="button" className="foogra-modal-close" aria-label="Close" onClick={closeSignIn}>
          <i className="icon_close" />
        </button>

        <div className="modal_header">
          <h3>{heading}</h3>
        </div>

        <form onSubmit={submit}>
          <div className="sign-in-wrapper">
            {error && (
              <div className="foogra-alert error">
                {error.message}
              </div>
            )}

            {mode === 'sign-in' && (
              <div className="foogra-demo-hint">
                <strong>Demo logins</strong> — password <code>password</code>
                <div className="foogra-demo-buttons">
                  {[
                    ['Admin', 'admin@foogra.test'],
                    ['Owner', 'owner@foogra.test'],
                    ['Customer', 'customer@foogra.test'],
                  ].map(([label, email]) => (
                    <button
                      key={email}
                      type="button"
                      onClick={() => setForm((c) => ({ ...c, email, password: 'password' }))}
                    >
                      {label}
                    </button>
                  ))}
                </div>
              </div>
            )}

            {mode === 'sign-up' && (
              <div className="form-group">
                <label>Full name</label>
                <input
                  type="text"
                  className="form-control"
                  value={form.name}
                  onChange={set('name')}
                  required
                />
                <i className="icon_profile" />
                {fieldError(error, 'name') && <small className="foogra-field-error">{fieldError(error, 'name')}</small>}
              </div>
            )}

            <div className="form-group">
              <label>Email</label>
              <input type="email" className="form-control" value={form.email} onChange={set('email')} required />
              <i className="icon_mail_alt" />
              {fieldError(error, 'email') && <small className="foogra-field-error">{fieldError(error, 'email')}</small>}
            </div>

            {mode !== 'forgot' && (
              <div className="form-group">
                <label>Password</label>
                <input
                  type="password"
                  className="form-control"
                  value={form.password}
                  onChange={set('password')}
                  required
                />
                <i className="icon_lock_alt" />
                {fieldError(error, 'password') && (
                  <small className="foogra-field-error">{fieldError(error, 'password')}</small>
                )}
              </div>
            )}

            {mode === 'sign-up' && (
              <>
                <div className="form-group">
                  <label>Confirm password</label>
                  <input
                    type="password"
                    className="form-control"
                    value={form.password_confirmation}
                    onChange={set('password_confirmation')}
                    required
                  />
                  <i className="icon_lock_alt" />
                </div>
                <div className="form-group">
                  <label>I am joining as</label>
                  <div className="styled-select">
                    <select value={form.role} onChange={set('role')}>
                      <option value="customer">A diner — book tables and write reviews</option>
                      <option value="owner">A restaurant owner — list my venue</option>
                    </select>
                  </div>
                </div>
              </>
            )}

            {mode === 'forgot' && (
              <p className="foogra-muted">
                We will email you a link to choose a new password. In development it lands in
                Mailpit at <a href="http://localhost:8025" target="_blank" rel="noreferrer">localhost:8025</a>.
              </p>
            )}

            {mode === 'sign-in' && (
              <div className="clearfix add_bottom_15">
                <div className="float-end">
                  <a href="#0" onClick={(e) => { e.preventDefault(); setMode('forgot'); setError(null) }}>
                    Forgot Password?
                  </a>
                </div>
              </div>
            )}

            <div className="text-center">
              <input
                type="submit"
                value={busy ? 'Please wait…' : heading}
                className="btn_1 full-width mb_5"
                disabled={busy}
              />

              {mode === 'sign-in' && (
                <>
                  Don’t have an account?{' '}
                  <a href="#0" onClick={(e) => { e.preventDefault(); setMode('sign-up'); setError(null) }}>
                    Sign up
                  </a>
                </>
              )}
              {mode !== 'sign-in' && (
                <a href="#0" onClick={(e) => { e.preventDefault(); setMode('sign-in'); setError(null) }}>
                  Back to sign in
                </a>
              )}
            </div>

            {mode === 'sign-in' && (
              <p className="text-center foogra-muted" style={{ marginTop: 12 }}>
                <Link to="/restaurants" onClick={closeSignIn}>
                  Or keep browsing without an account
                </Link>
              </p>
            )}
          </div>
        </form>
      </div>
    </div>
  )
}
