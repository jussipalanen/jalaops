import { createRouter, createWebHistory } from 'vue-router'
import { mobileAppsPage } from '@/features'
import { pageTitle, SITE_TITLE } from '@/pageTitle'
import HomeView from '../views/HomeView.vue'
import { useModuleRoutes } from './moduleRoutes'

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
      // A Python module's page, drawn from its module.json. Modules with
      // routes of their own also have addresses like /news (moduleRoutes.js).
      path: '/tools/modules/:id',
      name: 'module',
      meta: { title: 'Moduuli' },
      component: () => import('../views/ModuleView.vue'),
    },
    {
      path: '/about',
      name: 'about',
      meta: { title: 'Tietoja' },
      component: () => import('../views/AboutView.vue'),
    },
    // While the page is switched off, old links and QR codes open the home page.
    mobileAppsPage
      ? {
          path: '/mobile-apps',
          name: 'mobile-apps',
          meta: { title: 'Mobiilisovellukset' },
          component: () => import('../views/MobileAppsView.vue'),
        }
      : { path: '/mobile-apps', redirect: '/' },
    {
      path: '/map',
      name: 'map',
      component: () => import('../views/RequestMapView.vue'),
      meta: { title: 'Kartta', fullScreen: true },
    },
    {
      path: '/:path(.*)*',
      name: 'not-found',
      meta: { title: 'Sivua ei löytynyt' },
      component: () => import('../views/NotFoundView.vue'),
    },
  ],
})

useModuleRoutes(router)

// Each page has its own browser tab title; the home page keeps the full name.
router.afterEach((to) => {
  document.title = to.name === 'home' ? SITE_TITLE : pageTitle(to.meta.title)
})

export default router
