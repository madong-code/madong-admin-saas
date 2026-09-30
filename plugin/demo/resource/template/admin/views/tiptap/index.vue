<script lang="ts" setup>
import { ref } from 'vue';

import { Page } from '@vben/common-ui';

import { ElCard, ElSwitch } from 'element-plus';

import { VbenTiptap, VbenTiptapPreview, type ImageUploadOptions } from '@vben/plugins/tiptap';

const content = ref(`
  <h1>Vben Tiptap</h1>
  <p>这是一个富文本编辑器示例。</p>
  <p>你可以直接在各个 app 里通过 <code>@vben/plugins/tiptap</code> 引入。</p>
  <blockquote>默认内置 StarterKit、Underline、TextAlign、Placeholder。</blockquote>
`);

const enableImageUpload = ref(true);

const imageUpload: ImageUploadOptions = {
  accept: 'image/*',
  upload: async (_file: File, onProgress?: (percent: number) => void) => {
    onProgress?.(30);
    await new Promise((resolve) => setTimeout(resolve, 500));
    onProgress?.(70);
    await new Promise((resolve) => setTimeout(resolve, 500));
    onProgress?.(100);
    return `https://picsum.photos/id/${Math.floor(Math.random() * 100)}/800/400`;
  },
};
</script>

<template>
  <Page description="富文本编辑器组件示例" title="富文本编辑器">
    <ElCard class="mb-4">
      <template #header>
        <div class="flex items-center justify-between">
          <span>编辑器</span>
          <div class="flex items-center gap-2">
            <span class="text-sm">图片上传</span>
            <ElSwitch v-model="enableImageUpload" />
          </div>
        </div>
      </template>
      <VbenTiptap v-model="content" :image-upload="enableImageUpload ? imageUpload : undefined" class="min-h-[300px]" />
    </ElCard>

    <ElCard class="mb-4">
      <template #header>预览</template>
      <VbenTiptapPreview :value="content" />
    </ElCard>

    <ElCard>
      <template #header>HTML 输出</template>
      <pre class="max-h-[200px] overflow-auto bg-gray-50 p-4 text-sm">{{ content }}</pre>
    </ElCard>
  </Page>
</template>
