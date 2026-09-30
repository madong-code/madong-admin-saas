<script lang="ts" setup>
import { VBEN_DOC_URL } from '@vben/constants';
import { openWindow } from '@vben/utils';

import { ElButton } from 'element-plus';

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
