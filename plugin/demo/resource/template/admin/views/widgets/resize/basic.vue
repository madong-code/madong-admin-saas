<script lang="ts" setup>
import { ref } from 'vue';

import { Page } from '#/components/page';
import { VResize } from '#/core/ui/common';

const colorMap = ['#ef4444', '#22c55e', '#eab308', '#6b7280'];

const sizeList = ref([
  { width: 300, height: 200, top: 0, left: 0 },
  { width: 200, height: 200, top: 0, left: 320 },
  { width: 200, height: 150, top: 220, left: 0 },
  { width: 300, height: 150, top: 220, left: 220 },
]);
</script>

<template>
  <Page description="拖拽调整大小组件示例" title="拖拽调整">
    <div class="relative h-[400px] w-full">
      <VResize
        v-for="(item, index) in sizeList"
        :key="index"
        v-model:width="item.width"
        v-model:height="item.height"
        v-model:top="item.top"
        v-model:left="item.left"
        class="absolute"
      >
        <div
          :style="{ backgroundColor: colorMap[index] }"
          class="flex h-full w-full items-center justify-center border border-gray-400"
        >
          <div class="text-center text-white drop-shadow">
            <div>{{ item.width }} x {{ item.height }}</div>
            <div class="text-xs opacity-80">
              top: {{ item.top }}, left: {{ item.left }}
            </div>
          </div>
        </div>
      </VResize>
    </div>
  </Page>
</template>
