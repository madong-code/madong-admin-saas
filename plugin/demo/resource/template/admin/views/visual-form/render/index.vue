<script setup lang="ts">
import { ref } from 'vue';

import { useVForm } from '@madong/visual-form';
import { ElMessage } from 'element-plus';

import { $t } from '#/locales';

import { SAMPLE_FORM_DATA, SAMPLE_FORM_JSON } from '../builder/formSchema';

const { isReady, error } = useVForm();

type FormMode = 'disabled' | 'edit' | 'readonly';

const vFormRenderRef = ref<any>(null);
const formJson = ref<any>(null);
const formData = ref<any>({});
const optionData = ref<any>([]);

const currentMode = ref<FormMode>('edit');
const jsonDialogVisible = ref(false);
const activeJsonTab = ref('formSchema');
const jsonData = ref<null | {
  formData: any;
  formSchema: any;
  optionData: any;
}>(null);

const modeLabels: Record<FormMode, string> = {
  edit: '编辑模式',
  readonly: '只读模式',
  disabled: '禁用模式',
};

const modeDescriptions: Record<FormMode, string> = {
  edit: '所有表单字段可编辑，可修改内容后查看数据变化。',
  readonly: '表单字段为只读状态，不可修改内容，仅可查看。',
  disabled: '所有表单字段完全禁用，无法交互。',
};

const modeColors: Record<FormMode, string> = {
  edit: 'primary',
  readonly: 'info',
  disabled: 'warning',
};

// 初始化表单数据
loadFormData();

function loadFormData() {
  formJson.value = { ...SAMPLE_FORM_JSON };
  formData.value = { ...SAMPLE_FORM_DATA };
  optionData.value = [];
  currentMode.value = 'edit';
}

function handleModeChange(mode: FormMode) {
  if (!vFormRenderRef.value) return;

  const renderer = vFormRenderRef.value;

  switch (mode) {
    case 'disabled': {
      if (renderer.setReadMode) {
        renderer.setReadMode(false);
      }
      if (renderer.disableForm) {
        renderer.disableForm();
      }
      break;
    }
    case 'readonly': {
      if (renderer.setReadMode) {
        renderer.setReadMode(true);
      }
      if (renderer.disableForm) {
        renderer.disableForm();
      }
      break;
    }
    default: {
      if (renderer.setReadMode) {
        renderer.setReadMode(false);
      }
      if (renderer.enableForm) {
        renderer.enableForm();
      }
      break;
    }
  }
}

function handleReset() {
  // 重新加载表单，重置为编辑模式
  currentMode.value = 'edit';
  loadFormData();
  ElMessage.success('表单已重置');
}

function handleViewJson() {
  const renderer = vFormRenderRef.value;
  const data = renderer?.getFormData?.(false) || formData.value;

  jsonData.value = {
    formSchema: formJson.value,
    formData: data,
    optionData: optionData.value,
  };
  activeJsonTab.value = 'formSchema';
  jsonDialogVisible.value = true;
}

function formatJson(data: any): string {
  if (!data) return '';
  try {
    return JSON.stringify(data, null, 2);
  } catch {
    return String(data);
  }
}

async function copyJson() {
  if (!jsonData.value) return;
  const data =
    activeJsonTab.value === 'formSchema'
      ? jsonData.value.formSchema
      : (activeJsonTab.value === 'formData'
        ? jsonData.value.formData
        : jsonData.value.optionData);
  try {
    await navigator.clipboard.writeText(JSON.stringify(data, null, 2));
    ElMessage.success('已复制到剪贴板');
  } catch {
    ElMessage.error('复制失败');
  }
}
</script>

<template>
  <div class="form-render-page">
    <!-- 加载中 -->
    <div v-if="!isReady && !error" class="loading-wrapper">
      <ElButton type="primary" :loading="true">加载渲染器中...</ElButton>
    </div>
    <div v-else-if="error" class="loading-wrapper error-wrapper">
      <div class="error-msg">{{ error }}</div>
    </div>
    <template v-else>
      <!-- 顶部模式切换栏 -->
      <div class="render-toolbar">
        <div class="toolbar-left">
          <span class="toolbar-title">{{ $t('demo.form_render.title') }}</span>
          <ElTag
            :type="modeColors[currentMode] as any"
            effect="dark"
            size="small"
          >
            {{ modeLabels[currentMode] }}
          </ElTag>
        </div>
        <div class="toolbar-right">
          <ElRadioGroup v-model="currentMode" @change="handleModeChange">
            <ElRadioButton value="edit">编辑模式</ElRadioButton>
            <ElRadioButton value="readonly">只读模式</ElRadioButton>
            <ElRadioButton value="disabled">禁用模式</ElRadioButton>
          </ElRadioGroup>
          <ElButton @click="handleViewJson">查看 JSON</ElButton>
          <ElButton @click="handleReset">重置</ElButton>
        </div>
      </div>

      <!-- 模式描述 -->
      <div class="mode-description">
        {{ modeDescriptions[currentMode] }}
      </div>

      <!-- 表单渲染区域 -->
      <div class="render-content">
        <VFormRender
          ref="vFormRenderRef"
          :form-json="formJson"
          :form-data="formData"
          :option-data="optionData"
        />
      </div>

      <!-- JSON 对话框 -->
      <ElDialog
        v-model="jsonDialogVisible"
        title="表单 JSON"
        width="70%"
        destroy-on-close
      >
        <ElTabs v-model="activeJsonTab">
          <ElTabPane label="表单 Schema" name="formSchema">
            <pre class="json-content">{{
              formatJson(jsonData?.formSchema)
            }}</pre>
          </ElTabPane>
          <ElTabPane label="表单数据" name="formData">
            <pre class="json-content">{{ formatJson(jsonData?.formData) }}</pre>
          </ElTabPane>
          <ElTabPane label="选项数据" name="optionData">
            <pre class="json-content">{{
              formatJson(jsonData?.optionData)
            }}</pre>
          </ElTabPane>
        </ElTabs>
        <template #footer>
          <ElButton @click="copyJson">复制 JSON</ElButton>
          <ElButton type="primary" @click="jsonDialogVisible = false">
关闭
</ElButton>
        </template>
      </ElDialog>
    </template>
  </div>
</template>

<style scoped>
.form-render-page {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: #f5f5f5;
}

.render-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 16px;
  background: #fff;
  border-bottom: 1px solid #eef2f7;
  flex-shrink: 0;
  flex-wrap: wrap;
  gap: 8px;
  z-index: 10;
}

.toolbar-left {
  display: flex;
  align-items: center;
  gap: 8px;
}

.toolbar-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--el-text-color-primary);
}

.toolbar-right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.mode-description {
  padding: 8px 16px;
  background: #fafafa;
  border-bottom: 1px solid #eef2f7;
  font-size: 13px;
  color: var(--el-text-color-secondary);
  flex-shrink: 0;
}

.render-content {
  flex: 1;
  overflow: auto;
  padding: 16px;
  background: #fff;
  margin: 12px;
  border-radius: 4px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
}

.loading-wrapper {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}

.json-content {
  max-height: 500px;
  overflow: auto;
  background: #f5f5f5;
  padding: 16px;
  border-radius: 4px;
  font-size: 12px;
  line-height: 1.6;
}
</style>
