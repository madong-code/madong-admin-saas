<template>
  <div class="test-plugin-messages">
    <el-card shadow="hover">
      <template #header>
        <div class="card-header">
          <span>{{ $t('test.components.activityLog') }}</span>
          <el-button
            v-if="store.hasMessages"
            type="danger"
            size="small"
            text
            @click="store.reset()"
          >
            {{ $t('test.components.clearAll') }}
          </el-button>
        </div>
      </template>

      <div v-if="!store.hasMessages" class="empty-state">
        <el-empty :description="$t('test.components.noActivity')" :image-size="80" />
      </div>

      <div v-else class="message-list">
        <div
          v-for="msg in store.messages"
          :key="msg.id"
          class="message-item"
        >
          <el-alert
            :title="msg.text"
            :type="msg.type"
            :closable="true"
            show-icon
            @close="store.removeMessage(msg.id)"
          />
        </div>
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { useTestPluginStore } from '../stores'

const store = useTestPluginStore()
</script>

<style scoped lang="scss">
.test-plugin-messages {
  .card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .empty-state {
    padding: 20px 0;
  }

  .message-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 360px;
    overflow-y: auto;
  }
}
</style>
