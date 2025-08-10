<script setup lang="ts">
import { computed, ref } from "vue";

import { useDrawer } from "#/components/common-ui";
import { $t } from "#/locale";
import {cloneDeep } from "#/components/common/utils";

import { useForm } from "#/adapter/form";


import { formSchamas } from "../data";
import { DbSettingApi } from "#/api/platform/db";
import type{ DbSettingRow} from "#/api/platform/db";



const emit = defineEmits<{ reload: [] }>();

const api = new DbSettingApi();

const record=ref<DbSettingRow>()
const isUpdate= ref<Boolean>(false);


const [Form, formApi] = useForm({
  commonConfig: {
    formItemClass: 'col-span-12',
    componentProps: {
      class: "w-full",
    },
    labelWidth: 100,
  },
  schema: formSchamas(),
  showDefaultActions: false,
   wrapperClass: 'grid-cols-12'
});

const [Drawer, drawerApi] = useDrawer({
  async onOpenChange(isOpen) {
    await formApi.setState({ commonConfig: { disabled: false } });
    if (isOpen) {
        isUpdate.value=false;
        formApi.resetForm();
         const data= drawerApi.getData();
         const {id}= data;
        //编辑
        if(id){
          isUpdate.value=true;
          record.value = await api.get(id);
          await formApi.setValues({...record.value});
          if(data?.read==true){
            drawerApi.setState({ footer: false })
            // await formApi.setState({ commonConfig: { disabled: true } });
          }
        }
      }
    },
    onConfirm: handleConfirm,
});


/**
 * 表单提交
 */
 async function handleConfirm() {
  try {
    drawerApi.lock(true);
    const { valid } = await formApi.validate();
    if (!valid) {
      return;
    }
    const formData = await formApi.getValues();
    const data: Omit<DbSettingRow, "id"> = {
      name: formData.name || "",
      description: formData.description || "",
      driver: formData.driver || "",
      host: formData.host || "",
      port: formData.port || "",
      database: formData.database || "",
      username: formData.username || "",
      password: formData.password || "",
      prefix: formData.prefix || "",
      variable: formData.variable,
      is_default: formData.is_default || 0,
      enabled: formData.enabled || 0,
      created_date: formData.created_date || new Date().toISOString()
    };
    await (isUpdate.value ? api.update(data) : api.create(data));
    emit("reload");
    drawerApi.close();
  } catch (error) {
    console.error(error);
  } finally {
    drawerApi.lock(false);
  }
}

const title = computed(() => {
  return isUpdate.value ? $t('platform.db.list.table.columns.actions.detail'): $t('common.create');
});
</script>

<template>
  <Drawer :title="title" class="w-[800px]">
    <Form />
  </Drawer>
</template>
