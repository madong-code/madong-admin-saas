<script lang="ts" setup>
import { ElButton, ElMessage } from 'element-plus';

import { useVbenModal } from '#/core/ui/common';

const [Modal, modalApi] = useVbenModal({
  draggable: true,
  onCancel() {
    modalApi.close();
  },
  onConfirm() {
    ElMessage.info('onConfirm');
  },
  title: '动态修改配置示例',
});

const state = modalApi.useStore();

function handleUpdateTitle() {
  modalApi.setState({ title: '内部动态标题' });
}

function handleToggleFullscreen() {
  modalApi.setState((prev) => {
    return { ...prev, fullscreen: !prev.fullscreen };
  });
}
</script>

<template>
  <Modal>
    <div class="flex flex-col items-center">
      <ElButton class="mb-3" type="primary" @click="handleUpdateTitle()">
        内部动态修改标题
      </ElButton>
      <ElButton class="mb-3" type="primary" @click="handleToggleFullscreen()">
        {{ state.fullscreen ? '退出全屏' : '打开全屏' }}
      </ElButton>
    </div>
  </Modal>
</template>
