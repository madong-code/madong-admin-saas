<script lang="ts" setup>
import { ElCard, ElMessage } from 'element-plus';

import { useVbenForm } from '#/adapter/form';
import { Page } from '#/components/page';
import { $t } from '#/locales';

const [QueryForm] = useVbenForm({
  collapsed: false,
  commonConfig: { componentProps: { class: 'w-full' } },
  handleSubmit: onSubmit,
  layout: 'horizontal',
  schema: [
    {
      component: 'Input',
      componentProps: { placeholder: '请输入用户名' },
      fieldName: 'username',
      label: '用户名',
    },
    {
      component: 'Input',
      componentProps: {
        placeholder: '请输入密码',
        type: 'password',
        showPassword: true,
      },
      fieldName: 'password',
      label: '密码',
    },
    {
      component: 'InputNumber',
      componentProps: { placeholder: '请输入' },
      fieldName: 'number',
      label: '数字',
    },
    {
      component: 'Select',
      componentProps: {
        options: [
          { label: '选项1', value: '1' },
          { label: '选项2', value: '2' },
        ],
      },
      fieldName: 'options',
      label: '下拉选',
    },
    { component: 'DatePicker', fieldName: 'datePicker', label: '日期选择框' },
  ],
  showCollapseButton: true,
  submitButtonOptions: { content: '查询' },
  wrapperClass: 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
});

const [InlineForm] = useVbenForm({
  layout: 'inline',
  schema: [
    {
      component: 'Input',
      componentProps: { placeholder: '请输入用户名' },
      fieldName: 'username',
      label: '用户名',
    },
    {
      component: 'Input',
      componentProps: {
        placeholder: '请输入密码',
        type: 'password',
        showPassword: true,
      },
      fieldName: 'password',
      label: '密码',
    },
    {
      component: 'Select',
      componentProps: {
        options: [
          { label: '选项1', value: '1' },
          { label: '选项2', value: '2' },
        ],
      },
      fieldName: 'options',
      label: '下拉选',
    },
  ],
});

function onSubmit(values: Record<string, any>) {
  ElMessage.success(`form values: ${JSON.stringify(values)}`);
}
</script>

<template>
  <Page
    :description="$t('demo.form.query.desc')"
    :title="$t('demo.form.query.title')"
  >
    <ElCard class="mb-5">
      <template #header>查询表单，默认展开</template>
      <QueryForm />
    </ElCard>

    <ElCard>
      <template #header>行内表单</template>
      <InlineForm />
    </ElCard>
  </Page>
</template>
