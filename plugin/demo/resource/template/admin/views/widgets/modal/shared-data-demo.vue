<script lang="ts" setup>
import { ref } from 'vue';

import { ElMessage } from 'element-plus';

import { useVbenModal } from '#/core/ui/common';

const data = ref();

const [Modal, modalApi] = useVbenModal({
  onCancel() {
    modalApi.close();
  },
  onConfirm() {
    ElMessage.info('onConfirm');
  },
  onOpenChange(isOpen: boolean) {
    if (isOpen) {
      data.value = modalApi.getData<Record<string, any>>();
    }
  },
});
</script>

<template>
  <Modal title="数据共享示例">
    <div class="flex flex-col items-center">外部传递数据： {{ data }}</div>
  </Modal>
</template>
