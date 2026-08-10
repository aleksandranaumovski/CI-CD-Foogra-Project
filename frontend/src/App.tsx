import { Navigate, Route, Routes } from 'react-router-dom'
import { Layout } from './components/layout/Layout'
import { RequireAuth } from './components/layout/RequireAuth'
import { HomePage } from './pages/HomePage'
import { ListingPage } from './pages/ListingPage'
import { DetailPage } from './pages/DetailPage'
import { WishlistPage } from './pages/WishlistPage'
import { MyBookingsPage } from './pages/MyBookingsPage'
import { MyReviewsPage } from './pages/MyReviewsPage'
import { AccountPage } from './pages/AccountPage'
import { BookingConfirmationPage } from './pages/BookingConfirmationPage'
import { BookingLookupPage } from './pages/BookingLookupPage'
import { ResetPasswordPage } from './pages/ResetPasswordPage'
import { RegisterOwnerPage } from './pages/RegisterOwnerPage'
import { NotFoundPage } from './pages/NotFoundPage'
import { AdminLayout } from './pages/admin/AdminLayout'
import { DashboardPage } from './pages/admin/DashboardPage'
import { AdminRestaurantsPage } from './pages/admin/AdminRestaurantsPage'
import { AdminRestaurantEditPage } from './pages/admin/AdminRestaurantEditPage'
import { AdminMenuPage } from './pages/admin/AdminMenuPage'
import { AdminReviewsPage } from './pages/admin/AdminReviewsPage'
import { AdminBookingsPage } from './pages/admin/AdminBookingsPage'
import { AdminCategoriesPage } from './pages/admin/AdminCategoriesPage'
import { AdminUsersPage } from './pages/admin/AdminUsersPage'

export function App() {
  return (
    <Routes>
      {/* ------------------------------------------------------- public site */}
      <Route element={<Layout />}>
        <Route index element={<HomePage />} />
        <Route path="restaurants" element={<ListingPage />} />
        <Route path="restaurants/:slug" element={<DetailPage />} />

        <Route path="booking/:reference" element={<BookingConfirmationPage />} />
        <Route path="booking-lookup" element={<BookingLookupPage />} />
        <Route path="reset-password" element={<ResetPasswordPage />} />
        <Route path="register-owner" element={<RegisterOwnerPage />} />

        {/* Signed-in diner */}
        <Route path="wishlist" element={<RequireAuth><WishlistPage /></RequireAuth>} />
        <Route path="bookings" element={<RequireAuth><MyBookingsPage /></RequireAuth>} />
        <Route path="reviews" element={<RequireAuth><MyReviewsPage /></RequireAuth>} />
        <Route path="account" element={<RequireAuth><AccountPage /></RequireAuth>} />

        <Route path="404" element={<NotFoundPage />} />
        <Route path="*" element={<NotFoundPage />} />
      </Route>

      {/* --------------------------------------------- dashboard (admin/owner) */}
      <Route
        path="/admin"
        element={
          <RequireAuth staffOnly>
            <AdminLayout />
          </RequireAuth>
        }
      >
        <Route index element={<DashboardPage />} />
        <Route path="restaurants" element={<AdminRestaurantsPage />} />
        <Route path="restaurants/new" element={<AdminRestaurantEditPage />} />
        <Route path="restaurants/:slug" element={<AdminRestaurantEditPage />} />
        <Route path="restaurants/:slug/menu" element={<AdminMenuPage />} />
        <Route path="reviews" element={<AdminReviewsPage />} />
        <Route path="bookings" element={<AdminBookingsPage />} />
        <Route path="categories" element={<AdminCategoriesPage />} />
        <Route path="users" element={<RequireAuth adminOnly><AdminUsersPage /></RequireAuth>} />
        <Route path="*" element={<Navigate to="/admin" replace />} />
      </Route>
    </Routes>
  )
}
