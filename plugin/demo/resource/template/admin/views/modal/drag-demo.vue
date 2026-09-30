<script lang="ts" setup>
import { useVbenModal } from '@vben/common-ui';

import { ElMessage } from 'element-plus';

const [Modal, modalApi] = useVbenModal({
  draggable: true,
  onCancel() {
    modalApi.close();
  },
  onConfirm() {
    ElMessage.info('onConfirm');
  },
});
</script>

<template>
  <Modal title="可拖拽示例"> 鼠标移动到 header 上，可拖拽弹窗 </Modal>
</template>
