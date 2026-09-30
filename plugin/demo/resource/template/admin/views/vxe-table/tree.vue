<script lang="ts" setup>
import { ElButton } from 'element-plus';

import { useVbenVxeGrid } from '#/adapter/vxe-table';
import { Page } from '#/components/page';

import { MOCK_TREE_TABLE_DATA } from '../../mock/table-data';

interface RowType {
  date: string;
  id: number;
  name: string;
  parentId: null | number;
  size: number;
  type: string;
}

const gridOptions = {
  columns: [
    { type: 'seq' as const, width: 70 },
    { field: 'name', minWidth: 300, title: 'Name', treeNode: true },
    { field: 'size', title: 'Size' },
    { field: 'type', title: 'Type' },
    { field: 'date', title: 'Date' },
  ],
  data: MOCK_TREE_TABLE_DATA,
  pagerConfig: { enabled: false },
  treeConfig: { parentField: 'parentId', rowField: 'id', transform: true },
};

const [Grid, gridApi] = useVbenVxeGrid<RowType>({ gridOptions });

const expandAll = () => {
  gridApi.grid?.setAllTreeExpand(true);
};

const collapseAll = () => {
  gridApi.grid?.setAllTreeExpand(false);
};
</script>

<template>
  <Page>
    <Grid table-title="数据列表" table-title-help="提示">
      <template #toolbar-tools>
        <ElButton class="mr-2" type="primary" @click="expandAll">
展开全部
</ElButton>
        <ElButton type="primary" @click="collapseAll">折叠全部</ElButton>
      </template>
    </Grid>
  </Page>
</template>
