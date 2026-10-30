<script lang="ts" setup>
import type { FlowGraphData } from './types';

import { computed, onBeforeUnmount, ref, watch } from 'vue';

import FlowDesigner from '#lib/visual-flow/visual-flow.js';
import {
  ElButton,
  ElCard,
  ElMessage,
  ElRadioButton,
  ElRadioGroup,
} from 'element-plus';

import { Page } from '#/components/page';
import { usePreferences } from '#/core/preferences';
import { $t } from '#/locales';

import '#lib/visual-flow/style.css';

/**
 * 示例流程图数据（请假审批流程）
 * 参考根目录 visual-flow 工程 examples/assets/wf-leave.json
 */
const LEAVE_GRAPH: FlowGraphData = {
  name: 'wf-leave',
  display_name: '请假流程',
  instance_url: 'leaveForm',
  nodes: [
    {
      id: 'start',
      type: 'ingenious:start',
      x: 280,
      y: 280,
      properties: { width: 120, height: 40 },
      text: { x: 280, y: 320, value: '开始' },
    },
    {
      id: 'apply',
      type: 'ingenious:task',
      x: 480,
      y: 280,
      properties: {
        width: 120,
        height: 40,
        assignee: 'apply.operator',
        task_type: 'Major',
        perform_type: 'ANY',
        auto_execute: 'N',
      },
      text: { x: 480, y: 280, value: '请假申请' },
    },
    {
      id: 'approveDept',
      type: 'ingenious:task',
      x: 680,
      y: 280,
      properties: {
        width: 120,
        height: 40,
        assignment_handler: 'DeptAssignmentHandler',
        task_type: 'Major',
        perform_type: 'ANY',
        auto_execute: 'N',
      },
      text: { x: 680, y: 280, value: '部门领导审批' },
    },
    {
      id: 'd1',
      type: 'ingenious:decision',
      x: 880,
      y: 280,
      properties: { width: 120, height: 40 },
    },
    {
      id: 'approveHr',
      type: 'ingenious:task',
      x: 1080,
      y: 100,
      properties: {
        width: 120,
        height: 40,
        assignment_handler: 'HrAssignmentHandler',
        task_type: 'Major',
        perform_type: 'ANY',
        auto_execute: 'N',
      },
      text: { x: 1080, y: 100, value: '人事审批' },
    },
    {
      id: 'approveBoss',
      type: 'ingenious:task',
      x: 1080,
      y: 460,
      properties: {
        width: 120,
        height: 40,
        assignment_handler: 'BossAssignmentHandler',
        task_type: 'Major',
        perform_type: 'ANY',
        auto_execute: 'N',
      },
      text: { x: 1080, y: 460, value: '公司领导审批' },
    },
    {
      id: 'end',
      type: 'ingenious:end',
      x: 1280,
      y: 280,
      properties: { width: 120, height: 40 },
      text: { x: 1280, y: 320, value: '结束' },
    },
  ],
  edges: [
    {
      id: 't1',
      type: 'ingenious:transition',
      sourceNodeId: 'start',
      targetNodeId: 'apply',
      properties: {},
    },
    {
      id: 't2',
      type: 'ingenious:transition',
      sourceNodeId: 'apply',
      targetNodeId: 'approveDept',
      properties: {},
    },
    {
      id: 't3',
      type: 'ingenious:transition',
      sourceNodeId: 'approveDept',
      targetNodeId: 'd1',
      properties: {},
    },
    {
      id: 't4',
      type: 'ingenious:transition',
      sourceNodeId: 'd1',
      targetNodeId: 'approveHr',
      properties: { expr: '#f_day<3' },
      text: { value: '请假天数小于3' },
    },
    {
      id: 't5',
      type: 'ingenious:transition',
      sourceNodeId: 'd1',
      targetNodeId: 'approveBoss',
      properties: { expr: '#f_day>=3' },
      text: { value: '请假天数大于等于3' },
    },
    {
      id: 't6',
      type: 'ingenious:transition',
      sourceNodeId: 'approveBoss',
      targetNodeId: 'end',
      properties: {},
    },
    {
      id: 't7',
      type: 'ingenious:transition',
      sourceNodeId: 'approveHr',
      targetNodeId: 'end',
      properties: {},
    },
  ],
};

/** 当前渲染模式 */
type FlowMode = 'canvas' | 'dingtalk';
const mode = ref<FlowMode>('canvas');

/** 是否查看模式（预览） */
const viewer = ref(false);

/** 流程图数据（v-model:value） */
const graphData = ref<FlowGraphData>(structuredClone(LEAVE_GRAPH));

/** 全局主题，联动 visual-flow 的 data-theme */
const { isDark } = usePreferences();

/** 同步主题：给根节点设置 data-theme，visual-flow 画布会通过 MutationObserver 自动刷新 */
function applyTheme() {
  if (isDark.value) {
    document.documentElement.dataset.theme = 'dark';
  } else {
    delete document.documentElement.dataset.theme;
  }
}
applyTheme();
watch(isDark, applyTheme);

/** 事件日志 */
interface LogItem {
  time: string;
  type: string;
  msg: string;
}
const logs = ref<LogItem[]>([]);
function log(type: string, msg: string) {
  const now = new Date();
  const time = `${String(now.getHours()).padStart(2, '0')}:${String(
    now.getMinutes(),
  ).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`;
  logs.value.unshift({ time, type, msg });
  if (logs.value.length > 30) logs.value.pop();
}

/** 初始化事件：拿到 lf / designerApi 实例 */
function handleInit(api: any) {
  log('init', `设计器初始化完成（模式：${mode.value}），实例已就绪`);
  if (api?.eventCenter) {
    api.eventCenter.on('update:graphModel', (data: any) => {
      log('event', `update:graphModel 触发，name=${data?.name}`);
    });
  }
}

/** 节点点击 */
function handleNodeClick(params: any) {
  log(
    'event',
    `node-click: id=${params?.data?.id}, type=${params?.data?.type}, ` +
      `text=${params?.data?.text?.value}`,
  );
}

/** 边点击 */
function handleEdgeClick(params: any) {
  log(
    'event',
    `edge-click: id=${params?.data?.id}, type=${params?.data?.type}`,
  );
}

/** 保存事件 */
function handleSave(_data: any) {
  ElMessage.success('流程保存成功');
  log('ok', '触发保存事件 @on-save');
}

/** 重置数据 */
function reset() {
  graphData.value = structuredClone(LEAVE_GRAPH);
  ElMessage.success('已重置为初始数据');
  log('event', '已重置数据');
}

/** 组件卸载时清除主题标记 */
onBeforeUnmount(() => {
  delete document.documentElement.dataset.theme;
});

const modeOptions = computed(() => [
  { label: $t('demo.widgets.visual-flow.canvas_mode'), value: 'canvas' },
  { label: $t('demo.widgets.visual-flow.dingtalk_mode'), value: 'dingtalk' },
]);
</script>

<template>
  <Page auto-content-height class="visual-flow-page">
    <ElCard class="visual-flow-card">
      <template #header>
        <div class="flow-card-header">
          <div class="flow-card-title">
            {{ $t('demo.widgets.visual-flow.title') }}
          </div>
          <div class="flow-card-desc">
            {{ $t('demo.widgets.visual-flow.desc') }}
          </div>
        </div>
      </template>

      <div class="flow-toolbar">
        <div class="flow-toolbar-left">
          <ElRadioGroup v-model="mode" size="small">
            <ElRadioButton
              v-for="opt in modeOptions"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </ElRadioButton>
          </ElRadioGroup>
          <span class="flow-toolbar-tip">
            {{
              viewer
                ? $t('demo.widgets.visual-flow.viewer_on')
                : $t('demo.widgets.visual-flow.viewer_off')
            }}
          </span>
        </div>
        <div class="flow-toolbar-right">
          <ElButton size="small" @click="reset">
            {{ $t('demo.widgets.visual-flow.reset') }}
          </ElButton>
          <ElButton size="small" type="primary" @click="viewer = !viewer">
            {{ $t('demo.widgets.visual-flow.toggle_viewer') }}
          </ElButton>
        </div>
      </div>

      <div class="flow-body">
        <!--
          可视化流程设计器（来自 lib/visual-flow，组件名 VisualFlow）
          :key 绑定 viewer，切换查看/编辑时强制重建 LogicFlow，
          使 isSilentMode 生效（库只在 init 时读取 viewer，运行时切换不会重建实例）
        -->
        <div class="flow-designer-wrap">
          <FlowDesigner
            :key="viewer"
            v-model:value="graphData"
            :mode="mode"
            :viewer="viewer"
            @on-init="handleInit"
            @on-save="handleSave"
            @node-click="handleNodeClick"
            @edge-click="handleEdgeClick"
          />
        </div>

        <!-- 事件日志 -->
        <div class="flow-log-panel">
          <div class="flow-log-header">
            <span>{{ $t('demo.widgets.visual-flow.event_log') }}</span>
            <ElButton size="small" text @click="logs = []">
              {{ $t('demo.widgets.visual-flow.clear') }}
            </ElButton>
          </div>
          <div class="flow-log-body">
            <div
              v-for="(item, i) in logs"
              :key="i"
              class="flow-log-item"
              :class="`is-${item.type}`"
            >
              <span class="flow-log-time">{{ item.time }}</span>
              <span class="flow-log-msg">{{ item.msg }}</span>
            </div>
            <div v-if="logs.length === 0" class="flow-log-empty">
              {{ $t('demo.widgets.visual-flow.no_log') }}
            </div>
          </div>
        </div>
      </div>
    </ElCard>
  </Page>
</template>

<style scoped>
.visual-flow-page {
  height: 100%;
}

.visual-flow-card {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
}

.visual-flow-card :deep(.el-card__header) {
  padding: 12px 16px;
}

.visual-flow-card :deep(.el-card__body) {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-height: 0;
  padding: 0;
}

.flow-card-header {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.flow-card-title {
  font-size: 15px;
  font-weight: 600;
  line-height: 1.4;
  color: var(--el-text-color-primary);
}

.flow-card-desc {
  font-size: 12px;
  line-height: 1.5;
  color: var(--el-text-color-secondary);
}

.flow-toolbar {
  z-index: 10;
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: space-between;
  padding: 8px 16px;
  background: var(--el-bg-color-overlay);
  border-bottom: 1px solid var(--el-border-color-lighter);
}

.flow-toolbar-left,
.flow-toolbar-right {
  display: flex;
  gap: 12px;
  align-items: center;
}

.flow-toolbar-tip {
  font-size: 12px;
  color: var(--el-text-color-secondary);
}

.flow-body {
  position: relative;
  display: flex;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.flow-designer-wrap {
  position: relative;
  flex: 1;
  min-width: 0;
  height: 100%;
  overflow: hidden;
}

.flow-designer-wrap :deep(.flow-container) {
  width: 100%;
  height: 100%;
}

.flow-log-panel {
  position: absolute;
  right: 16px;
  bottom: 16px;
  z-index: 20;
  display: flex;
  flex-direction: column;
  width: 360px;
  max-height: 260px;
  overflow: hidden;
  background: var(--el-bg-color-overlay);
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 8px;
  box-shadow: var(--el-box-shadow-light);
}

.flow-log-header {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: space-between;
  padding: 6px 12px;
  font-size: 13px;
  font-weight: 600;
  color: var(--el-text-color-primary);
  background: var(--el-fill-color-light);
  border-bottom: 1px solid var(--el-border-color-lighter);
}

.flow-log-body {
  flex: 1;
  min-height: 0;
  padding: 6px 12px;
  overflow-y: auto;
  font-family: Consolas, Monaco, monospace;
  font-size: 11px;
}

.flow-log-item {
  display: flex;
  gap: 6px;
  padding: 3px 0;
  line-height: 1.4;
  border-bottom: 1px dashed var(--el-border-color-extra-light);
}

.flow-log-time {
  flex-shrink: 0;
  color: var(--el-text-color-secondary);
}

.flow-log-msg {
  flex: 1;
  color: var(--el-text-color-primary);
  word-break: break-all;
}

.flow-log-item.is-init .flow-log-msg {
  color: var(--el-color-info);
}

.flow-log-item.is-event .flow-log-msg {
  color: var(--el-color-primary);
}

.flow-log-item.is-ok .flow-log-msg {
  color: var(--el-color-success);
}

.flow-log-item.is-warn .flow-log-msg {
  color: var(--el-color-warning);
}

.flow-log-empty {
  padding: 24px 12px;
  color: var(--el-text-color-placeholder);
  text-align: center;
}
</style>
