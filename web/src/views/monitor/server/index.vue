<script setup lang="ts">
import { onMounted, ref } from "vue";

import { Page } from "#/components/common-ui";
import {Col, Row} from "ant-design-vue";


import CpuUsage from "./modules/cpu-usage.vue";
import MemoryUsage from "./modules/memory-usage.vue";
import DiskUsage from "./modules/disk-usage.vue";
import ServerEnvironment from "./modules/server-environment.vue";
import { ServerApi } from "#/api/monitor/server";


const api= new ServerApi();

const cpuInfo=ref<any>([]);
const memoryInfo=ref<any>([]);
const serverInfo=ref<any>([]);
const diskData=ref<any>([]);




async function loadInfo() {
  try {
    const data = await api.list();
    cpuInfo.value= objectToArray(data.cpu||[]);
    memoryInfo.value= objectToArray(data.memory||[]);
    serverInfo.value= objectToArray(data.php||[]);
    diskData.value= data.disk||[];
  } catch (error) {
    console.warn(error);
  }
}

onMounted(async () => {
  await loadInfo();
});


/**
 *
 * @param obj 转二维数组
 */
function objectToArray(obj) {
    return Object.entries(obj).map(([key, value]) => ({
        key,
        value
    }));
}

</script>

<template>
  <Page>
    <Row :gutter="[15, 15]">
      <Col :span="12">
        <CpuUsage :data="cpuInfo"/>
      </Col>
      <Col :span="12">
        <MemoryUsage :data="memoryInfo"/>
      </Col>
    </Row>
    <Row :gutter="[15, 15]" class="mt-5">
      <Col :span="24">
          <ServerEnvironment :data="serverInfo"/>
      </Col>
    </Row>
    <Row :gutter="[15, 15]" class="mt-5">
      <Col :span="24">
        <DiskUsage :data="diskData"/>
      </Col>
    </Row>
  </Page>
</template>

<style lang="scss" scoped>
:deep(.no-padding .ant-card-body) {
    padding: 0 !important;
}
</style>
