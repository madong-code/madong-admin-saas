<script lang="ts" setup>
import { ElButton, ElCard, ElMessage } from 'element-plus';

import { Page } from '#/components/page';
</script>

<template>
  <Page description="上下文菜单组件示例" title="上下文菜单">
    <ElCard>
      <template #header>右键点击按钮弹出上下文菜单</template>
      <div class="flex gap-4">
        <ElButton @contextmenu.prevent="() => ElMessage.info('刷新')">
          右键点击我
        </ElButton>
        <ElButton
          type="primary"
          @contextmenu.prevent="() => ElMessage.info('关闭当前')"
        >
          右键点击我
        </ElButton>
      </div>
      <p class="mt-4 text-sm text-gray-500">
        提示：VbenContextMenu 组件来自 @vben-core/shadcn-ui，在 admin
        应用中暂不可用。 你可以在 playground 应用中查看完整示例。
      </p>
    </ElCard>
  </Page>
</template>
