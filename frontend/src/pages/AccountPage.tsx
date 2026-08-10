import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useMutation } from '@tanstack/react-query'
import { useAuth } from '../context/AuthContext'
import { useUiStore } from '../context/UiContext'
import { auth as authApi } from '../lib/services'
import { fieldError, toApiError } from '../lib/api'
import type { ApiError } from '../lib/types'

export function AccountPage() {
  const { user, setUser, logout } = useAuth()
  const { notify } = useUiStore()
  const navigate = useNavigate()

  const [profile, setProfile] = useState({
    name: user?.name ?? '',
    email: user?.email ?? '',
    phone: user?.phone ?? '',
  })
  const [avatar, setAvatar] = useState<File | null>(null)
  const [profileError, setProfileError] = useState<ApiError | null>(null)

  const [passwords, setPasswords] = useState({
    current_password: '',
    password: '',
    password_confirmation: '',
  })
  const [passwordError, setPasswordError] = useState<ApiError | null>(null)

  const saveProfile = useMutation({
    mutationFn: () => {
      // Only send multipart when there is actually a file to upload.
      if (avatar) {
        const form = new FormData()
        form.append('name', profile.name)
        form.append('email', profile.email)
        form.append('phone', profile.phone ?? '')
        form.append('avatar', avatar)

        return authApi.updateProfile(form)
      }

      return authApi.updateProfile(profile)
    },
    onSuccess: (updated) => {
      setUser(updated)
      setAvatar(null)
      notify('Profile updated.')
    },
    onError: (error) => setProfileError(toApiError(error)),
  })

  const savePassword = useMutation({
    mutationFn: () => authApi.updatePassword(passwords),
    onSuccess: () => {
      setPasswords({ current_password: '', password: '', password_confirmation: '' })
      notify('Password updated. Other devices have been signed out.')
    },
    onError: (error) => setPasswordError(toApiError(error)),
  })

  return (
    <div className="container">
      <div className="foogra-page-head">
        <h1>Account settings</h1>
        <p className="foogra-muted">
          Signed in as <strong>{user?.email}</strong>
          <span className={`foogra-badge ${user?.role}`} style={{ marginLeft: 10 }}>{user?.role_label}</span>
        </p>
      </div>

      <div className="row">
        <div className="col-lg-7">
          {/* --------------------------------------------------------- profile */}
          <div className="admin-panel">
            <h3>Your details</h3>

            {profileError && !profileError.errors && (
              <div className="foogra-alert error">{profileError.message}</div>
            )}

            <form onSubmit={(e) => { e.preventDefault(); setProfileError(null); saveProfile.mutate() }}>
              <div className="admin-form-grid">
                <div>
                  <label htmlFor="acc_name">Full name</label>
                  <input
                    id="acc_name"
                    type="text"
                    value={profile.name}
                    onChange={(e) => setProfile({ ...profile, name: e.target.value })}
                    required
                  />
                  {fieldError(profileError, 'name') && (
                    <small className="foogra-field-error">{fieldError(profileError, 'name')}</small>
                  )}
                </div>

                <div>
                  <label htmlFor="acc_email">Email</label>
                  <input
                    id="acc_email"
                    type="email"
                    value={profile.email}
                    onChange={(e) => setProfile({ ...profile, email: e.target.value })}
                    required
                  />
                  {fieldError(profileError, 'email') && (
                    <small className="foogra-field-error">{fieldError(profileError, 'email')}</small>
                  )}
                </div>

                <div>
                  <label htmlFor="acc_phone">Phone</label>
                  <input
                    id="acc_phone"
                    type="tel"
                    value={profile.phone ?? ''}
                    onChange={(e) => setProfile({ ...profile, phone: e.target.value })}
                  />
                </div>

                <div>
                  <label htmlFor="acc_avatar">Profile photo</label>
                  <input
                    id="acc_avatar"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    onChange={(e) => setAvatar(e.target.files?.[0] ?? null)}
                  />
                  {fieldError(profileError, 'avatar') && (
                    <small className="foogra-field-error">{fieldError(profileError, 'avatar')}</small>
                  )}
                </div>
              </div>

              <div className="foogra-form-actions">
                <button type="submit" className="btn_1" disabled={saveProfile.isPending}>
                  {saveProfile.isPending ? 'Saving…' : 'Save changes'}
                </button>
              </div>
            </form>
          </div>

          {/* -------------------------------------------------------- password */}
          <div className="admin-panel">
            <h3>Change password</h3>

            {passwordError && !passwordError.errors && (
              <div className="foogra-alert error">{passwordError.message}</div>
            )}

            <form onSubmit={(e) => { e.preventDefault(); setPasswordError(null); savePassword.mutate() }}>
              <div className="admin-form-grid">
                <div className="full">
                  <label htmlFor="pw_current">Current password</label>
                  <input
                    id="pw_current"
                    type="password"
                    value={passwords.current_password}
                    onChange={(e) => setPasswords({ ...passwords, current_password: e.target.value })}
                    required
                  />
                  {fieldError(passwordError, 'current_password') && (
                    <small className="foogra-field-error">{fieldError(passwordError, 'current_password')}</small>
                  )}
                </div>

                <div>
                  <label htmlFor="pw_new">New password</label>
                  <input
                    id="pw_new"
                    type="password"
                    value={passwords.password}
                    onChange={(e) => setPasswords({ ...passwords, password: e.target.value })}
                    required
                    minLength={8}
                  />
                  {fieldError(passwordError, 'password') && (
                    <small className="foogra-field-error">{fieldError(passwordError, 'password')}</small>
                  )}
                </div>

                <div>
                  <label htmlFor="pw_confirm">Confirm new password</label>
                  <input
                    id="pw_confirm"
                    type="password"
                    value={passwords.password_confirmation}
                    onChange={(e) => setPasswords({ ...passwords, password_confirmation: e.target.value })}
                    required
                    minLength={8}
                  />
                </div>
              </div>

              <div className="foogra-form-actions">
                <button type="submit" className="btn_1" disabled={savePassword.isPending}>
                  {savePassword.isPending ? 'Updating…' : 'Update password'}
                </button>
              </div>
            </form>
          </div>
        </div>

        <div className="col-lg-5">
          <div className="admin-panel">
            <h3>Your activity</h3>
            <div className="stat-grid" style={{ gridTemplateColumns: '1fr 1fr' }}>
              <div className="stat-card">
                <div className="label">Bookings</div>
                <div className="value">{user?.bookings_count ?? 0}</div>
              </div>
              <div className="stat-card">
                <div className="label">Reviews</div>
                <div className="value">{user?.reviews_count ?? 0}</div>
              </div>
            </div>

            <ul style={{ marginTop: 18, fontSize: 14, lineHeight: 2 }}>
              <li><Link to="/bookings">View my bookings</Link></li>
              <li><Link to="/reviews">View my reviews</Link></li>
              <li><Link to="/wishlist">View my wishlist</Link></li>
              {(user?.role === 'owner' || user?.role === 'admin') && (
                <li><Link to="/admin">Open the dashboard</Link></li>
              )}
            </ul>

            <div className="foogra-form-actions">
              <button
                type="button"
                className="btn_1 outline"
                onClick={async () => { await logout(); navigate('/') }}
              >
                Sign out
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
