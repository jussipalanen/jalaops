import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
    },
    {
      path: '/requests',
      name: 'requests',
      component: () => import('../views/RequestListView.vue'),
    },
    {
      path: '/requests/new',
      name: 'request-create',
      component: () => import('../views/RequestCreateView.vue'),
    },
    {
      path: '/requests/:id(\\d+)',
      name: 'request-details',
      component: () => import('../views/RequestDetailView.vue'),
    },
    {
      path: '/requests/:id/edit',
      name: 'request-edit',
      component: () => import('../views/RequestEditView.vue'),
    },
    {
      path: '/map',
      name: 'map',
      component: () => import('../views/RequestMapView.vue'),
      meta: { fullScreen: true },
    },
  ],
})

export default router
