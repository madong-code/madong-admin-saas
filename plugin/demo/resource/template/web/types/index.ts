/**
 * 测试插件 - 类型定义
 */
export interface TestPluginState {
  /** 当前计数 */
  count: number
  /** 消息列表 */
  messages: TestMessage[]
  /** 是否加载中 */
  loading: boolean
}

export interface TestMessage {
  id: string
  text: string
  type: 'info' | 'success' | 'warning' | 'error'
  timestamp: number
}

export interface TestPluginConfig {
  /** 组件标题 */
  title?: string
  /** 初始计数 */
  initialCount?: number
  /** 是否启用动画 */
  animated?: boolean
}
