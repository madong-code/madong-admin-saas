export default [
  {
    path: '/plugin/test',
    component: () => import('./index.vue'),
    meta: {
      title: '插件演示',
    },
  },
]
