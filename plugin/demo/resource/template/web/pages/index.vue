<template>
  <div class="plugin-demo-page">
    <div class="page-header">
      <h1>{{ t('test.pages.title') }}</h1>
      <p class="description">{{ t('test.pages.pluginDescription') }}</p>
    </div>

    <div class="demo-grid">
      <TestCounterCard :title="t('test.pages.usageGuide')" animated />
      <TestMessageList />
    </div>
  </div>
</template>

<script setup lang="ts">
import { TestCounterCard, TestMessageList } from '~/plugin/demo'
</script>

<style scoped lang="scss">
.plugin-demo-page {
  max-width: 960px;
  margin: 0 auto;
  padding: 40px 24px;
}

.page-header {
  margin-bottom: 40px;

  h1 {
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 8px;
  }

  .description {
    color: var(--el-text-color-secondary);
    font-size: 15px;
    line-height: 1.6;
  }
}

.demo-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;

  @media (max-width: 768px) {
    grid-template-columns: 1fr;
  }
}
</style>
