<script lang="ts" setup>
import { ElButton, ElMessage } from 'element-plus';

import { useVbenDrawer } from '#/core/ui/common';

const [Drawer, drawerApi] = useVbenDrawer({
  onCancel() {
    drawerApi.close();
  },
  onClosed() {
    drawerApi.setState({ overlayBlur: 0, placement: 'right' });
  },
  onConfirm() {
    ElMessage.info('onConfirm');
  },
});

function lockDrawer() {
  drawerApi.lock();
  setTimeout(() => {
    drawerApi.unlock();
  }, 3000);
}
</script>

<template>
  <Drawer title="基础抽屉示例" title-tooltip="标题提示内容">
    <template #extra>extra</template>
    base demo
    <ElButton type="primary" @click="lockDrawer">锁定抽屉状态</ElButton>
  </Drawer>
</template>
