<script setup lang="ts">
import type { CrudSchema } from '#/adapter/crud';

import { useCrud } from '#/adapter/crud';
import { Page } from '#/components/page';

const mockData = [
  {
    id: 1,
    username: 'admin',
    nickname: '管理员',
    email: 'admin@example.com',
    status: 1,
    createTime: '2024-01-01 10:00:00',
  },
  {
    id: 2,
    username: 'zhangsan',
    nickname: '张三',
    email: 'zhangsan@example.com',
    status: 1,
    createTime: '2024-01-02 11:00:00',
  },
  {
    id: 3,
    username: 'lisi',
    nickname: '李四',
    email: 'lisi@example.com',
    status: 0,
    createTime: '2024-01-03 12:00:00',
  },
  {
    id: 4,
    username: 'wangwu',
    nickname: '王五',
    email: 'wangwu@example.com',
    status: 1,
    createTime: '2024-01-04 13:00:00',
  },
  {
    id: 5,
    username: 'zhaoliu',
    nickname: '赵六',
    email: 'zhaoliu@example.com',
    status: 0,
    createTime: '2024-01-05 14:00:00',
  },
  {
    id: 6,
    username: 'sunqi',
    nickname: '孙七',
    email: 'sunqi@example.com',
    status: 1,
    createTime: '2024-01-06 15:00:00',
  },
  {
    id: 7,
    username: 'zhouba',
    nickname: '周八',
    email: 'zhouba@example.com',
    status: 1,
    createTime: '2024-01-07 16:00:00',
  },
  {
    id: 8,
    username: 'wujiu',
    nickname: '吴九',
    email: 'wujiu@example.com',
    status: 0,
    createTime: '2024-01-08 17:00:00',
  },
  {
    id: 9,
    username: 'zhengshi',
    nickname: '郑十',
    email: 'zhengshi@example.com',
    status: 1,
    createTime: '2024-01-09 18:00:00',
  },
  {
    id: 10,
    username: 'qian11',
    nickname: '钱十一',
    email: 'qian11@example.com',
    status: 1,
    createTime: '2024-01-10 19:00:00',
  },
];

let nextId = 11;

const crudSchema: CrudSchema = {
  rowKey: 'id',
  dialogTitle: '用户',
  useCrud: true,
  useSearchForm: true,

  crudApi: {
    list: async (params: any) => {
      await new Promise((r) => setTimeout(r, 300));
      const { pageNum = 1, pageSize = 10, username, status } = params;
      let filtered = [...mockData];
      if (username)
        filtered = filtered.filter((i) => i.username.includes(username));
      if (status !== undefined && status !== '')
        filtered = filtered.filter((i) => i.status === Number(status));
      const start = (pageNum - 1) * pageSize;
      const items = filtered.slice(start, start + pageSize);
      return { items, total: filtered.length };
    },
    add: async (data: any) => {
      await new Promise((r) => setTimeout(r, 200));
      mockData.push({
        ...data,
        id: nextId++,
        createTime: new Date().toLocaleString(),
      });
    },
    edit: async (data: any) => {
      await new Promise((r) => setTimeout(r, 200));
      const idx = mockData.findIndex((i) => i.id === data.id);
      if (idx !== -1) mockData[idx] = { ...mockData[idx], ...data };
    },
    remove: async (data: any) => {
      await new Promise((r) => setTimeout(r, 200));
      const ids = data.ids || [];
      ids.forEach((id: number) => {
        const idx = mockData.findIndex((i) => i.id === id);
        if (idx !== -1) mockData.splice(idx, 1);
      });
    },
    view: async (id: number) => {
      await new Promise((r) => setTimeout(r, 100));
      return mockData.find((i) => i.id === id) || {};
    },
  },

  columns: [
    { type: 'checkbox', width: 60 },
    { field: 'id', title: 'ID', width: 80 },
    { field: 'username', title: '用户名', minWidth: 120 },
    { field: 'nickname', title: '昵称', minWidth: 120 },
    { field: 'email', title: '邮箱', minWidth: 180 },
    {
      field: 'status',
      title: '状态',
      width: 100,
      viewComponent: 'ApiDict',
      viewComponentProps: { code: 'status' },
    },
    { field: 'createTime', title: '创建时间', width: 180 },
  ],

  searchFormSchema: [
    {
      fieldName: 'username',
      label: '用户名',
      component: 'Input',
      componentProps: { placeholder: '请输入用户名' },
    },
    {
      fieldName: 'status',
      label: '状态',
      component: 'Select',
      componentProps: {
        placeholder: '请选择状态',
        options: [
          { label: '启用', value: 1 },
          { label: '禁用', value: 0 },
        ],
      },
    },
  ],

  formSchema: [
    {
      fieldName: 'username',
      label: '用户名',
      component: 'Input',
      rules: 'required',
      componentProps: { placeholder: '请输入用户名' },
    },
    {
      fieldName: 'nickname',
      label: '昵称',
      component: 'Input',
      rules: 'required',
      componentProps: { placeholder: '请输入昵称' },
    },
    {
      fieldName: 'email',
      label: '邮箱',
      component: 'Input',
      componentProps: { placeholder: '请输入邮箱' },
    },
    {
      fieldName: 'status',
      label: '状态',
      component: 'Select',
      defaultValue: 1,
      componentProps: {
        options: [
          { label: '启用', value: 1 },
          { label: '禁用', value: 0 },
        ],
      },
    },
  ],

  toolbarConfig: {
    refresh: true,
    custom: true,
    zoom: true,
    export: false,
  },
};

const [Crud] = useCrud(crudSchema);
const onRegister = (_instance: any) => {};
</script>

<template>
  <Page auto-content-height>
    <Crud :crud-schema="crudSchema" @register="onRegister" />
  </Page>
</template>
