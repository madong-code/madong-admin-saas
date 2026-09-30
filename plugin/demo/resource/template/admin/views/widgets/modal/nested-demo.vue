<script lang="ts" setup>
import { useVbenModal } from '@vben/common-ui';

import { ElButton } from 'element-plus';

import DragDemo from './drag-demo.vue';

const [Modal] = useVbenModal({ destroyOnClose: true });
const [BaseModal, baseModalApi] = useVbenModal({
  connectedComponent: DragDemo,
});

function openNestedModal() {
  baseModalApi.open();
}
</script>

<template>
  <Modal title="嵌套弹窗示例">
    <ElButton type="primary" @click="openNestedModal">打开子弹窗</ElButton>
    <BaseModal />
  </Modal>
</template>
