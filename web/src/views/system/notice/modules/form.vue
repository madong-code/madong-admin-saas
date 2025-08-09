<script setup lang="ts">
import { computed, ref } from "vue";

import {  useModal } from "#/components/common-ui";
import { $t } from "#/locale";
import { cloneDeep } from "#/components/common/utils";

import { useForm } from "#/adapter/form";

import { omit } from "lodash-es";

import { formSchemas } from "../data";
import type{ SystemNoticeRow } from "#/api/system/notice";
import { SystemNoticeApi } from "#/api/system/notice";


const emit = defineEmits<{ reload: [] }>();

const api = new SystemNoticeApi();


const record = ref<SystemNoticeRow>();
const isUpdate = ref<Boolean>(false);

const [Form, formApi] = useForm({
  commonConfig: {
    formItemClass: "col-span-2",
    componentProps: {
      class: "w-full",
    },
    labelWidth: 110,
  },
  schema: formSchemas(),
  showDefaultActions: false,
  wrapperClass: "grid-cols-2",
});

const [Modal, modalApi] = useModal({
  draggable:true,
  async onOpenChange(isOpen) {
    if (isOpen) {
      isUpdate.value = false;
      formApi.resetForm();
      const data = modalApi.getData<Record<string, any>>();
      const record = data?.record;
      //编辑
      if (record?.id) {
        isUpdate.value = true;
        record.value = await api.get(record.id);
        await formApi.setValues(record.value);
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
    modalApi.lock(true);
    const { valid } = await formApi.validate();
    if (!valid) {
      return;
    }
    const data = cloneDeep(await formApi.getValues());
    await (isUpdate.value ? api.update(data) : api.create(omit(data, ["id"])));
    emit("reload");
    modalApi.close();
  } catch (error) {
    console.error(error);
  } finally {
    modalApi.lock(false);
  }
}

const title = computed(() => {
  return isUpdate.value ? $t('common.edit') : $t('common.create');
});
</script>

<template>
  <Modal :title="title" class="w-[700px]">
    <Form/>
  </Modal>
</template>
