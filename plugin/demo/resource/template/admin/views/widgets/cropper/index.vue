<script lang="ts" setup>
import { ref } from 'vue';

import { ElButton, ElCard, ElOption, ElSelect } from 'element-plus';

import { Page } from '#/components/page';
import { VCropper } from '#/core/ui/common';

const imageUrl = ref('');
const aspectRatio = ref<string>('');

const options = [
  { label: '不限制', value: '' },
  { label: '1:1', value: '1:1' },
  { label: '16:9', value: '16:9' },
  { label: '4:3', value: '4:3' },
];

function handleUpload() {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*';
  input.addEventListener('change', (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) {
      const reader = new FileReader();
      reader.addEventListener('load', (ev) => {
        imageUrl.value = ev.target?.result as string;
      });
      reader.readAsDataURL(file);
    }
  });
  input.click();
}
</script>

<template>
  <Page description="图片裁剪组件示例" title="图片裁剪">
    <ElCard class="mb-4">
      <template #header>
        <div class="flex items-center justify-between">
          <span>裁剪示例</span>
          <div class="flex items-center gap-2">
            <ElSelect v-model="aspectRatio" style="width: 120px">
              <ElOption
                v-for="opt in options"
                :key="opt.value"
                :label="opt.label"
                :value="opt.value"
              />
            </ElSelect>
            <ElButton type="primary" @click="handleUpload">上传图片</ElButton>
          </div>
        </div>
      </template>
      <div v-if="imageUrl" class="flex justify-center">
        <VCropper
          :img="imageUrl"
          :aspect-ratio="aspectRatio || undefined"
          output-type="png"
          class="w-[400px]"
        />
      </div>
      <div v-else class="py-10 text-center text-gray-400">
        请上传图片进行裁剪
      </div>
    </ElCard>
  </Page>
</template>
