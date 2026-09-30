<script lang="ts" setup>
import { ElButton } from 'element-plus';

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

const gridOptions = {
  columns: [
    { fixed: 'left' as const, title: '序号', type: 'seq' as const, width: 50 },
    { field: 'category', title: 'Category', width: 300 },
    { field: 'color', title: 'Color', width: 300 },
    { field: 'productName', title: 'Product Name', width: 300 },
    { field: 'price', title: 'Price', width: 300 },
    {
      field: 'releaseDate',
      formatter: 'formatDateTime',
      title: 'DateTime',
      width: 500,
    },
    {
      field: 'action',
      fixed: 'right' as const,
      slots: { default: 'action' },
      title: '操作',
      width: 120,
    },
  ],
  height: 'auto',
  pagerConfig: {},
  proxyConfig: {
    ajax: {
      query: async ({ page }: any) => {
        return await getExampleTableApi({
          page: page.currentPage,
          pageSize: page.pageSize,
        });
      },
    },
  },
  rowConfig: { isHover: true },
};

const [Grid] = useVbenVxeGrid<RowType>({ gridOptions });
</script>

<template>
  <Page auto-content-height>
    <Grid>
      <template #action>
        <ElButton type="primary" link>编辑</ElButton>
      </template>
    </Grid>
  </Page>
</template>
