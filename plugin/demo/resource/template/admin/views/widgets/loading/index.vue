<script lang="ts" setup>
import { Loading, Spinner } from '@vben/common-ui';

import { ElCard } from 'element-plus';

import { Page } from '#/components/page';
</script>

<template>
  <Page description="加载状态组件示例" title="Loading">
    <ElCard class="mb-4">
      <template #header>Vben Loading</template>
      <div class="flex gap-4">
        <div class="h-[100px] w-[100px]">
          <Loading :spinning="true" />
        </div>
        <div class="h-[100px] w-[100px]">
          <Loading :spinning="true" text="加载中..." />
        </div>
      </div>
    </ElCard>

    <ElCard class="mb-4">
      <template #header>Vben Spinner</template>
      <div class="flex gap-4">
        <Spinner class="h-[40px] w-[40px]" />
        <Spinner class="h-[40px] w-[40px]" :spinning="true" />
      </div>
    </ElCard>

    <ElCard>
      <template #header>v-loading 指令</template>
      <div
        v-loading="true"
        class="h-[100px] w-[200px] flex items-center justify-center"
      >
        内容区域
      </div>
    </ElCard>
  </Page>
</template>
