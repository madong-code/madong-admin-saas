<script lang="ts" setup>
import { reactive } from 'vue';

import { refAutoReset, watchDebounced } from '@vueuse/core';
import {
  ElButton,
  ElCard,
  ElCol,
  ElForm,
  ElFormItem,
  ElInputNumber,
  ElOption,
  ElRow,
  ElSelect,
} from 'element-plus';

import { Page } from '#/components/page';
import { Motion, MotionGroup, MotionPresets } from '#/core/plugins/motion';

// 本例子用不到visible类型的动画
const presets = MotionPresets.filter((v) => !v.includes('Visible'));
const showCard1 = refAutoReset(true, 100);
const showCard2 = refAutoReset(true, 100);
const showCard3 = refAutoReset(true, 100);

const motionProps = reactive({
  delay: 0,
  duration: 300,
  enter: { scale: 1 },
  hovered: { scale: 1.1, transition: { delay: 0, duration: 50 } },
  preset: 'fade',
  tapped: { scale: 0.9, transition: { delay: 0, duration: 50 } },
});

const motionGroupProps = reactive({
  delay: 0,
  duration: 300,
  enter: { scale: 1 },
  hovered: { scale: 1.1, transition: { delay: 0, duration: 50 } },
  preset: 'fade',
  tapped: { scale: 0.9, transition: { delay: 0, duration: 50 } },
});

watchDebounced(
  motionProps,
  () => {
    showCard2.value = false;
  },
  { debounce: 200, deep: true },
);

watchDebounced(
  motionGroupProps,
  () => {
    showCard3.value = false;
  },
  { debounce: 200, deep: true },
);

function openDocPage() {
  window.open('https://motion.vueuse.org/', '_blank');
}
</script>

<template>
  <Page title="Motion">
    <template #description>
      <span>一个易于使用的为其它组件赋予动画效果的组件。</span>
      <ElButton type="primary" link @click="openDocPage">查看文档</ElButton>
    </template>

    <ElCard class="mb-2" shadow="never">
      <template #header>
        <div class="flex items-center justify-between">
          <span>使用指令</span>
          <ElButton type="primary" @click="showCard1 = false">重载</ElButton>
        </div>
      </template>
      <div>
        <div class="relative flex gap-2 overflow-hidden" v-if="showCard1">
          <ElButton v-motion-fade-visible>fade</ElButton>
          <ElButton v-motion-pop-visible :duration="500">pop</ElButton>
          <ElButton v-motion-slide-left>slide-left</ElButton>
          <ElButton v-motion-slide-right>slide-right</ElButton>
          <ElButton v-motion-slide-bottom>slide-bottom</ElButton>
          <ElButton v-motion-slide-top>slide-top</ElButton>
        </div>
      </div>
    </ElCard>

    <ElCard class="mb-2" shadow="never">
      <template #header>
        <span>使用组件（将内部作为一个整体添加动画）</span>
      </template>
      <div class="flex-center relative min-h-32 gap-2 overflow-hidden">
        <Motion
          v-bind="motionProps"
          v-if="showCard2"
          class="flex items-center gap-2"
        >
          <ElButton size="large">这个按钮在显示时会有动画效果</ElButton>
          <span>附属组件，会作为整体处理动画</span>
        </Motion>
      </div>
      <div class="flex-center relative min-h-32 gap-2 overflow-hidden">
        <div v-if="showCard2" class="flex items-center gap-2">
          <span>顺序延迟</span>
          <Motion
            v-bind="{
              ...motionProps,
              delay: motionProps.delay + 100 * i,
            }"
            v-for="i in 5"
            :key="i"
          >
            <ElButton size="large">按钮{{ i }}</ElButton>
          </Motion>
        </div>
      </div>
      <div class="mt-2">
        <ElForm :model="motionProps" label-width="100px" size="small">
          <ElRow :gutter="16">
            <ElCol :span="8">
              <ElFormItem label="动画效果">
                <ElSelect v-model="motionProps.preset">
                  <ElOption
                    v-for="preset in presets"
                    :key="preset"
                    :label="preset"
                    :value="preset"
                  />
                </ElSelect>
              </ElFormItem>
            </ElCol>
            <ElCol :span="8">
              <ElFormItem label="持续时间">
                <ElInputNumber v-model="motionProps.duration" />
              </ElFormItem>
            </ElCol>
            <ElCol :span="8">
              <ElFormItem label="延迟动画">
                <ElInputNumber v-model="motionProps.delay" />
              </ElFormItem>
            </ElCol>
            <ElCol :span="8">
              <ElFormItem label="Hover缩放">
                <ElInputNumber
                  v-model="motionProps.hovered.scale"
                  :step="0.1"
                />
              </ElFormItem>
            </ElCol>
            <ElCol :span="8">
              <ElFormItem label="按下时缩放">
                <ElInputNumber v-model="motionProps.tapped.scale" :step="0.1" />
              </ElFormItem>
            </ElCol>
          </ElRow>
        </ElForm>
      </div>
    </ElCard>

    <ElCard shadow="never">
      <template #header>
        <span>分组动画（每个子元素都会应用相同的独立动画）</span>
      </template>
      <div class="flex-center relative min-h-32 gap-2 overflow-hidden">
        <MotionGroup v-bind="motionGroupProps" v-if="showCard3">
          <ElButton size="large">按钮1</ElButton>
          <ElButton size="large">按钮2</ElButton>
          <ElButton size="large">按钮3</ElButton>
          <ElButton size="large">按钮4</ElButton>
          <ElButton size="large">按钮5</ElButton>
        </MotionGroup>
      </div>
      <div class="mt-2">
        <ElForm :model="motionGroupProps" label-width="100px" size="small">
          <ElRow :gutter="16">
            <ElCol :span="8">
              <ElFormItem label="动画效果">
                <ElSelect v-model="motionGroupProps.preset">
                  <ElOption
                    v-for="preset in presets"
                    :key="preset"
                    :label="preset"
                    :value="preset"
                  />
                </ElSelect>
              </ElFormItem>
            </ElCol>
            <ElCol :span="8">
              <ElFormItem label="持续时间">
                <ElInputNumber v-model="motionGroupProps.duration" />
              </ElFormItem>
            </ElCol>
            <ElCol :span="8">
              <ElFormItem label="延迟动画">
                <ElInputNumber v-model="motionGroupProps.delay" />
              </ElFormItem>
            </ElCol>
            <ElCol :span="8">
              <ElFormItem label="Hover缩放">
                <ElInputNumber
                  v-model="motionGroupProps.hovered.scale"
                  :step="0.1"
                />
              </ElFormItem>
            </ElCol>
            <ElCol :span="8">
              <ElFormItem label="按下时缩放">
                <ElInputNumber
                  v-model="motionGroupProps.tapped.scale"
                  :step="0.1"
                />
              </ElFormItem>
            </ElCol>
          </ElRow>
        </ElForm>
      </div>
    </ElCard>
  </Page>
</template>
