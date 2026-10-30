<script lang="ts" setup>
import { ElButton } from 'element-plus';

import { openWindow } from '#/core/shared';
import { VBEN_DOC_URL } from '#/core/shared/constants';

defineProps<{ path?: string }>();
</script>

<template>
  <ElButton
    v-if="path"
    link
    type="primary"
    @click="openWindow(`${VBEN_DOC_URL}${path}`, { target: '_blank' })"
  >
    查看文档
  </ElButton>
</template>
