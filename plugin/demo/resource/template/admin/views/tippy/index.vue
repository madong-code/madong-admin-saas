<script lang="ts" setup>
import { Page, Tippy } from '@vben/common-ui';

import { ElButton, ElCard } from 'element-plus';
</script>

<template>
  <Page description="Tippy 提示工具组件示例" title="Tippy">
    <ElCard class="mb-4">
      <template #header>指令形式使用</template>
      <div class="flex flex-wrap gap-4">
        <ElButton v-tippy="'默认提示'">默认提示</ElButton>
        <ElButton v-tippy="{ content: '指定主题', theme: 'light' }">light 主题</ElButton>
        <ElButton v-tippy="{ content: '指定延时', delay: [500, 200] }">延时显示</ElButton>
        <ElButton v-tippy="{ content: '指定动画', animation: 'scale' }">scale 动画</ElButton>
      </div>
    </ElCard>

    <ElCard>
      <template #header>组件形式使用</template>
      <div class="flex flex-wrap gap-4">
        <Tippy content="组件形式提示">
          <ElButton>组件形式</ElButton>
        </Tippy>
        <Tippy content="light 主题" theme="light">
          <ElButton>light 主题</ElButton>
        </Tippy>
        <Tippy content="点击触发" trigger="click">
          <ElButton>点击触发</ElButton>
        </Tippy>
      </div>
    </ElCard>
  </Page>
</template>
