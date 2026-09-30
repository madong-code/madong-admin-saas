/**
 * 测试插件 - 入口文件
 *
 * 纯前端示例插件，无需后端 API 支持。
 * 演示了 Pinia 状态管理、Element Plus 组件、国际化等能力。
 *
 * 使用方式：
 *   import { useTestPluginStore } from '~/plugin/test/stores'
 *   import { CounterCard, MessageList } from '~/plugin/test/components'
 *
 *   const store = useTestPluginStore()
 *   store.increment()
 */

// 导出类型
export type * from './types'

// 导出 Store
export { useTestPluginStore } from './stores'

// 导出组件
export { default as TestCounterCard } from './components/CounterCard.vue'
export { default as TestMessageList } from './components/MessageList.vue'

/**
 * 插件配置默认值
 */
export const PLUGIN_NAME = 'test'
export const PLUGIN_VERSION = '1.0.0'
