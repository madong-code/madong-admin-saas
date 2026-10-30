<script lang="ts" setup>
import { ElButton, ElMessage } from 'element-plus';

import { useVbenDrawer } from '#/core/ui/common';

const [Drawer, drawerApi] = useVbenDrawer({
  onCancel() {
    drawerApi.close();
  },
  onConfirm() {
    ElMessage.info('onConfirm');
  },
  title: '动态修改配置示例',
});

function handleUpdateTitle() {
  drawerApi.setState({ title: '内部动态标题' });
}
</script>

<template>
  <Drawer>
    <div class="flex flex-col items-center">
      <ElButton class="mb-3" type="primary" @click="handleUpdateTitle()">
        内部动态修改标题
      </ElButton>
    </div>
  </Drawer>
</template>
