<script lang="ts" setup>
import { ref } from 'vue';

import { ElCard, ElTabPane, ElTabs } from 'element-plus';

import { ImageSelector } from '#/components/form/components';
import { Page } from '#/components/page';
import { $t } from '#/locales';

// 1. 单选
const singleValue = ref('');
// 2. 多选
const multipleValue = ref<string[]>([]);
// 3. 大尺寸单选（带默认值）
const defaultSingleValue = ref('/upload/default-avatar.webp');
// 4. 禁用
const disabledValue = ref('/upload/default-avatar.webp');
</script>

<template>
  <Page
    :description="$t('demo.widgets.image-selector.desc')"
    :title="$t('demo.widgets.image-selector.title')"
  >
    <!-- 1. 单选模式 -->
    <ElCard class="mb-4" shadow="never">
      <template #header>
        <span class="font-medium"
          >1. {{ $t('demo.widgets.image-selector.single_mode') }}</span
        >
      </template>
      <div class="flex items-center gap-5">
        <ImageSelector
          v-model="singleValue"
          accept="image/*"
          object-type="default"
          shape="circle"
          size="medium"
        />
        <div class="text-sm">
          <div class="font-medium">
            {{ $t('demo.widgets.image-selector.selected') }}:
          </div>
          <div
            class="mt-1 break-all"
            style="color: var(--el-text-color-secondary)"
          >
            {{ singleValue || $t('demo.widgets.image-selector.not_selected') }}
          </div>
        </div>
      </div>
    </ElCard>

    <!-- 2. 多选模式 -->
    <ElCard class="mb-4" shadow="never">
      <template #header>
        <span class="font-medium"
          >2. {{ $t('demo.widgets.image-selector.multiple_mode') }}</span
        >
      </template>
      <ImageSelector
        v-model="multipleValue"
        accept="image/*"
        object-type="default"
        :multiple="true"
        shape="square"
        size="small"
      />
      <div class="mt-3 text-sm">
        <div class="font-medium">
          {{ $t('demo.widgets.image-selector.selected') }} ({{
            multipleValue.length
          }}
          {{ $t('demo.widgets.image-selector.count') }}):
        </div>
        <div
          class="mt-1 break-all"
          style="color: var(--el-text-color-secondary)"
        >
          {{
            multipleValue.length > 0
              ? multipleValue.join(', ')
              : $t('demo.widgets.image-selector.not_selected')
          }}
        </div>
      </div>
    </ElCard>

    <!-- 3. 大尺寸单选（带默认值） -->
    <ElCard class="mb-4" shadow="never">
      <template #header>
        <span class="font-medium"
          >3. {{ $t('demo.widgets.image-selector.large_single') }}</span
        >
      </template>
      <div class="flex items-center gap-5">
        <ImageSelector
          v-model="defaultSingleValue"
          accept="image/*"
          object-type="default"
          shape="square"
          size="large"
        />
        <div class="text-sm">
          <div class="font-medium">
            {{ $t('demo.widgets.image-selector.selected') }}:
          </div>
          <div
            class="mt-1 break-all"
            style="color: var(--el-text-color-secondary)"
          >
            {{
              defaultSingleValue ||
              $t('demo.widgets.image-selector.not_selected')
            }}
          </div>
        </div>
      </div>
    </ElCard>

    <!-- 4. 禁用状态 -->
    <ElCard class="mb-4" shadow="never">
      <template #header>
        <span class="font-medium"
          >4. {{ $t('demo.widgets.image-selector.disabled') }}</span
        >
      </template>
      <ImageSelector
        v-model="disabledValue"
        accept="image/*"
        object-type="default"
        :disabled="true"
        shape="circle"
        size="medium"
      />
      <div class="mt-2 text-sm" style="color: var(--el-text-color-secondary)">
        {{ $t('demo.widgets.image-selector.disabled_tip') }}
      </div>
    </ElCard>

    <!-- 5. 代码示例 -->
    <ElCard shadow="never">
      <template #header>
        <span class="font-medium"
          >5. {{ $t('demo.widgets.image-selector.code_example') }}</span
        >
      </template>
      <ElTabs>
        <ElTabPane :label="$t('demo.widgets.image-selector.single')">
          <pre class="code-block"><code>&lt;ImageSelector
  v-model=&quot;singleValue&quot;
  accept=&quot;image/*&quot;
  object-type=&quot;default&quot;
  shape=&quot;circle&quot;
  size=&quot;medium&quot;
/&gt;</code></pre>
        </ElTabPane>
        <ElTabPane :label="$t('demo.widgets.image-selector.multiple')">
          <pre class="code-block"><code>&lt;ImageSelector
  v-model=&quot;multipleValue&quot;
  accept=&quot;image/*&quot;
  object-type=&quot;default&quot;
  :multiple=&quot;true&quot;
  shape=&quot;square&quot;
  size=&quot;small&quot;
/&gt;</code></pre>
        </ElTabPane>
        <ElTabPane :label="$t('demo.widgets.image-selector.large')">
          <pre class="code-block"><code>&lt;ImageSelector
  v-model=&quot;defaultSingleValue&quot;
  accept=&quot;image/*&quot;
  object-type=&quot;default&quot;
  shape=&quot;square&quot;
  size=&quot;large&quot;
/&gt;</code></pre>
        </ElTabPane>
        <ElTabPane :label="$t('demo.widgets.image-selector.disabled')">
          <pre class="code-block"><code>&lt;ImageSelector
  v-model=&quot;disabledValue&quot;
  accept=&quot;image/*&quot;
  object-type=&quot;default&quot;
  :disabled=&quot;true&quot;
  shape=&quot;circle&quot;
  size=&quot;medium&quot;
/&gt;</code></pre>
        </ElTabPane>
      </ElTabs>
    </ElCard>
  </Page>
</template>

<style lang="scss" scoped>
.code-block {
  padding: 0.75rem;
  overflow: auto;
  font-size: 0.875rem;
  background: var(--el-fill-color-light);
  border-radius: var(--el-border-radius-base);
}
</style>
