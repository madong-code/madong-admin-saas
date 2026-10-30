/** visual-flow 流程图数据结构（画布模式，LogicFlow 兼容） */

export interface FlowNode {
  id: string;
  type: string;
  x: number;
  y: number;
  properties?: Record<string, any>;
  text?: {
    value: string;
    x?: number;
    y?: number;
  };
}

export interface FlowEdge {
  id: string;
  type: string;
  sourceNodeId: string;
  targetNodeId: string;
  properties?: Record<string, any>;
  text?: {
    value: string;
  };
}

/**
 * 与 visual-flow 的 FDConfigData 结构对齐：
 * nodes/edges 均为可选数组，以便 v-model:value 双向绑定类型兼容。
 */
export interface FlowGraphData {
  name?: string;
  display_name?: string;
  type?: string;
  instance_url?: string;
  mode?: 'canvas' | 'dingtalk';
  nodes?: FlowNode[];
  edges?: FlowEdge[];
  [key: string]: any;
}
