<script lang="ts" setup>
import { ElCard, ElMessage } from 'element-plus';

import { Page } from '#/components/page';
import { SliderCaptcha } from '#/core/ui/common';
</script>

<template>
  <Page description="滑块验证码组件示例" title="滑块验证">
    <ElCard class="mb-4">
      <template #header>基本示例</template>
      <div class="flex justify-center p-4">
        <SliderCaptcha
          src="https://unpkg.com/@vbenjs/static-source@0.1.7/source/pro-avatar.webp"
          @success="() => ElMessage.success('验证通过！')"
        />
      </div>
    </ElCard>
  </Page>
</template>
