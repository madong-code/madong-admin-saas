<script lang="ts" setup>
import { JsonViewer } from '@vben/common-ui';

import { ElCard, ElMessage } from 'element-plus';

import { Page } from '#/components/page';

const json1 = {
  additionalInfo: {
    author: 'Your Name',
    debug: true,
    version: '1.3.10',
    versionCode: 132,
  },
  tools: [
    { name: 'Tool 1', description: 'Description of Tool 1' },
    { name: 'Tool 2', description: 'Description of Tool 2' },
    { name: 'Tool 3', description: 'Description of Tool 3' },
  ],
};

const json2 = {
  id: 'chatgpt-123',
  object: 'chat.completion',
  created: 1_677_652_288,
  model: 'gpt-3.5-turbo-0613',
  choices: [
    {
      index: 0,
      message: {
        role: 'assistant',
        content: 'Hello there, how may I assist you today?',
      },
      finish_reason: 'stop',
    },
  ],
  usage: { prompt_tokens: 9, completion_tokens: 12, total_tokens: 21 },
};
</script>

<template>
  <Page description="JSON 查看器组件示例" title="JsonViewer">
    <ElCard class="mb-4">
      <template #header>默认配置</template>
      <JsonViewer :value="json1" />
    </ElCard>

    <ElCard class="mb-4">
      <template #header>可复制 + 默认展开3层 + 显示边框</template>
      <JsonViewer
        :value="json2"
        :copyable="true"
        :expand-depth="3"
        bordered
        @copy="() => ElMessage.success('已复制到剪贴板')"
      />
    </ElCard>

    <ElCard>
      <template #header>预览模式</template>
      <JsonViewer :value="json1" preview />
    </ElCard>
  </Page>
</template>
