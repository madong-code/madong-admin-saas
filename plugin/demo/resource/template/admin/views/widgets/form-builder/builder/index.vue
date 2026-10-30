<script setup lang="ts">
import { reactive, ref } from 'vue';

import { ElButton, ElDialog, ElMessage, ElTabPane, ElTabs } from 'element-plus';

import { useVForm } from '#/core/plugins/visual-form';
import { $t } from '#/locales';

import { EMPTY_FORM_JSON, SAMPLE_FORM_JSON } from './formSchema';

const { isReady, error } = useVForm();

const vFormDesignerComponent = 'VFormDesigner';
const vfDesignerRef = ref<any>(null);

const jsonDialogVisible = ref(false);
const activeJsonTab = ref('formSchema');
const jsonData = ref<null | { formData: any; formSchema: any }>(null);

const formJson = ref<any>({ ...EMPTY_FORM_JSON });

const designerConfig = reactive({
  formTemplates: true,
  componentLib: true,
  logoHeader: false,
  exportCodeButton: false,
  generateSFCButton: false,
  toolbarMaxWidth: 300,
});

function loadSampleForm() {
  formJson.value = { ...SAMPLE_FORM_JSON };
  if (vfDesignerRef.value?.setFormJson) {
    vfDesignerRef.value.setFormJson({ ...SAMPLE_FORM_JSON });
  }
  ElMessage.success('已加载示例表单');
}

function clearDesigner() {
  formJson.value = { ...EMPTY_FORM_JSON };
  if (vfDesignerRef.value?.clearDesigner) {
    vfDesignerRef.value.clearDesigner();
  }
  ElMessage.success('已清空');
}

function getFormJson() {
  if (!vfDesignerRef.value) {
    ElMessage.warning('设计器未初始化');
    return;
  }
  const schema = vfDesignerRef.value.getFormJson?.() || formJson.value;
  jsonData.value = {
    formSchema: schema,
    formData: {},
  };
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
      : jsonData.value.formData;
  try {
    await navigator.clipboard.writeText(JSON.stringify(data, null, 2));
    ElMessage.success('已复制到剪贴板');
  } catch {
    ElMessage.error('复制失败');
  }
}
</script>

<template>
  <div class="form-builder-page">
    <!-- 加载中 -->
    <div v-if="!isReady && !error" class="designer-wrapper loading-wrapper">
      <div class="loading-text">加载设计器...</div>
    </div>
    <div v-else-if="error" class="designer-wrapper error-wrapper">
      <div class="error-msg">{{ error }}</div>
    </div>
    <template v-else>
      <!-- 顶部工具栏 -->
      <div class="builder-toolbar">
        <div class="toolbar-left">
          <span class="toolbar-title">{{
            $t('demo.widgets.form-builder.title')
          }}</span>
        </div>
        <div class="toolbar-right">
          <ElButton @click="loadSampleForm">加载示例</ElButton>
          <ElButton @click="clearDesigner">清空</ElButton>
          <ElButton type="primary" @click="getFormJson">获取 JSON</ElButton>
        </div>
      </div>

      <!-- 设计器区域 -->
      <div class="designer-wrapper">
        <component
          :is="vFormDesignerComponent"
          ref="vfDesignerRef"
          :designer-config="designerConfig"
        >
          <template #customToolButtons>
            <ElButton type="primary" link @click="getFormJson">
              查看 JSON
            </ElButton>
          </template>
        </component>
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
.form-builder-page {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--el-fill-color-lighter);
}

.builder-toolbar {
  z-index: 10;
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: space-between;
  padding: 8px 16px;
  background: var(--el-bg-color-overlay);
  border-bottom: 1px solid var(--el-border-color-lighter);
}

.toolbar-left {
  display: flex;
  gap: 8px;
  align-items: center;
}

.toolbar-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--el-text-color-primary);
}

.toolbar-right {
  display: flex;
  gap: 8px;
}

.designer-wrapper {
  position: relative;
  flex: 1;
  overflow: hidden;
}

.json-content {
  max-height: 500px;
  padding: 16px;
  overflow: auto;
  font-size: 12px;
  line-height: 1.6;
  background: var(--el-fill-color-light);
  border-radius: 4px;
}
</style>
