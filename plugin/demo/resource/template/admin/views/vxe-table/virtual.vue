<script lang="ts" setup>
import { onMounted } from 'vue';

import { useVbenVxeGrid } from '#/adapter/vxe-table';
import { Page } from '#/components/page';

interface RowType {
  id: number;
  name: string;
  role: string;
  sex: string;
}

const gridOptions = {
  columns: [
    { type: 'seq' as const, width: 70 },
    { field: 'name', title: 'Name' },
    { field: 'role', title: 'Role' },
    { field: 'sex', title: 'Sex' },
  ],
  data: [],
  height: 'auto',
  pagerConfig: { enabled: false },
  scrollY: { enabled: true, gt: 0 },
  showOverflow: true,
};

const [Grid, gridApi] = useVbenVxeGrid<RowType>({ gridOptions });

const loadList = (size = 200) => {
  const dataList: RowType[] = [];
  for (let i = 0; i < size; i++) {
    dataList.push({
      id: 10_000 + i,
      name: `Test${i}`,
      role: 'Developer',
      sex: '男',
    });
  }
  gridApi.setGridOptions({ data: dataList });
};

onMounted(() => {
  loadList(1000);
});
</script>

<template>
  <Page auto-content-height>
    <Grid />
  </Page>
</template>
