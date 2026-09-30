<script lang="ts" setup>
import { ElButton, ElCard, ElMessage } from 'element-plus';

import { useVbenForm } from '#/adapter/form';
import { Page } from '#/components/page';
import { $t } from '#/locales';

const [BaseForm, baseFormApi] = useVbenForm({
  commonConfig: {
    colon: true,
    componentProps: { class: 'w-full' },
  },
  handleSubmit: onSubmit,
  layout: 'horizontal',
  schema: [
    {
      component: 'Input',
      componentProps: { placeholder: '请输入用户名' },
      fieldName: 'username',
      label: '用户名',
      rules: 'required',
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
    },
    {
      component: 'InputNumber',
      componentProps: { placeholder: '请输入', min: 0, max: 150 },
      fieldName: 'age',
      label: '年龄',
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
    },
    {
      component: 'CheckboxGroup',
      componentProps: {
        options: [
          { label: '阅读', value: 'reading' },
          { label: '运动', value: 'sports' },
          { label: '音乐', value: 'music' },
        ],
      },
      fieldName: 'checkboxGroup',
      label: '多选组',
    },
    {
      component: 'Switch',
      componentProps: { class: 'w-auto' },
      fieldName: 'switch',
      label: '开关',
    },
    {
      component: 'DatePicker',
      fieldName: 'datePicker',
      label: '日期选择框',
    },
    {
      component: 'TimePicker',
      fieldName: 'timePicker',
      label: '时间选择框',
    },
    {
      component: 'TreeSelect',
      componentProps: {
        data: [
          {
            id: 1,
            label: 'root 1',
            children: [
              {
                id: 2,
                label: 'parent 1',
                children: [
                  { id: 3, label: 'leaf 1' },
                  { id: 4, label: 'leaf 2' },
                ],
              },
            ],
          },
        ],
        nodeKey: 'id',
        props: { label: 'label', children: 'children' },
      },
      fieldName: 'treeSelect',
      label: '树选择',
    },
    {
      component: 'IconPicker',
      fieldName: 'icon',
      label: '图标',
    },
  ],
  wrapperClass: 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
});

function onSubmit(values: Record<string, any>) {
  ElMessage.success(`form values: ${JSON.stringify(values)}`);
}

function handleSetFormValue() {
  baseFormApi.setValues({
    username: 'admin',
    password: '123456',
    age: 25,
    options: '1',
    radioGroup: '1',
    checkboxGroup: ['reading', 'music'],
    switch: true,
    datePicker: '2024-01-01',
    treeSelect: 3,
  });
}
</script>

<template>
  <Page
    :description="$t('demo.form.basic.desc')"
    :title="$t('demo.form.basic.title')"
  >
    <ElCard>
      <template #header>
        <div class="flex items-center justify-between">
          <span>基础示例</span>
          <ElButton type="primary" @click="handleSetFormValue">
            设置表单值
          </ElButton>
        </div>
      </template>
      <BaseForm />
    </ElCard>
  </Page>
</template>
