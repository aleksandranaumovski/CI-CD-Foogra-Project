import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useMutation } from '@tanstack/react-query'
import { auth as authApi } from '../lib/services'
import { fieldError, toApiError } from '../lib/api'
import { useUiStore } from '../context/UiContext'
import type { ApiError } from '../lib/types'

/**
 * The landing page for the reset link Laravel emails. Token and address come in
 * on the query string; the SPA just collects the new password.
 */
export function ResetPasswordPage() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const { notify, openSignIn } = useUiStore()

  const token = searchParams.get('token') ?? ''
  const email = searchParams.get('email') ?? ''

  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [error, setError] = useState<ApiError | null>(null)

  const reset = useMutation({
    mutationFn: () =>
      authApi.resetPassword({ token, email, password, password_confirmation: confirmation }),
    onSuccess: ({ message }) => {
      notify(message)
      navigate('/')
      openSignIn()
    },
    onError: (err) => setError(toApiError(err)),
  })

  if (!token || !email) {
    return (
      <div className="container margin_60_40">
        <div className="row justify-content-center">
          <div className="col-lg-6">
            <div className="admin-panel">
              <h3>Reset link incomplete</h3>
              <p className="foogra-muted">
                This page needs the token and email from your reset email. Open the link in that
                message, or <Link to="/">request a new one</Link>.
              </p>
            </div>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="container margin_60_40">
      <div className="row justify-content-center">
        <div className="col-lg-6">
          <div className="admin-panel">
            <h3>Choose a new password</h3>
            <p className="foogra-muted">Resetting the password for <strong>{email}</strong>.</p>

            {error && !error.errors && <div className="foogra-alert error">{error.message}</div>}
            {fieldError(error, 'email') && <div className="foogra-alert error">{fieldError(error, 'email')}</div>}

            <form onSubmit={(e) => { e.preventDefault(); setError(null); reset.mutate() }}>
              <div className="admin-form-grid">
                <div className="full">
                  <label htmlFor="reset_pw">New password</label>
                  <input
                    id="reset_pw"
                    type="password"
                    minLength={8}
                    required
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                  />
                  {fieldError(error, 'password') && (
                    <small className="foogra-field-error">{fieldError(error, 'password')}</small>
                  )}
                </div>
                <div className="full">
                  <label htmlFor="reset_confirm">Confirm new password</label>
                  <input
                    id="reset_confirm"
                    type="password"
                    minLength={8}
                    required
                    value={confirmation}
                    onChange={(e) => setConfirmation(e.target.value)}
                  />
                </div>
              </div>

              <div className="foogra-form-actions">
                <button type="submit" className="btn_1" disabled={reset.isPending}>
                  {reset.isPending ? 'Saving…' : 'Reset password'}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  )
}
