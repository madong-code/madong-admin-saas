<script lang="ts" setup>
import { ElButton, ElMessage } from 'element-plus';

import { useVbenModal } from '#/core/ui/common';

const [Modal, modalApi] = useVbenModal({
  onCancel() {
    modalApi.close();
  },
  onClosed() {
    ElMessage.info('onClosed：关闭动画结束');
  },
  onConfirm() {
    ElMessage.info('onConfirm');
  },
  onOpened() {
    ElMessage.info('onOpened：打开动画结束');
  },
});

function lockModal() {
  modalApi.lock();
  setTimeout(() => {
    modalApi.unlock();
  }, 3000);
}
</script>

<template>
  <Modal class="w-[600px]" title="基础弹窗示例" title-tooltip="标题提示内容">
    base demo
    <ElButton type="primary" @click="lockModal">锁定弹窗</ElButton>
  </Modal>
</template>
