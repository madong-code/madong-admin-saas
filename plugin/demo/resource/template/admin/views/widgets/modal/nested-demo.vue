<script lang="ts" setup>
import { ElButton } from 'element-plus';

import { useVbenModal } from '#/core/ui/common';

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
