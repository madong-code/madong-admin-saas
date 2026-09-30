<script lang="ts" setup>
import { Page, alert, clearAllAlerts, confirm, prompt, useVbenModal } from '@vben/common-ui';

import { ElButton, ElCard, ElMessage } from 'element-plus';

import { $t } from '#/locales';

import AutoHeightDemo from './auto-height-demo.vue';
import BlurDemo from './blur-demo.vue';
import DragDemo from './drag-demo.vue';
import DynamicDemo from './dynamic-demo.vue';
import FormModalDemo from './form-modal-demo.vue';
import InContentModalDemo from './in-content-demo.vue';
import NestedDemo from './nested-demo.vue';
import SharedDataDemo from './shared-data-demo.vue';

import DocButton from '../doc-button.vue';

const [BaseModal, baseModalApi] = useVbenModal({
  onConfirm() {
    ElMessage.info('onConfirm');
  },
});

const [InContentModal, inContentModalApi] = useVbenModal({
  connectedComponent: InContentModalDemo,
  destroyOnClose: false,
});

const [AutoHeightModal, autoHeightModalApi] = useVbenModal({
  connectedComponent: AutoHeightDemo,
});

const [DragModal, dragModalApi] = useVbenModal({
  connectedComponent: DragDemo,
});

const [DynamicModal, dynamicModalApi] = useVbenModal({
  connectedComponent: DynamicDemo,
});

const [SharedDataModal, sharedDataModalApi] = useVbenModal({
  connectedComponent: SharedDataDemo,
});

const [FormModal, formModalApi] = useVbenModal({
  connectedComponent: FormModalDemo,
});

const [NestedModal, nestedModalApi] = useVbenModal({
  connectedComponent: NestedDemo,
});

const [BlurModal, blurModalApi] = useVbenModal({
  connectedComponent: BlurDemo,
});

function openSharedDataModal() {
  sharedDataModalApi.setData({ content: '外部传递的数据', payload: { key: 'value' } });
  sharedDataModalApi.open();
}

function openFormModal() {
  formModalApi.setData({ values: { field1: '默认值1', field2: '默认值2' } });
  formModalApi.open();
}
</script>

<template>
  <Page :description="$t('demo.modal.desc')" :title="$t('demo.modal.title')">
    <template #extra>
      <DocButton path="/components/common-ui/vben-modal" />
    </template>

    <ElCard class="mb-4">
      <template #header>基本使用</template>
      <div class="flex flex-wrap gap-2">
        <ElButton type="primary" @click="baseModalApi.open()">基本弹窗</ElButton>
      </div>
    </ElCard>

    <ElCard class="mb-4">
      <template #header>更多功能</template>
      <div class="flex flex-wrap gap-2">
        <ElButton @click="inContentModalApi.open()">内容区域打开</ElButton>
        <ElButton @click="autoHeightModalApi.open()">内容高度自适应</ElButton>
        <ElButton @click="dragModalApi.open()">可拖拽</ElButton>
        <ElButton @click="dynamicModalApi.open()">动态配置</ElButton>
        <ElButton @click="openSharedDataModal()">内外数据共享</ElButton>
        <ElButton @click="openFormModal()">表单弹窗</ElButton>
        <ElButton @click="nestedModalApi.open()">嵌套弹窗</ElButton>
        <ElButton @click="blurModalApi.open()">遮罩模糊</ElButton>
      </div>
    </ElCard>

    <ElCard class="mb-4">
      <template #header>轻量提示弹窗</template>
      <div class="flex flex-wrap gap-2">
        <ElButton @click="alert('这是一个 Alert 弹窗')">Alert</ElButton>
        <ElButton @click="confirm('确定要删除吗？').then(() => ElMessage.success('已确认'))">Confirm</ElButton>
        <ElButton @click="prompt({ content: '请输入名称' }).then((v: any) => ElMessage.success(`输入: ${v}`))">Prompt</ElButton>
        <ElButton @click="clearAllAlerts()">关闭所有弹窗</ElButton>
      </div>
    </ElCard>

    <BaseModal title="基础弹窗" class="w-[500px]">
      <p>这是一个基础弹窗示例</p>
    </BaseModal>
    <InContentModal />
    <AutoHeightModal />
    <DragModal />
    <DynamicModal />
    <SharedDataModal />
    <FormModal />
    <NestedModal />
    <BlurModal />
  </Page>
</template>
