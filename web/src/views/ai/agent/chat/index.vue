<script setup lang="ts">
import type { ChatMessage, ChatSession } from "./types/scheam.js";

import { computed, nextTick, onMounted, ref } from "vue";

import {
  DeleteOutlined,
  EditOutlined,
  MessageOutlined,
  PaperClipOutlined,
  PlusOutlined,
  QuestionCircleOutlined,
  SearchOutlined,
  SendOutlined,
  SettingOutlined,
  SmileOutlined,
} from "@ant-design/icons-vue";

import {
  Layout,
  LayoutSider,
  InputSearch,
  List,
  ListItem,
  Input,
  Tooltip,
  Button,
  Space,
  Tag,
  Textarea,
  Slider,
  Popconfirm,
  Avatar,
  Modal,
} from "ant-design-vue";

import { mockApi } from "./api";
import { MessageContent } from "./message-content";

const loading = ref(false);
const inputMessage = ref("");
const currentChatId = ref("");
const messageContainer = ref<HTMLElement | null>(null);
const chatList = ref<ChatSession[]>([]);
const currentMessages = ref<ChatMessage[]>([]);
const searchText = ref("");
const showSettings = ref(false);
const contextLength = ref(50);

const filteredChatList = computed(() => {
  if (!searchText.value) return chatList.value;
  const searchKeyword = searchText.value.toLowerCase().trim();

  return chatList.value.filter((chat) => {
    // 搜索标题
    const titleMatch = chat.title.toLowerCase().includes(searchKeyword);

    // 搜索消息内容
    const contentMatch = chat.messages?.some((message) =>
      message.content.toLowerCase().includes(searchKeyword)
    );

    // 搜索最后一条消息
    const lastMessageMatch = chat.lastMessage?.toLowerCase().includes(searchKeyword);

    return titleMatch || contentMatch || lastMessageMatch;
  });
});

// 修改欢迎消息的构建方式
const buildWelcomeMessage = () => {
  const examples = [
    "用js写一个for循环",
    "说一下三体小说的梗概",
    "写一首关于夏天的七言绝句",
    "请你扮演一个柏拉图式的老师，教我分析和解决问题，我的第一个问题是：14*34-18=?",
  ];

  return {
    id: "1",
    type: "ai" as const,
    content: `你好！我是 AI助手，我可以回答您的任何问题。我还会写代码，查看代码中的问题，帮你分析代码等。\n如果您不知道问什么，以下是一些示例，你可以直接点击尝试：\n${examples
      .map((example) => `• ${example}`)
      .join("\n")}`,
    time: new Date().toLocaleTimeString(),
    examples,
  };
};
// 添加滚动到底部的方法
const scrollToBottom = () => {
  if (messageContainer.value) {
    nextTick(() => {
      const container = messageContainer.value;
      container?.scrollTo({
        top: container.scrollHeight,
        behavior: "smooth",
      });
    });
  }
};

// 创建新会话
const createNewChat = async () => {
  try {
    currentChatId.value = "";
    currentMessages.value = [buildWelcomeMessage()];
    scrollToBottom();
  } catch (error) {
    console.error("创建新会话失败:", error);
  }
};

// 初始化数据
const initData = async () => {
  try {
    // 获取会话列表
    chatList.value = await mockApi.getSessionList();

    // 默认创建新对话
    await createNewChat();
  } catch (error) {
    console.error("初始化数据失败:", error);
  }
};

// 切换会话
const switchChat = async (chatId: string) => {
  try {
    currentChatId.value = chatId;
    currentMessages.value = await mockApi.getSessionMessages(chatId);
    scrollToBottom();
  } catch (error) {
    console.error("切换会话失败:", error);
  }
};

// 发送消息
const sendMessage = async () => {
  if (!inputMessage.value.trim()) return;

  try {
    loading.value = true;

    // 如果是新对话且还没有发送过消息，先创建会话
    if (!currentChatId.value || currentMessages.value.length <= 1) {
      // 使用用户的第一条消息作为会话标题（截取一部分）
      const title =
        inputMessage.value.slice(0, 20) + (inputMessage.value.length > 20 ? "..." : "");
      const newSession = await mockApi.createSession(title);
      chatList.value.unshift(newSession);
      currentChatId.value = newSession.id;
    }

    // 发送用户消息
    const userMessage = await mockApi.sendMessage(
      currentChatId.value,
      inputMessage.value
    );
    currentMessages.value.push(userMessage);
    inputMessage.value = "";
    scrollToBottom();

    // 获取AI回复
    const aiMessage = await mockApi.getAiResponse(
      currentChatId.value,
      userMessage.content
    );
    currentMessages.value.push(aiMessage);

    // 更新会话列表中的最后一条消息
    const currentChat = chatList.value.find((chat) => chat.id === currentChatId.value);
    if (currentChat) {
      currentChat.lastMessage = aiMessage.content;
      currentChat.timestamp = Date.now();
    }
  } catch (error) {
    console.error("发送消息失败:", error);
  } finally {
    loading.value = false;
    scrollToBottom();
  }
};

// 删除会话
const deleteChat = async (index: number) => {
  try {
    const chatItem = chatList.value[index];
    if (!chatItem) return; // 添加空值检查
    const sessionId = chatItem.id;
    await mockApi.deleteSession(sessionId);
    chatList.value.splice(index, 1);
    await (chatList.value.length > 0 ? switchChat(chatItem.id) : createNewChat());
  } catch (error) {
    console.error("删除会话失败:", error);
  }
};

// 修改编辑标题方法
const editTitle = (index: number) => {
  const chatItem = chatList.value[index];
  if (!chatItem) return; // 添加空值检查
  if (!chatItem.isEditing) {
    // 先将其他正在编辑的项关闭
    chatList.value.forEach((item) => {
      item.isEditing = false;
    });
    // 开启当前项的编辑状态
    chatItem.isEditing = true;
    // 下一个tick后聚焦输入框
    nextTick(() => {
      const input = document.querySelector(".title-input input") as HTMLInputElement;
      if (input) {
        input.focus();
        input.select();
      }
    });
  }
};

// 修改保存标题方法
const saveTitle = async (index: number) => {
  try {
    const chatItem = chatList.value[index];
    if (!chatItem) return; // 添加空值检查
    if (!chatItem.title.trim()) {
      chatItem.title = "新的对话";
    }
    await mockApi.updateSessionTitle(chatItem.id, chatItem.title);
    chatItem.isEditing = false;
  } catch (error) {
    console.error("保存标题失败:", error);
  }
};

// 组件挂载时初始化数据
onMounted(() => {
  initData();
});

// 处理设置保存
const handleSettingsSave = () => {
  // 这里可以添加设置保存的逻辑
  showSettings.value = false;
};

</script>

<template>
  <Layout class="chat-layout">
    <!-- 左侧对话列表 -->
    <LayoutSider width="260" class="chat-sider">
      <!-- 返回首页 -->
      <div class="sider-header">
        <InputSearch
          v-model:value="searchText"
          placeholder="搜索对话"
          class="search-input"
          allow-clear
        >
          <template #prefix>
            <SearchOutlined />
          </template>
        </InputSearch>
      </div>

      <!-- 新的对话按钮 -->
      <div class="new-chat-container">
        <Button class="new-chat-button" block @click="createNewChat">
          <PlusOutlined />
          <span>新的对话</span>
        </Button>
      </div>

      <!-- 对话列表容器 -->
      <List :data-source="filteredChatList" class="chat-list" pagination>
        <template #renderItem="{ item, index }">
          <ListItem
            class="chat-item"
            :class="[{ active: currentChatId === item.id }]"
            @click="switchChat(item.id)"
          >
            <div class="chat-item-content">
              <MessageOutlined />
              <!-- 编辑状态 -->
              <template v-if="item.isEditing">
                <Input
                  v-model:value="item.title"
                  size="small"
                  class="title-input"
                  @press-enter="saveTitle(index)"
                  @blur="saveTitle(index)"
                  @click.stop
                />
              </template>
              <!-- 非编辑状态 -->
              <template v-else>
                <Tooltip v-if="item.title.length > 15" :title="item.title">
                  <span class="chat-title">{{ item.title }}</span>
                </Tooltip>
                <span v-else class="chat-title">{{ item.title }}</span>
              </template>
            </div>
            <div
              class="chat-item-actions"
              v-show="currentChatId === item.id || item.isEditing"
            >
              <Button type="text" size="small" @click.stop="editTitle(index)">
                <EditOutlined />
              </Button>
              <Popconfirm
                title="确定要删除这个对话吗？"
                @confirm="deleteChat(index)"
                @click.stop
              >
                <Button type="text" size="small">
                  <DeleteOutlined />
                </Button>
              </Popconfirm>
            </div>
          </ListItem>
        </template>
      </List>
    </LayoutSider>

    <!-- 右侧聊天区域 -->
    <LayoutContent class="chat-content">
      <!-- 聊天头部 -->
      <div class="chat-header">
        <Space>
          <span class="model-tag">当前模型: v1.0.1 </span>
          <span class="price-tag">
            <Tag color="blue">更新</Tag>
          </span>
        </Space>
      </div>

      <!-- 聊天消息区域 -->
      <div ref="messageContainer" class="message-container custom-scrollbar">
        <List :data-source="currentMessages">
          <template #renderItem="{ item }">
            <ListItem>
              <div
                class="message"
                :class="[item.type === 'ai' ? 'ai-message' : 'user-message']"
              >
                <Space>
                  <Avatar>{{ item.type === "ai" ? "AI" : "我" }}</Avatar>
                  <div class="message-content">
                    <div class="message-time">{{ item.time }}</div>
                    <MessageContent
                      :message="item"
                      @select-example="
                        (question) => {
                          inputMessage = question;
                          sendMessage();
                        }
                      "
                    />
                  </div>
                </Space>
              </div>
            </ListItem>
          </template>
        </List>
      </div>

      <!-- 输入区域 -->
      <div class="input-container">
        <div class="input-wrapper">
          <Textarea
            v-model:value="inputMessage"
            :rows="3"
            placeholder="请输入一个问题，Ctrl+Enter发行，Enter发送消息"
            :disabled="loading"
            @press-enter.prevent="sendMessage"
          />
          <div class="input-toolbar">
            <Space>
              <Button type="text">
                <template #icon><PlusOutlined /></template>
              </Button>
              <Button type="text">
                <template #icon><PaperClipOutlined /></template>
              </Button>
              <Button type="text">
                <template #icon><SmileOutlined /></template>
              </Button>
              <Popconfirm
                :show-cancel="false"
                @confirm="handleSettingsSave"
                :overlay-style="{ width: '320px' }"
              >
                <template #title>
                  <div class="setting-label">
                    <span>上下文长度</span>
                    <Tooltip title="设置AI回复时参考的历史消息长度">
                      <QuestionCircleOutlined />
                    </Tooltip>
                  </div>
                  <div class="setting-item">
                    <div class="context-slider">
                      <Slider
                        v-model:value="contextLength"
                        :marks="{
                          0: '',
                          25: '问短回长',
                          50: '问回相当',
                          75: '问长回短',
                          100: '',
                        }"
                        :step="1"
                      />
                      <div class="slider-info">
                        <span>上下文+问题 = {{ 6320 }}</span>
                        <span>回复 = {{ 5680 }}</span>
                      </div>
                    </div>
                  </div>
                </template>
                <Button type="text">
                  <template #icon><SettingOutlined /></template>
                </Button>
              </Popconfirm>
            </Space>
            <Button
              type="primary"
              :loading="loading"
              @click="sendMessage"
              class="send-button"
            >
              <template #icon><SendOutlined /></template>
              发送
            </Button>
          </div>
        </div>
      </div>
    </LayoutContent>
  </Layout>

  <!-- 添加设置弹窗 -->
  <Modal
    v-model:open="showSettings"
    title="对话设置"
    @ok="handleSettingsSave"
    @cancel="showSettings = false"
    :mask-closable="false"
    width="500px"
  >
    <div class="settings-content">
      <div class="setting-label">
        <span>上下文设置</span>
        <Tooltip title="设置AI回复时参考的历史消息长度">
          <QuestionCircleOutlined />
        </Tooltip>
      </div>
      <div class="context-slider">
        <Slider
          v-model:value="contextLength"
          :marks="{
            0: '问短回长',
            50: '问回相当',
            100: '问长回短',
          }"
          :step="1"
        />
        <div class="slider-info">
          <span>上下文+问题 = {{ 6320 }}</span>
          <span>回复 = {{ 5680 }}</span>
        </div>
      </div>
    </div>
  </Modal>
</template>

<style scoped lang="less">
:deep(.ant-list-item) {
  border-block-end: 0 !important;
}
.chat-layout {
  height: calc(100vh - 250px);
  background: #fff;
}

.chat-sider {
  border-right: 1px solid #f0f0f0;
  background: #fff;
  display: flex;
  flex-direction: column;
  height: calc(100vh - 250px);
  overflow: hidden;
}

.sider-header {
  padding: 16px;
  border-bottom: 1px solid #f0f0f0;
  flex-shrink: 0;
}

.back-button {
  color: #1890ff;
  padding: 0;
  height: auto;

  :deep(.anticon) {
    margin-right: 8px;
  }
}

.new-chat-container {
  padding: 8px 16px;
  margin-bottom: 4px;
  flex-shrink: 0;
}

.new-chat-button {
  height: 44px;
  border: 1px dashed #d9d9d9;
  border-radius: 6px;
  color: #595959;
  background: transparent;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;

  &:hover {
    color: #40a9ff;
    border-color: #40a9ff;
  }

  :deep(.anticon) {
    font-size: 16px;
  }
}

.chat-list {
  padding: 8px 16px;
  height: 100%;
}

.chat-item {
  height: 44px;
  padding: 0 12px !important;
  margin: 4px 0;
  cursor: pointer;
  transition: all 0.3s;
  border: 1px solid #d9d9d9 !important;
  border-radius: 6px;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.chat-item-content {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  min-width: 0;
  padding-right: 8px;
}

.title-input {
  flex: 1;
  width: 100%;
  min-width: 120px;

  :deep(.ant-input) {
    height: 28px;
    padding: 0 8px;
  }
}

.chat-title {
  flex: 1;
  font-size: 14px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
  max-width: 180px;
}

.chat-item-actions {
  display: none;
  gap: 4px;
  flex-shrink: 0;
}

.chat-item:hover .chat-item-actions,
.chat-item.active .chat-item-actions {
  display: flex;
}

/* 确保输入框样式正确 */
:deep(.ant-input) {
  border-radius: 4px;

  &:focus {
    box-shadow: 0 0 0 2px rgba(24, 144, 255, 0.2);
  }
}

.chat-content {
  display: flex;
  flex-direction: column;
}

.chat-header {
  padding: 16px;
  border-bottom: 1px solid #f0f0f0;
}

.message-container {
  flex: 1;
  padding: 16px;
  overflow-y: auto;
  scroll-behavior: smooth;
}

.message {
  max-width: 80%;
  margin: 8px 0;
  opacity: 0;
  transform: translateY(20px);
  animation: messageIn 0.3s ease forwards;
}

@keyframes messageIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.ai-message {
  margin-right: auto;
  animation-delay: 0.1s;
}

.user-message {
  margin-left: auto;
  animation-delay: 0s;
}

.message-content {
  background: #f5f5f5;
  padding: 12px;
  border-radius: 8px;
  transition: all 0.3s ease;

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  }
}

.message-time {
  font-size: 12px;
  color: #999;
  margin-bottom: 4px;
}

.input-container {
  padding: 16px;
  border-top: 1px solid #f0f0f0;
}

.input-wrapper {
  position: relative;
  border: 1px solid #d9d9d9;
  border-radius: 8px;
  background: #fff;

  :deep(.ant-input) {
    border: none;
    resize: none;
    padding-bottom: 40px;

    &:focus {
      box-shadow: none;
    }
  }
}

.input-toolbar {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 8px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #fff;
  border-top: 1px solid #f0f0f0;
  border-bottom-left-radius: 8px;
  border-bottom-right-radius: 8px;
}

.send-button {
  margin-left: auto;
}

.active {
  background: #e6f7ff;
}

/* 自定义滚动条样式 */
.custom-scrollbar {
  &::-webkit-scrollbar {
    width: 6px;
    height: 6px;
    display: block;
  }

  &::-webkit-scrollbar-track {
    background: #f5f5f5;
    border-radius: 3px;
  }

  &::-webkit-scrollbar-thumb {
    background: #d9d9d9;
    border-radius: 3px;

    &:hover {
      background: #bfbfbf;
    }
  }

  /* Firefox */
  scrollbar-width: thin;
  scrollbar-color: #d9d9d9 #f5f5f5;
}

.search-input {
  :deep(.ant-input-affix-wrapper) {
    border-radius: 6px;

    &:hover,
    &:focus {
      border-color: #40a9ff;
    }
  }

  :deep(.ant-input-prefix) {
    color: #999;
    margin-right: 8px;
  }

  :deep(.ant-input) {
    &::placeholder {
      color: #999;
    }
  }
}

:deep(.ant-tooltip) {
  .ant-tooltip-inner {
    max-width: 300px;
    word-break: break-all;
  }
}

.message-content {
  :deep(.example-link) {
    color: #1890ff;
    cursor: pointer;
    transition: color 0.3s;

    &:hover {
      color: #40a9ff;
      text-decoration: underline;
    }
  }
}

/* 修改示例链接样式 */
.message-text :deep(.example-item) {
  color: #1890ff;
  margin: 8px 0;
  padding-left: 8px;
  position: relative;
}

.message-text :deep(.example-text) {
  color: #1890ff;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  display: block;
  padding: 8px 12px;
  background: #fff;
  border-radius: 4px;
  border: 1px solid #e8e8e8;
  text-decoration: none;

  &:hover {
    transform: translateX(8px);
    box-shadow: 0 2px 8px rgba(24, 144, 255, 0.1);
  }
}

.message-text :deep(.example-text:hover) {
  color: #40a9ff;
  background: #f0f7ff;
  border-color: #40a9ff;
  transform: translateX(4px);
  text-decoration: none;
}

/* 确保链接样式不被覆盖 */
.message-text :deep(a.example-text) {
  color: #1890ff !important;
  text-decoration: none !important;
}

.message-text :deep(a.example-text:hover) {
  color: #40a9ff !important;
}

.settings-content {
  padding: 16px;
}

.setting-item {
  padding: 8px 0;
}

.setting-label {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.context-slider {
  :deep(.ant-slider) {
    margin: 8px 28px 16px 0;
  }

  :deep(.ant-slider-mark-text) {
    color: #666;
    font-size: 12px;
  }

  :deep(.ant-slider-track) {
    background-color: #52c41a;
  }

  :deep(.ant-slider-handle) {
    border-color: #52c41a;

    &:hover {
      border-color: #73d13d;
    }
  }
}

.slider-info {
  display: flex;
  justify-content: space-between;
  color: #666;
  font-size: 12px;
}

:deep(.ant-popover-message-title) {
  padding-left: 0;
}
</style>


<style scoped lang="less">
// 黑暗模式变量
@dark-bg: #141414;
@dark-border: #303030;
@dark-text: rgba(255, 255, 255, 0.85);
@dark-hover-bg: #1f1f1f;
@dark-active-bg: #1a1a1a;
@dark-input-bg: #1f1f1f;
@dark-card-bg: #1f1f1f;
@dark-tooltip-bg: #1f1f1f;

// 基础样式
.chat-layout {
  height: calc(100vh - 250px);
  
  // 黑暗模式适配
  .dark & {
    background: @dark-bg;
    color: @dark-text;
  }
}

.chat-sider {
  border-right: 1px solid #f0f0f0;
  background: #fff;
  display: flex;
  flex-direction: column;
  height: calc(100vh - 250px);
  overflow: hidden;
  
  // 黑暗模式适配
  .dark & {
    background: @dark-bg;
    border-right-color: @dark-border;
  }
}

.sider-header {
  padding: 16px;
  border-bottom: 1px solid #f0f0f0;
  flex-shrink: 0;
  
  // 黑暗模式适配
  .dark & {
    border-bottom-color: @dark-border;
  }
}

// 搜索输入框
.search-input {
  :deep(.ant-input-affix-wrapper) {
    background: #fff;
    border-radius: 6px;
    
    .dark & {
      background: @dark-input-bg;
      border-color: @dark-border;
      color: @dark-text;
    }
  }
  
  :deep(.ant-input-prefix) {
    color: #999;
    
    .dark & {
      color: rgba(255, 255, 255, 0.45);
    }
  }
  
  :deep(.ant-input) {
    .dark & {
      background: transparent;
      color: @dark-text;
      
      &::placeholder {
        color: rgba(255, 255, 255, 0.3);
      }
    }
  }
}

.new-chat-button {
  border: 1px dashed #d9d9d9;
  color: #595959;
  background: transparent;
  
  .dark & {
    border-color: @dark-border;
    color: rgba(255, 255, 255, 0.65);
    background: @dark-card-bg;
    
    &:hover {
      color: #40a9ff;
      border-color: #40a9ff;
      background: @dark-hover-bg;
    }
  }
}

.chat-item {
  border: 1px solid #d9d9d9 !important;
  background: #fff;
  
  .dark & {
    background: @dark-card-bg;
    border-color: @dark-border !important;
    
    &:hover {
      background: @dark-hover-bg;
    }
    
    &.active {
      background: rgba(24, 144, 255, 0.2);
    }
  }
}

.chat-title {
  color: rgba(0, 0, 0, 0.85);
  
  .dark & {
    color: @dark-text;
  }
}

.chat-content {
  .dark & {
    background: @dark-bg;
  }
}

.chat-header {
  border-bottom: 1px solid #f0f0f0;
  
  .dark & {
    border-bottom-color: @dark-border;
  }
}

.message-content {
  background: #f5f5f5;
  
  .dark & {
    background: @dark-card-bg;
    color: @dark-text;
  }
}

.message-time {
  color: #999;
  
  .dark & {
    color: rgba(255, 255, 255, 0.45);
  }
}

.input-container {
  border-top: 1px solid #f0f0f0;
  
  .dark & {
    border-top-color: @dark-border;
  }
}

.input-wrapper {
  border: 1px solid #d9d9d9;
  background: #fff;
  
  .dark & {
    background: @dark-input-bg;
    border-color: @dark-border;
  }
}

.input-toolbar {
  background: #fff;
  border-top: 1px solid #f0f0f0;
  
  .dark & {
    background: @dark-input-bg;
    border-top-color: @dark-border;
  }
}

// 自定义滚动条
.custom-scrollbar {
  &::-webkit-scrollbar-track {
    background: #f5f5f5;
    
    .dark & {
      background: @dark-bg;
    }
  }
  
  &::-webkit-scrollbar-thumb {
    background: #d9d9d9;
    
    .dark & {
      background: @dark-border;
    }
  }
}

// 设置弹窗
.settings-content {
  .dark & {
    background: @dark-bg;
    color: @dark-text;
  }
}

.context-slider {
  :deep(.ant-slider) {
    .dark & {
      color: @dark-text;
    }
  }
  
  :deep(.ant-slider-mark-text) {
    .dark & {
      color: rgba(255, 255, 255, 0.65);
    }
  }
}

.slider-info {
  color: #666;
  
  .dark & {
    color: rgba(255, 255, 255, 0.45);
  }
}

// 示例链接
.message-text :deep(.example-text) {
  background: #fff;
  border: 1px solid #e8e8e8;
  
  .dark & {
    background: @dark-card-bg;
    border-color: @dark-border;
    color: #40a9ff !important;
    
    &:hover {
      background: rgba(24, 144, 255, 0.1);
    }
  }
}

// 工具提示
:deep(.ant-tooltip) {
  .ant-tooltip-inner {
    .dark & {
      background: @dark-tooltip-bg;
      color: @dark-text;
    }
  }
  
  .ant-tooltip-arrow-content {
    .dark & {
      background: @dark-tooltip-bg;
    }
  }
}

// 按钮样式调整
:deep(.ant-btn-text) {
  .dark & {
    color: rgba(255, 255, 255, 0.65);
    
    &:hover {
      color: #40a9ff;
      background: rgba(255, 255, 255, 0.08);
    }
  }
}

// 弹出确认框
:deep(.ant-popconfirm) {
  .ant-popconfirm-message {
    .dark & {
      color: @dark-text;
    }
  }
  
  .ant-popconfirm-buttons {
    .ant-btn-default {
      .dark & {
        background: @dark-card-bg;
        border-color: @dark-border;
        color: @dark-text;
      }
    }
  }
}
</style>