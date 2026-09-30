<script lang="ts" setup>
import { ElButton, ElCard, ElMessage } from 'element-plus';

import { useVbenForm } from '#/adapter/form';
import { Page } from '#/components/page';
import { $t } from '#/locales';

const [Form, formApi] = useVbenForm({
  handleSubmit: onSubmit,
  schema: [
    {
      component: 'Switch',
      defaultValue: true,
      fieldName: 'field1Switch',
      help: '通过Dom控制销毁',
      label: '显示字段1',
    },
    {
      component: 'Switch',
      defaultValue: true,
      fieldName: 'field2Switch',
      help: '通过css控制隐藏',
      label: '显示字段2',
    },
    {
      component: 'Switch',
      fieldName: 'field3Switch',
      label: '禁用字段3',
    },
    {
      component: 'Switch',
      fieldName: 'field4Switch',
      label: '字段4必填',
    },
    {
      component: 'Input',
      dependencies: {
        if(values) {
          return !!values.field1Switch;
        },
        triggerFields: ['field1Switch'],
      },
      fieldName: 'field1',
      label: '字段1',
    },
    {
      component: 'Input',
      dependencies: {
        show(values) {
          return !!values.field2Switch;
        },
        triggerFields: ['field2Switch'],
      },
      fieldName: 'field2',
      label: '字段2',
    },
    {
      component: 'Input',
      dependencies: {
        disabled(values) {
          return !!values.field3Switch;
        },
        triggerFields: ['field3Switch'],
      },
      fieldName: 'field3',
      label: '字段3',
    },
    {
      component: 'Input',
      dependencies: {
        required(values) {
          return !!values.field4Switch;
        },
        triggerFields: ['field4Switch'],
      },
      fieldName: 'field4',
      label: '字段4',
    },
    {
      component: 'Input',
      dependencies: {
        rules(values) {
          if (values.field1 === '123') return 'required';
          return null;
        },
        triggerFields: ['field1'],
      },
      fieldName: 'field5',
      help: '当字段1的值为`123`时，必填',
      label: '动态rules',
    },
    {
      component: 'Select',
      componentProps: {
        options: [
          { label: '选项1', value: '1' },
          { label: '选项2', value: '2' },
        ],
      },
      dependencies: {
        componentProps(values) {
          if (values.field2 === '123') {
            return {
              options: [
                { label: '选项1', value: '1' },
                { label: '选项2', value: '2' },
                { label: '选项3', value: '3' },
              ],
            };
          }
          return {};
        },
        triggerFields: ['field2'],
      },
      fieldName: 'field6',
      help: '当字段2的值为`123`时，更改下拉选项',
      label: '动态配置',
    },
    { component: 'Input', fieldName: 'field7', label: '字段7' },
  ],
  wrapperClass: 'grid-cols-1 md:grid-cols-3 lg:grid-cols-4',
});

function onSubmit(values: Record<string, any>) {
  ElMessage.success(`form values: ${JSON.stringify(values)}`);
}

function handleDelete() {
  formApi.setState((prev) => ({
    schema: prev.schema?.filter((item) => item.fieldName !== 'field7'),
  }));
}

function handleAdd() {
  formApi.setState((prev) => ({
    schema: [
      ...(prev?.schema ?? []),
      { component: 'Input', fieldName: `field${Date.now()}`, label: '字段+' },
    ],
  }));
}

function handleUpdate() {
  formApi.setState((prev) => ({
    schema: prev.schema?.map((item) =>
      item.fieldName === 'field3' ? { ...item, label: '字段3-修改' } : item,
    ),
  }));
}
</script>

<template>
  <Page
    :description="$t('demo.form.dynamic.desc')"
    :title="$t('demo.form.dynamic.title')"
  >
    <ElCard>
      <template #header>
        <div class="flex items-center justify-between">
          <span>表单动态联动示例</span>
          <div class="flex gap-2">
            <ElButton @click="handleUpdate">修改字段3</ElButton>
            <ElButton @click="handleDelete">删除字段7</ElButton>
            <ElButton @click="handleAdd">添加字段</ElButton>
          </div>
        </div>
      </template>
      <Form />
    </ElCard>
  </Page>
</template>
