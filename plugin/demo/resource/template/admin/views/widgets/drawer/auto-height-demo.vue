<script lang="ts" setup>
import { ref } from 'vue';

import { ElButton, ElMessage } from 'element-plus';

import { useVbenDrawer } from '#/core/ui/common';

const list = ref<number[]>([]);

const [Drawer, drawerApi] = useVbenDrawer({
  onCancel() {
    drawerApi.close();
  },
  onConfirm() {
    ElMessage.info('onConfirm');
  },
  onOpenChange(isOpen) {
    if (isOpen) {
      handleUpdate(10);
    }
  },
});

function handleUpdate(len: number) {
  drawerApi.setState({ loading: true });
  setTimeout(() => {
    list.value = Array.from({ length: len }, (_v, k) => k + 1);
    drawerApi.setState({ loading: false });
  }, 2000);
}
</script>

<template>
  <Drawer title="自动计算高度">
    <div
      v-for="item in list"
      :key="item"
      class="flex h-[55px] w-full items-center justify-center bg-gray-100 even:bg-gray-200"
    >
      {{ item }}
    </div>
    <template #prepend-footer>
      <ElButton type="primary" link @click="handleUpdate(6)">
        点击更新数据
      </ElButton>
    </template>
  </Drawer>
</template>
