import { api, toQuery } from './api'
import type {
  AvailabilityResponse,
  Booking,
  Category,
  DashboardStats,
  Dish,
  MenuSection,
  Paginated,
  Restaurant,
  RestaurantCard,
  RestaurantDetailResponse,
  RestaurantFilters,
  RestaurantListResponse,
  Review,
  ReviewReply,
  User,
} from './types'

/** One place that knows every endpoint, so components never build URLs. */

// -------------------------------------------------------------------- catalogue

export const catalogue = {
  categories: async (): Promise<Category[]> =>
    (await api.get<{ data: Category[] }>('/categories')).data.data,

  restaurants: async (filters: RestaurantFilters): Promise<RestaurantListResponse> =>
    (await api.get<RestaurantListResponse>(`/restaurants?${toQuery(filters)}`)).data,

  featured: async (limit = 8): Promise<RestaurantCard[]> =>
    (await api.get<{ data: RestaurantCard[] }>(`/restaurants/featured?limit=${limit}`)).data.data,

  deals: async (limit = 6): Promise<RestaurantCard[]> =>
    (await api.get<{ data: RestaurantCard[] }>(`/restaurants/deals?limit=${limit}`)).data.data,

  suggestions: async (q: string): Promise<RestaurantCard[]> =>
    (await api.get<{ data: RestaurantCard[] }>(`/restaurants/suggestions?q=${encodeURIComponent(q)}`))
      .data.data,

  restaurant: async (slug: string): Promise<RestaurantDetailResponse> =>
    (await api.get<RestaurantDetailResponse>(`/restaurants/${slug}`)).data,

  menu: async (slug: string): Promise<MenuSection[]> =>
    (await api.get<{ data: MenuSection[] }>(`/restaurants/${slug}/menu`)).data.data,

  availability: async (slug: string, date: string): Promise<AvailabilityResponse> =>
    (await api.get<AvailabilityResponse>(`/restaurants/${slug}/availability?date=${date}`)).data,

  reviews: async (slug: string, page = 1, sort = 'recent'): Promise<Paginated<Review>> =>
    (await api.get<Paginated<Review>>(`/restaurants/${slug}/reviews?page=${page}&sort=${sort}`)).data,
}

// ------------------------------------------------------------------------ auth

export interface Credentials {
  email: string
  password: string
}

export interface RegistrationPayload extends Credentials {
  name: string
  password_confirmation: string
  phone?: string
  role?: 'customer' | 'owner'
}

export const auth = {
  login: async (payload: Credentials): Promise<{ user: User; token: string }> =>
    (await api.post('/auth/login', payload)).data,

  register: async (payload: RegistrationPayload): Promise<{ user: User; token: string }> =>
    (await api.post('/auth/register', payload)).data,

  logout: async (): Promise<void> => {
    await api.post('/auth/logout')
  },

  me: async (): Promise<User> => (await api.get<{ user: User }>('/auth/me')).data.user,

  updateProfile: async (payload: FormData | Record<string, unknown>): Promise<User> => {
    // PHP does not parse multipart bodies on PATCH, so files go POST + _method.
    if (payload instanceof FormData) {
      payload.append('_method', 'PATCH')

      return (await api.post<{ user: User }>('/auth/profile', payload, headersFor(payload))).data.user
    }

    return (await api.patch<{ user: User }>('/auth/profile', payload)).data.user
  },

  updatePassword: async (payload: {
    current_password: string
    password: string
    password_confirmation: string
  }): Promise<void> => {
    await api.put('/auth/password', payload)
  },

  forgotPassword: async (email: string): Promise<{ message: string }> =>
    (await api.post('/auth/forgot-password', { email })).data,

  resetPassword: async (payload: {
    token: string
    email: string
    password: string
    password_confirmation: string
  }): Promise<{ message: string }> => (await api.post('/auth/reset-password', payload)).data,
}

// -------------------------------------------------------------- diner actions

export const diner = {
  wishlist: async (): Promise<RestaurantCard[]> =>
    (await api.get<{ data: RestaurantCard[] }>('/wishlist')).data.data,

  toggleWishlist: async (slug: string): Promise<{ is_wishlisted: boolean; message: string }> =>
    (await api.post(`/wishlist/${slug}/toggle`)).data,

  myReviews: async (page = 1): Promise<Paginated<Review>> =>
    (await api.get<Paginated<Review>>(`/reviews/mine?page=${page}`)).data,

  createReview: async (
    slug: string,
    payload: {
      title: string
      body: string
      rating_food: number
      rating_service: number
      rating_location: number
      rating_price: number
    },
  ): Promise<Review> => (await api.post(`/restaurants/${slug}/reviews`, payload)).data.data,

  updateReview: async (id: number, payload: Partial<Review>): Promise<Review> =>
    (await api.patch(`/reviews/${id}`, payload)).data.data,

  deleteReview: async (id: number): Promise<void> => {
    await api.delete(`/reviews/${id}`)
  },

  voteReview: async (id: number, isHelpful: boolean) =>
    (await api.post(`/reviews/${id}/vote`, { is_helpful: isHelpful })).data,

  removeVote: async (id: number) => (await api.delete(`/reviews/${id}/vote`)).data,

  replyToReview: async (id: number, body: string): Promise<ReviewReply> =>
    (await api.post(`/reviews/${id}/reply`, { body })).data.data,

  myBookings: async (scope: 'upcoming' | 'past' | 'all' = 'upcoming'): Promise<Paginated<Booking>> =>
    (await api.get<Paginated<Booking>>(`/bookings?scope=${scope}`)).data,

  book: async (payload: {
    restaurant_id: number
    guest_name: string
    guest_email: string
    guest_phone?: string
    booking_date: string
    booking_time: string
    party_size: number
    notes?: string
  }): Promise<{ data: Booking; message: string }> => (await api.post('/bookings', payload)).data,

  findBooking: async (reference: string, email: string): Promise<Booking> =>
    (await api.get(`/bookings/${reference}?email=${encodeURIComponent(email)}`)).data.data,

  cancelBooking: async (reference: string, reason?: string): Promise<Booking> =>
    (await api.post(`/bookings/${reference}/cancel`, { reason })).data.data,
}

// ------------------------------------------------------------------- dashboard

export const admin = {
  stats: async (): Promise<DashboardStats> => (await api.get<DashboardStats>('/admin/stats')).data,

  restaurants: async (params: Record<string, unknown> = {}): Promise<Paginated<Restaurant>> =>
    (await api.get<Paginated<Restaurant>>(`/admin/restaurants?${toQuery(params)}`)).data,

  restaurant: async (slug: string): Promise<Restaurant> =>
    (await api.get(`/admin/restaurants/${slug}`)).data.data,

  createRestaurant: async (payload: FormData | Record<string, unknown>): Promise<Restaurant> =>
    (await api.post('/admin/restaurants', payload, headersFor(payload))).data.data,

  updateRestaurant: async (
    slug: string,
    payload: FormData | Record<string, unknown>,
  ): Promise<Restaurant> => {
    // Laravel cannot parse multipart on PATCH, so files go via POST + _method.
    if (payload instanceof FormData) {
      payload.append('_method', 'PATCH')

      return (await api.post(`/admin/restaurants/${slug}`, payload, headersFor(payload))).data.data
    }

    return (await api.patch(`/admin/restaurants/${slug}`, payload)).data.data
  },

  deleteRestaurant: async (slug: string): Promise<void> => {
    await api.delete(`/admin/restaurants/${slug}`)
  },

  restoreRestaurant: async (id: number): Promise<Restaurant> =>
    (await api.post(`/admin/restaurants/${id}/restore`)).data.data,

  syncOpeningHours: async (slug: string, hours: unknown[]) =>
    (await api.put(`/admin/restaurants/${slug}/opening-hours`, { hours })).data,

  menuSections: async (restaurantId: number): Promise<MenuSection[]> =>
    (await api.get<{ data: MenuSection[] }>(`/admin/menu-sections?restaurant_id=${restaurantId}`))
      .data.data,

  createMenuSection: async (payload: Record<string, unknown>): Promise<MenuSection> =>
    (await api.post('/admin/menu-sections', payload)).data.data,

  updateMenuSection: async (id: number, payload: Record<string, unknown>): Promise<MenuSection> =>
    (await api.patch(`/admin/menu-sections/${id}`, payload)).data.data,

  deleteMenuSection: async (id: number): Promise<void> => {
    await api.delete(`/admin/menu-sections/${id}`)
  },

  createDish: async (payload: Record<string, unknown>): Promise<Dish> =>
    (await api.post('/admin/dishes', payload)).data.data,

  updateDish: async (id: number, payload: Record<string, unknown>): Promise<Dish> =>
    (await api.patch(`/admin/dishes/${id}`, payload)).data.data,

  toggleDish: async (id: number): Promise<Dish> =>
    (await api.patch(`/admin/dishes/${id}/availability`)).data.data,

  deleteDish: async (id: number): Promise<void> => {
    await api.delete(`/admin/dishes/${id}`)
  },

  categories: async (): Promise<Category[]> =>
    (await api.get<{ data: Category[] }>('/admin/categories')).data.data,

  createCategory: async (payload: Record<string, unknown>): Promise<Category> =>
    (await api.post('/admin/categories', payload)).data.data,

  updateCategory: async (slug: string, payload: Record<string, unknown>): Promise<Category> =>
    (await api.patch(`/admin/categories/${slug}`, payload)).data.data,

  deleteCategory: async (slug: string): Promise<void> => {
    await api.delete(`/admin/categories/${slug}`)
  },

  reviews: async (params: Record<string, unknown> = {}): Promise<Paginated<Review>> =>
    (await api.get<Paginated<Review>>(`/admin/reviews?${toQuery(params)}`)).data,

  moderateReview: async (id: number, status: 'approved' | 'rejected' | 'pending'): Promise<Review> =>
    (await api.patch(`/admin/reviews/${id}/status`, { status })).data.data,

  deleteReview: async (id: number): Promise<void> => {
    await api.delete(`/admin/reviews/${id}`)
  },

  bookings: async (params: Record<string, unknown> = {}): Promise<Paginated<Booking>> =>
    (await api.get<Paginated<Booking>>(`/admin/bookings?${toQuery(params)}`)).data,

  transitionBooking: async (
    reference: string,
    status: string,
    cancellationReason?: string,
  ): Promise<Booking> =>
    (
      await api.post(`/admin/bookings/${reference}/transition`, {
        status,
        cancellation_reason: cancellationReason,
      })
    ).data.data,

  deleteBooking: async (reference: string): Promise<void> => {
    await api.delete(`/admin/bookings/${reference}`)
  },

  users: async (params: Record<string, unknown> = {}): Promise<Paginated<User>> =>
    (await api.get<Paginated<User>>(`/admin/users?${toQuery(params)}`)).data,

  createUser: async (payload: Record<string, unknown>): Promise<User> =>
    (await api.post('/admin/users', payload)).data.data,

  updateUser: async (id: number, payload: Record<string, unknown>): Promise<User> =>
    (await api.patch(`/admin/users/${id}`, payload)).data.data,

  deleteUser: async (id: number): Promise<void> => {
    await api.delete(`/admin/users/${id}`)
  },
}

function headersFor(payload: unknown) {
  return payload instanceof FormData
    ? { headers: { 'Content-Type': 'multipart/form-data' } }
    : undefined
}
