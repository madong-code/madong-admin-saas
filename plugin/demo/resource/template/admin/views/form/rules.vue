<script lang="ts" setup>
import { ElButton, ElCard, ElMessage } from 'element-plus';

import { useVbenForm, z } from '#/adapter/form';
import { Page } from '#/components/page';
import { $t } from '#/locales';

const [Form, formApi] = useVbenForm({
  commonConfig: { componentProps: { class: 'w-full' } },
  handleSubmit: onSubmit,
  layout: 'horizontal',
  schema: [
    {
      component: 'Input',
      componentProps: { placeholder: '请输入' },
      fieldName: 'field1',
      label: '字段1',
      rules: 'required',
    },
    {
      component: 'Input',
      componentProps: { placeholder: '请输入' },
      defaultValue: '默认值',
      fieldName: 'field2',
      label: '默认值(必填)',
      rules: 'required',
    },
    {
      component: 'Input',
      componentProps: { placeholder: '请输入' },
      fieldName: 'field3',
      label: '默认值(非必填)',
      rules: z.string().default('默认值').optional(),
    },
    {
      component: 'Input',
      componentProps: { placeholder: '请输入' },
      fieldName: 'field31',
      label: '自定义信息',
      rules: z.string().min(1, { message: '最少输入1个字符' }),
    },
    {
      component: 'Input',
      componentProps: { placeholder: '请输入' },
      fieldName: 'field4',
      label: '邮箱',
      rules: z.string().email('请输入正确的邮箱'),
    },
    {
      component: 'InputNumber',
      componentProps: { placeholder: '请输入' },
      fieldName: 'number',
      label: '数字',
      rules: 'required',
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
      rules: 'selectRequired',
    },
    {
      component: 'RadioGroup',
      componentProps: {
        options: [
          { label: '选项1', value: '1' },
          { label: '选项2', value: '2' },
        ],
      },
      fieldName: 'radioGroup',
      label: '单选组',
      rules: 'selectRequired',
    },
    {
      component: 'CheckboxGroup',
      componentProps: {
        options: [
          { label: '选项1', value: '1' },
          { label: '选项2', value: '2' },
        ],
      },
      fieldName: 'checkboxGroup',
      label: '多选组',
      rules: 'selectRequired',
    },
    {
      component: 'DatePicker',
      fieldName: 'datePicker',
      label: '日期选择框',
      rules: 'selectRequired',
    },
    {
      component: 'Input',
      componentProps: {
        placeholder: '请输入',
        type: 'password',
        showPassword: true,
      },
      fieldName: 'password',
      label: '密码',
      rules: 'required',
    },
    {
      component: 'Input',
      componentProps: { placeholder: '请输入' },
      fieldName: 'input-async',
      label: '异步校验',
      rules: z
        .string()
        .min(3, '用户名至少需要3个字符')
        .refine(
          async (username) => {
            await new Promise((resolve) => setTimeout(resolve, 1000));
            return username !== 'existingUser';
          },
          { message: '用户名已存在' },
        ),
    },
  ],
  wrapperClass: 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
});

function onSubmit(values: Record<string, any>) {
  ElMessage.success(`form values: ${JSON.stringify(values)}`);
}
</script>

<template>
  <Page
    :description="$t('demo.form.rules.desc')"
    :title="$t('demo.form.rules.title')"
  >
    <ElCard>
      <template #header>
        <div class="flex items-center justify-between">
          <span>基础组件校验示例</span>
          <div class="flex gap-2">
            <ElButton @click="() => formApi.validate()">校验表单</ElButton>
            <ElButton @click="() => formApi.resetValidate()">
              清空校验信息
            </ElButton>
          </div>
        </div>
      </template>
      <Form />
    </ElCard>
  </Page>
</template>
