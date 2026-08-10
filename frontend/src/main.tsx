import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AuthProvider } from './context/AuthContext'
import { UiProvider } from './context/UiContext'
import { App } from './App'
import './styles/foogra-react.css'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      refetchOnWindowFocus: false,
      // A 401/403/404/422 will not fix itself on retry; anything else gets one more go.
      retry: (failureCount, error) => {
        const status = (error as { response?: { status?: number } })?.response?.status

        if (status && [401, 403, 404, 422].includes(status)) return false

        return failureCount < 1
      },
    },
  },
})

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <UiProvider>
          <AuthProvider>
            <App />
          </AuthProvider>
        </UiProvider>
      </BrowserRouter>
    </QueryClientProvider>
  </StrictMode>,
)
