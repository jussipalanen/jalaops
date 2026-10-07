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
      path: '/requests/:id/edit',
      name: 'request-edit',
      component: () => import('../views/RequestEditView.vue'),
    },
  ],
})

export default router
