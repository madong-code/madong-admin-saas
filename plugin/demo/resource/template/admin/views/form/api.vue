<script lang="ts" setup>
import { ref } from 'vue';

import { ElButton, ElCard, ElMessage } from 'element-plus';

import { useVbenForm } from '#/adapter/form';
import { Page } from '#/components/page';
import { $t } from '#/locales';

const isReverseActionButtons = ref(false);

const [BaseForm, formApi] = useVbenForm({
  actionButtonsReverse: false,
  commonConfig: { componentProps: { class: 'w-full' } },
  handleSubmit: onSubmit,
  layout: 'horizontal',
  schema: [
    {
      component: 'Input',
      componentProps: { placeholder: '请输入用户名' },
      fieldName: 'field1',
      label: 'field1',
    },
    {
      component: 'Input',
      componentProps: { placeholder: '请输入' },
      fieldName: 'field2',
      label: 'field2',
    },
    {
      component: 'Select',
      componentProps: {
        options: [
          { label: '选项1', value: '1' },
          { label: '选项2', value: '2' },
        ],
      },
      fieldName: 'fieldOptions',
      label: '下拉选',
    },
  ],
  wrapperClass: 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
});

function onSubmit(values: Record<string, any>) {
  ElMessage.success(`form values: ${JSON.stringify(values)}`);
}

function handleClick(action: string) {
  switch (action) {
    case 'batchAddSchema': {
      formApi.setState((prev) => {
        const newSchema = [];
        for (let i = 0; i < 3; i++)
          newSchema.push({
            component: 'Input',
            componentProps: { placeholder: '请输入' },
            fieldName: `field${i}${Date.now()}`,
            label: 'field+',
          });
        return { schema: [...(prev?.schema ?? []), ...newSchema] };
      });
      break;
    }
    case 'batchDeleteSchema': {
      formApi.setState((prev) => ({
        schema: (prev?.schema ?? []).slice(0, -3),
      }));
      break;
    }
    case 'disabled': {
      formApi.setState({ commonConfig: { disabled: true } });
      break;
    }
    case 'hiddenAction': {
      formApi.setState({ showDefaultActions: false });
      break;
    }
    case 'labelWidth': {
      formApi.setState({ commonConfig: { labelWidth: 150 } });
      break;
    }
    case 'resetDisabled': {
      formApi.setState({ commonConfig: { disabled: false } });
      break;
    }
    case 'resetLabelWidth': {
      formApi.setState({ commonConfig: { labelWidth: 100 } });
      break;
    }
    case 'reverseActionButtons': {
      isReverseActionButtons.value = !isReverseActionButtons.value;
      formApi.setState({ actionButtonsReverse: isReverseActionButtons.value });
      break;
    }
    case 'showAction': {
      formApi.setState({ showDefaultActions: true });
      break;
    }
    case 'updateSchema': {
      formApi.updateSchema([
        {
          componentProps: {
            options: [
              { label: '选项1', value: '1' },
              { label: '选项2', value: '2' },
              { label: '选项3', value: '3' },
            ],
          },
          fieldName: 'fieldOptions',
        },
      ]);
      ElMessage.success('字段 `fieldOptions` 下拉选项更新成功。');
      break;
    }
  }
}
</script>

<template>
  <Page
    :description="$t('demo.form.api.desc')"
    :title="$t('demo.form.api.title')"
  >
    <div class="mb-5 flex flex-wrap gap-2">
      <ElButton @click="handleClick('updateSchema')">updateSchema</ElButton>
      <ElButton @click="handleClick('labelWidth')">更改labelWidth</ElButton>
      <ElButton @click="handleClick('resetLabelWidth')">
        还原labelWidth
      </ElButton>
      <ElButton @click="handleClick('disabled')">禁用表单</ElButton>
      <ElButton @click="handleClick('resetDisabled')">解除禁用</ElButton>
      <ElButton @click="handleClick('reverseActionButtons')">
        翻转操作按钮位置
      </ElButton>
      <ElButton @click="handleClick('hiddenAction')">隐藏操作按钮</ElButton>
      <ElButton @click="handleClick('showAction')">显示操作按钮</ElButton>
      <ElButton @click="handleClick('batchAddSchema')">批量添加表单项</ElButton>
      <ElButton @click="handleClick('batchDeleteSchema')">
        批量删除表单项
      </ElButton>
    </div>
    <ElCard title="操作示例">
      <BaseForm />
    </ElCard>
  </Page>
</template>
