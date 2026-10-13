import { createRouter, createWebHistory } from 'vue-router'
import { pageTitle, SITE_TITLE } from '@/pageTitle'
import HomeView from '../views/HomeView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      meta: { title: 'Etusivu' },
      component: HomeView,
    },
    {
      path: '/requests',
      name: 'requests',
      meta: { title: 'Pyynnöt' },
      component: () => import('../views/RequestListView.vue'),
    },
    {
      path: '/requests/new',
      name: 'request-create',
      meta: { title: 'Uusi pyyntö' },
      component: () => import('../views/RequestCreateView.vue'),
    },
    {
      path: '/requests/:id(\\d+)',
      name: 'request-details',
      meta: { title: 'Pyyntö' },
      component: () => import('../views/RequestDetailView.vue'),
    },
    {
      path: '/requests/:id/edit',
      name: 'request-edit',
      meta: { title: 'Muokkaa pyyntöä' },
      component: () => import('../views/RequestEditView.vue'),
    },
    {
      path: '/customers',
      name: 'customers',
      meta: { title: 'Asiakkaat' },
      component: () => import('../views/CustomersView.vue'),
    },
    {
      path: '/customers/new',
      name: 'customer-create',
      meta: { title: 'Uusi asiakas' },
      component: () => import('../views/CustomerFormView.vue'),
    },
    {
      path: '/customers/:id(\\d+)/edit',
      name: 'customer-edit',
      meta: { title: 'Muokkaa asiakasta' },
      component: () => import('../views/CustomerFormView.vue'),
    },
    {
      path: '/invoices',
      name: 'invoices',
      meta: { title: 'Laskut' },
      component: () => import('../views/InvoicesView.vue'),
    },
    {
      path: '/invoices/new',
      name: 'invoice-create',
      meta: { title: 'Uusi lasku' },
      component: () => import('../views/InvoiceCreateView.vue'),
    },
    {
      path: '/invoices/:id(\\d+)',
      name: 'invoice-details',
      meta: { title: 'Lasku' },
      component: () => import('../views/InvoiceDetailView.vue'),
    },
    {
      path: '/tools',
      name: 'tools',
      meta: { title: 'Työkalut' },
      component: () => import('../views/ToolsView.vue'),
    },
    {
      path: '/about',
      name: 'about',
      meta: { title: 'Tietoja' },
      component: () => import('../views/AboutView.vue'),
    },
    {
      path: '/map',
      name: 'map',
      component: () => import('../views/RequestMapView.vue'),
      meta: { title: 'Kartta', fullScreen: true },
    },
  ],
})

// Each page has its own browser tab title; the home page keeps the full name.
router.afterEach((to) => {
  document.title = to.name === 'home' ? SITE_TITLE : pageTitle(to.meta.title)
})

export default router
