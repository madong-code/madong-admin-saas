<script setup lang="ts">
import type { CrudSchema } from '#/adapter/crud';

import { useCrud } from '#/adapter/crud';
import { Page } from '#/components/page';

const mockData = [
  {
    id: 1,
    title: '系统公告',
    type: 1,
    isTop: 1,
    status: 1,
    createTime: '2024-01-01',
  },
  {
    id: 2,
    title: '版本更新通知',
    type: 2,
    isTop: 0,
    status: 1,
    createTime: '2024-01-02',
  },
  {
    id: 3,
    title: '维护通知',
    type: 3,
    isTop: 0,
    status: 0,
    createTime: '2024-01-03',
  },
  {
    id: 4,
    title: '活动通知',
    type: 2,
    isTop: 1,
    status: 1,
    createTime: '2024-01-04',
  },
  {
    id: 5,
    title: '安全提醒',
    type: 1,
    isTop: 0,
    status: 1,
    createTime: '2024-01-05',
  },
];

let nextId = 6;

const crudSchema: CrudSchema = {
  rowKey: 'id',
  dialogTitle: '通知',
  dialogType: 'drawer',
  dialogWidth: 'w-[500px]',
  useCrud: true,
  useSearchForm: true,

  crudApi: {
    list: async (params: any) => {
      await new Promise((r) => setTimeout(r, 300));
      const { pageNum = 1, pageSize = 10, title } = params;
      let filtered = [...mockData];
      if (title) filtered = filtered.filter((i) => i.title.includes(title));
      const start = (pageNum - 1) * pageSize;
      const items = filtered.slice(start, start + pageSize);
      return { items, total: filtered.length };
    },
    add: async (data: any) => {
      await new Promise((r) => setTimeout(r, 200));
      mockData.push({
        ...data,
        id: nextId++,
        createTime: new Date().toLocaleDateString(),
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
    { field: 'title', title: '标题', minWidth: 200 },
    {
      field: 'type',
      title: '类型',
      width: 120,
      viewComponent: 'ApiDict',
      viewComponentProps: { code: 'notice_type' },
    },
    {
      field: 'isTop',
      title: '置顶',
      width: 80,
      viewComponent: 'ApiDict',
      viewComponentProps: { code: 'yes_no' },
    },
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
      fieldName: 'title',
      label: '标题',
      component: 'Input',
      componentProps: { placeholder: '请输入标题' },
    },
  ],

  formSchema: [
    {
      fieldName: 'title',
      label: '标题',
      component: 'Input',
      rules: 'required',
    },
    {
      fieldName: 'type',
      label: '类型',
      component: 'Select',
      rules: 'required',
      componentProps: {
        options: [
          { label: '通知', value: 1 },
          { label: '公告', value: 2 },
          { label: '提醒', value: 3 },
        ],
      },
    },
    {
      fieldName: 'isTop',
      label: '是否置顶',
      component: 'Select',
      defaultValue: 0,
      componentProps: {
        options: [
          { label: '否', value: 0 },
          { label: '是', value: 1 },
        ],
      },
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
