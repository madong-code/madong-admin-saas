<script lang="ts" setup>
import { ElButton } from 'element-plus';

import { useVbenVxeGrid } from '#/adapter/vxe-table';
import { Page } from '#/components/page';
import { $t } from '#/locales';

import { MOCK_TABLE_DATA } from '../../mock/table-data';
import DocButton from '../doc-button.vue';

interface RowType {
  address: string;
  age: number;
  id: number;
  name: string;
  nickname: string;
  role: string;
}

const [Grid, gridApi] = useVbenVxeGrid<RowType>({
  gridOptions: {
    columns: [
      { title: '序号', type: 'seq' as const, width: 50 },
      { field: 'name', title: 'Name' },
      { field: 'nickname', title: 'Nickname' },
      { field: 'role', title: 'Role' },
      { field: 'age', title: 'Age' },
      { field: 'address', title: 'Address' },
    ],
    data: MOCK_TABLE_DATA,
    height: 'auto',
    pagerConfig: { enabled: false },
  },
});

function toggleLoading() {
  gridApi.setLoading(true);
  setTimeout(() => gridApi.setLoading(false), 2000);
}
</script>

<template>
  <Page auto-content-height :title="$t('demo.vxe_table.basic.title')">
    <template #extra>
      <DocButton path="/components/common-ui/vben-vxe-table" />
    </template>
    <div class="mb-2 flex gap-2">
      <ElButton @click="toggleLoading">显示Loading</ElButton>
    </div>
    <Grid />
  </Page>
</template>
