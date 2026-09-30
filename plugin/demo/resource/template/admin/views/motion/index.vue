<script lang="ts" setup>
import { reactive } from 'vue';

import { Page } from '@vben/common-ui';
import { Motion, MotionGroup, MotionPresets } from '@vben/plugins/motion';

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
import { refAutoReset, watchDebounced } from '@vueuse/core';

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
      <el-button type="primary" link @click="openDocPage">查看文档</el-button>
    </template>

    <el-card class="mb-2" shadow="never">
      <template #header>
        <div class="flex items-center justify-between">
          <span>使用指令</span>
          <el-button type="primary" @click="showCard1 = false">重载</el-button>
        </div>
      </template>
      <div>
        <div class="relative flex gap-2 overflow-hidden" v-if="showCard1">
          <el-button v-motion-fade-visible>fade</el-button>
          <el-button v-motion-pop-visible :duration="500">pop</el-button>
          <el-button v-motion-slide-left>slide-left</el-button>
          <el-button v-motion-slide-right>slide-right</el-button>
          <el-button v-motion-slide-bottom>slide-bottom</el-button>
          <el-button v-motion-slide-top>slide-top</el-button>
        </div>
      </div>
    </el-card>

    <el-card class="mb-2" shadow="never">
      <template #header>
        <span>使用组件（将内部作为一个整体添加动画）</span>
      </template>
      <div class="flex-center relative min-h-32 gap-2 overflow-hidden">
        <Motion v-bind="motionProps" v-if="showCard2" class="flex items-center gap-2">
          <el-button size="large">这个按钮在显示时会有动画效果</el-button>
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
            <el-button size="large">按钮{{ i }}</el-button>
          </Motion>
        </div>
      </div>
      <div class="mt-2">
        <el-form :model="motionProps" label-width="100px" size="small">
          <el-row :gutter="16">
            <el-col :span="8">
              <el-form-item label="动画效果">
                <el-select v-model="motionProps.preset">
                  <el-option
                    v-for="preset in presets"
                    :key="preset"
                    :label="preset"
                    :value="preset"
                  />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="持续时间">
                <el-input-number v-model="motionProps.duration" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="延迟动画">
                <el-input-number v-model="motionProps.delay" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="Hover缩放">
                <el-input-number v-model="motionProps.hovered.scale" :step="0.1" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="按下时缩放">
                <el-input-number v-model="motionProps.tapped.scale" :step="0.1" />
              </el-form-item>
            </el-col>
          </el-row>
        </el-form>
      </div>
    </el-card>

    <el-card shadow="never">
      <template #header>
        <span>分组动画（每个子元素都会应用相同的独立动画）</span>
      </template>
      <div class="flex-center relative min-h-32 gap-2 overflow-hidden">
        <MotionGroup v-bind="motionGroupProps" v-if="showCard3">
          <el-button size="large">按钮1</el-button>
          <el-button size="large">按钮2</el-button>
          <el-button size="large">按钮3</el-button>
          <el-button size="large">按钮4</el-button>
          <el-button size="large">按钮5</el-button>
        </MotionGroup>
      </div>
      <div class="mt-2">
        <el-form :model="motionGroupProps" label-width="100px" size="small">
          <el-row :gutter="16">
            <el-col :span="8">
              <el-form-item label="动画效果">
                <el-select v-model="motionGroupProps.preset">
                  <el-option
                    v-for="preset in presets"
                    :key="preset"
                    :label="preset"
                    :value="preset"
                  />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="持续时间">
                <el-input-number v-model="motionGroupProps.duration" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="延迟动画">
                <el-input-number v-model="motionGroupProps.delay" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="Hover缩放">
                <el-input-number v-model="motionGroupProps.hovered.scale" :step="0.1" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="按下时缩放">
                <el-input-number v-model="motionGroupProps.tapped.scale" :step="0.1" />
              </el-form-item>
            </el-col>
          </el-row>
        </el-form>
      </div>
    </el-card>
  </Page>
</template>
