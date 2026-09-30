<script setup lang="ts">
import type { CrudSchema } from '#/adapter/crud';

import { useCrud } from '#/adapter/crud';
import { Page } from '#/components/page';

const mockData = [
  {
    id: 1,
    name: '技术部',
    parentId: 0,
    sort: 1,
    status: 1,
    createTime: '2024-01-01',
  },
  {
    id: 2,
    name: '产品部',
    parentId: 0,
    sort: 2,
    status: 1,
    createTime: '2024-01-01',
  },
  {
    id: 3,
    name: '前端组',
    parentId: 1,
    sort: 1,
    status: 1,
    createTime: '2024-01-02',
  },
  {
    id: 4,
    name: '后端组',
    parentId: 1,
    sort: 2,
    status: 1,
    createTime: '2024-01-02',
  },
  {
    id: 5,
    name: '测试组',
    parentId: 1,
    sort: 3,
    status: 0,
    createTime: '2024-01-02',
  },
  {
    id: 6,
    name: '产品规划组',
    parentId: 2,
    sort: 1,
    status: 1,
    createTime: '2024-01-03',
  },
  {
    id: 7,
    name: 'UI设计组',
    parentId: 2,
    sort: 2,
    status: 1,
    createTime: '2024-01-03',
  },
];

let nextId = 8;

const crudSchema: CrudSchema = {
  rowKey: 'id',
  dialogTitle: '部门',
  useCrud: true,
  useSearchForm: false,

  tree: {
    id: 'id',
    pid: 'parentId',
  },

  crudApi: {
    list: async () => {
      await new Promise((r) => setTimeout(r, 300));
      return { items: mockData, total: mockData.length };
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
    { field: 'name', title: '部门名称', minWidth: 200, treeNode: true },
    { field: 'sort', title: '排序', width: 100 },
    {
      field: 'status',
      title: '状态',
      width: 100,
      viewComponent: 'ApiDict',
      viewComponentProps: { code: 'status' },
    },
    { field: 'createTime', title: '创建时间', width: 180 },
  ],

  formSchema: [
    {
      fieldName: 'name',
      label: '部门名称',
      component: 'Input',
      rules: 'required',
    },
    {
      fieldName: 'parentId',
      label: '上级部门',
      component: 'Input',
      componentProps: { placeholder: '上级部门ID' },
    },
    {
      fieldName: 'sort',
      label: '排序',
      component: 'InputNumber',
      defaultValue: 0,
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
