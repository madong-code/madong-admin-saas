<script lang="ts" setup>
import { CountTo, Page } from '@vben/common-ui';

import { ElCard } from 'element-plus';
</script>

<template>
  <Page description="数字滚动动画组件示例" title="CountTo">
    <ElCard class="mb-4">
      <template #header>基本使用</template>
      <div class="flex flex-wrap gap-8">
        <div class="text-center">
          <CountTo :end-val="1000" class="text-3xl font-bold" prefix="$" />
          <div class="mt-2 text-sm text-gray-500">基础数字</div>
        </div>
        <div class="text-center">
          <CountTo
            :end-val="9999"
            :duration="3000"
            class="text-3xl font-bold text-blue-500"
            separator=","
          />
          <div class="mt-2 text-sm text-gray-500">千分位分隔</div>
        </div>
        <div class="text-center">
          <CountTo
            :end-val="99.99"
            :decimals="2"
            class="text-3xl font-bold text-green-500"
            suffix="%"
          />
          <div class="mt-2 text-sm text-gray-500">小数+后缀</div>
        </div>
      </div>
    </ElCard>

    <ElCard>
      <template #header>动画效果</template>
      <div class="flex flex-wrap gap-8">
        <div class="text-center">
          <CountTo
            :start-val="0"
            :end-val="5000"
            :duration="2000"
            class="text-3xl font-bold text-red-500"
          />
          <div class="mt-2 text-sm text-gray-500">2秒动画</div>
        </div>
        <div class="text-center">
          <CountTo
            :start-val="5000"
            :end-val="0"
            :duration="2000"
            class="text-3xl font-bold text-orange-500"
          />
          <div class="mt-2 text-sm text-gray-500">倒计时</div>
        </div>
      </div>
    </ElCard>
  </Page>
</template>
