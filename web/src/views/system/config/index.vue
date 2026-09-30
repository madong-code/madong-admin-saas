<script setup lang="ts">
import { ref, defineAsyncComponent } from "vue";
import { Page } from "#/components/common-ui";
import { TabPane, Tabs, Card } from "ant-design-vue";
import { $t } from "#/locale";

const settingList = ref([
  {
    //@ts-ignore
    component: defineAsyncComponent(() => import("./modules/site-setting.vue")),
    key: "1",
    name: $t('system.config.site.title'),
  },
  {
    //@ts-ignore
    component: defineAsyncComponent(() => import("./modules/upload-setting.vue")),
    key: "2",
    name: $t('system.config.upload.title'),
  },
  {
    //@ts-ignore
    component: defineAsyncComponent(() => import("./modules/email-setting.vue")),
    key: "3",
   name: $t('system.config.email.title'),
  },
  {
    //@ts-ignore
    component: defineAsyncComponent(() => import("./modules/sms-setting.vue")),
    key: "4",
   name: $t('system.config.sms.title'),
  },
]);
</script>

<template>
  <Page>
    <Card>
      <Tabs class="h-full">
        <TabPane v-for="item in settingList" :key="item.key" :tab="item.name">
          <component :is="item.component" v-bind="$attrs" />
        </TabPane>
      </Tabs>
    </Card>
  </Page>
</template>

<style scoped>
.custom-tabs {
  min-height: 500px;
}
:deep(.custom-tabs .ant-tabs-nav) {
  width: 220px; /* 设置左侧选项卡的宽度 */
}
</style>
