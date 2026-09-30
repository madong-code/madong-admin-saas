<script lang="ts" setup>
import { EllipsisText } from '@vben/common-ui';

import { ElCard } from 'element-plus';

import { Page } from '#/components/page';

import DocButton from '../../doc-button.vue';

const longText =
  '这是一段很长的文本内容，用来测试文本省略组件的效果。当文本超过容器宽度时，会自动省略显示，鼠标悬停可以查看完整内容。支持自定义最大宽度、多行省略、点击展开等功能。';
</script>

<template>
  <Page description="文本省略组件示例" title="文本省略">
    <template #extra>
      <DocButton path="/components/common-ui/ellipsis-text" />
    </template>

    <ElCard class="mb-4">
      <template #header>基本使用</template>
      <div class="w-[300px]">
        <EllipsisText :max-width="300">{{ longText }}</EllipsisText>
      </div>
    </ElCard>

    <ElCard class="mb-4">
      <template #header>多行省略</template>
      <div class="w-[300px]">
        <EllipsisText :line="2">{{ longText }}</EllipsisText>
      </div>
    </ElCard>

    <ElCard>
      <template #header>点击展开</template>
      <div class="w-[300px]">
        <EllipsisText :line="2" expand>{{ longText }}</EllipsisText>
      </div>
    </ElCard>
  </Page>
</template>
