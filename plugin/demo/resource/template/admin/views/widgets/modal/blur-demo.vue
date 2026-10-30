<script lang="ts" setup>
import { ref, watch } from 'vue';

import { ElSlider } from 'element-plus';

import { useVbenModal } from '#/core/ui/common';

const blur = ref(5);
const [Modal, modalApi] = useVbenModal({
  overlayBlur: blur.value,
});
watch(blur, (val) => {
  modalApi.setState({ overlayBlur: val });
});
</script>

<template>
  <Modal title="遮罩层模糊">
    <p>调整滑块来改变遮罩层模糊程度：{{ blur }}</p>
    <ElSlider v-model="blur" :max="30" :min="0" />
  </Modal>
</template>
