<script lang="ts" setup>
import { ElCard } from 'element-plus';

import { useVbenForm } from '#/adapter/form';
import { Page } from '#/components/page';
import { $t } from '#/locales';

const [CustomLayoutForm] = useVbenForm({
  commonConfig: { componentProps: { class: 'w-full' } },
  layout: 'horizontal',
  schema: [
    { component: 'Select', fieldName: 'field1', label: '字符串' },
    { component: 'TreeSelect', fieldName: 'field2', label: '字符串' },
    { component: 'Input', fieldName: 'field3', label: '字符串' },
    { component: 'Input', fieldName: 'field4', label: '字符串' },
    {
      component: 'InputNumber',
      fieldName: 'field5',
      formItemClass: 'col-start-3',
      label: '前面空了一列',
    },
    {
      component: 'Divider',
      fieldName: '_divider',
      formItemClass: 'col-span-3',
      hideLabel: true,
      renderComponentContent: () => ({ default: () => '分割线' }),
    },
    {
      component: 'Input',
      fieldName: 'field6',
      formItemClass: 'col-span-3 items-baseline',
      label: '占满三列',
      componentProps: { type: 'textarea', rows: 3 },
    },
    {
      component: 'Input',
      fieldName: 'field7',
      formItemClass: 'col-span-2 col-start-2',
      label: '占满2列',
    },
    {
      component: 'Input',
      fieldName: 'field8',
      formItemClass: 'col-start-2',
      label: '左右留空',
    },
    {
      component: 'Input',
      fieldName: 'field9',
      formItemClass: 'col-start-1',
      label: '字符串',
      componentProps: { type: 'password', showPassword: true },
    },
  ],
  wrapperClass: 'grid-cols-3',
});
</script>

<template>
  <Page
    :description="$t('demo.form.custom_layout.desc')"
    :title="$t('demo.form.custom_layout.title')"
  >
    <ElCard title="使用tailwind自定义布局">
      <CustomLayoutForm />
    </ElCard>
  </Page>
</template>
