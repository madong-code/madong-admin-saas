<template>
  <div class="test-plugin-counter">
    <el-card shadow="hover" class="counter-card">
      <template #header>
        <div class="card-header">
          <span>{{ title }}</span>
          <el-tag :type="animated ? 'success' : 'info'" size="small">
            {{ animated ? '动画开启' : '动画关闭' }}
          </el-tag>
        </div>
      </template>

      <div class="counter-body">
        <div class="count-display" :class="{ animated }">
          <span class="count-value">{{ store.count }}</span>
          <span class="count-label">{{ $t('test.components.currentCount') }}</span>
        </div>

        <div class="counter-actions">
          <el-button-group>
            <el-button type="danger" :icon="Minus" @click="store.decrement()" round />
            <el-button type="primary" :icon="Refresh" @click="store.reset()" round />
            <el-button type="success" :icon="Plus" @click="store.increment()" round />
          </el-button-group>
        </div>

        <div class="async-action">
          <el-button
            :loading="store.loading"
            type="warning"
            plain
            @click="store.simulateAsync()"
          >
            {{ store.loading ? $t('test.components.loading') : $t('test.components.asyncIncrement') }}
          </el-button>
        </div>

        <div class="store-info">
          <el-descriptions :column="2" size="small" border>
            <el-descriptions-item :label="$t('test.components.doubleCount')">
              <el-tag type="warning">{{ store.doubleCount }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('test.components.messageCount')">
              <el-tag>{{ store.messages.length }}</el-tag>
            </el-descriptions-item>
          </el-descriptions>
        </div>
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { Minus, Plus, Refresh } from '@element-plus/icons-vue'
import { useTestPluginStore } from '../stores'

interface Props {
  title?: string
  animated?: boolean
}

withDefaults(defineProps<Props>(), {
  title: '测试插件 - 计数器',
  animated: false,
})

const store = useTestPluginStore()
</script>

<style scoped lang="scss">
.test-plugin-counter {
  .counter-card {
    max-width: 420px;
  }

  .card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .counter-body {
    display: flex;
    flex-direction: column;
    gap: 20px;
  }

  .count-display {
    text-align: center;
    padding: 24px 0;

    .count-value {
      font-size: 48px;
      font-weight: bold;
      color: var(--el-color-primary);
      display: block;
      line-height: 1.2;
      transition: all 0.3s ease;
    }

    .count-label {
      font-size: 14px;
      color: var(--el-text-color-secondary);
      margin-top: 4px;
      display: block;
    }

    &.animated .count-value {
      animation: pulse 0.3s ease;
    }
  }

  .counter-actions {
    display: flex;
    justify-content: center;
  }

  .async-action {
    display: flex;
    justify-content: center;
  }
}

@keyframes pulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.15); }
  100% { transform: scale(1); }
}
</style>
