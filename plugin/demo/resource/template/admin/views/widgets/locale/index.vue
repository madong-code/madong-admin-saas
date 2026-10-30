<script setup lang="ts">
import { computed } from 'vue';

import { useI18n } from '#/core/locales';
import { $t } from '#/locales';

const { locale, mergeLocaleMessage } = useI18n();

const currentLocale = computed(() => locale.value);

function testTranslation() {
  console.warn(
    'demo.widgets.locale.test.greeting:',
    $t('demo.widgets.locale.test.greeting'),
  );
  console.warn(
    'demo.widgets.locale.test.welcome:',
    $t('demo.widgets.locale.test.welcome'),
  );
  console.warn(
    'demo.widgets.locale.test.menu_home:',
    $t('demo.widgets.locale.test.menu_home'),
  );
  console.warn(
    'demo.widgets.locale.test.menu_settings:',
    $t('demo.widgets.locale.test.menu_settings'),
  );
}

async function switchLocale(lang: string) {
  locale.value = lang;
  await reloadLocaleForLang(lang);
}

async function reloadLocaleForLang(lang: string) {
  try {
    const { loadAllLocaleMessages } = await import('#/locales/loader');
    const result = loadAllLocaleMessages(lang as any);
    mergeLocaleMessage(lang, result);
    console.warn(`语言包已加载并合并到 ${lang}`);
  } catch (error) {
    console.error('加载语言包失败:', error);
  }
}

async function reloadLocale() {
  try {
    const { loadAllLocaleMessages } = await import('#/locales/loader');
    const result = loadAllLocaleMessages(currentLocale.value as any);
    mergeLocaleMessage(currentLocale.value, result);
    setTimeout(() => {
      window.location.reload();
    }, 1000);
  } catch (error) {
    console.error('加载语言包失败:', error);
  }
}
</script>

<template>
  <div class="locale-backend-test">
    <div class="test-container">
      <div class="header">
        <h1>🌐 后端语言包测试页面</h1>
        <p>{{ $t('demo.widgets.locale.test.description') }}</p>
      </div>

      <div class="test-section">
        <button class="reload-btn" @click="reloadLocale">重新加载语言包</button>
        <button class="reload-btn" @click="testTranslation">测试翻译</button>
      </div>

      <div class="test-section">
        <h2>{{ $t('demo.widgets.locale.test.plugin_locale_test') }}</h2>
        <div class="test-grid">
          <div class="test-card">
            <div class="card-label">demo.widgets.locale.test.greeting</div>
            <div class="card-value">
              {{ $t('demo.widgets.locale.test.greeting') }}
            </div>
          </div>
          <div class="test-card">
            <div class="card-label">demo.widgets.locale.test.welcome</div>
            <div class="card-value">
              {{ $t('demo.widgets.locale.test.welcome') }}
            </div>
          </div>
          <div class="test-card">
            <div class="card-label">demo.widgets.locale.test.menu_home</div>
            <div class="card-value">
              {{ $t('demo.widgets.locale.test.menu_home') }}
            </div>
          </div>
          <div class="test-card">
            <div class="card-label">demo.widgets.locale.test.menu_settings</div>
            <div class="card-value">
              {{ $t('demo.widgets.locale.test.menu_settings') }}
            </div>
          </div>
        </div>
      </div>

      <div class="test-section">
        <h2>{{ $t('demo.widgets.locale.test.framework_locale_test') }}</h2>
        <div class="test-grid">
          <div class="test-card">
            <div class="card-label">common.confirm</div>
            <div class="card-value">{{ $t('common.confirm') }}</div>
          </div>
          <div class="test-card">
            <div class="card-label">common.cancel</div>
            <div class="card-value">{{ $t('common.cancel') }}</div>
          </div>
        </div>
      </div>

      <div class="test-section">
        <h2>{{ $t('demo.widgets.locale.test.current_locale') }}</h2>
        <div class="locale-info">
          <div class="info-row">
            <span class="info-label"
              >{{ $t('demo.widgets.locale.test.current_lang') }}:</span
            >
            <span class="info-value">{{ currentLocale }}</span>
          </div>
        </div>
      </div>

      <div class="test-section">
        <h2>{{ $t('demo.widgets.locale.test.switch_language') }}</h2>
        <div class="lang-buttons">
          <button
            class="lang-btn"
            :class="{
              active: currentLocale === 'zh-CN' || currentLocale === 'zh',
            }"
            @click="switchLocale('zh-CN')"
          >
            🇨🇳 {{ $t('demo.widgets.locale.test.chinese') }}
          </button>
          <button
            class="lang-btn"
            :class="{
              active: currentLocale === 'en-US' || currentLocale === 'en',
            }"
            @click="switchLocale('en-US')"
          >
            🇬🇧 {{ $t('demo.widgets.locale.test.english') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.locale-backend-test {
  min-height: 100vh;
  padding: 24px;
  background: var(--el-fill-color-lighter);
}

.test-container {
  max-width: 1200px;
  margin: 0 auto;
}

.header {
  padding: 20px;
  margin-bottom: 24px;
  color: white;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px;

  h1 {
    margin: 0 0 8px;
    font-size: 24px;
  }

  p {
    margin: 0;
    opacity: 0.9;
  }
}

.reload-btn {
  padding: 12px 24px;
  font-size: 14px;
  color: white;
  cursor: pointer;
  background: #667eea;
  border: none;
  border-radius: 8px;
  transition: all 0.2s;

  &:hover {
    background: #5a67d8;
    transform: translateY(-2px);
  }
}

.test-section {
  padding: 20px;
  margin-bottom: 24px;
  background: var(--el-bg-color-overlay);
  border-radius: 12px;
  box-shadow: 0 2px 8px rgb(0 0 0 / 8%);

  h2 {
    margin: 0 0 16px;
    font-size: 18px;
    color: var(--el-text-color-primary);
  }
}

.test-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 16px;
}

.test-card {
  padding: 16px;
  background: var(--el-fill-color-light);
  border-left: 4px solid #667eea;
  border-radius: 8px;

  .card-label {
    margin-bottom: 8px;
    font-size: 14px;
    color: var(--el-text-color-regular);
  }

  .card-value {
    font-size: 16px;
    font-weight: 600;
    color: var(--el-text-color-primary);
  }
}

.locale-info {
  padding: 16px;
  background: var(--el-color-primary-light-9);
  border-radius: 8px;
}

.info-row {
  display: flex;
  align-items: center;
  justify-content: space-between;

  .info-label {
    font-weight: 500;
    color: var(--el-text-color-regular);
  }

  .info-value {
    padding: 4px 12px;
    font-weight: 600;
    color: #3b82f6;
    background: var(--el-bg-color-overlay);
    border-radius: 4px;
  }
}

.lang-buttons {
  display: flex;
  gap: 12px;
}

.lang-btn {
  padding: 12px 28px;
  font-size: 15px;
  cursor: pointer;
  background: var(--el-bg-color-overlay);
  border: 2px solid var(--el-border-color);
  border-radius: 8px;
  transition: all 0.2s ease;

  &:hover {
    color: #667eea;
    border-color: #667eea;
    box-shadow: 0 4px 12px rgb(102 126 234 / 20%);
    transform: translateY(-2px);
  }

  &.active {
    color: white;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-color: #667eea;
    box-shadow: 0 4px 12px rgb(102 126 234 / 30%);
  }
}
</style>
