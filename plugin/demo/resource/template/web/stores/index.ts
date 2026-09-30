import { defineStore } from 'pinia'
import type { TestPluginState, TestMessage } from '../types'

/**
 * 测试插件 - Pinia Store
 *
 * 使用方式：
 *   const store = useTestPluginStore()
 *   store.increment()
 */
export const useTestPluginStore = defineStore('test-plugin', {
  state: (): TestPluginState => ({
    count: 0,
    messages: [],
    loading: false,
  }),

  getters: {
    doubleCount: (state) => state.count * 2,
    hasMessages: (state) => state.messages.length > 0,
    latestMessage: (state) => state.messages[state.messages.length - 1] ?? null,
  },

  actions: {
    increment() {
      this.count++
      this.addMessage({
        text: `计数 +1，当前: ${this.count}`,
        type: 'success',
      })
    },

    decrement() {
      this.count--
      this.addMessage({
        text: `计数 -1，当前: ${this.count}`,
        type: 'info',
      })
    },

    reset() {
      this.count = 0
      this.messages = []
    },

    addMessage(msg: { text: string; type: TestMessage['type'] }) {
      this.messages.push({
        id: `${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
        text: msg.text,
        type: msg.type,
        timestamp: Date.now(),
      })
    },

    removeMessage(id: string) {
      this.messages = this.messages.filter((m) => m.id !== id)
    },

    async simulateAsync() {
      this.loading = true
      await new Promise((resolve) => setTimeout(resolve, 1500))
      this.count += 10
      this.addMessage({ text: '异步操作完成，计数 +10', type: 'success' })
      this.loading = false
    },
  },
})
