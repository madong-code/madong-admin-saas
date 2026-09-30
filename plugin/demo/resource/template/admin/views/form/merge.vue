<script lang="ts" setup>
import { ref } from 'vue';

import { ElButton, ElCard, ElMessage, ElSwitch } from 'element-plus';

import { useVbenForm } from '#/adapter/form';
import { Page } from '#/components/page';
import { $t } from '#/locales';

const currentTab = ref(0);

const [FirstForm, firstFormApi] = useVbenForm({
  commonConfig: { componentProps: { class: 'w-full' } },
  handleSubmit: (values) => {
    ElMessage.success(`form1: ${JSON.stringify(values)}`);
    currentTab.value = 1;
  },
  layout: 'horizontal',
  resetButtonOptions: { show: false },
  schema: [
    {
      component: 'Input',
      componentProps: { placeholder: '请输入' },
      fieldName: 'formFirst',
      label: '表单1字段',
      rules: 'required',
    },
  ],
  submitButtonOptions: { content: '下一步' },
  wrapperClass: 'grid-cols-1',
});

const [SecondForm, secondFormApi] = useVbenForm({
  commonConfig: { componentProps: { class: 'w-full' } },
  handleReset: () => {
    currentTab.value = 0;
  },
  handleSubmit: (values) => {
    ElMessage.success(`form2: ${JSON.stringify(values)}`);
  },
  layout: 'horizontal',
  resetButtonOptions: { content: '上一步' },
  schema: [
    {
      component: 'Input',
      componentProps: { placeholder: '请输入' },
      fieldName: 'formSecond',
      label: '表单2字段',
      rules: 'required',
    },
  ],
  wrapperClass: 'grid-cols-1',
});

const needMerge = ref(true);

async function handleMergeSubmit() {
  const values = await firstFormApi
    .merge(secondFormApi)
    .submitAllForm(needMerge.value);
  ElMessage.success(`merged: ${JSON.stringify(values)}`);
}
</script>

<template>
  <Page
    :description="$t('demo.form.merge.desc')"
    :title="$t('demo.form.merge.title')"
  >
    <ElCard>
      <template #header>
        <div class="flex items-center justify-between">
          <span>分步表单合并</span>
          <div class="flex items-center gap-4">
            <ElSwitch
              v-model="needMerge"
              active-text="开启字段合并"
              inactive-text="关闭字段合并"
            />
            <ElButton type="primary" @click="handleMergeSubmit">
              合并提交
            </ElButton>
          </div>
        </div>
      </template>
      <div class="mx-auto max-w-lg p-5">
        <div class="mb-4 flex gap-2">
          <div
            :class="
              currentTab === 0 ? 'font-bold text-blue-500' : 'text-gray-400'
            "
            class="cursor-pointer"
            @click="currentTab = 0"
          >
            步骤1
          </div>
          <div class="text-gray-400">→</div>
          <div
            :class="
              currentTab === 1 ? 'font-bold text-blue-500' : 'text-gray-400'
            "
            class="cursor-pointer"
            @click="currentTab = 1"
          >
            步骤2
          </div>
        </div>
        <FirstForm v-show="currentTab === 0" />
        <SecondForm v-show="currentTab === 1" />
      </div>
    </ElCard>
  </Page>
</template>
