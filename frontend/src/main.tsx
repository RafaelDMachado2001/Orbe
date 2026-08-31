import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { RouterProvider } from 'react-router-dom'

import { Providers } from './app/Providers'
import { router } from './app/router'
import './styles.css'

const container = document.getElementById('root')

if (container === null) {
  throw new Error('Elemento #root não encontrado no index.html.')
}

createRoot(container).render(
  <StrictMode>
    <Providers>
      <RouterProvider router={router} />
    </Providers>
  </StrictMode>,
)
