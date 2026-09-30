<script lang="ts" setup>
import { ElMessage } from 'element-plus';

import { useVbenVxeGrid } from '#/adapter/vxe-table';
import { Page } from '#/components/page';

import { getExampleTableApi } from '../../mock/api';

interface RowType {
  category: string;
  color: string;
  id: string;
  price: string;
  productName: string;
  releaseDate: string;
}

const formOptions = {
  collapsed: false,
  schema: [
    {
      component: 'Input',
      defaultValue: '1',
      fieldName: 'category',
      label: 'Category',
    },
    { component: 'Input', fieldName: 'productName', label: 'ProductName' },
    { component: 'Input', fieldName: 'price', label: 'Price' },
    {
      component: 'Select',
      componentProps: {
        options: [
          { label: 'Color1', value: '1' },
          { label: 'Color2', value: '2' },
        ],
      },
      fieldName: 'color',
      label: 'Color',
    },
    { component: 'DatePicker', fieldName: 'date', label: 'Date' },
  ],
  showCollapseButton: true,
  submitOnChange: true,
  submitOnEnter: false,
};

const gridOptions = {
  checkboxConfig: { highlight: true, labelField: 'name' },
  columns: [
    { title: '序号', type: 'seq' as const, width: 50 },
    {
      align: 'left' as const,
      title: 'Name',
      type: 'checkbox' as const,
      width: 100,
    },
    { field: 'category', title: 'Category' },
    { field: 'color', title: 'Color' },
    { field: 'productName', title: 'Product Name' },
    { field: 'price', title: 'Price' },
    { field: 'releaseDate', formatter: 'formatDateTime', title: 'Date' },
  ],
  exportConfig: {},
  height: 'auto',
  keepSource: true,
  pagerConfig: {},
  proxyConfig: {
    ajax: {
      query: async ({ page }: any, formValues: any) => {
        ElMessage.info(`Query params: ${JSON.stringify(formValues)}`);
        return await getExampleTableApi({
          page: page.currentPage,
          pageSize: page.pageSize,
          ...formValues,
        });
      },
    },
  },
  toolbarConfig: {
    custom: true,
    export: true,
    refresh: true,
    resizable: true,
    search: true,
    zoom: true,
  },
};

const [Grid] = useVbenVxeGrid<RowType>({ formOptions, gridOptions });
</script>

<template>
  <Page auto-content-height>
    <Grid />
  </Page>
</template>
