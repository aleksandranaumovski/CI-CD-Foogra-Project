/**
 * TypeScript mirrors of the Laravel API Resources. Kept deliberately close to
 * the JSON shape so a payload can be dropped straight into a component without
 * a mapping layer in between.
 */

export type UserRole = 'admin' | 'owner' | 'customer'
export type RestaurantStatus = 'draft' | 'published' | 'archived'
export type ReviewStatus = 'pending' | 'approved' | 'rejected'
export type BookingStatus = 'pending' | 'confirmed' | 'seated' | 'completed' | 'cancelled'

export interface User {
  id: number
  name: string
  avatar_url: string | null
  role: UserRole
  role_label: string
  email?: string
  phone?: string | null
  email_verified_at?: string | null
  restaurants_count?: number
  reviews_count?: number
  bookings_count?: number
  created_at?: string
}

export interface Category {
  id: number
  name: string
  slug: string
  icon: string
  description: string | null
  average_price: number
  sort_order: number
  is_active: boolean
  restaurants_count?: number
}

export interface Rating {
  score: number
  label: string
  reviews_count: number
}

/** The trimmed payload behind a listing / carousel card. */
export interface RestaurantCard {
  id: number
  name: string
  slug: string
  address: string
  city: string | null
  thumbnail: string | null
  /** Null for venues with no coordinates yet — the map skips those. */
  latitude: number | null
  longitude: number | null
  average_price: number
  discount_percent: number | null
  is_featured: boolean
  rating: Rating
  category?: { name: string; slug: string; icon: string }
  is_open_now?: boolean
  distance_km?: number
  is_wishlisted?: boolean
}

export interface RestaurantImage {
  id: number
  url: string
  thumbnail_url: string
  caption: string | null
  sort_order: number
}

export interface OpeningHour {
  id: number
  day_of_week: number
  day_name: string
  service: 'lunch' | 'dinner'
  opens_at: string | null
  closes_at: string | null
  is_closed: boolean
}

export interface Dish {
  id: number
  restaurant_id: number
  menu_section_id: number
  name: string
  description: string | null
  price: number
  image_url: string | null
  allergens: string[]
  is_vegetarian: boolean
  is_available: boolean
  sort_order: number
}

export interface MenuSection {
  id: number
  restaurant_id: number
  name: string
  description: string | null
  sort_order: number
  is_special_offers: boolean
  dishes?: Dish[]
  dishes_count?: number
}

export interface ReviewReply {
  id: number
  review_id: number
  body: string
  author?: User
  created_at: string
}

export interface Review {
  id: number
  restaurant_id: number
  title: string
  body: string
  ratings: {
    food: number
    service: number
    location: number
    price: number
    overall: number
    label: string
  }
  status: ReviewStatus
  votes: {
    helpful: number
    unhelpful: number
    /** true = helpful, false = not helpful, null = no vote yet. */
    mine?: boolean | null
  }
  author?: User
  restaurant?: RestaurantCard
  reply?: ReviewReply | null
  published_at: string | null
  created_at: string
}

export interface Restaurant {
  id: number
  name: string
  slug: string
  tagline: string | null
  description: string | null
  location: {
    address: string
    city: string | null
    postal_code: string | null
    country: string
    latitude: number | null
    longitude: number | null
    directions_url: string | null
    distance_km?: number
  }
  contact: { phone: string | null; email: string | null; website: string | null }
  average_price: number
  discount_percent: number | null
  rating: Rating
  images: { hero: string | null; thumbnail: string | null; gallery?: RestaurantImage[] }
  services: string[]
  payment_methods: string[]
  social_links: { facebook?: string; instagram?: string; twitter?: string }
  is_featured: boolean
  status: RestaurantStatus
  published_at: string | null
  is_open_now?: boolean
  category?: Category
  owner?: User
  opening_hours?: OpeningHour[]
  menu_sections?: MenuSection[]
  reviews?: Review[]
  bookings_count: number
  reviews_count?: number
  wishlisted_count?: number
  is_wishlisted?: boolean
  created_at: string
  updated_at: string
  deleted_at: string | null
}

export interface Booking {
  id: number
  reference: string
  restaurant_id: number
  guest: { name: string; email: string; phone: string | null }
  booking_date: string
  booking_time: string
  booked_for: string | null
  party_size: number
  notes: string | null
  discount_percent: number | null
  status: BookingStatus
  status_label: string
  is_upcoming: boolean
  confirmed_at: string | null
  cancelled_at: string | null
  cancellation_reason: string | null
  restaurant?: RestaurantCard
  user?: User
  created_at: string
}

// ------------------------------------------------------------------ envelopes

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page?: number
  total: number
  from?: number | null
  to?: number | null
  pending_total?: number
}

export interface Paginated<T> {
  data: T[]
  meta: PaginationMeta
}

export interface RatingFacet {
  min_rating: number
  label: string
  count: number
}

export interface PriceFacet {
  min_price: number
  max_price: number
  label: string
  count: number
}

export interface RestaurantFacets {
  categories: Record<string, number>
  ratings: RatingFacet[]
  prices: PriceFacet[]
}

export interface RestaurantListResponse extends Paginated<RestaurantCard> {
  facets: RestaurantFacets
  applied_filters: Record<string, unknown>
}

export interface RatingBreakdown {
  total: number
  overall: number
  label: string
  food: number
  service: number
  location: number
  price: number
}

export interface RestaurantDetailResponse {
  data: Restaurant
  rating_breakdown: RatingBreakdown
}

export interface AvailabilityService {
  service: 'lunch' | 'dinner'
  opens_at: string
  closes_at: string
  times: string[]
}

export interface AvailabilityResponse {
  date: string
  is_closed: boolean
  discount_percent: number | null
  services: AvailabilityService[]
}

/** The query the listing page turns into a request. */
export interface RestaurantFilters {
  q?: string
  location?: string
  categories?: string[]
  min_rating?: number
  min_price?: number
  max_price?: number
  lat?: number
  lng?: number
  radius?: number
  open_now?: boolean
  featured?: boolean
  has_discount?: boolean
  sort?: string
  page?: number
  per_page?: number
}

export interface DashboardStats {
  scope: 'platform' | 'my-restaurants'
  restaurants: { total: number; published: number; draft: number; archived: number }
  bookings: {
    total: number
    pending: number
    confirmed: number
    cancelled: number
    today: number
    upcoming: number
    covers_next_7_days: number
  }
  reviews: { total: number; pending: number; approved: number; average_score: number }
  users: { total: number; admins: number; owners: number; customers: number } | null
  bookings_trend: { date: string; bookings: number }[]
  top_restaurants: {
    id: number
    name: string
    slug: string
    rating: number
    reviews: number
    bookings: number
  }[]
  recent_bookings: Booking[]
  reviews_awaiting_moderation: Review[]
}

/** The error envelope every failed API call returns. */
export interface ApiError {
  message: string
  errors?: Record<string, string[]>
  status?: number
}
