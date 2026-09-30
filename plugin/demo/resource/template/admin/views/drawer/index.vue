<script lang="ts" setup>
import { Page, useVbenDrawer } from '@vben/common-ui';

import { ElButton, ElCard, ElMessage } from 'element-plus';

import DocButton from '../doc-button.vue';
import AutoHeightDemo from './auto-height-demo.vue';
import DynamicDemo from './dynamic-demo.vue';
import FormDrawerDemo from './form-drawer-demo.vue';
import InContentDemo from './in-content-demo.vue';
import SharedDataDemo from './shared-data-demo.vue';

const [BaseDrawer, baseDrawerApi] = useVbenDrawer({
  onConfirm() {
    ElMessage.info('onConfirm');
  },
});

const [InContentDrawer, inContentDrawerApi] = useVbenDrawer({
  connectedComponent: InContentDemo,
  destroyOnClose: false,
});

const [AutoHeightDrawer, autoHeightDrawerApi] = useVbenDrawer({
  connectedComponent: AutoHeightDemo,
});

const [DynamicDrawer, dynamicDrawerApi] = useVbenDrawer({
  connectedComponent: DynamicDemo,
});

const [SharedDataDrawer, sharedDataDrawerApi] = useVbenDrawer({
  connectedComponent: SharedDataDemo,
});

const [FormDrawer, formDrawerApi] = useVbenDrawer({
  connectedComponent: FormDrawerDemo,
});

function openBaseDrawer(
  placement: 'bottom' | 'left' | 'right' | 'top' = 'right',
) {
  baseDrawerApi.setState({ placement });
  baseDrawerApi.open();
}

function openSharedDataDrawer() {
  sharedDataDrawerApi.setData({
    content: '外部传递的数据',
    payload: { key: 'value' },
  });
  sharedDataDrawerApi.open();
}

function openFormDrawer() {
  formDrawerApi.setData({ values: { field1: '默认值1', field2: '默认值2' } });
  formDrawerApi.open();
}
</script>

<template>
  <Page description="抽屉组件示例" title="抽屉">
    <template #extra>
      <DocButton path="/components/common-ui/vben-drawer" />
    </template>

    <ElCard class="mb-4">
      <template #header>基本使用</template>
      <div class="flex flex-wrap gap-2">
        <ElButton type="primary" @click="baseDrawerApi.open()">
          基本抽屉
        </ElButton>
        <ElButton @click="openBaseDrawer('left')">左侧打开</ElButton>
        <ElButton @click="openBaseDrawer('right')">右侧打开</ElButton>
        <ElButton @click="openBaseDrawer('top')">上方打开</ElButton>
        <ElButton @click="openBaseDrawer('bottom')">下方打开</ElButton>
      </div>
    </ElCard>

    <ElCard>
      <template #header>更多功能</template>
      <div class="flex flex-wrap gap-2">
        <ElButton @click="inContentDrawerApi.open()">内容区域打开</ElButton>
        <ElButton @click="autoHeightDrawerApi.open()">内容高度自适应</ElButton>
        <ElButton @click="dynamicDrawerApi.open()">动态配置</ElButton>
        <ElButton @click="openSharedDataDrawer()">内外数据共享</ElButton>
        <ElButton @click="openFormDrawer()">表单抽屉</ElButton>
      </div>
    </ElCard>

    <BaseDrawer title="基础抽屉" class="w-[400px]">
      <p>这是一个基础抽屉示例</p>
    </BaseDrawer>
    <InContentDrawer />
    <AutoHeightDrawer />
    <DynamicDrawer />
    <SharedDataDrawer />
    <FormDrawer />
  </Page>
</template>
