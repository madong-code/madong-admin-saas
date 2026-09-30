<script lang="ts" setup>
import { ElButton, ElImage, ElSwitch, ElTag } from 'element-plus';

import { useVbenVxeGrid } from '#/adapter/vxe-table';
import { Page } from '#/components/page';

import { getExampleTableApi } from '../../mock/api';

interface RowType {
  category: string;
  color: string;
  id: string;
  imageUrl: string;
  open: boolean;
  price: string;
  productName: string;
  releaseDate: string;
  status: string;
}

const gridOptions = {
  checkboxConfig: { highlight: true, labelField: 'name' },
  columns: [
    { title: '序号', type: 'seq' as const, width: 50 },
    { field: 'category', title: 'Category', width: 100 },
    {
      field: 'imageUrl',
      slots: { default: 'image-url' },
      title: 'Image',
      width: 100,
    },
    {
      cellRender: { name: 'CellImage' },
      field: 'imageUrl2',
      title: 'Render Image',
      width: 130,
    },
    { field: 'open', slots: { default: 'open' }, title: 'Open', width: 100 },
    {
      field: 'status',
      slots: { default: 'status' },
      title: 'Status',
      width: 100,
    },
    { field: 'color', title: 'Color', width: 100 },
    { field: 'productName', title: 'Product Name', width: 200 },
    { field: 'price', title: 'Price', width: 100 },
    {
      field: 'releaseDate',
      formatter: 'formatDateTime',
      title: 'Date',
      width: 200,
    },
    {
      cellRender: { name: 'CellLink', props: { text: '编辑' } },
      field: 'action',
      fixed: 'right' as const,
      title: '操作',
      width: 120,
    },
  ],
  height: 'auto',
  keepSource: true,
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
  showOverflow: false,
};

const [Grid] = useVbenVxeGrid<RowType>({ gridOptions });
</script>

<template>
  <Page auto-content-height>
    <Grid>
      <template #image-url="{ row }">
        <ElImage :src="row.imageUrl" style="width: 30px; height: 30px" />
      </template>
      <template #open="{ row }">
        <ElSwitch v-model="row.open" />
      </template>
      <template #status="{ row }">
        <ElTag
          :type="
            row.status === 'success'
              ? 'success'
              : row.status === 'warning'
                ? 'warning'
                : 'danger'
          "
          >
{{ row.status }}
</ElTag>
      </template>
      <template #action>
        <ElButton type="primary" link>编辑</ElButton>
      </template>
    </Grid>
  </Page>
</template>
