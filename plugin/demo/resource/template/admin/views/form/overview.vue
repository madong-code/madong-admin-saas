<script setup lang="ts">
import { useVbenDrawer } from '@vben/common-ui';

import { ElButton, ElCard, ElMessage } from 'element-plus';

import { useVbenForm } from '#/adapter/form';
import { Page } from '#/components/page';

const [BasicForm, basicApi] = useVbenForm({
  schema: [
    {
      component: 'Input',
      fieldName: 'username',
      label: '用户名',
      rules: 'required',
    },
    {
      component: 'Input',
      fieldName: 'password',
      label: '密码',
      componentProps: { type: 'password', showPassword: true },
    },
    {
      component: 'InputNumber',
      fieldName: 'age',
      label: '年龄',
      componentProps: { min: 0, max: 150 },
    },
    { component: 'Input', fieldName: 'email', label: '邮箱' },
    { component: 'Input', fieldName: 'phone', label: '手机号' },
    {
      component: 'Input',
      fieldName: 'remark',
      label: '备注',
      componentProps: { type: 'textarea', rows: 3 },
    },
  ],
  handleSubmit: (values) => {
    ElMessage.success(`基础表单: ${JSON.stringify(values)}`);
  },
});

const [SelectForm, selectApi] = useVbenForm({
  schema: [
    {
      component: 'Select',
      fieldName: 'status',
      label: '状态',
      componentProps: {
        options: [
          { label: '启用', value: 1 },
          { label: '禁用', value: 0 },
        ],
      },
    },
    {
      component: 'RadioGroup',
      fieldName: 'gender',
      label: '性别',
      componentProps: {
        options: [
          { label: '男', value: 1 },
          { label: '女', value: 2 },
        ],
      },
    },
    {
      component: 'RadioGroup',
      fieldName: 'level',
      label: '等级(按钮)',
      componentProps: {
        isButton: true,
        options: [
          { label: '初级', value: 1 },
          { label: '中级', value: 2 },
          { label: '高级', value: 3 },
        ],
      },
    },
    {
      component: 'CheckboxGroup',
      fieldName: 'hobbies',
      label: '爱好',
      componentProps: {
        options: [
          { label: '阅读', value: 'reading' },
          { label: '运动', value: 'sports' },
          { label: '音乐', value: 'music' },
          { label: '旅行', value: 'travel' },
        ],
      },
    },
    {
      component: 'CheckboxGroup',
      fieldName: 'skills',
      label: '技能(按钮)',
      componentProps: {
        isButton: true,
        options: [
          { label: 'Vue', value: 'vue' },
          { label: 'React', value: 'react' },
          { label: 'Angular', value: 'angular' },
        ],
      },
    },
    {
      component: 'TreeSelect',
      fieldName: 'department',
      label: '部门',
      componentProps: {
        data: [
          {
            id: 1,
            label: '技术部',
            children: [
              { id: 3, label: '前端组' },
              { id: 4, label: '后端组' },
            ],
          },
          {
            id: 2,
            label: '产品部',
            children: [{ id: 5, label: '产品规划组' }],
          },
        ],
        nodeKey: 'id',
        props: { label: 'label', children: 'children' },
      },
    },
    {
      component: 'IconPicker',
      fieldName: 'icon',
      label: '图标',
    },
  ],
  handleSubmit: (values) => {
    ElMessage.success(`选择器表单: ${JSON.stringify(values)}`);
  },
});

const [DateForm, dateApi] = useVbenForm({
  schema: [
    { component: 'DatePicker', fieldName: 'date', label: '日期' },
    {
      component: 'DatePicker',
      fieldName: 'dateRange',
      label: '日期范围',
      componentProps: { type: 'daterange' },
    },
    { component: 'TimePicker', fieldName: 'time', label: '时间' },
    {
      component: 'TimePicker',
      fieldName: 'timeRange',
      label: '时间范围',
      componentProps: { isRange: true },
    },
    {
      component: 'DatePicker',
      fieldName: 'datetime',
      label: '日期时间',
      componentProps: { type: 'datetime' },
    },
    {
      component: 'Switch',
      fieldName: 'enabled',
      label: '启用',
      defaultValue: true,
    },
    {
      component: 'Switch',
      fieldName: 'notification',
      label: '通知',
      defaultValue: false,
    },
  ],
  handleSubmit: (values) => {
    ElMessage.success(`日期表单: ${JSON.stringify(values)}`);
  },
});

const [ValidateForm, validateApi] = useVbenForm({
  schema: [
    { component: 'Input', fieldName: 'name', label: '姓名', rules: 'required' },
    {
      component: 'Input',
      fieldName: 'account',
      label: '账号',
      rules: 'required',
    },
    {
      component: 'Input',
      fieldName: 'pwd',
      label: '密码',
      rules: 'required',
      componentProps: { type: 'password' },
    },
    {
      component: 'Select',
      fieldName: 'role',
      label: '角色',
      rules: 'selectRequired',
      componentProps: {
        options: [
          { label: '管理员', value: 'admin' },
          { label: '普通用户', value: 'user' },
          { label: '访客', value: 'guest' },
        ],
      },
    },
    {
      component: 'Input',
      fieldName: 'email',
      label: '邮箱',
      rules: 'required',
    },
    {
      component: 'InputNumber',
      fieldName: 'sort',
      label: '排序',
      defaultValue: 0,
    },
  ],
  handleSubmit: (values) => {
    ElMessage.success(`校验通过: ${JSON.stringify(values)}`);
  },
});

const [DrawerForm] = useVbenForm({
  schema: [
    {
      component: 'Input',
      fieldName: 'title',
      label: '标题',
      rules: 'required',
    },
    {
      component: 'Select',
      fieldName: 'type',
      label: '类型',
      componentProps: {
        options: [
          { label: '通知', value: 1 },
          { label: '公告', value: 2 },
        ],
      },
    },
    {
      component: 'Switch',
      fieldName: 'isTop',
      label: '置顶',
      defaultValue: false,
    },
    {
      component: 'Input',
      fieldName: 'content',
      label: '内容',
      componentProps: { type: 'textarea', rows: 4 },
    },
  ],
  handleSubmit: (values) => {
    ElMessage.success(`抽屉表单: ${JSON.stringify(values)}`);
  },
});

const [Drawer, drawerApi] = useVbenDrawer();

function handleBasicSubmit() {
  basicApi.submitForm();
}
function handleSelectSubmit() {
  selectApi.submitForm();
}
function handleDateSubmit() {
  dateApi.submitForm();
}
function handleValidateSubmit() {
  validateApi.submitForm();
}

function setBasicValues() {
  basicApi.setValues({
    username: 'admin',
    password: '123456',
    age: 25,
    email: 'admin@example.com',
    phone: '13800138000',
    remark: '这是一段备注信息',
  });
}
</script>

<template>
  <Page
    :description="$t('demo.form.overview.desc')"
    :title="$t('demo.form.overview.title')"
  >
    <div class="grid gap-4 lg:grid-cols-2">
      <ElCard>
        <template #header>
          <div class="flex items-center">
            <span class="flex-auto">基础表单</span>
            <ElButton type="primary" @click="handleBasicSubmit">提交</ElButton>
            <ElButton @click="() => basicApi.resetForm()">重置</ElButton>
            <ElButton type="success" @click="setBasicValues">设值</ElButton>
          </div>
        </template>
        <BasicForm />
      </ElCard>

      <ElCard>
        <template #header>
          <div class="flex items-center">
            <span class="flex-auto">选择器组件</span>
            <ElButton type="primary" @click="handleSelectSubmit">提交</ElButton>
            <ElButton @click="() => selectApi.resetForm()">重置</ElButton>
          </div>
        </template>
        <SelectForm />
      </ElCard>

      <ElCard>
        <template #header>
          <div class="flex items-center">
            <span class="flex-auto">日期时间 & 开关</span>
            <ElButton type="primary" @click="handleDateSubmit">提交</ElButton>
            <ElButton @click="() => dateApi.resetForm()">重置</ElButton>
          </div>
        </template>
        <DateForm />
      </ElCard>

      <ElCard>
        <template #header>
          <div class="flex items-center">
            <span class="flex-auto">表单校验</span>
            <ElButton type="primary" @click="handleValidateSubmit">
              提交
            </ElButton>
            <ElButton @click="() => validateApi.resetForm()">重置</ElButton>
          </div>
        </template>
        <ValidateForm />
      </ElCard>
    </div>

    <ElCard class="mt-4">
      <template #header>
        <div class="flex items-center">
          <span class="flex-auto">抽屉表单</span>
          <ElButton type="primary" @click="drawerApi.open">打开抽屉</ElButton>
        </div>
      </template>
      <span class="text-gray-500">点击按钮在抽屉中展示表单</span>
    </ElCard>

    <Drawer class="w-[500px]" title="抽屉表单">
      <DrawerForm />
    </Drawer>
  </Page>
</template>
