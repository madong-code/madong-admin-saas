<script lang="ts" setup>
import { useVbenModal } from '@vben/common-ui';

import { ElMessage } from 'element-plus';

import { useVbenForm } from '#/adapter/form';

defineOptions({ name: 'FormModalDemo' });

const [Form, formApi] = useVbenForm({
  handleSubmit: onSubmit,
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
      fieldName: 'field2',
      label: '字段2',
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
      fieldName: 'field3',
      label: '字段3',
      rules: 'selectRequired',
    },
  ],
  showDefaultActions: false,
});

const [Modal, modalApi] = useVbenModal({
  fullscreenButton: false,
  onCancel() {
    modalApi.close();
  },
  onConfirm: async () => {
    await formApi.validateAndSubmitForm();
  },
  onOpenChange(isOpen: boolean) {
    if (isOpen) {
      const { values } = modalApi.getData<Record<string, any>>();
      if (values) {
        formApi.setValues(values);
      }
    }
  },
  title: '内嵌表单示例',
});

function onSubmit(values: Record<string, any>) {
  ElMessage({ message: '正在提交中...', type: 'info', duration: 0 });
  modalApi.lock();
  setTimeout(() => {
    modalApi.close();
    ElMessage({
      message: `提交成功：${JSON.stringify(values)}`,
      type: 'success',
    });
  }, 3000);
}
</script>

<template>
  <Modal>
    <Form />
  </Modal>
</template>
