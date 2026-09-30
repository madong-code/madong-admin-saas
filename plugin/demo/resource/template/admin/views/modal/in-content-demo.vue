<script lang="ts" setup>
import { ref } from 'vue';

import { useVbenModal } from '@vben/common-ui';

import { ElInput, ElMessage } from 'element-plus';

const value = ref('');

const [Modal, modalApi] = useVbenModal({
  destroyOnClose: false,
  onCancel() {
    modalApi.close();
  },
  onConfirm() {
    ElMessage.info('onConfirm');
  },
});
</script>

<template>
  <Modal
    append-to-main
    class="w-[600px]"
    title="基础弹窗示例"
    title-tooltip="标题提示内容"
  >
    此弹窗指定在内容区域打开，并且在关闭之后弹窗内容不会被销毁
    <ElInput v-model="value" placeholder="KeepAlive测试" />
  </Modal>
</template>
